<?php
/* СКИНОЛАКС: общие функции сервера заказов (Beget, PHP 8).
   Настройки и данные лежат в папке private рядом с public_html, браузеру они недоступны. */
declare(strict_types=1);
date_default_timezone_set('Europe/Moscow');

const SKX_STATUS = ['NEW' => 'создан', 'NO_PAYMENT' => 'без онлайн-оплаты', 'INIT_FAILED' => 'платёж не создан', 'WAITING_PAYMENT' => 'ждёт оплаты',
    'AUTHORIZED' => 'сумма заблокирована', 'CONFIRMED' => 'оплачен', 'REJECTED' => 'отклонён', 'CANCELED' => 'отменён', 'REVERSED' => 'блокировка снята',
    'REFUNDED' => 'возврат', 'PARTIAL_REFUNDED' => 'частичный возврат'];
const SKX_ITEMS = ['standard-100' => 'СКИНОЛАКС Стандарт 100 см²', 'compact-100' => 'СКИНОЛАКС Компакт 100 см²'];

function skx_private(): string {
    $p = getenv('SKINOLAX_PRIVATE') ?: dirname(__DIR__, 2) . '/private';
    return rtrim($p, '/');
}

function skx_config(): array {
    static $c = null;
    if ($c === null) {
        $f = skx_private() . '/config.php';
        $c = is_file($f) ? require $f : [];
    }
    return $c;
}

function skx_data(string $name): string {
    $d = skx_private() . '/data';
    if (!is_dir($d)) mkdir($d, 0700, true);
    return "$d/$name";
}

function skx_shop(): array {
    return json_decode((string)file_get_contents(dirname(__DIR__) . '/shop.json'), true) ?: [];
}

function skx_json($code, array $data): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* Чтение и запись JSON-файла под блокировкой: $fn получает данные и возвращает новые (или null — не менять). */
function skx_locked(string $file, callable $fn) {
    $h = fopen($file, 'c+');
    flock($h, LOCK_EX);
    $raw = stream_get_contents($h);
    $data = $raw ? (json_decode($raw, true) ?: []) : [];
    [$new, $ret] = $fn($data);
    if ($new !== null) {
        ftruncate($h, 0); rewind($h);
        fwrite($h, json_encode($new, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        fflush($h);
    }
    flock($h, LOCK_UN); fclose($h);
    return $ret;
}

/* Не больше $max запросов в минуту с одного IP. */
function skx_rate_limit(string $bucket, int $max = 20): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $now = time();
    $ok = skx_locked(skx_data('rate.json'), function ($d) use ($bucket, $ip, $now, $max) {
        foreach ($d as $k => $ts) { $d[$k] = array_values(array_filter($ts, fn($t) => $now - $t < 60)); if (!$d[$k]) unset($d[$k]); }
        $key = $bucket . '|' . hash('sha256', $ip);
        $d[$key] = $d[$key] ?? [];
        if (count($d[$key]) >= $max) return [$d, false];
        $d[$key][] = $now;
        return [$d, true];
    });
    if (!$ok) skx_json(429, ['error' => 'rate_limit']);
}

function skx_check_origin(): void {
    $allowed = skx_config()['site_origin'] ?? 'https://skinolax.ru';
    $origins = is_array($allowed) ? $allowed : [$allowed];
    if (!in_array($_SERVER['HTTP_ORIGIN'] ?? '', $origins, true)) skx_json(403, ['error' => 'origin']);
}

function skx_rub(float $v): string {
    return number_format($v, $v == floor($v) ? 0 : 2, ',', ' ') . ' ₽';
}

/* ---------- промокоды ---------- */

function skx_promo_norm(string $code): string {
    return mb_strtoupper(trim($code));
}

/* Проверка промокода для суммы товаров $sum (₽). Возвращает [скидка ₽, промокод] или [0, ошибка-строка]. */
function skx_promo_apply(string $code, int $sum): array {
    $code = skx_promo_norm($code);
    if ($code === '') return [0, null];
    $f = skx_data('promo.json');
    $all = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
    $p = $all[$code] ?? null;
    if (!$p || empty($p['active'])) return [0, 'Промокод не найден'];
    if (!empty($p['expires']) && date('Y-m-d') > $p['expires']) return [0, 'Срок действия промокода истёк'];
    if (!empty($p['max_uses']) && ($p['used'] ?? 0) >= $p['max_uses']) return [0, 'Промокод уже использован'];
    if (!empty($p['min_sum']) && $sum < $p['min_sum']) return [0, 'Промокод действует от ' . skx_rub($p['min_sum'])];
    $disc = $p['type'] === 'percent' ? (int)floor($sum * $p['value'] / 100) : (int)$p['value'];
    $disc = max(0, min($disc, $sum - 1));  // к оплате остаётся хотя бы 1 ₽
    return [$disc, $p + ['code' => $code]];
}

function skx_promo_used(string $code): void {
    $code = skx_promo_norm($code);
    if ($code === '') return;
    skx_locked(skx_data('promo.json'), function ($d) use ($code) {
        if (!isset($d[$code])) return [null, null];
        $d[$code]['used'] = ($d[$code]['used'] ?? 0) + 1;
        return [$d, null];
    });
}

/* ---------- заказы ---------- */

function skx_order_file(string $id): string {
    if (!preg_match('/^Z-\d{6}-[A-F0-9]{6}$/', $id)) throw new RuntimeException('order id');
    $d = skx_data('orders');
    if (!is_dir($d)) mkdir($d, 0700, true);
    return "$d/$id.json";
}

function skx_order_load(string $id): ?array {
    try { $f = skx_order_file($id); } catch (RuntimeException) { return null; }
    return is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
}

function skx_order_save(array $o): void {
    file_put_contents(skx_order_file($o['id']), json_encode($o, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function skx_order_rows(array $o): array {
    return [
        'Заказ' => $o['id'],
        'Состав' => implode('; ', array_map(fn($i) => "{$i['name']} × {$i['qty']}", $o['items'])),
        'Товары' => skx_rub($o['goods']),
        'Промокод' => $o['promo'] ? "{$o['promo']} (−" . skx_rub($o['discount']) . ')' : '',
        'К оплате' => skx_rub($o['amount']),
        'Получение' => $o['delivery'],
        'Адрес ПВЗ' => $o['address'],
        'Доставка' => $o['free_delivery'] ? 'бесплатно' : 'по тарифу СДЭК, сообщить клиенту',
        'Клиент' => $o['name'],
        'Телефон' => $o['phone'],
        'E-mail' => $o['email'],
        'Комментарий' => $o['comment'],
        'PaymentId' => $o['payment_id'] ?? '',
        'Статус оплаты' => SKX_STATUS[$o['status']] ?? $o['status'],
    ];
}

/* ---------- Т-Банк ---------- */

function skx_tbank_ready(array $c): bool {
    foreach (['terminal_key', 'password'] as $k) if (empty($c[$k]) || str_contains($c[$k], 'ЗАМЕНИТЬ')) return false;
    return true;
}

/* Token = SHA-256 от склеенных значений корневых параметров и Password, отсортированных по ключу.
   Вложенные объекты (DATA, Receipt) и сам Token в подпись не входят. */
function skx_tbank_token(array $p, string $password): string {
    $flat = [];
    foreach ($p as $k => $v) if ($k !== 'Token' && !is_array($v)) $flat[$k] = is_bool($v) ? ($v ? 'true' : 'false') : (string)$v;
    $flat['Password'] = $password;
    ksort($flat, SORT_STRING);
    return hash('sha256', implode('', $flat));
}

function skx_tbank_call(string $method, array $p): array {
    $c = skx_config()['tbank'] ?? [];
    $p['TerminalKey'] = $c['terminal_key'];
    $p['Token'] = skx_tbank_token($p, $c['password']);
    $ch = curl_init(rtrim($c['api_url'] ?? 'https://securepay.tinkoff.ru/v2/', '/') . '/' . $method);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($p, JSON_UNESCAPED_UNICODE)]);
    $r = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($r === false) throw new RuntimeException('tbank: ' . $err);
    return json_decode($r, true) ?: ['Success' => false, 'Message' => 'bad json'];
}

/* Строки чека 54-ФЗ в копейках. Скидка распределяется по позициям; если цена за штуку не делится нацело,
   позиция делится на две строки, чтобы Price × Quantity = Amount и сумма строк = Amount платежа. */
function skx_receipt_items(array $items, int $goodsKop, int $amountKop, string $tax): array {
    $out = [];
    $left = $amountKop;
    foreach (array_values($items) as $i => $it) {
        $line = $it['price'] * 100 * $it['qty'];
        $pay = $i === count($items) - 1 ? $left : (int)floor($line * $amountKop / $goodsKop);
        $left -= $pay;
        $q = $it['qty'];
        $unit = intdiv($pay, $q);
        $rest = $pay - $unit * $q;
        $mk = fn($price, $qty) => ['Name' => mb_substr($it['name'], 0, 128), 'Price' => $price, 'Quantity' => $qty, 'Amount' => $price * $qty,
            'Tax' => $tax, 'PaymentMethod' => 'full_prepayment', 'PaymentObject' => 'commodity'];
        if ($rest === 0) $out[] = $mk($unit, $q);
        else { if ($q > 1) $out[] = $mk($unit, $q - 1); $out[] = $mk($unit + $rest, 1); }
    }
    return $out;
}

/* ---------- почта (SMTP Beget) ---------- */

function skx_mail(string $subject, array $rows, ?string $replyTo = null, ?string $to = null): bool {
    $m = skx_config()['mail'] ?? [];
    $to = $to ?? ($m['to'] ?? 'info@menoramed.ru');
    $body = '';
    foreach ($rows as $k => $v) if ((string)$v !== '') $body .= "$k: $v\r\n";
    $body .= "\r\n— Сайт skinolax.ru\r\n";
    $enc = fn($s) => '=?UTF-8?B?' . base64_encode($s) . '?=';
    $from = $m['from'] ?? 'orders@skinolax.ru';
    $headers = [
        'Date: ' . date('r'),
        'From: ' . $enc($m['from_name'] ?? 'Сайт СКИНОЛАКС') . " <$from>",
        "To: <$to>",
        'Subject: ' . $enc($subject),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . substr(strrchr($from, '@'), 1) . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $headers[] = "Reply-To: <$replyTo>";
    $msg = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body));
    try {
        skx_smtp($m, $from, $to, $msg);
        return true;
    } catch (Throwable $e) {
        file_put_contents(skx_data('mail-errors.log'), date('c') . " $subject: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
        return false;
    }
}

function skx_smtp(array $m, string $from, string $to, string $msg): void {
    $host = $m['smtp_host'] ?? 'smtp.beget.com';
    $port = (int)($m['smtp_port'] ?? 465);
    $secure = $m['smtp_secure'] ?? 'ssl';  // ssl (465) или none (только для локального теста)
    $s = stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . "$host:$port", $no, $str, 20);
    if (!$s) throw new RuntimeException("connect $str");
    stream_set_timeout($s, 20);
    $read = function () use ($s) {
        $r = '';
        while (($l = fgets($s, 1024)) !== false) { $r .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; }
        return $r;
    };
    $cmd = function (?string $c, int $want) use ($s, $read) {
        if ($c !== null) fwrite($s, $c . "\r\n");
        $r = $read();
        if ((int)substr($r, 0, 3) !== $want) throw new RuntimeException(trim(($c && str_starts_with($c, 'AUTH') ? 'AUTH' : $c) . ' → ' . $r));
    };
    $cmd(null, 220);
    $cmd('EHLO skinolax.ru', 250);
    if (!empty($m['smtp_user'])) {
        $cmd('AUTH LOGIN', 334);
        $cmd(base64_encode($m['smtp_user']), 334);
        $cmd(base64_encode($m['smtp_password'] ?? ''), 235);
    }
    $cmd("MAIL FROM:<$from>", 250);
    $cmd("RCPT TO:<$to>", 250);
    $cmd('DATA', 354);
    $cmd(preg_replace('/^\./m', '..', $msg) . "\r\n.", 250);
    fwrite($s, "QUIT\r\n");
    fclose($s);
}
