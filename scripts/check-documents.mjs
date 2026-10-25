// Проверяет, что PDF-документы, на которые ссылаются страницы, лежат в public/documents.
// PDF не хранятся в репозитории (≈75 МБ); без них сайт собирается, но ссылки на документы будут вести в пустоту.
import { readdirSync, readFileSync, existsSync, statSync } from 'node:fs';
import { join } from 'node:path';

const walk = (dir) => readdirSync(dir).flatMap((f) => {
  const p = join(dir, f);
  return statSync(p).isDirectory() ? walk(p) : [p];
});
const refs = new Set();
for (const f of walk('src').filter((f) => /\.(astro|ts|json)$/.test(f))) {
  for (const m of readFileSync(f, 'utf8').matchAll(/documents\/[\w./-]+\.pdf/g)) refs.add(m[0]);
}
const methods = JSON.parse(readFileSync('src/data/metody.json', 'utf8')).methods;
for (const m of Object.values(methods)) refs.add(`documents/methods/${m.file}`);
const missing = [...refs].filter((r) => !existsSync(join('public', r))).sort();
if (missing.length) {
  console.warn(`Нет ${missing.length} из ${refs.size} PDF в public/documents (скопируйте из прежней сборки skinolax_v4/documents):`);
  for (const r of missing) console.warn('  ' + r);
} else {
  console.log(`Все ${refs.size} PDF на месте.`);
}
