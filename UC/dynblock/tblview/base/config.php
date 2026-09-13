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
        'tablename' => [
            'column' => 'tablename',
            'label' => 'Таблица',
            'order' => 2,
            'visible' => true,
            'export' => true,
        ],
        'parent' => [
            'column' => 'parent',
            'label' => 'Родитель',
            'order' => 3,
            'visible' => true,
            'export' => true,
        ],
        'fields_count' => [
            'column' => 'fields_count',
            'label' => 'Полей',
            'order' => 4,
            'visible' => true,
            'export' => true,
        ],
        'children_count' => [
            'column' => 'children_count',
            'label' => 'Дочерних',
            'order' => 5,
            'visible' => true,
            'export' => true,
        ],
        'table_exists' => [
            'column' => 'table_exists',
            'label' => 'Таблица есть',
            'order' => 6,
            'visible' => true,
            'export' => true,
        ],
        'color' => [
            'column' => 'color',
            'label' => 'Цвет',
            'order' => 7,
            'visible' => true,
            'export' => true,
        ],
    ],
    'meta' => [
        'table_name' => 'tblview',
        'title' => 'Справочники',
        'ui' => 'admin-table',
        'actions' => [
            'create' => true,
            'edit' => true,
            'delete' => true,
            'inline' => false,
        ],
    ],
];
