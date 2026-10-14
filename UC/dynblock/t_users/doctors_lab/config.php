<?php

/**
 * Столбцы как легаси tbldynelements id=79: те же, что у 53, без «Маршрут».
 */
return [
    'columns' => [
        'email' => [
            'column' => 'email',
            'label' => 'E-Mail',
            'order' => 0,
            'visible' => true,
            'export' => true,
        ],
        'phone' => [
            'column' => 'phone',
            'label' => 'Телефон',
            'order' => 1,
            'visible' => true,
            'export' => true,
        ],
        'varc_surname' => [
            'column' => 'varc_surname',
            'label' => 'Фамилия',
            'order' => 2,
            'visible' => true,
            'export' => true,
        ],
        'varc_name' => [
            'column' => 'varc_name',
            'label' => 'Имя',
            'order' => 3,
            'visible' => true,
            'export' => true,
        ],
        'varc_patron' => [
            'column' => 'varc_patron',
            'label' => 'Отчество',
            'order' => 4,
            'visible' => true,
            'export' => true,
        ],
        'view_region' => [
            'column' => 'view_region',
            'label' => 'Регион',
            'order' => 5,
            'visible' => true,
            'export' => true,
        ],
        'custom_templateparam_tests' => [
            'column' => 'custom_templateparam_tests',
            'label' => 'Число пациентов',
            'order' => 6,
            'visible' => true,
            'export' => true,
            'filter' => false,
            'cell_formatter' => <<<'CODE'
<?php
$n = (int) ($record['custom_templateparam_tests'] ?? 0);
echo $n > 0 ? (int) $n : '';
CODE
            ,
        ],
        'view_lab' => [
            'column' => 'view_lab',
            'label' => 'Лаборатория',
            'order' => 7,
            'visible' => true,
            'export' => true,
        ],
        'view_med' => [
            'column' => 'view_med',
            'label' => 'Медучреждение',
            'order' => 8,
            'visible' => true,
            'export' => true,
        ],
        '_patients' => [
            'column' => '_patients',
            'label' => 'Действия',
            'order' => 9,
            'visible' => true,
            'export' => false,
            'filter' => false,
            'cell_formatter' => <<<'CODE'
<?php
$tests = (int) ($record['custom_templateparam_tests'] ?? 0);
$id = (int) ($record['cat'] ?? 0);
if ($tests > 0 && $id > 0):
?>
<a href="/manager/doctor/<?= $id ?>/" title="Перейти к списку пациентов врача">пациенты</a>
<?php endif; ?>
CODE
            ,
        ],
    ],
    'meta' => [
        'table_name' => 't_users',
        'title' => 'Врачи',
        'ui' => 'admin-table',
        'actions' => [
            'create' => false,
            'edit' => false,
            'delete' => false,
            'inline' => false,
        ],
    ],
];
