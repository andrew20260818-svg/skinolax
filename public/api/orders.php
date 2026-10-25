<?php
/* POST /api/orders — заказ из корзины: проверка, расчёт суммы по shop.json и промокоду, платёж Т-Банка, письмо менеджеру. */
declare(strict_types=1);
require __DIR__ . '/_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') skx_json(405, ['error' => 'method']);
skx_check_origin();
if (explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0] !== 'application/json') skx_json(415, ['error' => 'content_type']);
$raw = file_get_contents('php://input', false, null, 0, 16385);
if ($raw === '' || strlen($raw) > 16384) skx_json(413, ['error' => 'size']);
skx_rate_limit('orders', 10);
$d = json_decode($raw, true);
if (!is_array($d) || ($d['form'] ?? '') !== 'order' || !is_array($d['fields'] ?? null) || ($d['consent'] ?? null) !== true) skx_json(422, ['error' => 'schema']);

$f = $d['fields'];
$req = ['name', 'phone', 'email', 'delivery', 'address', 'payment', 'items'];
$opt = ['comment', 'promo'];
if (array_diff(array_keys($f), $req, $opt)) skx_json(422, ['error' => 'fields']);
foreach ($f as $k => $v) if (!is_string($v) || mb_strlen($v) > ($k === 'comment' ? 2000 : 200)) skx_json(422, ['error' => 'length']);
$f = array_map('trim', $f) + array_fill_keys($opt, '');
$shop = skx_shop();
$pickup = str_starts_with($f['delivery'] ?? '', 'Самовывоз');
$missing = array_values(array_filter($req, fn($k) => ($f[$k] ?? '') === '' && !($k === 'address' && $pickup)));
if ($missing) skx_json(422, ['error' => 'required', 'fields' => $missing]);
if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) skx_json(422, ['error' => 'email']);
if (strlen(preg_replace('/\D/', '', $f['phone'])) < 10) skx_json(422, ['error' => 'phone']);
if (!in_array($f['delivery'], $shop['delivery'] ?? [], true)) skx_json(422, ['error' => 'delivery']);
if (!preg_match('/^(standard|compact)-100:\d{1,4}(;(standard|compact)-100:\d{1,4})?$/', $f['items'])) skx_json(422, ['error' => 'items']);

$price = (int)$shop['price'];  // 750 ₽ за пакет: цену из браузера не берём
$items = [];
foreach (explode(';', $f['items']) as $x) {
    [$k, $q] = explode(':', $x);
    if ((int)$q > 0) $items[$k] = ['key' => $k, 'name' => SKX_ITEMS[$k], 'qty' => ($items[$k]['qty'] ?? 0) + (int)$q, 'price' => $price];
}
if (!$items) skx_json(422, ['error' => 'items']);
$items = array_values($items);
$goods = array_sum(array_map(fn($i) => $i['qty'] * $i['price'], $items));

[$discount, $promo] = skx_promo_apply($f['promo'], $goods);
if (is_string($promo)) skx_json(422, ['error' => 'promo', 'message' => $promo]);
$amount = $goods - $discount;

$o = [
    'id' => 'Z-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3))),
    'created' => date('c'), 'items' => $items, 'goods' => $goods,
    'promo' => $promo['code'] ?? '', 'discount' => $discount, 'amount' => $amount,
    'delivery' => $f['delivery'], 'address' => $pickup ? '' : $f['address'],
    'free_delivery' => $pickup || $amount >= (int)$shop['free_delivery_from'],
    'name' => $f['name'], 'phone' => $f['phone'], 'email' => $f['email'], 'comment' => $f['comment'],
    'status' => 'NEW', 'history' => [[date('c'), 'NEW']],
];

$c = skx_config();
$tb = $c['tbank'] ?? [];
if (!skx_tbank_ready($tb)) {
    // Ключей терминала ещё нет: заказ сохраняется и приходит на почту, оплату менеджер согласует с клиентом.
    $o['status'] = 'NO_PAYMENT';
    skx_order_save($o);
    skx_mail("Новый заказ {$o['id']} (онлайн-оплата не подключена)", skx_order_rows($o), $o['email']);
    skx_json(200, ['order_id' => $o['id'], 'amount' => $amount, 'test' => true]);
}

$kop = $amount * 100;
$params = [
    'Amount' => $kop, 'OrderId' => $o['id'], 'Description' => mb_substr("СКИНОЛАКС, заказ {$o['id']}", 0, 140),
    'PayType' => $tb['pay_type'] ?? 'O',
    'SuccessURL' => $tb['success_url'] ?? '', 'FailURL' => $tb['fail_url'] ?? '', 'NotificationURL' => $tb['notification_url'] ?? '',
    'DATA' => ['Phone' => $o['phone'], 'Email' => $o['email']],
    'Receipt' => ['Email' => $o['email'], 'Phone' => $o['phone'], 'Taxation' => $tb['taxation'] ?? 'osn',
        'Items' => skx_receipt_items($items, $goods * 100, $kop, $tb['vat'] ?? 'vat10')],
];
$params = array_filter($params, fn($v) => $v !== '');
try { $r = skx_tbank_call('Init', $params); }
catch (Throwable $e) { $r = ['Success' => false, 'ErrorCode' => 'network', 'Message' => $e->getMessage()]; }

if (empty($r['Success']) || empty($r['PaymentURL'])) {
    $o['status'] = 'INIT_FAILED';
    $o['history'][] = [date('c'), 'INIT_FAILED ' . ($r['ErrorCode'] ?? '') . ' ' . ($r['Message'] ?? '')];
    skx_order_save($o);
    skx_mail("Заказ {$o['id']}: платёж не создан", skx_order_rows($o) + ['Ошибка Т-Банка' => ($r['ErrorCode'] ?? '') . ' ' . ($r['Details'] ?? $r['Message'] ?? '')], $o['email']);
    skx_json(502, ['error' => 'payment', 'order_id' => $o['id']]);
}
$o['payment_id'] = (string)$r['PaymentId'];
$o['status'] = 'WAITING_PAYMENT';
$o['history'][] = [date('c'), 'Init ' . $o['payment_id']];
skx_order_save($o);
skx_mail("Новый заказ {$o['id']} на " . skx_rub($amount) . ', ждёт оплаты', skx_order_rows($o), $o['email']);
skx_json(200, ['order_id' => $o['id'], 'payment_url' => $r['PaymentURL']]);
