<?php
/* POST /api/tbank/notify — уведомления Т-Банка о статусе платежа. Подпись проверяется, ответ — «OK». */
declare(strict_types=1);
require __DIR__ . '/_lib.php';

$d = json_decode((string)file_get_contents('php://input', false, null, 0, 16384), true);
$tb = skx_config()['tbank'] ?? [];
if (!is_array($d) || !skx_tbank_ready($tb) || ($d['TerminalKey'] ?? '') !== $tb['terminal_key']
    || !hash_equals(skx_tbank_token($d, $tb['password']), (string)($d['Token'] ?? ''))) {
    http_response_code(403); exit('token');
}
$o = skx_order_load((string)($d['OrderId'] ?? ''));
$st = (string)($d['Status'] ?? '');
if ($o && $st !== '' && $st !== $o['status']) {
    $o['status'] = $st;
    $o['history'][] = [date('c'), $st . ' ' . ($d['PaymentId'] ?? '')];
    if ((int)($d['Amount'] ?? 0) !== $o['amount'] * 100) $o['history'][] = [date('c'), 'Сумма в уведомлении не совпадает: ' . ($d['Amount'] ?? '')];
    $paid = in_array($st, ['AUTHORIZED', 'CONFIRMED'], true);
    if ($paid && $o['promo'] && empty($o['promo_counted'])) { skx_promo_used($o['promo']); $o['promo_counted'] = true; }
    skx_order_save($o);
    $titles = ['AUTHORIZED' => 'сумма заблокирована, подтвердите заказ и спишите оплату в админке', 'CONFIRMED' => 'оплачен',
        'REJECTED' => 'оплата отклонена', 'CANCELED' => 'оплата отменена', 'REVERSED' => 'блокировка снята',
        'REFUNDED' => 'деньги возвращены', 'PARTIAL_REFUNDED' => 'частичный возврат'];
    $oneStage = ($tb['pay_type'] ?? 'O') === 'O';
    if ($oneStage && $st === 'AUTHORIZED') unset($titles['AUTHORIZED']);  // при одностадийной оплате сразу придёт CONFIRMED
    if (isset($titles[$st])) skx_mail("Заказ {$o['id']}: {$titles[$st]}", skx_order_rows($o), $o['email']);
}
header('Content-Type: text/plain'); echo 'OK';
