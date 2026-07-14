<?php

return [
    'name' => 'L2Forge CMS',
    'theme' => env('CMS_THEME', 'default'),
    'themes_path' => base_path('themes'),

    'news' => [
        'uploads_path' => env('NEWS_UPLOADS_PATH', public_path('uploads')),
    ],

    'pages' => [
        'uploads_path' => env('PAGE_UPLOADS_PATH', public_path('uploads')),
    ],

    'settings' => [
        'uploads_path' => env('SETTINGS_UPLOADS_PATH', public_path('uploads')),
    ],

    'site_defaults' => [
        'name' => env('SITE_NAME', env('APP_NAME', 'L2Forge CMS')),
        'description' => env('SITE_DESCRIPTION', 'Бесплатная open-source CMS для серверов Lineage II.'),
        'timezone' => env('APP_TIMEZONE', 'Europe/Moscow'),
        'admin_email' => env('SITE_ADMIN_EMAIL', ''),
        'footer_text' => env('SITE_FOOTER_TEXT', '© 2026 L2Forge-CMS'),
        'translations' => [
            'ru' => [
                'name' => env('SITE_NAME_RU', env('SITE_NAME', env('APP_NAME', 'L2Forge CMS'))),
                'description' => env('SITE_DESCRIPTION_RU', env('SITE_DESCRIPTION', 'Бесплатная open-source CMS для серверов Lineage II.')),
                'footer_text' => env('SITE_FOOTER_TEXT_RU', env('SITE_FOOTER_TEXT', '© 2026 L2Forge-CMS')),
            ],
            'en' => [
                'name' => env('SITE_NAME_EN', env('SITE_NAME', env('APP_NAME', 'L2Forge CMS'))),
                'description' => env('SITE_DESCRIPTION_EN', 'Free open-source CMS for Lineage II servers.'),
                'footer_text' => env('SITE_FOOTER_TEXT_EN', env('SITE_FOOTER_TEXT', '© 2026 L2Forge-CMS')),
            ],
        ],
    ],

    'registration' => [
        'enabled' => (bool) env('REGISTRATION_ENABLED', false),
        'email_verification_required' => (bool) env('REGISTRATION_EMAIL_VERIFICATION', true),
    ],

    'admin' => [
        'login_max_attempts' => (int) env('ADMIN_LOGIN_MAX_ATTEMPTS', 5),
        'login_decay_seconds' => (int) env('ADMIN_LOGIN_DECAY_SECONDS', 60),
        'login_ip_max_attempts_per_minute' => (int) env('ADMIN_LOGIN_IP_MAX_ATTEMPTS_PER_MINUTE', 10),
        'login_ip_max_attempts_per_hour' => (int) env('ADMIN_LOGIN_IP_MAX_ATTEMPTS_PER_HOUR', 100),
        'login_log_retention_days' => (int) env('ADMIN_LOGIN_LOG_RETENTION_DAYS', 30),
        'two_factor_max_attempts_per_minute' => (int) env('ADMIN_2FA_MAX_ATTEMPTS_PER_MINUTE', 5),
        'two_factor_max_attempts_per_hour' => (int) env('ADMIN_2FA_MAX_ATTEMPTS_PER_HOUR', 20),
    ],

    'audit' => [
        'retention_days' => (int) env('AUDIT_LOG_RETENTION_DAYS', 90),
    ],

    'server' => [
        'name' => env('GAME_SERVER_NAME', 'L2Server x1'),
        'chronicle' => env('GAME_CHRONICLE', 'High Five'),
        'rates' => env('GAME_RATES', 'x1'),
        'mode' => env('GAME_MODE', 'PvP'),
        'max_online' => (int) env('GAME_MAX_ONLINE', 5000),
    ],
];
