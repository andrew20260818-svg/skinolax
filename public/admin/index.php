<?php
/* Админка СКИНОЛАКС: промокоды и заказы. Вход по паролю из private/config.php (admin_password_hash). */
declare(strict_types=1);
require dirname(__DIR__) . '/api/_lib.php';

header('X-Frame-Options: DENY');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
session_name('skxadm');
session_set_cookie_params(['lifetime' => 0, 'path' => '/admin/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Strict']);
session_start();
$c = skx_config();
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$csrf = $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
$msg = '';

function page(string $title, string $body): never {
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>' . $title . '</title><style>'
        . ':root{--c:#3CC3DE;--t:#36A7BD;--ink:#133E45;--ink2:#28626E;--mist:#DDF6FA;--ice:#EEFAFD;--bg:#FBFDFE}'
        . 'body{margin:0;font:16px/1.45 Roboto,system-ui,Arial,sans-serif;color:#333;background:var(--bg)}main{max-width:1100px;margin:0 auto;padding:24px 16px}'
        . 'h1{color:var(--t);text-transform:uppercase;font-size:28px;margin:0 0 20px}h2{color:var(--ink);font-size:20px;margin:32px 0 12px}'
        . '.card{background:#fff;border-radius:18px;box-shadow:0 10px 30px rgba(19,62,69,.08);padding:20px;margin-bottom:16px}'
        . 'table{width:100%;border-collapse:collapse;font-size:14px}th,td{text-align:left;padding:8px 10px;border-bottom:1px solid #D3EEF6;vertical-align:top}th{color:var(--ink2);font-weight:600}'
        . 'input,select{font:inherit;padding:8px 10px;border:1px solid #C5EDF5;border-radius:10px;min-width:0}label{display:flex;flex-direction:column;gap:4px;font-size:14px;color:var(--ink2)}'
        . '.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;align-items:end}'
        . 'button{font:inherit;font-weight:600;border:0;border-radius:999px;padding:9px 18px;background:var(--c);color:var(--ink);cursor:pointer}button.ghost{background:var(--mist)}'
        . '.msg{background:var(--ice);border-left:4px solid var(--c);padding:10px 14px;border-radius:8px;margin-bottom:16px}.off{color:#888}.wrap{overflow-x:auto}'
        . 'nav{display:flex;gap:16px;margin-bottom:12px}nav a{color:var(--ink)}</style></head><body><main>' . $body . '</main></body></html>';
    exit;
}

/* --- вход --- */
if (empty($c['admin_password_hash'])) {
    $hint = '';
    if (($_POST['newpass'] ?? '') !== '' && strlen($_POST['newpass']) >= 10)
        $hint = '<p>Вставьте эту строку в private/config.php в поле admin_password_hash:</p><pre>' . $h(password_hash($_POST['newpass'], PASSWORD_DEFAULT)) . '</pre>';
    page('Настройка админки', '<h1>Настройка админки</h1><div class="card"><p>Пароль администратора ещё не задан. Придумайте пароль (не меньше 10 символов): страница покажет его хеш, а сам пароль никуда не сохранится.</p>'
        . '<form method="post" class="grid"><label>Новый пароль<input type="password" name="newpass" minlength="10" required></label><button>Получить хеш</button></form>' . $hint . '</div>');
}
if (isset($_POST['logout'])) { session_destroy(); header('Location: ./'); exit; }
if (empty($_SESSION['ok'])) {
    $err = '';
    if (isset($_POST['password'])) {
        skx_rate_limit('admin_login', 5);
        if (password_verify((string)$_POST['password'], $c['admin_password_hash'])) { session_regenerate_id(true); $_SESSION['ok'] = 1; header('Location: ./'); exit; }
        $err = '<p class="msg">Неверный пароль.</p>';
    }
    page('Вход', '<h1>Админка СКИНОЛАКС</h1><div class="card">' . $err . '<form method="post" class="grid"><label>Пароль<input type="password" name="password" required autofocus></label><button>Войти</button></form></div>');
}

/* --- действия --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('csrf'); }
    $a = $_POST['action'] ?? '';
    if ($a === 'promo_add') {
        $code = skx_promo_norm((string)$_POST['code']);
        $type = $_POST['type'] === 'fixed' ? 'fixed' : 'percent';
        $value = (int)$_POST['value'];
        $exp = (string)($_POST['expires'] ?? '');
        if (!preg_match('/^[A-ZА-ЯЁ0-9_-]{3,30}$/u', $code)) $msg = 'Код: от 3 до 30 символов, буквы, цифры, «-» и «_».';
        elseif ($type === 'percent' && ($value < 1 || $value > 90)) $msg = 'Скидка в процентах: от 1 до 90.';
        elseif ($type === 'fixed' && ($value < 1 || $value > 100000)) $msg = 'Скидка в рублях: от 1 до 100 000.';
        elseif ($exp !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $exp)) $msg = 'Дата окончания в формате ГГГГ-ММ-ДД.';
        else $msg = skx_locked(skx_data('promo.json'), function ($d) use ($code, $type, $value, $exp) {
            if (isset($d[$code])) return [null, "Промокод $code уже есть."];
            $d[$code] = ['type' => $type, 'value' => $value, 'expires' => $exp, 'max_uses' => max(0, (int)$_POST['max_uses']),
                'min_sum' => max(0, (int)$_POST['min_sum']), 'used' => 0, 'active' => true, 'created' => date('c')];
            return [$d, "Промокод $code создан."];
        });
    } elseif ($a === 'promo_toggle' || $a === 'promo_delete') {
        $code = skx_promo_norm((string)$_POST['code']);
        $msg = skx_locked(skx_data('promo.json'), function ($d) use ($code, $a) {
            if (!isset($d[$code])) return [null, 'Промокод не найден.'];
            if ($a === 'promo_delete') { unset($d[$code]); return [$d, "Промокод $code удалён."]; }
            $d[$code]['active'] = empty($d[$code]['active']);
            return [$d, "Промокод $code " . ($d[$code]['active'] ? 'включён.' : 'выключен.')];
        });
    } elseif ($a === 'confirm' || $a === 'cancel') {
        $o = skx_order_load((string)$_POST['order']);
        if (!$o || empty($o['payment_id'])) $msg = 'Заказ не найден.';
        else {
            try {
                $r = skx_tbank_call($a === 'confirm' ? 'Confirm' : 'Cancel', ['PaymentId' => $o['payment_id']]);
                if (!empty($r['Success'])) { $o['status'] = $r['Status'] ?? $o['status']; $o['history'][] = [date('c'), "admin $a → {$o['status']}"]; skx_order_save($o); }
                $msg = !empty($r['Success']) ? "Заказ {$o['id']}: " . ($a === 'confirm' ? 'оплата списана.' : 'оплата отменена.') : 'Т-Банк: ' . ($r['Message'] ?? '') . ' ' . ($r['Details'] ?? '');
            } catch (Throwable $e) { $msg = 'Т-Банк недоступен: ' . $e->getMessage(); }
        }
    }
    $_SESSION['msg'] = $msg;
    header('Location: ./' . (str_starts_with($a, 'promo') ? '#promo' : '#orders')); exit;
}
$msg = $_SESSION['msg'] ?? ''; unset($_SESSION['msg']);

/* --- страница --- */
$f = skx_data('promo.json');
$promos = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
$hid = '<input type="hidden" name="csrf" value="' . $csrf . '">';
$rows = '';
foreach ($promos as $code => $p) {
    $state = empty($p['active']) ? 'выключен' : (!empty($p['expires']) && date('Y-m-d') > $p['expires'] ? 'истёк' : (!empty($p['max_uses']) && $p['used'] >= $p['max_uses'] ? 'исчерпан' : 'действует'));
    $rows .= '<tr' . ($state === 'действует' ? '' : ' class="off"') . '><td><b>' . $h($code) . '</b></td><td>' . ($p['type'] === 'percent' ? $p['value'] . ' %' : skx_rub((float)$p['value']))
        . '</td><td>' . ($p['min_sum'] ? 'от ' . skx_rub((float)$p['min_sum']) : '—') . '</td><td>' . ($p['expires'] ? $h(date('d.m.Y', strtotime($p['expires']))) : 'бессрочно')
        . '</td><td>' . (int)$p['used'] . ($p['max_uses'] ? ' из ' . (int)$p['max_uses'] : '') . "</td><td>$state</td><td style=\"white-space:nowrap\">"
        . '<form method="post" style="display:inline">' . $hid . '<input type="hidden" name="code" value="' . $h($code) . '"><button class="ghost" name="action" value="promo_toggle">' . (empty($p['active']) ? 'Включить' : 'Выключить') . '</button></form> '
        . '<form method="post" style="display:inline" onsubmit="return confirm(\'Удалить промокод?\')">' . $hid . '<input type="hidden" name="code" value="' . $h($code) . '"><button class="ghost" name="action" value="promo_delete">Удалить</button></form></td></tr>';
}
$od = skx_data('orders');
$files = is_dir($od) ? glob("$od/Z-*.json") : [];
usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
$st = SKX_STATUS;
$orows = '';
foreach (array_slice($files, 0, 100) as $file) {
    $o = json_decode((string)file_get_contents($file), true);
    $btn = $o['status'] === 'AUTHORIZED'
        ? '<form method="post" style="display:inline">' . $hid . '<input type="hidden" name="order" value="' . $h($o['id']) . '"><button name="action" value="confirm">Списать</button></form> '
        . '<form method="post" style="display:inline" onsubmit="return confirm(\'Отменить оплату?\')">' . $hid . '<input type="hidden" name="order" value="' . $h($o['id']) . '"><button class="ghost" name="action" value="cancel">Отменить</button></form>' : '';
    $orows .= '<tr><td><b>' . $h($o['id']) . '</b><br>' . $h(date('d.m.Y H:i', strtotime($o['created']))) . '</td><td>' . $h(implode('; ', array_map(fn($i) => "{$i['name']} × {$i['qty']}", $o['items'])))
        . '</td><td>' . skx_rub((float)$o['amount']) . ($o['promo'] ? '<br><small>' . $h($o['promo']) . ' −' . skx_rub((float)$o['discount']) . '</small>' : '')
        . '</td><td>' . $h($o['name']) . '<br>' . $h($o['phone']) . '<br>' . $h($o['email']) . '</td><td>' . $h($o['delivery']) . ($o['address'] ? '<br>' . $h($o['address']) : '')
        . '</td><td>' . $h($st[$o['status']] ?? $o['status']) . "</td><td>$btn</td></tr>";
}
page('Админка СКИНОЛАКС', '<h1>Админка СКИНОЛАКС</h1><nav><a href="#promo">Промокоды</a><a href="#orders">Заказы</a>'
    . '<form method="post" style="margin-left:auto"><button class="ghost" name="logout" value="1">Выйти</button></form></nav>' . ($msg ? '<p class="msg">' . $h($msg) . '</p>' : '')
    . '<h2 id="promo">Новый промокод</h2><div class="card"><form method="post" class="grid">' . $hid . '<input type="hidden" name="action" value="promo_add">'
    . '<label>Код<input name="code" required maxlength="30" placeholder="ВРАЧ10" style="text-transform:uppercase"></label>'
    . '<label>Тип скидки<select name="type"><option value="percent">Процент</option><option value="fixed">Рубли на заказ</option></select></label>'
    . '<label>Размер<input name="value" type="number" min="1" required></label>'
    . '<label>Заказ от, ₽<input name="min_sum" type="number" min="0" value="0"></label>'
    . '<label>Действует до<input name="expires" type="date"></label>'
    . '<label>Сколько раз (0 — без ограничений)<input name="max_uses" type="number" min="0" value="0"></label>'
    . '<button>Создать</button></form><p style="font-size:14px;color:#28626E">Скидка считается от стоимости товаров по 750 ₽ за пакет. Использованием считается оплаченный заказ.</p></div>'
    . '<h2>Промокоды</h2><div class="card wrap"><table><tr><th>Код</th><th>Скидка</th><th>Условие</th><th>До</th><th>Использован</th><th>Статус</th><th></th></tr>'
    . ($rows ?: '<tr><td colspan="7">Промокодов пока нет.</td></tr>') . '</table></div>'
    . '<h2 id="orders">Заказы</h2><div class="card wrap"><table><tr><th>Заказ</th><th>Состав</th><th>Сумма</th><th>Клиент</th><th>Получение</th><th>Оплата</th><th></th></tr>'
    . ($orows ?: '<tr><td colspan="7">Заказов пока нет.</td></tr>') . '</table></div>');
