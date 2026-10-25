// Карта сайта: основные страницы в порядке src/data/pages.json (без методик для специалистов, как в прежней сборке).
import pages from '../data/pages.json';

export const GET = () => new Response(
  '<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
  + Object.keys(pages).map((p) => `<url><loc>https://skinolax.ru/${p}</loc></url>`).join('')
  + '</urlset>',
  { headers: { 'Content-Type': 'application/xml' } },
);
