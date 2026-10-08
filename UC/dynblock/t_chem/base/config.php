<?php

/**
 * Нативный base: столбцы из tblfields (пустой columns).
 */
return [
    'meta' => [
        'table_name' => 't_chem',
        'title' => 'Химиотерапевтическое лечение',
        'ui' => 'admin-table',
        'actions' => [
            'create' => true,
            'edit' => true,
            'delete' => true,
            'inline' => false,
        ],
    ],
];
