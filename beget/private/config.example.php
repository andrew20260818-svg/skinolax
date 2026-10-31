<?php
/* Настройки сервера заказов СКИНОЛАКС. Скопируйте файл в config.php в этой же папке (private, рядом с public_html)
   и впишите пароли. Файл не публикуйте и не пересылайте. */
return [
    // Адрес сайта: заказы принимаются только со страниц этого домена.
    'site_origin' => ['https://skinolax.ru', 'https://www.skinolax.ru'],

    // Хеш пароля админки /admin/. Пока пусто, админка сама покажет, как его получить.
    'admin_password_hash' => '',

    // Почта: заказы уходят с orders@skinolax.ru на info@menoramed.ru через SMTP Beget.
    'mail' => [
        'to' => 'info@menoramed.ru',
        'from' => 'orders@skinolax.ru',
        'from_name' => 'Сайт СКИНОЛАКС',
        'smtp_host' => 'smtp.beget.com',
        'smtp_port' => 465,
        'smtp_secure' => 'ssl',
        'smtp_user' => 'orders@skinolax.ru',
        'smtp_password' => 'ЗАМЕНИТЬ_пароль_ящика_orders',
    ],

    // Т-Банк, интернет-эквайринг: личный кабинет → Магазины → Терминалы.
    // Пока здесь «ЗАМЕНИТЬ», заказ приходит на почту без онлайн-оплаты.
    'tbank' => [
        'terminal_key' => 'ЗАМЕНИТЬ_TerminalKey',
        'password' => 'ЗАМЕНИТЬ_пароль_терминала',
        'api_url' => 'https://securepay.tinkoff.ru/v2/',
        'pay_type' => 'O',          // O — деньги списываются сразу; T — сумма блокируется, списание кнопкой в админке
        'taxation' => 'osn',        // система налогообложения для чека 54-ФЗ: уточнить у бухгалтера
        'vat' => 'vat10',           // ставка НДС в чеке: на сайте указано «включая НДС 10 %»
        'success_url' => 'https://skinolax.ru/cart.html?pay=success',
        'fail_url' => 'https://skinolax.ru/cart.html?pay=fail',
        'notification_url' => 'https://skinolax.ru/api/tbank/notify',
    ],

    // Какой сервис принимает оплату заказов: 'tbank' или 'cdekpay'.
    'payment_provider' => 'tbank',

    // CDEK Pay (ООО «СДЭК Финанс»), сервер https://secure.cdekfin.ru/.
    // Логин магазина: secure.cdekfin.ru/stores. Ключи: личный кабинет → Интеграция → Настройка API.
    // Адрес уведомлений для кабинета: https://skinolax.ru/api/cdekpay/notify
    // Ключи вписываются только здесь, на сервере. Пока здесь «ЗАМЕНИТЬ», заказ приходит на почту без онлайн-оплаты.
    'cdekpay' => [
        'login' => 'ЗАМЕНИТЬ_логин_магазина',
        'secret_key' => 'ЗАМЕНИТЬ_секретный_ключ',          // боевой ключ, валюта RUR
        'test_secret_key' => 'ЗАМЕНИТЬ_тестовый_ключ',      // тестовый ключ, валюта TST
        'test' => true,             // true — тестовые платежи (TST), false — настоящие (RUR)
        'api_url' => 'https://secure.cdekfin.ru/merchant_api/',
        'link_life_time' => 60,     // минут на оплату по ссылке; потом заказ в админке помечается «ссылка истекла»
        'receipt' => true,          // true, если в кабинете включена отправка чеков: тогда передаётся список товаров
        'vat' => 'vat10',           // vat_type в чеке: уточнить у бухгалтера
        'payment_method' => 'full_prepayment',  // признак расчёта: при full_prepayment нужен чек полного расчёта при выдаче, уточнить у бухгалтера
        'success_url' => 'https://skinolax.ru/cart.html?pay=success',
        'fail_url' => 'https://skinolax.ru/cart.html?pay=fail',
    ],
];
