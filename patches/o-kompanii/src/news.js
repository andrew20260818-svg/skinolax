/* СКИНОЛАКС: новости из админки и «Читать полностью» в обращении директора.
   В HTML уже есть новости из сборки (news-seed.json) — если /api/news.php недоступен, они и остаются. */
(function () {
  var M = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; }); }
  function date(n) { if (n.date_text) return esc(n.date_text); var p = String(n.date).split('-'); return +p[2] + ' ' + M[+p[1] - 1] + ' ' + p[0]; }
  function excerpt(t) { t = String(t).replace(/\s+/g, ' ').trim(); return t.length > 180 ? t.slice(0, 180).replace(/\s+\S*$/, '') + '…' : t; }
  function paras(t) { return String(t).trim().split(/\n\s*\n/).map(function (p) { return '<p>' + esc(p.trim()).replace(/\n/g, '<br>') + '</p>'; }).join(''); }
  function safeLink(u) { return /^(https:\/\/|documents\/)/.test(u || '') ? esc(u) : ''; }
  function card(n, full) {
    var ph = n.photo && /^uploads\/news\//.test(n.photo) ? '<img class="news-photo" src="' + esc(n.photo) + '" alt="" loading="lazy">' : '';
    var link = full && safeLink(n.link) ? '<a class="link" href="' + safeLink(n.link) + '" target="_blank" rel="noopener">Документ ↗</a>' : '';
    var more = full ? '' : '<a class="link" href="novosti.html#n-' + esc(n.id) + '">Подробнее →</a>';
    return '<article class="card news-card' + (full ? ' full' : '') + '"' + (full ? ' id="n-' + esc(n.id) + '"' : '') + '>' + ph +
      '<div class="news-body"><p class="eyebrow"><time datetime="' + esc(n.date) + '">' + date(n) + '</time></p><h3>' + esc(n.title) + '</h3>' +
      (full ? paras(n.text) : '<p>' + esc(excerpt(n.text)) + '</p>') + link + more + '</div></article>';
  }
  function teaser(n) { return '<a class="teaser" href="novosti.html#n-' + esc(n.id) + '"><small>' + date(n) + '</small>' + esc(n.title) + '</a>'; }

  var boxes = document.querySelectorAll('[data-news]');
  if (boxes.length && window.fetch) {
    fetch('api/news.php', {headers: {Accept: 'application/json'}}).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
      if (!d || !Array.isArray(d.news)) return;
      boxes.forEach(function (box) {
        var lim = box.getAttribute('data-news'), mode = box.getAttribute('data-mode') || 'card';
        var list = lim === 'all' ? d.news : d.news.slice(0, +lim);
        if (!list.length) { if (mode !== 'teaser') box.innerHTML = '<p class="small">Новостей пока нет.</p>'; else box.innerHTML = ''; return; }
        box.innerHTML = list.map(function (n) { return mode === 'teaser' ? teaser(n) : card(n, mode === 'full'); }).join('');
      });
      if (location.hash) { var t = document.getElementById(location.hash.slice(1)); if (t) t.scrollIntoView(); }
    }).catch(function () {});
  }

  document.querySelectorAll('[data-collapse]').forEach(function (el) {
    var btn = el.parentNode.querySelector('.ok-more');
    if (!btn) return;
    function check() { btn.hidden = !(el.classList.contains('collapsed') && el.scrollHeight > el.clientHeight + 4); }
    el.classList.add('collapsed'); check();
    window.addEventListener('resize', check);
    btn.addEventListener('click', function () { el.classList.remove('collapsed'); btn.hidden = true; });
  });
})();
