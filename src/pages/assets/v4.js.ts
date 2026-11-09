// Скрипт сайта (меню, корзина, промокод, формы). Цена и способы получения подставляются из src/data/shop.json,
// картинки позиций корзины — из каталога src/data/media.json (src/lib/media.ts).
import shop from '../../data/shop.json';
import source from '../../scripts/v4.js?raw';
import { cartMap } from '../../lib/media';

export const GET = () => new Response(
  source.replaceAll('__SHOP__', JSON.stringify(shop)).replaceAll('__MEDIA__', JSON.stringify(cartMap())),
  { headers: { 'Content-Type': 'text/javascript' } },
);
