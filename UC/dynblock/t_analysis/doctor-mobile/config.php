<?php

/**
 * Карточки врача на телефоне. Легаси tbldynelements id 66, alias doctor-mobile.
 * Без meta.ui: рисует template.php, не admin-table.
 * Справочные подписи (диагноз, анализ, статус) ядро добирает по этим столбцам.
 */

return [
    'columns' => [
        'view_diagnoz' => [
            'column' => 'view_diagnoz',
            'label' => 'Диагноз',
            'order' => 0,
            'visible' => true,
            'export' => false,
            'filter' => false,
        ],
        'view_test' => [
            'column' => 'view_test',
            'label' => 'Анализ',
            'order' => 1,
            'visible' => true,
            'export' => false,
            'filter' => false,
        ],
        'view_status' => [
            'column' => 'view_status',
            'label' => 'Статус',
            'order' => 2,
            'visible' => true,
            'export' => false,
            'filter' => false,
        ],
    ],
];
