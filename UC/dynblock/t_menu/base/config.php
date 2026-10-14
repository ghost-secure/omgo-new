<?php

/**
 * Нативный base: столбцы из tblfields (пустой columns).
 * Поле catlink_pages в форме — редактор дерева страниц.
 */
return [
    'meta' => [
        'table_name' => 't_menu',
        'title' => 'Меню',
        'ui' => 'admin-table',
        'actions' => [
            'create' => true,
            'edit' => true,
            'delete' => true,
            'inline' => false,
        ],
    ],
];
