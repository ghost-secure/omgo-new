<?php
/**
 * Автоматически сгенерированный конфиг для таблицы t_users
 * Дата генерации: 2026-10-08 20:54:57
 * 
 * КАК ИСПОЛЬЗОВАТЬ PHP КОД В КОНФИГЕ:
 * 
 * cell_formatter (для всех столбцов):
 *    - Это PHP код с HTML, который выполняется напрямую
 *    - Может содержать условия, циклы, HTML разметку
 *    - Используется через eval('?>' . $code)
 *    - Примеры:
 *      
 *      Простой вывод:
 *      <<<'CODE'
 *      <?= htmlspecialchars($record['cat'] ?? '—') ?>
 *      CODE
 *      
 *      С условием:
 *      <<<'CODE'
 *      <?php if ($record['cat'] > 50): ?>
 *          <button onclick="openModal(<?= $record['cat'] ?>)">Открыть</button>
 *      <?php else: ?>
 *          <span class="text-muted">—</span>
 *      <?php endif; ?>
 *      CODE
 * 
 * condition (опционально):
 *    - Используется для ПОЛНОГО скрытия ячейки (показывает "—" вместо содержимого)
 *    - Если не указан, ячейка всегда показывается, логику скрытия/показа пишите в cell_formatter
 *    - Это код, который ДОЛЖЕН ВЕРНУТЬ boolean (true/false)
 *    - Используется через eval('return ' . $condition . ';')
 *    - Пример: $record['cat'] > 100
 * 
 * ВАЖНО: Используйте <<<'CODE' для всех блоков кода (nowdoc синтаксис)
 *        Это позволяет писать код без экранирования кавычек и переменных
 */
return [
    'columns' => [
        'cat' => [
            'column' => 'cat',
            'label' => 'ID',
            'order' => 0,
            'visible' => true,
            'export' => true,
            'cell_formatter' => <<<'CODE'
<?= htmlspecialchars($record['cat'] ?? '—') ?>
CODE
            ,
        ],

        'varc_surname' => [
            'column' => 'varc_surname',
            'label' => 'Фамилия',
            'order' => 1,
            'visible' => true,
            'export' => true,
            'cell_formatter' => <<<'CODE'
<?= \Core\Reference\Field::formatValue($record['varc_surname'], 'varc') ?>
CODE
            ,
        ],

        'varc_name' => [
            'column' => 'varc_name',
            'label' => 'Имя',
            'order' => 2,
            'visible' => true,
            'export' => true,
            'cell_formatter' => <<<'CODE'
<?= \Core\Reference\Field::formatValue($record['varc_name'], 'varc') ?>
CODE
            ,
        ],

        'varc_patron' => [
            'column' => 'varc_patron',
            'label' => 'Отчество',
            'order' => 3,
            'visible' => true,
            'export' => true,
            'cell_formatter' => <<<'CODE'
<?= \Core\Reference\Field::formatValue($record['varc_patron'], 'varc') ?>
CODE
            ,
        ],

        'view_lab' => [
            'column' => 'view_lab',
            'label' => 'Лаборатория',
            'order' => 4,
            'visible' => true,
            'export' => true,
            'cell_formatter' => <<<'CODE'
<?= \Core\Reference\Field::formatValue($record['view_lab_name'] ?? $record['view_lab'] ?? null, 'varc') ?>
CODE
            ,
        ],

        'view_med' => [
            'column' => 'view_med',
            'label' => 'Медучреждение',
            'order' => 5,
            'visible' => true,
            'export' => true,
            'cell_formatter' => <<<'CODE'
<?= \Core\Reference\Field::formatValue($record['view_med_name'] ?? $record['view_med'] ?? null, 'varc') ?>
CODE
            ,
        ],

        'address_dadata' => [
            'column' => 'address_dadata',
            'label' => 'Адрес',
            'order' => 6,
            'visible' => true,
            'export' => true,
            'cell_formatter' => <<<'CODE'
<?= \Core\Reference\Field::formatValue($record['address_dadata'], 'address') ?>
CODE
            ,
        ],

        'view_region' => [
            'column' => 'view_region',
            'label' => 'Регион',
            'order' => 7,
            'visible' => false,
            'export' => false,
            'cell_formatter' => <<<'CODE'
<?= \Core\Reference\Field::formatValue($record['view_region_name'] ?? $record['view_region'] ?? null, 'varc') ?>
CODE
            ,
        ],

        'ico_avatar' => [
            'column' => 'ico_avatar',
            'label' => 'Аватар',
            'order' => 8,
            'visible' => true,
            'export' => true,
            'cell_formatter' => <<<'CODE'
<?php
$imageUrl = $record['ico_avatar'] ?? null;

if (empty($imageUrl)) {
    echo '<span class="text-muted">—</span>';
} else {
    echo '<img src="' . htmlspecialchars($imageUrl) . '" alt="" style="height: 40px; width: auto; border-radius: var(--radius-sm); object-fit: cover;" />';
}
?>
CODE
            ,
        ],

        'view_status' => [
            'column' => 'view_status',
            'label' => 'Статус',
            'order' => 9,
            'visible' => false,
            'export' => false,
            'cell_formatter' => <<<'CODE'
<?= \Core\Reference\Field::formatValue($record['view_status_name'] ?? $record['view_status'] ?? null, 'varc') ?>
CODE
            ,
        ],

        'login' => [
            'column' => 'login',
            'label' => 'Логин',
            'order' => 10,
            'visible' => true,
            'export' => true,
            'cell_formatter' => <<<'CODE'
<?= \Core\Reference\Field::formatValue($record['login'], 'varc') ?>
CODE
            ,
        ],

        'email' => [
            'column' => 'email',
            'label' => 'Email',
            'order' => 11,
            'visible' => true,
            'export' => true,
            'cell_formatter' => <<<'CODE'
<?= \Core\Reference\Field::formatValue($record['email'], 'varc') ?>
CODE
            ,
        ],

        'phone' => [
            'column' => 'phone',
            'label' => 'Телефон',
            'order' => 12,
            'visible' => true,
            'export' => true,
            'cell_formatter' => <<<'CODE'
<?= \Core\Reference\Field::formatValue($record['phone'], 'varc') ?>
CODE
            ,
        ],

        'passwd' => [
            'column' => 'passwd',
            'label' => 'Пароль',
            'order' => 13,
            'visible' => false,
            'export' => true,
            'cell_formatter' => <<<'CODE'
<?php
$value = $record['passwd'] ?? null;
if (empty($value)) {
    echo '<span class="text-muted">—</span>';
} else {
    echo '<span style="font-family: monospace; color: var(--color-text-secondary);">' . str_repeat('*', min(strlen($value), 20)) . '</span>';
}
?>
CODE
            ,
        ],

        'salt' => [
            'column' => 'salt',
            'label' => 'Соль',
            'order' => 14,
            'visible' => false,
            'export' => true,
            'cell_formatter' => <<<'CODE'
<?php
$value = $record['salt'] ?? null;
if (empty($value)) {
    echo '<span class="text-muted">—</span>';
} else {
    echo '<span style="font-family: monospace; color: var(--color-text-secondary);">' . str_repeat('*', min(strlen($value), 20)) . '</span>';
}
?>
CODE
            ,
        ],

        'hash' => [
            'column' => 'hash',
            'label' => 'Хэш',
            'order' => 15,
            'visible' => false,
            'export' => true,
            'cell_formatter' => <<<'CODE'
<?php
$value = $record['hash'] ?? null;
if (empty($value)) {
    echo '<span class="text-muted">—</span>';
} else {
    echo '<span style="font-family: monospace; color: var(--color-text-secondary);">' . str_repeat('*', min(strlen($value), 20)) . '</span>';
}
?>
CODE
            ,
        ],

        'regdate' => [
            'column' => 'regdate',
            'label' => 'Дата регистрации',
            'order' => 16,
            'visible' => true,
            'export' => true,
            'cell_formatter' => <<<'CODE'
<?php
$timestamp = $record['regdate'] ?? null;
if (empty($timestamp)) {
    echo '<span class="text-muted">—</span>';
} else {
    echo '<span>' . date('d.m.Y H:i', (int)$timestamp) . '</span>';
}
?>
CODE
            ,
        ],

        'visdate' => [
            'column' => 'visdate',
            'label' => 'Дата последнего визита',
            'order' => 17,
            'visible' => true,
            'export' => true,
            'cell_formatter' => <<<'CODE'
<?php
$timestamp = $record['visdate'] ?? null;
if (empty($timestamp)) {
    echo '<span class="text-muted">—</span>';
} else {
    echo '<span>' . date('d.m.Y H:i', (int)$timestamp) . '</span>';
}
?>
CODE
            ,
        ],

        'status' => [
            'column' => 'status',
            'label' => 'Статус',
            'order' => 18,
            'visible' => true,
            'export' => true,
            'cell_formatter' => <<<'CODE'
<?= \Core\Reference\Field::formatValue($record['status'], 'stat') ?>
CODE
            ,
        ],

        'role' => [
            'column' => 'role',
            'label' => 'Группа',
            'order' => 19,
            'visible' => true,
            'export' => true,
            'cell_formatter' => <<<'CODE'
<?= \Core\Reference\Field::formatValue($record['role_name'] ?? $record['role'] ?? null, 'varc') ?>
CODE
            ,
        ]
    ],
    
    // Стилизация строк (добавьте при необходимости)
    // 'row_styling' => [
    //     [
    //         'condition' => <<<'CODE'
    // $record['cat'] > 100
    // CODE
    //         ,
    //         'class' => 'row-danger',
    //         'style' => null,
    //     ],
    // ],
    
    // Стилизация ячеек (добавьте при необходимости)
    // 'cell_styling' => [
    //     'cnt_cost' => [
    //         'condition' => <<<'CODE'
    // $record['cnt_cost'] > 1000000
    // CODE
    //         ,
    //         'class' => 'cell-highlight',
    //         'style' => 'font-weight: bold; color: green;',
    //     ],
    // ],
    

    
    'meta' => [
        'table_name' => 't_users',
        'title' => 'Пользователи',
        'ui' => 'admin-table',
        'generated_at' => '2026-10-08 20:54:57',
        'version' => 2,
        'actions' => [
            'create' => true,
            'edit' => true,
            'delete' => true,
            'inline' => false,
        ],
    ],
];