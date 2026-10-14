<?php

/**
 * Пациенты врача. Столбцы как в легаси tbldynelements id=54.
 * Значения pacient_* / doctor_* / analysis_double — AnalysisService::analysisGridQuery().
 */
$config = [
    'columns' => [
        'cat' => [
            'column' => 'cat',
            'label' => '#ID',
            'order' => 0,
            'visible' => true,
            'export' => true,
            'filter' => false,
        ],
        'created_add' => [
            'column' => 'created_add',
            'label' => 'Дата создания',
            'order' => 1,
            'visible' => true,
            'export' => true,
            'filter' => false,
        ],
        'date_correctmaterial' => [
            'column' => 'date_correctmaterial',
            'label' => 'Дата принятия материала',
            'order' => 2,
            'visible' => true,
            'export' => true,
            'filter' => false,
            'cell_formatter' => <<<'CODE'
<?php
$raw = $record['date_correctmaterial'] ?? null;
if ($raw === null || $raw === '' || (int) $raw === 0) {
    echo '<span class="text-muted">—</span>';
    return;
}
echo \Core\Reference\Field::formatValue($raw, 'timestamp');
CODE
            ,
        ],
        'date_execauto' => [
            'column' => 'date_execauto',
            'label' => 'Дата выполнения',
            'order' => 3,
            'visible' => true,
            'export' => true,
            'filter' => false,
            'cell_formatter' => <<<'CODE'
<?php
$raw = $record['date_execauto'] ?? null;
if ($raw === null || $raw === '' || (int) $raw === 0) {
    echo '<span class="text-muted">—</span>';
    return;
}
echo \Core\Reference\Field::formatValue($raw, 'timestamp');
CODE
            ,
        ],
        'pacient_code' => [
            'column' => 'pacient_code',
            'label' => 'Условный код',
            'order' => 4,
            'visible' => true,
            'export' => true,
            'filter' => false,
            'cell_formatter' => <<<'CODE'
<?php
$code = trim((string) ($record['pacient_code'] ?? ''));
echo $code !== ''
    ? htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    : '<span class="text-muted">—</span>';
if ((int) ($record['stat_express'] ?? 0) === 1) {
    echo '<div>экспресс</div>';
}
CODE
            ,
        ],
        'analysis_double' => [
            'column' => 'analysis_double',
            'label' => 'Дубль',
            'order' => 5,
            'visible' => true,
            'export' => true,
            'filter' => false,
            'cell_formatter' => <<<'CODE'
<?php
if ((int) ($record['analysis_double'] ?? 0) === 2) {
    echo '<span title="Дубль в другой лаборатории">!</span>';
}
CODE
            ,
        ],
        'pacient_name' => [
            'column' => 'pacient_name',
            'label' => 'ФИО пациента',
            'order' => 6,
            'visible' => true,
            'export' => true,
            'filter' => false,
            'cell_formatter' => <<<'CODE'
<?php
$name = trim((string) ($record['pacient_name'] ?? ''));
$guid = trim((string) ($record['varc_guid'] ?? ''));
if ($name === '') {
    echo '<span class="text-muted">—</span>';
    return;
}
$safe = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
if ($guid === '') {
    echo $safe;
    return;
}
$href = '/manager/passport/' . rawurlencode($guid) . '/';
echo '<a href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" target="_blank">' . $safe . '</a>';
CODE
            ,
        ],
        'pacient_bd' => [
            'column' => 'pacient_bd',
            'label' => 'Дата рождения',
            'order' => 7,
            'visible' => true,
            'export' => true,
            'filter' => false,
        ],
        'pacient_male' => [
            'column' => 'pacient_male',
            'label' => 'Пол',
            'order' => 8,
            'visible' => true,
            'export' => true,
            'filter' => false,
        ],
        'view_diagnoz' => [
            'column' => 'view_diagnoz',
            'label' => 'Диагноз',
            'order' => 9,
            'visible' => true,
            'export' => true,
            'filter' => false,
        ],
        'doctor_region' => [
            'column' => 'doctor_region',
            'label' => 'Регион врача',
            'order' => 10,
            'visible' => true,
            'export' => true,
            'filter' => false,
        ],
        'doctor_name' => [
            'column' => 'doctor_name',
            'label' => 'Врач',
            'order' => 11,
            'visible' => true,
            'export' => true,
            'filter' => false,
        ],
        'pacient_region' => [
            'column' => 'pacient_region',
            'label' => 'Регион пациента',
            'order' => 12,
            'visible' => true,
            'export' => true,
            'filter' => false,
        ],
        'view_lab' => [
            'column' => 'view_lab',
            'label' => 'Лаборатория',
            'order' => 13,
            'visible' => true,
            'export' => true,
            'filter' => false,
        ],
        'view_test' => [
            'column' => 'view_test',
            'label' => 'Анализ',
            'order' => 14,
            'visible' => true,
            'export' => true,
            'filter' => false,
        ],
        'view_result' => [
            'column' => 'view_result',
            'label' => 'Статус мутации',
            'order' => 15,
            'visible' => true,
            'export' => true,
            'filter' => false,
        ],
        'view_hrd' => [
            'column' => 'view_hrd',
            'label' => 'Статус HRD',
            'order' => 16,
            'visible' => true,
            'export' => true,
            'filter' => false,
        ],
        'view_status' => [
            'column' => 'view_status',
            'label' => 'Статус',
            'order' => 17,
            'visible' => true,
            'export' => true,
            'filter' => false,
            'cell_formatter' => <<<'CODE'
<?php
$name = trim((string) ($record['view_status_name'] ?? ''));
echo $name !== ''
    ? htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    : '<span class="text-muted">—</span>';
$late = (int) ($record['cnt_late'] ?? 0);
if ($late > 0) {
    echo '<div>' . $late . ' дн.</div>';
}
CODE
            ,
        ],
    ],
    'meta' => [
        'table_name' => 't_analysis',
        'title' => 'Список пациентов',
        'ui' => 'admin-table',
        'actions' => [
            'create' => false,
            'edit' => false,
            'delete' => false,
            'inline' => false,
        ],
    ],
];

// Поля вне грида всё равно попадают в панель фильтров, пока в columns нет filter => false.
$silent = [
    'view_pacient', 'view_localization', 'view_illness', 'view_gistotype', 'view_myonkoan',
    'view_stage', 'view_code', 'view_method', 'view_reagent', 'view_patogen', 'view_materialaction',
    'view_chem', 'view_late', 'view_progressing', 'view_linetherapy', 'view_confirmprogress',
    'view_teraphyduration', 'stat_chem', 'stat_materialblood', 'stat_materialgisto', 'stat_genom',
    'stat_materialnormal', 'stat_patogen', 'stat_onkogen', 'stat_copiynost', 'stat_conclusion',
    'stat_hasprogress', 'stat_express', 'varc_markblood', 'varc_variant', 'varc_allel', 'varc_guid',
    'varc_markgisto', 'varc_gistotype', 'varc_myonkoan', 'varc_marknormal', 'varc_ecozone',
    'varc_coords', 'varc_result', 'varc_spread', 'varc_hormonesensitive', 'varc_antitelo',
    'varc_noresultreason', 'varc_testpten', 'cnt_vardepth', 'cnt_genom', 'cnt_snpdepth',
    'cnt_brcadepth', 'cnt_readdepth', 'cnt_analysislinked', 'cnt_late', 'cnt_glisson',
    'cnt_agediagnoz', 'cnt_noexpression', 'date_accept', 'date_exec', 'date_starttherapy',
    'gallery_gisto', 'gallery_epicris', 'user_doctor',
];
$order = 100;
foreach ($silent as $column) {
    if (isset($config['columns'][$column])) {
        continue;
    }
    $config['columns'][$column] = [
        'column' => $column,
        'label' => $column,
        'order' => $order++,
        'visible' => false,
        'export' => false,
        'filter' => false,
    ];
}

return $config;
