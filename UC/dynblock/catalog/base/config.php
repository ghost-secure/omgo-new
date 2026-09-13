<?php

return [
    'columns' => [
        'id' => [
            'column' => 'id',
            'label' => 'ID',
            'order' => 0,
            'visible' => true,
            'export' => true,
        ],
        'name' => [
            'column' => 'name',
            'label' => 'Название',
            'order' => 1,
            'visible' => true,
            'export' => true,
        ],
        'mapurl' => [
            'column' => 'mapurl',
            'label' => 'URL (slug)',
            'order' => 2,
            'visible' => true,
            'export' => true,
        ],
        'url' => [
            'column' => 'url',
            'label' => 'Путь',
            'order' => 3,
            'visible' => true,
            'export' => true,
        ],
        'id_up' => [
            'column' => 'id_up',
            'label' => 'Родитель',
            'order' => 4,
            'visible' => true,
            'export' => true,
        ],
        'php' => [
            'column' => 'php',
            'label' => 'PHP файл',
            'order' => 5,
            'visible' => true,
            'export' => true,
        ],
        'pattern' => [
            'column' => 'pattern',
            'label' => 'Шаблон',
            'order' => 6,
            'visible' => true,
            'export' => true,
        ],
        'status' => [
            'column' => 'status',
            'label' => 'Статус',
            'order' => 7,
            'visible' => true,
            'export' => true,
        ],
    ],
    'meta' => [
        'table_name' => 'catalog',
        'title' => 'Страницы',
        'ui' => 'admin-table',
        'actions' => [
            'create' => true,
            'edit' => true,
            'delete' => true,
            'inline' => false,
        ],
    ],
];
