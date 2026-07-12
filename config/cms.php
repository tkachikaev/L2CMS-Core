<?php

return [
    'name' => 'L2Forge CMS',
    'version' => '0.6.0',
    'theme' => env('CMS_THEME', 'default'),
    'themes_path' => base_path('themes'),

    'news' => [
        'uploads_path' => env('NEWS_UPLOADS_PATH', public_path('uploads')),
    ],

    'admin' => [
        'login_max_attempts' => (int) env('ADMIN_LOGIN_MAX_ATTEMPTS', 5),
        'login_decay_seconds' => (int) env('ADMIN_LOGIN_DECAY_SECONDS', 60),
    ],

    'server' => [
        'name' => env('GAME_SERVER_NAME', 'L2Server x1'),
        'chronicle' => env('GAME_CHRONICLE', 'High Five'),
        'rates' => env('GAME_RATES', 'x1'),
        'mode' => env('GAME_MODE', 'PvP'),
        'max_online' => (int) env('GAME_MAX_ONLINE', 5000),
    ],
];
