// shop.json читает PHP-сервер заказов: цену и порог бесплатной доставки берёт отсюда и сумму к оплате считает сам.
import shop from '../data/shop.json';

export const GET = () => new Response(JSON.stringify(shop, null, 1), { headers: { 'Content-Type': 'application/json' } });
