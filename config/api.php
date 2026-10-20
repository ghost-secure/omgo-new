<?php

return [
    'jsapi' => [
        'form.auth' => [
            'handler' => \Api\JsApi\AuthLoginHandler::class,
            'public' => false,
            'description' => 'Вход: логин, e-mail или телефон',
        ],
        'form.reg' => [
            'handler' => \Api\JsApi\AuthRegisterHandler::class,
            'public' => false,
            'description' => 'Регистрация врача',
        ],
        'form.recover' => [
            'handler' => \Api\JsApi\AuthRecoverHandler::class,
            'public' => false,
            'description' => 'Новый пароль на e-mail',
        ],
        'admin.flow' => [
            'handler' => \Api\JsApi\AdminFlowHandler::class,
            'public' => false,
            'description' => 'Кабинет: HTML сценариев модалок (project/phpfiles/flow), GET id/action/dataid/table/hash',
        ],
        'admin.analysis-route' => [
            'handler' => \Api\JsApi\AnalysisRouteSaveHandler::class,
            'public' => false,
            'description' => 'Кабинет: сохранить строку t_analysis_route (флоу route)',
        ],
        'admin.code-pool' => [
            'handler' => \Api\JsApi\CodePoolHandler::class,
            'public' => false,
            'description' => 'Кабинет: пул проверочных кодов t_code (флоу codes)',
        ],
        'admin.analysis-event' => [
            'handler' => \Api\JsApi\AnalysisEventHandler::class,
            'public' => false,
            'description' => 'Кабинет: событие воркфлоу заявки t_analysis (флоу analysis)',
        ],
        'auth.impersonate' => [
            'handler' => \Api\JsApi\AuthImpersonateHandler::class,
            'public' => false,
            'description' => 'Кабинет: вход под пользователем / выход из имперсонализации',
        ],
    ],
];
