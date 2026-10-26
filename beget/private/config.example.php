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
];
