<?php

use Services\AnalysisService;

/** @var array $row */
/** @var array $card */
/** @var array $materials */

$items = [
    AnalysisService::PTEN_EXPRESSION_LOSS => AnalysisService::PTEN_EXPRESSION_LOSS,
    AnalysisService::PTEN_EXPRESSION_PRESENT => AnalysisService::PTEN_EXPRESSION_PRESENT,
    AnalysisService::PTEN_NO_ASSESSMENT => AnalysisService::PTEN_NO_ASSESSMENT,
];
$currentResult = trim((string) ($row['varc_result'] ?? ''));
$resultComment = '';
if ($currentResult !== '' && !isset($items[$currentResult])) {
    $resultComment = omgoH($currentResult) . ' <span class="text-color-red">указано ранее — замените на правильный вариант</span>';
    $currentResult = '';
}
$antibodies = [
    'VENTANA PTEN (SP218)' => 'VENTANA PTEN (SP218)',
    'Abcam (32199)' => 'Abcam (32199)',
    'Dako Agilent 6H2.1' => 'Dako Agilent 6H2.1',
    'Celnovte Biotechnology (10Q23)' => 'Celnovte Biotechnology (10Q23)',
    'Другое' => 'Другое',
];
$antibody = trim((string) ($row['varc_antitelo'] ?? ''));
$antibodyCustom = $antibody;
if ($antibody !== '' && !isset($antibodies[$antibody])) {
    $antibody = 'Другое';
}
$percent = (int) ($row['cnt_noexpression'] ?? 0);
?>
<div class="d-flex flex-column gap-md">
    <?php \UC\FormBlockTitle::render('Материалы'); ?>
    <?php \UC\FormCard::open(); ?>
        <?php omgoLabMaterial(
            'Блок с опухолевой тканью',
            'stat_materialgisto',
            !empty($row['stat_materialgisto']),
            !empty($row['stat_materialgisto']),
            'Номер блока',
            'varc_markgisto',
            (string) ($row['varc_markgisto'] ?? ''),
            AnalysisService::MATERIAL_GISTO,
            'check',
            $materials
        ); ?>
    <?php \UC\FormCard::close(); ?>
</div>

<div class="d-flex flex-column gap-md">
    <?php \UC\FormBlockTitle::render('Анализ'); ?>
    <?php \UC\FormCard::open(); ?>
        <?php omgoPlain('Выполненный анализ', (string) ($card['type_name'] ?? '')); ?>
        <?php omgoSelect('Результат ИГХ исследования', 'fields[varc_result]', $items, $currentResult, true, [
            ['w' => 'yes', 'h' => [AnalysisService::PTEN_NO_ASSESSMENT]],
            ['w' => 'no', 'h' => [AnalysisService::PTEN_EXPRESSION_LOSS, AnalysisService::PTEN_EXPRESSION_PRESENT]],
        ], $resultComment); ?>
        <div data-wrapper="yes" class="d-flex flex-column gap-md">
            <?php omgoField(
                'Процент клеток с отсутствием экспрессии',
                'fields[cnt_noexpression]',
                $percent > 0 ? (string) $percent : '',
                true
            ); ?>
            <div class="d-flex gap-md" style="flex-wrap: wrap">
                <div style="flex: 1 1 16rem">
                    <?php omgoSelect('Используемое антитело/клон', 'fields[varc_antitelo]', $antibodies, $antibody, true, [
                        ['w' => 'antitelo', 'v' => ['Другое'], 'h' => null],
                    ]); ?>
                </div>
                <div style="flex: 1 1 16rem">
                    <div data-wrapper="antitelo">
                        <?php omgoField('Укажите свой вариант', 'fields[antitelo_value]', $antibody === 'Другое' ? $antibodyCustom : '', true); ?>
                    </div>
                </div>
            </div>
        </div>
        <div data-wrapper="no">
            <?php omgoField('Причина невозможности оценки', 'fields[varc_noresultreason]', (string) ($row['varc_noresultreason'] ?? ''), true); ?>
        </div>
    <?php \UC\FormCard::close(); ?>
</div>
