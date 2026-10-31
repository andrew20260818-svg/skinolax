<?php
/* POST /api/cdekpay/notify — уведомления CDEK Pay об успешной оплате и о возврате.
   Подпись: значения объекта payment по алфавиту ключей + секретный ключ, SHA-256 в верхнем регистре. Ответ 200 — уведомление принято. */
declare(strict_types=1);
require __DIR__ . '/_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
$d = json_decode((string)file_get_contents('php://input', false, null, 0, 16384), true);
$cp = skx_config()['cdekpay'] ?? [];
$p = is_array($d) ? ($d['payment'] ?? null) : null;
$cur = is_array($p) ? (string)($p['currency'] ?? '') : '';
// Принимаются только уведомления в валюте текущего режима (TST в тесте, RUR в бою), подписанные ключом этого режима.
if (!is_array($p) || !skx_cdekpay_ready($cp) || $cur !== skx_cdekpay_currency($cp)
    || !hash_equals(skx_cdekpay_sign($p, skx_cdekpay_secret($cp, $cur)), strtoupper((string)($d['signature'] ?? '')))) {
    http_response_code(403); exit('signature');
}

$id = skx_cdekpay_index((string)($p['access_key'] ?? ''));
$o = $id ? skx_order_load($id) : null;
if (!$o || ($o['cdek_order_id'] ?? '') !== (string)($p['order_id'] ?? '')) {
    // Заказ не найден: отвечаем 200, чтобы CDEK Pay не повторял, и пишем менеджеру.
    skx_mail('CDEK Pay: уведомление по неизвестному заказу', ['order_id' => (string)($p['order_id'] ?? ''), 'access_key' => (string)($p['access_key'] ?? ''),
        'Сумма, коп.' => (string)($p['pay_amount'] ?? $p['refund_amount'] ?? '')]);
    header('Content-Type: text/plain'); exit('OK');
}

$key = (string)($p['id'] ?? '');
$o['events'] = $o['events'] ?? [];
if (isset($p['refund_amount'])) {
    $ev = "refund:$key:" . (int)$p['refund_amount'];
    if (!in_array($ev, $o['events'], true)) {
        $o['events'][] = $ev;
        $o['refunded'] = ($o['refunded'] ?? 0) + (int)$p['refund_amount'];
        $o['status'] = $o['refunded'] >= $o['amount'] * 100 ? 'REFUNDED' : 'PARTIAL_REFUNDED';
        $o['history'][] = [date('c'), "{$o['status']} " . skx_rub($p['refund_amount'] / 100)];
        skx_order_save($o);
        skx_mail("Заказ {$o['id']}: " . ($o['status'] === 'REFUNDED' ? 'деньги возвращены' : 'частичный возврат'), skx_order_rows($o), $o['email']);
    }
} elseif (isset($p['pay_amount'])) {
    $ev = "pay:$key";
    if (!in_array($ev, $o['events'], true)) {
        $o['events'][] = $ev;
        $o['payment_id'] = $key;
        $o['status'] = 'CONFIRMED';
        $o['history'][] = [date('c'), "CONFIRMED $key"];
        if ((int)$p['pay_amount'] !== $o['amount'] * 100) $o['history'][] = [date('c'), 'Сумма в уведомлении не совпадает: ' . $p['pay_amount']];
        if ($o['promo'] && empty($o['promo_counted'])) { skx_promo_used($o['promo']); $o['promo_counted'] = true; }
        skx_order_save($o);
        skx_mail("Заказ {$o['id']}: оплачен", skx_order_rows($o), $o['email']);
    }
}
header('Content-Type: text/plain'); echo 'OK';
