# Сайт СКИНОЛАКС на Astro

Статический сайт skinolax.ru, собранный на [Astro](https://astro.build). Страницы, адреса и тексты те же, что в прежней сборке `skinolax_v4` (Python-скрипт `build_skinolax_v4.py`). Заказы, промокоды, оплата Т-Банком и админка заказов работают на PHP и лежат в сборке без изменений.

## Как устроено

| Что | Где |
| --- | --- |
| Общий каркас: `<head>`, шапка, подвал | `src/layouts/Base.astro`, `src/components/Header.astro`, `src/components/Footer.astro` |
| Заголовки и описания страниц | `src/data/pages.json` |
| Цена, НДС, порог бесплатной доставки, способы получения | `src/data/shop.json` (попадает в шапку, корзину `assets/v4.js` и в `shop.json` для PHP) |
| Страницы сайта | `src/pages/*.astro` (содержимое `<main>` перенесено из прежней сборки) |
| Методы по нозологиям (24 страницы) и «Применение по показаниям» | один шаблон `src/pages/metod-[slug].astro` и `src/pages/specialistam-metody.astro`; тексты в `src/data/metody.json` и `src/data/metody-obshchee.json` (бывшие `data_nozologii.py` и `build_metody.py`) |
| Скрипт сайта: меню, корзина, промокод, формы | `src/scripts/v4.js` |
| Стили, картинки, `metody.js` | `public/assets/` |
| PHP: заказы, промокоды, уведомления Т-Банка, админка | `public/api/`, `public/admin/` |
| Настройки PHP-сервера (пароли и ключи), кладутся рядом с `public_html` | `beget/private/config.example.php` |

## Сборка

```bash
npm install
npm run dev      # предпросмотр на http://localhost:4321
npm run build    # готовый сайт в dist/
```

PDF-документы (инструкция, РУ, Рекомендации, отчёты, методики, около 75 МБ) в репозиторий не входят. Перед сборкой для заливки скопируйте папку `documents` из прежней сборки `skinolax_v4` в `public/documents`. Команда `npm run check:docs` покажет, каких PDF не хватает.

## Заливка на Beget

Содержимое `dist/` (вместе со скрытыми файлами `.htaccess`) заливается в `public_html`, как раньше папка `skinolax_v4`. Остальные шаги (папка `private`, ключи Т-Банка, почта, проверка заказом) описаны в `УСТАНОВКА_Beget.md` в материалах проекта и не меняются.

Сайт пока закрыт от поисковиков (`robots.txt` и `noindex` на страницах), как и прежняя тестовая сборка.

## Тестовый адрес на Netlify

Настройки сборки лежат в `netlify.toml`: команда `npm run build`, папка `dist`, Node 22. Netlify отдаёт только статические страницы, PHP он не исполняет, поэтому `api/` и `admin/` в его сборку не попадают. На адресе `*.netlify.app` оформление заказа и промокод покажут ошибку «Не удалось проверить…»: заказы по-прежнему принимает PHP на Beget. PDF из `public/documents` в репозитории нет, поэтому ссылки на документы на Netlify тоже не откроются, пока PDF не добавлены.
