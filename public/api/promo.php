<?php
/* GET /api/promo?code=…&sum=… — предварительная проверка промокода для корзины. Итоговую скидку сервер считает заново в /api/orders. */
declare(strict_types=1);
require __DIR__ . '/_lib.php';

skx_rate_limit('promo', 15);
$code = (string)($_GET['code'] ?? '');
$sum = max(0, min(10_000_000, (int)($_GET['sum'] ?? 0)));
if ($code === '' || mb_strlen($code) > 40) skx_json(422, ['ok' => false, 'message' => 'Введите промокод']);
[$disc, $p] = skx_promo_apply($code, $sum);
if (!is_array($p)) skx_json(200, ['ok' => false, 'message' => $p ?? 'Промокод не найден']);
skx_json(200, ['ok' => true, 'code' => $p['code'], 'type' => $p['type'], 'value' => $p['value'], 'min_sum' => (int)($p['min_sum'] ?? 0),
    'discount' => $disc, 'label' => $p['type'] === 'percent' ? "−{$p['value']} %" : '−' . skx_rub((float)$p['value'])]);
