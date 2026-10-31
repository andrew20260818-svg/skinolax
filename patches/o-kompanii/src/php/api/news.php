<?php
/* СКИНОЛАКС: новости для сайта (главная, «О компании», «Новости»).
   GET /api/news.php → {"news":[…]} — только опубликованные, новые сверху.
   Новости ведутся в админке (/admin/news.php) и лежат в private/data/news.json.
   Пока там ничего не сохранено, отдаётся стартовый набор news-seed.json, который собирает build_skinolax_v4.py. */
declare(strict_types=1);
require_once __DIR__ . '/_lib.php';

function skx_news_all(): array {
    $f = skx_private() . '/data/news.json';
    if (is_file($f) && filesize($f) > 0) return json_decode((string)file_get_contents($f), true) ?: [];
    $seed = json_decode((string)@file_get_contents(dirname(__DIR__) . '/news-seed.json'), true) ?: [];
    foreach ($seed as &$n) $n['published'] = true;
    return $seed;
}

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'news.php' && basename(dirname($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'api') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') skx_json(405, ['error' => 'method']);
    $keep = ['id', 'date', 'date_text', 'title', 'text', 'photo', 'link'];
    $list = array_values(array_filter(skx_news_all(), fn($n) => !empty($n['published'])));
    usort($list, fn($a, $b) => strcmp((string)$b['date'], (string)$a['date']));
    $out = array_map(fn($n) => array_intersect_key($n, array_flip($keep)), $list);
    skx_json(200, ['news' => $out]);
}
