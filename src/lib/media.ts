// Картинки товара: адреса и srcset по каталогу src/data/media.json и итогам scripts/build-images.mjs.
// Используется на страницах (компонент PackImg) и для корзины (подставляется в assets/v4.js как __MEDIA__).
import cat from '../data/media.json';
import gen from '../data/media.generated.json';

type Role = keyof typeof cat.roles;
type Img = { src: string; alt: string; bg: 'white' | 'transparent' | 'photo'; roles: string[]; count?: number };
type Gen = { hash: string; width: number; height: number; widths: number[]; fallback: 'png' | 'jpg' };

const images = cat.images as Record<string, Img>;
const built = gen as Record<string, Gen>;
const DIR = 'assets/media/';

export function picture(id: string, role: Role) {
  const im = images[id], g = built[id], r = cat.roles[role];
  if (!im || !g) throw new Error(`media: нет картинки «${id}» — проверьте src/data/media.json и запустите npm run images`);
  if (!im.roles.includes(role)) throw new Error(`media: у картинки «${id}» нет роли «${role}»`);
  // только ширины этой роли (обрезанные по исходнику), чтобы браузер не выбирал лишнее
  const ws = [...new Set(r.widths.map(w => Math.min(w, g.width)))];
  const url = (w: number, f: string) => `${DIR}${id}.${g.hash}-${w}.${f}`;
  const set = (f: string) => ws.map(w => `${url(w, f)} ${w}w`).join(', ');
  const fbw = Math.min(r.fallback, g.width);
  return {
    id, alt: im.alt, bg: im.bg, width: g.width, height: g.height, sizes: r.sizes,
    avif: set('avif'), webp: set('webp'), fallbackSet: set(g.fallback), src: url(fbw, g.fallback),
  };
}

// Картинка для позиции корзины: точный SKU → линейка (часть до первого дефиса) → нет картинки
export function imageForSku(sku: string): string | undefined {
  return (cat.sku as Record<string, string>)[sku] ?? (cat.line as Record<string, string>)[sku.split('-')[0]];
}

// Компактная карта для корзины в v4.js: ключ SKU или линейки → готовые srcset
export function cartMap() {
  const out: Record<string, { a: string; w: string; f: string; s: string; W: number; H: number; bg: string; alt: string }> = {};
  for (const [k, id] of [...Object.entries(cat.sku), ...Object.entries(cat.line)]) {
    const p = picture(id, 'cart');
    out[k] = { a: p.avif, w: p.webp, f: p.fallbackSet, s: p.src, W: p.width, H: p.height, bg: p.bg, alt: p.alt };
  }
  return { sizes: cat.roles.cart.sizes, items: out };
}
