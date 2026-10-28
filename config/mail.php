<?php

return [


    'default' => env('MAIL_MAILER', 'log'),

    'invoice' => [
        'queue' => env('MAIL_INVOICE_QUEUE', 'emails'),
        'dispatch_delay' => (int) env('MAIL_INVOICE_DISPATCH_DELAY', 3),
        'after_response' => (bool) env('MAIL_INVOICE_AFTER_RESPONSE', false),
    ],


    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => match (strtolower(trim((string) env('MAIL_SCHEME', 'smtp')))) {
                '', 'null', 'smtp', 'starttls', 'tls' => 'smtp',
                'ssl', 'smtps' => 'smtps',
                default => env('MAIL_SCHEME'),
            },
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => strtolower((string) env('MAIL_HOST')) === 'smtp.gmail.com'
                ? preg_replace('/\s+/', '', (string) env('MAIL_PASSWORD'))
                : env('MAIL_PASSWORD'),
            'timeout' => (int) env('MAIL_TIMEOUT', 15),
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],


    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
    ],

];
