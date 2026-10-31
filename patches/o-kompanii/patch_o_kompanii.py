"""Страница «О компании» по утверждённой структуре + новости из админки (тред «Предложить структуру страницы «О компании»», 31.10.2026).

Запуск: python3 patch_o_kompanii.py <папка «Новый сайт»>   (там лежат build_skinolax_v4.py, skinolax_v4_src, orders-php)
Сайт на Beget и skinolax.ru не трогает. Повторный запуск безопасен.

1. Бэкап в backup_2026-10-31_pre-o-kompanii.
2. build_skinolax_v4.py:
   - новости: стартовый набор NEWS → news-seed.json; карточки с фото, датой, заголовком и началом текста;
     главная (блок «Новости и материалы»), «О компании» и «Новости» подгружают свежие новости из /api/news.php;
   - o-kompanii.html: шапка с якорями → коротко о компании (факты из Выписки) → резидент технопарка Медтех
     → обращение генерального директора → видео с производства → новости → документы и реквизиты → плашка;
   - убраны утверждения без источника: «основана в 2021 году», «собственное производство», ISO 13485.
3. skinolax_v4_src: o-kompanii.css, news.js, medteh-moskva.svg (логотип из медиапака Медтеха).
4. orders-php/public: api/news.php, admin/news.php (вкладка «Новости»), uploads/news/.htaccess; ссылка «Новости» в меню админки.
5. Пересборка skinolax_v4.

Что заполнить потом (в build_skinolax_v4.py, блок «o-kompanii 2026-10-31»):
   DIRECTOR = текст обращения (абзацы), ФИО; фото — skinolax_v4_src/photos/direktor.jpg, подпись — photos/podpis.png;
   PROD_VIDEO = ссылка на плеер RuTube, обложка, подпись; MEDTECH_SINCE = «с <месяц год>».
"""
import sys, shutil, subprocess, re
from pathlib import Path

HERE = Path(__file__).resolve().parent
B = Path(sys.argv[1]).resolve()
SRC, OUT = B / 'skinolax_v4_src', B / 'skinolax_v4'
PHP = B / 'orders-php' / 'public'
BUILD = B / 'build_skinolax_v4.py'
MARK = '# o-kompanii 2026-10-31'

bk = B / 'backup_2026-10-31_pre-o-kompanii'
if not bk.exists():
    bk.mkdir()
    shutil.copy2(BUILD, bk / BUILD.name)
    if (PHP / 'admin/index.php').exists():
        shutil.copy2(PHP / 'admin/index.php', bk / 'admin-index.php')

# ---------- файлы ----------
for f in ['o-kompanii.css', 'news.js', 'medteh-moskva.svg']:
    shutil.copy2(HERE / 'src' / f, SRC / f)
shutil.copytree(HERE / 'src/php', PHP, dirs_exist_ok=True)

adm = PHP / 'admin/index.php'
if adm.exists():
    a = adm.read_text(encoding='utf-8')
    if 'news.php' not in a:
        a = a.replace('<a href="#leads">Заявки</a>', '<a href="#leads">Заявки</a><a href="news.php">Новости</a>', 1)
        adm.write_text(a, encoding='utf-8')

# ---------- сборка ----------
NEWS_CODE = r'''# ================= НОВОСТИ И МАТЕРИАЛЫ =================
# o-kompanii 2026-10-31: новости ведутся в админке (/admin/news.php → private/data/news.json, фото в uploads/news).
# NEWS — стартовый набор: пока в админке ничего не сохранено, сайт показывает его (news-seed.json для /api/news.php).
# В новостях — только события, подтверждённые документами проекта.
import html as _html
NEWS = [
    {'id': 'ru-izmeneniya-2026', 'date': '2026-01-31', 'title': 'Внесены изменения в регистрационное удостоверение',
     'text': 'Росздравнадзор внёс изменения в регистрационное удостоверение СКИНОЛАКС РЗН 2023/20723. Удостоверение действует бессрочно.',
     'link': 'documents/site/vypiska-reestr-2026.pdf', 'photo': '', 'published': True},
    {'id': 'instrukciya-2026', 'date': '2026-01-01', 'date_text': '2026', 'title': 'Инструкция по применению: редакция 2026',
     'text': 'Опубликована редакция 2026 года инструкции по применению покрытия.', 'link': INSTR, 'photo': '', 'published': True},
    {'id': 'registraciya-2023', 'date': '2023-08-03', 'title': 'СКИНОЛАКС зарегистрирован как медицинское изделие',
     'text': 'Первичная государственная регистрация медицинского изделия, регистрационный номер РЗН 2023/20723.',
     'link': 'documents/site/vypiska-reestr-2026.pdf', 'photo': '', 'published': True},
]
(OUT / 'news-seed.json').write_text(json.dumps(NEWS, ensure_ascii=False, indent=1), encoding='utf-8')
for _f in ['o-kompanii.css', 'news.js', 'medteh-moskva.svg']:
    shutil.copy2(SRC / _f, OUT / 'assets' / _f)
NEWS_ASSETS = '<link rel="stylesheet" href="assets/o-kompanii.css"><script src="assets/news.js" defer></script>'
_MONTHS = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря']


def news_date(n):
    if n.get('date_text'):
        return n['date_text']
    y, m, d = n['date'].split('-')
    return f'{int(d)}{NB}{_MONTHS[int(m) - 1]} {y}'


def news_excerpt(t):
    t = ' '.join(t.split())
    return t if len(t) <= 180 else t[:180].rsplit(' ', 1)[0] + '…'


def news_card(n, full=False):
    e = _html.escape
    ph = f'<img class="news-photo" src="{e(n["photo"])}" alt="" loading="lazy">' if n.get('photo') else ''
    body = ''.join(f'<p>{e(p.strip())}</p>' for p in re.split(r'\n\s*\n', n['text'].strip()) if p.strip()) if full else f'<p>{e(news_excerpt(n["text"]))}</p>'
    link = f'<a class="link" href="{e(n["link"])}" target="_blank" rel="noopener">Документ ↗</a>' if full and n.get('link') else ''
    more = '' if full else f'<a class="link" href="novosti.html#n-{n["id"]}">Подробнее →</a>'
    ident = f' id="n-{n["id"]}"' if full else ''
    return (f'<article class="card news-card{" full" if full else ""}"{ident}>{ph}<div class="news-body"><p class="eyebrow"><time datetime="{n["date"]}">{news_date(n)}</time></p>'
            f'<h3>{e(n["title"])}</h3>{body}{link}{more}</div></article>')


NEWS_SORTED = sorted([n for n in NEWS if n.get('published')], key=lambda n: n['date'], reverse=True)
NEWS_TEASER = ('<div class="teaser-row"><div><p class="eyebrow">Компания</p><h2 style="font-size:28px">Новости и материалы</h2>'
               '<p class="small" style="margin-top:8px"><a href="o-kompanii.html">О компании →</a></p></div>'
               '<div class="teaser-links"><div class="teaser-news" data-news="2" data-mode="teaser">'
               + ''.join(f'<a class="teaser" href="novosti.html#n-{n["id"]}"><small>{news_date(n)}</small>{_html.escape(n["title"])}</a>' for n in NEWS_SORTED[:2])
               + '</div><a class="teaser" href="materialy.html"><small>Материалы</small>Инструкция, методики, видео</a></div></div>' + NEWS_ASSETS)
'''

NOVOSTI_CODE = r'''page('novosti.html', 'Новости | СКИНОЛАКС', 'Новости ООО «МЕНОРА-МЕД» и медицинского изделия СКИНОЛАКС.',
     page_hero([('index.html', 'Главная'), ('o-kompanii.html', 'О компании'), (None, 'Новости')], 'Новости', 'События компании «МЕНОРА-МЕД» и изменения в документах на СКИНОЛАКС.')
     + sec('<div class="news-list" data-news="all" data-mode="full">' + ''.join(news_card(n, True) for n in NEWS_SORTED) + '</div>', 'ice')
     + sec(f'<div class="conclusion"><div class="concl-text"><img class="concl-logo" src="assets/logo-white.png" alt="СКИНОЛАКС" width="1631" height="324" loading="lazy"><h2>Посмотреть покрытие</h2></div><a class="btn" href="product.html">О продукте</a></div>'),
     extra_head=NEWS_ASSETS)

'''

OK_CODE = r'''# o-kompanii 2026-10-31: «О компании» по структуре, утверждённой Андреем 31.10.2026.
# Заполнить: DIRECTOR['name'];
# фото — skinolax_v4_src/photos/direktor.jpg (4:5), подпись — photos/podpis.png (прозрачный фон).
# Текст — вариант А, выбран Андреем 31.10.2026. Фраза «вместе с врачами, которые нам доверяют» документами не подтверждена.
DIRECTOR = {'name': '', 'role': 'Генеральный директор ООО «МЕНОРА-МЕД»', 'text': [
    'Здравствуйте!',
    'Рана, ожог или пролежень — это не только медицинская задача. Это боль, тревога, ежедневные перевязки и заботы, которые ложатся на самого человека и на его близких. Мы знаем, как это тяжело, и поэтому делаем СКИНОЛАКС.',
    '<b>Наша миссия — сделать уход за раной проще, спокойнее и бережнее для каждого, кто с этим столкнулся.</b> Чтобы помощь была под рукой: в больнице, дома, в дороге.',
    'СКИНОЛАКС — российское медицинское изделие, мы производим его в Москве. Покрытие формируется прямо на ране, защищает её от загрязнения, микробов и повторной травмы и не прилипает к влажной ране, поэтому перевязки проходят безболезненно.',
    'Наши цели — чтобы СКИНОЛАКС был доступен там, где он нужен; чтобы каждый мог разобраться, как им пользоваться; и чтобы мы продолжали развивать производство вместе с врачами, которые нам доверяют.',
    'Если у вас остались вопросы — позвоните или напишите нам. Мы ответим. Берегите себя и своих близких.',
]}
# Видео с производства: ссылка на плеер RuTube (https://rutube.ru/play/embed/…), обложка в assets, подпись «где и когда снято».
PROD_VIDEO = {'embed': '', 'poster': 'assets/rutube-test.jpg', 'title': 'Производство СКИНОЛАКС', 'caption': ''}
MEDTECH_URL = 'https://www.medtech.moscow/skinolax'
MEDTECH_SINCE = ''  # «с марта 2025 года» — после подтверждения


def _podpis():
    for ext in ('png', 'webp'):
        f = PHOTOS / f'podpis.{ext}'
        if f.exists():
            (OUT / 'assets/photos').mkdir(exist_ok=True)
            shutil.copy2(f, OUT / 'assets/photos' / f.name)
            return f'<img src="assets/photos/{f.name}" alt="Подпись">'
    return ''


_dir_text = (''.join(f'<p>{p}</p>' for p in DIRECTOR['text']) if DIRECTOR['text']
             else '<div class="ok-dir-ph"><b>Обращение генерального директора</b><p class="small" style="margin:6px 0 0">Текст появится после утверждения. Черновики — в предложении по странице «О компании».</p></div>')
_video = (rutube(PROD_VIDEO['embed'], PROD_VIDEO['title'], PROD_VIDEO['poster']) if PROD_VIDEO['embed']
          else '<div class="media ph" style="--ratio:16/9" role="img" aria-label="Место для видео с производства"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h12v12H4z"/><path d="M16 10l4-2v8l-4-2"/></svg><b>Видео с производства</b><small>Ссылку RuTube вписать в PROD_VIDEO</small></div>')
OK_TOC = ''.join(f'<a href="#{i}">{t}</a>' for i, t in [('about', 'О компании'), ('medtech', 'Резидент технопарка'), ('direktor', 'Обращение директора'),
                                                          ('proizvodstvo', 'Производство'), ('novosti', 'Новости'), ('dokumenty', 'Документы')])
page('o-kompanii.html', 'О компании МЕНОРА-МЕД', 'ООО «МЕНОРА-МЕД» — производитель медицинского изделия СКИНОЛАКС: о компании, обращение генерального директора, производство, новости, документы.',
     page_hero([('index.html', 'Главная'), (None, 'О компании')], 'ООО <span>«МЕНОРА-МЕД»</span>',
               f'Производитель медицинского изделия СКИНОЛАКС®{ref("s26")}', f'<nav class="ok-toc" aria-label="Разделы страницы">{OK_TOC}</nav>')
     # 1. Коротко о компании — только Выписка из реестра (s26)
     + sec('<div class="ok-about"><div>' + head('О компании')
           + f'<p class="lead">ООО «МЕНОРА-МЕД» — российская компания, производитель покрытия медицинского из пенополиуретана для закрытия ран и ожогов СКИНОЛАКС®{ref("s26")}. '
           f'Место производства — Москва, проспект Вернадского, 96{ref("s26")}.</p></div>'
           '<div class="ok-facts">'
           f'<div class="ok-fact"><b>03.08.2023</b><span>первичная регистрация СКИНОЛАКС как медицинского изделия{ref("s26")}</span></div>'
           f'<div class="ok-fact"><b>Бессрочно</b><span>срок действия РУ РЗН 2023/20723{ref("s26")}</span></div>'
           f'<div class="ok-fact"><b>2 модели</b><span>СКИНОЛАКС 100 и СКИНОЛАКС 200{ref("s26")}</span></div></div></div>', '', 'about')
     # 2. Резидент технопарка: логотип из медиапака Медтеха, в его цвете, на белом; только здесь
     + sec(f'<div class="ok-medtech"><a class="mt-logo" href="{MEDTECH_URL}" target="_blank" rel="noopener"><img src="assets/medteh-moskva.svg" alt="Медтех. Москва" width="220" height="62"></a>'
           f'<div><p class="eyebrow">Резидент технопарка</p><p>ООО «МЕНОРА-МЕД» — резидент технопарка «Медтех»{" " + MEDTECH_SINCE if MEDTECH_SINCE else ""}.</p>'
           f'<a class="link" href="{MEDTECH_URL}" target="_blank" rel="noopener">СКИНОЛАКС на сайте технопарка ↗</a></div></div>', '', 'medtech')
     # 3. Обращение генерального директора
     + sec('<div class="ok-director">' + media('direktor', DIRECTOR['role'], 'Фото генерального директора', '4/5')
           + '<div><p class="eyebrow">Обращение генерального директора</p>'
           + f'<div class="ok-dir-text" data-collapse>{_dir_text}</div><button type="button" class="ok-more" hidden>Читать полностью</button>'
           + f'<div class="ok-sign">{_podpis()}<b>{DIRECTOR["name"] or "[Фамилия Имя Отчество]"}</b><span>{DIRECTOR["role"]}</span></div></div></div>', 'ice', 'direktor')
     # 4. Видео с производства
     + sec(head('Производство') + f'<div class="ok-video">{_video}'
           + (f'<p class="small">{PROD_VIDEO["caption"]}</p>' if PROD_VIDEO['caption'] else '')
           + f'<p class="small">Смотрите также: <a href="{TEST_EMBED.replace("/play/embed/", "/video/")}/" target="_blank" rel="noopener">испытания материала СКИНОЛАКС — RuTube, канал «ГОСТ», 2:36 ↗</a></p></div>', '', 'proizvodstvo')
     # 5. Новости — свежие из админки (news.js), в HTML — стартовый набор
     + sec('<div class="ok-news-head">' + head('Новости') + '<a class="btn small ghost" href="novosti.html">Все новости</a></div>'
           + '<div class="news-grid" data-news="3">' + ''.join(news_card(n) for n in NEWS_SORTED[:3]) + '</div>', 'ice', 'novosti')
     # 6. Документы, реквизиты, контакты
     + sec('<div class="two-col"><div class="card"><h3>Документы</h3>'
           f'<p>Регистрационное удостоверение РЗН 2023/20723{ref("s2")}</p><p>Первичная регистрация 03.08.2023, изменения внесены 31.01.2026{ref("s26")}</p><p>ТУ 21.20.24-001-70598476-2021{ref("s26")}</p>'
           '<div class="actions"><a class="btn small ghost" href="dokumenty.html">Все документы</a><a class="btn small ghost" href="documents/site/vypiska-reestr-2026.pdf" target="_blank" rel="noopener">Выписка из реестра</a><a class="btn small ghost" href="' + INSTR + '" target="_blank" rel="noopener">Инструкция</a></div></div>'
           '<div class="card"><h3>Реквизиты и контакты</h3><p>ООО «МЕНОРА-МЕД»<br>ОГРН 1217700485309 · ИНН 9702037110 · КПП 772901001</p>'
           f'<p>119571, Москва, проспект Вернадского, 96{ref("s26")}</p>'
           '<p><a href="tel:+74955454950">+7 (495) 545-49-50</a><br><a href="mailto:info@menoramed.ru">info@menoramed.ru</a></p>'
           '<div class="actions"><a class="btn small ghost" href="partnery.html">Партнёрам</a><a class="btn small ghost" href="nezhelatelnoe-yavlenie.html">Сообщить о нежелательном явлении</a></div></div></div>', '', 'dokumenty')
     + sec(f'<div class="conclusion"><div class="concl-text"><img class="concl-logo" src="assets/logo-white.png" alt="СКИНОЛАКС" width="1631" height="324" loading="lazy"><h2>Посмотреть покрытие</h2></div><a class="btn" href="product.html">О продукте</a></div>'),
     extra_head=NEWS_ASSETS)

'''


def cut(s, start, end, new):
    i = s.index(start)
    j = s.index(end, i)
    return s[:i] + new + s[j:]


b = BUILD.read_text(encoding='utf-8')
if MARK not in b:
    b = cut(b, '# ================= НОВОСТИ И МАТЕРИАЛЫ =================', 'chips = ', NEWS_CODE)
    b = cut(b, "page('novosti.html'", "page('materialy.html'", NOVOSTI_CODE)
    b = cut(b, "page('o-kompanii.html'", "page('dokumenty.html'", OK_CODE)
    BUILD.write_text(b, encoding='utf-8')
    print('build_skinolax_v4.py: обновлён')
else:
    print('build_skinolax_v4.py: уже обновлён')

subprocess.run([sys.executable, BUILD.name], cwd=B, check=True)
print('Готово: skinolax_v4/o-kompanii.html, novosti.html, admin/news.php')
