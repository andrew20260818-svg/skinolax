<?php
/* Админка СКИНОЛАКС: новости. Вход тот же, что в /admin/ (пароль из private/config.php).
   Новость: дата, заголовок, текст, фото (необязательно), ссылка на документ (необязательно), «опубликована».
   Данные — private/data/news.json, фото — uploads/news/ на сайте. */
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/_lib.php';
require_once dirname(__DIR__) . '/api/news.php';

header('X-Frame-Options: DENY');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
session_name('skxadm');
session_set_cookie_params(['lifetime' => 0, 'path' => '/admin/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Strict']);
session_start();
if (empty($_SESSION['ok'])) { header('Location: ./'); exit; }  // вход — на главной странице админки
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$csrf = $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
$hid = '<input type="hidden" name="csrf" value="' . $h($csrf) . '">';
$dir = dirname(__DIR__) . '/uploads/news';
$file = skx_data('news.json');
$msg = '';

/* Первое сохранение: переносим стартовый набор (news-seed.json), чтобы его можно было править. */
function news_load(string $file): array {
    return (is_file($file) && filesize($file) > 0) ? (json_decode((string)file_get_contents($file), true) ?: []) : skx_news_all();
}

function news_photo(array $f, string $dir, string $id): array {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [null, null];
    if ($f['error'] !== UPLOAD_ERR_OK) return [null, 'Фото не загрузилось (код ' . (int)$f['error'] . ').'];
    if ($f['size'] > 8 * 1024 * 1024) return [null, 'Фото больше 8 МБ.'];
    $info = @getimagesize($f['tmp_name']);
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
    if (!$ext) return [null, 'Фото должно быть JPG, PNG или WebP.'];
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = $id . '-' . bin2hex(random_bytes(4));
    if (function_exists('imagecreatefromstring') && ($im = @imagecreatefromstring((string)file_get_contents($f['tmp_name'])))) {
        // пересохраняем: убираем метаданные и уменьшаем до 1600 px по ширине
        $w = imagesx($im);
        if ($w > 1600) { $im2 = imagescale($im, 1600); imagedestroy($im); $im = $im2; }
        imagejpeg($im, "$dir/$name.jpg", 85);
        imagedestroy($im);
        return ["uploads/news/$name.jpg", null];
    }
    if (!move_uploaded_file($f['tmp_name'], "$dir/$name.$ext")) return [null, 'Не удалось сохранить фото.'];
    return ["uploads/news/$name.$ext", null];
}

function news_photo_delete(?string $p, string $dir): void {
    if ($p && str_starts_with($p, 'uploads/news/')) @unlink($dir . '/' . basename($p));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('csrf'); }
    $a = (string)($_POST['action'] ?? '');
    $id = preg_replace('/[^a-z0-9-]/', '', (string)($_POST['id'] ?? ''));
    if ($a === 'save') {
        $title = trim((string)$_POST['title']);
        $text = trim(str_replace("\r\n", "\n", (string)$_POST['text']));
        $date = (string)($_POST['date'] ?? '');
        $link = trim((string)($_POST['link'] ?? ''));
        if ($title === '' || mb_strlen($title) > 160) $msg = 'Заголовок: от 1 до 160 знаков.';
        elseif ($text === '' || mb_strlen($text) > 8000) $msg = 'Текст: от 1 до 8000 знаков.';
        elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $msg = 'Укажите дату.';
        elseif ($link !== '' && !preg_match('~^(https://|documents/)[^\s"<>]+$~', $link)) $msg = 'Ссылка должна начинаться с https:// или documents/.';
        else {
            $isNew = $id === '';
            if ($isNew) $id = date('Ymd') . '-' . bin2hex(random_bytes(3));
            [$photo, $err] = news_photo($_FILES['photo'] ?? [], $dir, $id);
            if ($err) $msg = $err;
            else {
                $all = news_load($file);
                $old = null;
                foreach ($all as $k => $n) if ($n['id'] === $id) { $old = $k; break; }
                $item = ($old !== null ? $all[$old] : ['id' => $id, 'created' => date('c')]);
                $item = array_merge($item, ['date' => $date, 'title' => $title, 'text' => $text, 'link' => $link,
                    'published' => !empty($_POST['published']), 'updated' => date('c')]);
                unset($item['date_text']);  // дата из формы точнее подписи стартового набора
                if ($photo) { news_photo_delete($item['photo'] ?? null, $dir); $item['photo'] = $photo; }
                elseif (!empty($_POST['photo_remove'])) { news_photo_delete($item['photo'] ?? null, $dir); $item['photo'] = ''; }
                if ($old !== null) $all[$old] = $item; else $all[] = $item;
                skx_locked($file, fn($d) => [array_values($all), null]);
                header('Location: news.php?saved=1'); exit;
            }
        }
    } elseif ($a === 'toggle' || $a === 'delete') {
        $all = news_load($file);
        foreach ($all as $k => $n) {
            if ($n['id'] !== $id) continue;
            if ($a === 'toggle') $all[$k]['published'] = empty($n['published']);
            else { news_photo_delete($n['photo'] ?? null, $dir); unset($all[$k]); }
        }
        skx_locked($file, fn($d) => [array_values($all), null]);
        header('Location: news.php'); exit;
    }
}
if (isset($_GET['saved'])) $msg = 'Новость сохранена.';

$all = news_load($file);
usort($all, fn($a, $b) => strcmp((string)$b['date'], (string)$a['date']));
$edit = null;
if (!empty($_GET['edit'])) foreach ($all as $n) if ($n['id'] === $_GET['edit']) $edit = $n;
if ($msg && $_SERVER['REQUEST_METHOD'] === 'POST') $edit = array_merge($edit ?? [], ['id' => $id ?? '', 'title' => $_POST['title'] ?? '', 'text' => $_POST['text'] ?? '',
    'date' => $_POST['date'] ?? '', 'link' => $_POST['link'] ?? '', 'published' => !empty($_POST['published'])]);
$e = $edit ?? ['id' => '', 'date' => date('Y-m-d'), 'title' => '', 'text' => '', 'link' => '', 'photo' => '', 'published' => true];

$rows = '';
foreach ($all as $n) {
    $rows .= '<tr' . (empty($n['published']) ? ' class="off"' : '') . '><td style="white-space:nowrap">' . $h(!empty($n['date_text']) ? $n['date_text'] : date('d.m.Y', strtotime($n['date']))) . '</td>'
        . '<td>' . (!empty($n['photo']) ? '<img src="../' . $h($n['photo']) . '" alt="" style="width:72px;height:48px;object-fit:cover;border-radius:8px">' : '—') . '</td>'
        . '<td><b>' . $h($n['title']) . '</b><br><small>' . $h(mb_substr($n['text'], 0, 120)) . (mb_strlen($n['text']) > 120 ? '…' : '') . '</small></td>'
        . '<td>' . (empty($n['published']) ? 'черновик' : 'на сайте') . '</td><td style="white-space:nowrap">'
        . '<a class="btnl" href="?edit=' . $h($n['id']) . '#form">Изменить</a> '
        . '<form method="post" style="display:inline">' . $hid . '<input type="hidden" name="id" value="' . $h($n['id']) . '"><button class="ghost" name="action" value="toggle">' . (empty($n['published']) ? 'Опубликовать' : 'Снять') . '</button></form> '
        . '<form method="post" style="display:inline" onsubmit="return confirm(\'Удалить новость?\')">' . $hid . '<input type="hidden" name="id" value="' . $h($n['id']) . '"><button class="ghost" name="action" value="delete">Удалить</button></form></td></tr>';
}

echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Новости — админка СКИНОЛАКС</title><style>'
    . ':root{--c:#3CC3DE;--t:#36A7BD;--ink:#133E45;--ink2:#28626E;--mist:#DDF6FA;--ice:#EEFAFD;--bg:#FBFDFE}'
    . 'body{margin:0;font:16px/1.45 Roboto,system-ui,Arial,sans-serif;color:#333;background:var(--bg)}main{max-width:1100px;margin:0 auto;padding:24px 16px}'
    . 'h1{color:var(--t);text-transform:uppercase;font-size:28px;margin:0 0 20px}h2{color:var(--ink);font-size:20px;margin:32px 0 12px}'
    . '.card{background:#fff;border-radius:18px;box-shadow:0 10px 30px rgba(19,62,69,.08);padding:20px;margin-bottom:16px}'
    . 'table{width:100%;border-collapse:collapse;font-size:14px}th,td{text-align:left;padding:8px 10px;border-bottom:1px solid #D3EEF6;vertical-align:top}th{color:var(--ink2);font-weight:600}'
    . 'input,textarea{font:inherit;padding:8px 10px;border:1px solid #C5EDF5;border-radius:10px;min-width:0}textarea{min-height:220px;resize:vertical}'
    . 'label{display:flex;flex-direction:column;gap:4px;font-size:14px;color:var(--ink2)}label.chk{flex-direction:row;align-items:center;gap:8px}'
    . '.form{display:grid;gap:14px}.row{display:grid;grid-template-columns:200px 1fr;gap:14px}'
    . 'button,.btnl{font:inherit;font-weight:600;border:0;border-radius:999px;padding:9px 18px;background:var(--c);color:var(--ink);cursor:pointer;text-decoration:none;display:inline-block}button.ghost,.btnl{background:var(--mist)}'
    . '.msg{background:var(--ice);border-left:4px solid var(--c);padding:10px 14px;border-radius:8px;margin-bottom:16px}.off{color:#888}.wrap{overflow-x:auto}.hint{font-size:13px;color:var(--ink2);margin:0}'
    . 'nav{display:flex;gap:16px;margin-bottom:12px}nav a{color:var(--ink)}@media(max-width:700px){.row{grid-template-columns:1fr}}</style></head><body><main>'
    . '<h1>Новости</h1><nav><a href="./">← Промокоды и заказы</a><a href="../novosti.html" target="_blank">Новости на сайте ↗</a></nav>'
    . ($msg ? '<p class="msg">' . $h($msg) . '</p>' : '')
    . '<h2 id="form">' . ($e['id'] ? 'Изменить новость' : 'Новая новость') . '</h2><div class="card"><form method="post" enctype="multipart/form-data" class="form">' . $hid
    . '<input type="hidden" name="action" value="save"><input type="hidden" name="id" value="' . $h($e['id']) . '">'
    . '<div class="row"><label>Дата<input type="date" name="date" required value="' . $h($e['date']) . '"></label>'
    . '<label>Заголовок<input name="title" required maxlength="160" value="' . $h($e['title']) . '"></label></div>'
    . '<label>Текст<textarea name="text" required maxlength="8000">' . $h($e['text']) . '</textarea></label>'
    . '<p class="hint">Пустая строка — новый абзац. На главной и на странице «О компании» видно начало текста, полностью новость открывается на странице «Новости». Цифры и сроки о покрытии — только по Инструкции и документам.</p>'
    . '<div class="row"><label>Фото (JPG, PNG или WebP, до 8 МБ)<input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></label>'
    . '<div>' . (!empty($e['photo']) ? '<img src="../' . $h($e['photo']) . '" alt="" style="width:160px;border-radius:10px;display:block;margin-bottom:6px"><label class="chk"><input type="checkbox" name="photo_remove" value="1">Убрать фото</label>' : '<p class="hint">Лучше горизонтальное, 3:2 или 16:9. Без клинических фото ран.</p>') . '</div></div>'
    . '<label>Ссылка на документ (необязательно)<input name="link" maxlength="300" placeholder="https://… или documents/…" value="' . $h($e['link'] ?? '') . '"></label>'
    . '<label class="chk"><input type="checkbox" name="published" value="1"' . (!empty($e['published']) ? ' checked' : '') . '>Опубликовать на сайте</label>'
    . '<div><button>Сохранить</button>' . ($e['id'] ? ' <a class="btnl" href="news.php">Отмена</a>' : '') . '</div></form></div>'
    . '<h2>Все новости</h2><div class="card wrap"><table><tr><th>Дата</th><th>Фото</th><th>Новость</th><th>Статус</th><th></th></tr>'
    . ($rows ?: '<tr><td colspan="5">Новостей пока нет.</td></tr>') . '</table></div></main></body></html>';
