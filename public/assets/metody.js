// Всплывающая рекомендация по выбранной нозологии; без JS карточки ведут на страницу метода
(function () {
  function open(slug) {
    var d = document.getElementById('pop-' + slug);
    if (!d || typeof d.showModal !== 'function') { if (slug) location.href = 'metod-' + slug + '.html'; return; }
    d.showModal();
    if (history.replaceState) history.replaceState(null, '', '#' + slug);
  }
  document.addEventListener('click', function (e) {
    var a = e.target.closest('[data-pop]');
    if (a) { e.preventDefault(); open(a.getAttribute('data-pop')); return; }
    if (e.target.closest('[data-pick]')) { var s = document.getElementById('nz-select'); if (s && s.value) open(s.value); else if (s) s.focus(); return; }
    if (e.target.closest('[data-close]')) { e.target.closest('dialog').close(); return; }
    if (e.target.tagName === 'DIALOG') e.target.close(); // клик по подложке
  });
  document.addEventListener('change', function (e) { if (e.target.id === 'nz-select' && e.target.value) open(e.target.value); });
  document.addEventListener('close', function () { if (history.replaceState) history.replaceState(null, '', location.pathname); }, true);
  window.addEventListener('DOMContentLoaded', function () { var h = location.hash.slice(1); if (h) open(h); });
})();
