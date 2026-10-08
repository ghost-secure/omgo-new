<?php

$env = static function (string $key, $default = null) {
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
};

return [
    'app' => [
        'name' => 'OMGO',
        'version' => '1.0.0',
        'env' => (string)$env('APP_ENV', 'development'),
        'timezone' => (int)$env('APP_TIMEZONE', 3),
        'base_url' => rtrim((string)$env('APP_BASE_URL', 'http://omgo'), '/'),
    ],

    'site' => [
        'title' => 'OMGO',
        'description' => 'Лабораторный кабинет',
    ],

    'auth' => [
        // all — этот проект без публички. manager — как test (витрина открыта). off — выключить гейт.
        'gate' => 'all',
        'login_url' => '/login',
        'public_paths' => [
            '/login',
            '/auth',
            '/reg',
            '/recover',
            '/jsapi/csrf',
            '/jsapi/form/auth',
            '/jsapi/form.auth',
            '/jsapi/form/reg',
            '/jsapi/form/recover',
            '/jsapi/auth.logout',
            '/jsapi/auth/logout',
        ],
        'manager_roles' => [1, 2, 3, 4],
        'unique_fields' => ['login', 'email', 'phone'],
    ],

    'menu' => [
        'admin_code' => 'admin_left',
        'catalog_prefix' => '/manager',
        'skip_mapurls' => ['add', 'login', 'auth', 'reg', 'recover'],
    ],

    'secure' => [
        'securesalt' => (string)$env('APP_SECURE_SALT', 'change-omgo-securesalt'),
    ],
];
