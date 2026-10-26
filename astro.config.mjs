// @ts-check
import { defineConfig } from 'astro/config';

// Статическая сборка: страницы собираются в dist/ как *.html с теми же адресами, что на skinolax.ru.
// PHP-обработчики заказов (public/api, public/admin) копируются в dist/ без изменений и работают на Beget.
export default defineConfig({
  site: 'https://skinolax.ru',
  output: 'static',
  trailingSlash: 'never',
  build: { format: 'file', assets: '_astro' },
  compressHTML: false,
});
