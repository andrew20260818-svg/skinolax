// Готовит картинки товара из media/ по каталогу src/data/media.json.
// Для каждой картинки: обрезает пустые поля, добавляет ровный отступ, режет на ширины всех её ролей
// и сохраняет AVIF, WebP и запасной PNG (прозрачный фон) или JPEG (белый фон).
// Имена файлов содержат хэш исходника: assets/media/box-x10.3f2a9c1e-480.avif — их можно кешировать навсегда.
// Результат: public/assets/media/* и src/data/media.generated.json (размеры и списки ширин для страниц и корзины).
// Запуск: npm run images (сам вызывается в npm run dev и npm run build). Повторный запуск пропускает неизменённые картинки.
import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const SRC_DIR = join(ROOT, 'media');
const OUT_DIR = join(ROOT, 'public/assets/media');
const META = join(ROOT, 'src/data/media.generated.json');
const VERSION = 'v1'; // поменять, если изменились настройки ниже: все файлы пересоберутся
const PAD = 0.04;     // отступ вокруг упаковки после обрезки, доля от длинной стороны
const Q = { avif: 52, webp: 80, jpeg: 82, png: 85 };

const cat = JSON.parse(readFileSync(join(ROOT, 'src/data/media.json'), 'utf8'));
const old = existsSync(META) ? JSON.parse(readFileSync(META, 'utf8')) : {};
mkdirSync(OUT_DIR, { recursive: true });

const errors = [];
const check = (ok, msg) => { if (!ok) errors.push(msg); };
// Проверка каталога: каждая ссылка ведёт на существующую картинку и роль
for (const [id, im] of Object.entries(cat.images)) {
  check(/^[a-z0-9-]+$/.test(id), `${id}: имя только из латиницы, цифр и дефиса`);
  check(existsSync(join(SRC_DIR, im.src)), `${id}: нет файла media/${im.src}`);
  check(im.alt?.trim(), `${id}: пустой alt`);
  check(['white', 'transparent', 'photo'].includes(im.bg), `${id}: bg должен быть white, transparent или photo`);
  for (const r of im.roles) check(cat.roles[r], `${id}: неизвестная роль ${r}`);
}
for (const [k, id] of [...Object.entries(cat.sku), ...Object.entries(cat.line)]) check(cat.images[id], `${k}: ссылка на неизвестную картинку ${id}`);
for (const s of cat.showcase) check(cat.images[s.image], `витрина ${s.id}: нет картинки ${s.image}`);
if (errors.length) { console.error('media.json:\n  ' + errors.join('\n  ')); process.exit(1); }

const meta = {};
const keep = new Set(['.htaccess']);
let built = 0;
for (const [id, im] of Object.entries(cat.images)) {
  const buf = readFileSync(join(SRC_DIR, im.src));
  const hash = createHash('sha1').update(VERSION).update(JSON.stringify(Q)).update(String(PAD)).update(im.bg).update(JSON.stringify(im.roles.map(r => cat.roles[r]))).update(buf).digest('hex').slice(0, 8);
  const fb = im.bg === 'transparent' ? 'png' : 'jpg';
  const base = `${id}.${hash}`;
  const prev = old[id];
  const fresh = prev?.hash === hash && prev.widths.every(w => ['avif', 'webp', fb].every(f => existsSync(join(OUT_DIR, `${base}-${w}.${f}`))));
  if (fresh) { meta[id] = prev; prev.widths.forEach(w => ['avif', 'webp', fb].forEach(f => keep.add(`${base}-${w}.${f}`))); continue; }

  // 1. обрезка пустых полей по фону и ровный отступ
  //    (фото-сцены, bg: photo, не обрезаются: кадрирование задаётся в CSS через object-position)
  let mbuf;
  if (im.bg === 'photo') mbuf = await sharp(buf).flatten({ background: '#ffffff' }).png().toBuffer({ resolveWithObject: true });
  else {
    const bgColor = im.bg === 'white' ? { r: 255, g: 255, b: 255, alpha: 1 } : { r: 0, g: 0, b: 0, alpha: 0 };
    const trimmed = await sharp(buf).ensureAlpha().trim({ background: im.bg === 'white' ? '#ffffff' : bgColor, threshold: 10 }).toBuffer({ resolveWithObject: true });
    const p = Math.round(Math.max(trimmed.info.width, trimmed.info.height) * PAD);
    let master = sharp(trimmed.data).extend({ top: p, bottom: p, left: p, right: p, background: bgColor });
    if (im.bg === 'white') master = master.flatten({ background: '#ffffff' });
    mbuf = await master.png().toBuffer({ resolveWithObject: true });
  }
  const W = mbuf.info.width, H = mbuf.info.height;

  // 2. ширины: все ширины ролей картинки, но не больше исходной (без увеличения)
  const want = [...new Set(im.roles.flatMap(r => cat.roles[r].widths))].sort((a, b) => a - b);
  const widths = [...new Set(want.map(w => Math.min(w, W)))];

  for (const w of widths) {
    const img = () => sharp(mbuf.data).resize({ width: w, withoutEnlargement: true });
    const out = f => join(OUT_DIR, `${base}-${w}.${f}`);
    await img().avif({ quality: Q.avif, effort: 6 }).toFile(out('avif'));
    await img().webp({ quality: Q.webp, alphaQuality: 90, effort: 6 }).toFile(out('webp'));
    if (fb === 'jpg') await img().jpeg({ quality: Q.jpeg, mozjpeg: true }).toFile(out('jpg'));
    else await img().png({ palette: true, quality: Q.png, effort: 9 }).toFile(out('png'));
    ['avif', 'webp', fb].forEach(f => keep.add(`${base}-${w}.${f}`));
  }
  meta[id] = { hash, width: W, height: H, widths, fallback: fb };
  built++;
}

// Удаляем файлы прежних версий, чтобы в сборку не попадал мусор
for (const f of readdirSync(OUT_DIR)) if (!keep.has(f)) rmSync(join(OUT_DIR, f));
// Долгий кеш: имя меняется вместе с содержимым
writeFileSync(join(OUT_DIR, '.htaccess'), '<IfModule mod_headers.c>\n  Header set Cache-Control "public, max-age=31536000, immutable"\n</IfModule>\nAddType image/avif .avif\nAddType image/webp .webp\n');
writeFileSync(META, JSON.stringify(meta, null, 1) + '\n');
console.log(`Картинки товара: ${Object.keys(meta).length} в каталоге, пересобрано ${built}.`);
