<?php

/*
| Two separate logins on the same users table:
|  - "web"   customers on the website
|  - "admin" staff in the admin panel (Filament)
| Signing in to one never signs you in to the other, even in the same browser.
*/
return [
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    'guards' => [
        'web' => ['driver' => 'session', 'provider' => 'users'],
        'admin' => ['driver' => 'session', 'provider' => 'users'],
    ],

    'providers' => [
        'users' => ['driver' => 'eloquent', 'model' => env('AUTH_MODEL', App\Models\User::class)],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),
];
