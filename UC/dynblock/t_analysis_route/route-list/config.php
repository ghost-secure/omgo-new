<?php

/**
 * Список маршрутов врача. Легаси Dyndata alias=route-list (Lu::Block в wf/route.php).
 */
return [
    'columns' => [
        'view_type' => [
            'column' => 'view_type',
            'label' => 'Анализ',
            'order' => 0,
            'visible' => true,
            'export' => true,
            'filter' => false,
            'filter_type' => 'view',
            'filter_table_ext' => 't_analiztype',
            'filter_column_ext' => 'varc_name',
        ],
        'view_lab' => [
            'column' => 'view_lab',
            'label' => 'Лаборатория',
            'order' => 1,
            'visible' => true,
            'export' => true,
            'filter' => false,
            'filter_type' => 'view',
            'filter_table_ext' => 't_lab',
            'filter_column_ext' => 'varc_name',
        ],
    ],
    'meta' => [
        'table_name' => 't_analysis_route',
        'title' => 'Существующие маршруты',
        'ui' => 'admin-table',
        'actions' => [
            'create' => false,
            'edit' => false,
            'delete' => false,
            'inline' => false,
        ],
    ],
];
