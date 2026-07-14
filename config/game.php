<?php

return [
    'adapter' => env('GAME_ADAPTER', 'mock'),
    'server' => [
        'host' => env('GAME_SERVER_HOST', '127.0.0.1'),
        'port' => (int) env('GAME_SERVER_PORT', 7777),
        'login_host' => env('GAME_LOGIN_HOST', '127.0.0.1'),
        'login_port' => (int) env('GAME_LOGIN_PORT', 2106),
        'timeout' => 1.5,
    ],
];
