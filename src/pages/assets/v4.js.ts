// Скрипт сайта (меню, корзина, промокод, формы). Цена и способы получения подставляются из src/data/shop.json.
import shop from '../../data/shop.json';
import source from '../../scripts/v4.js?raw';

export const GET = () => new Response(source.replaceAll('__SHOP__', JSON.stringify(shop)), { headers: { 'Content-Type': 'text/javascript' } });
