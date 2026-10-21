<?php

use Services\AnalysisService;

/** @var array $row */
/** @var array $opts */
/** @var array $materials */
/** @var array $card */
/** @var string $methodName */
/** @var int $typeId */

$result = (int) ($row['view_result'] ?? 0);
if ($result <= 0) {
    $result = 2;
}
$depth = (string) ($row['cnt_vardepth'] ?? '');
if ($depth === '0') {
    $depth = '';
}
$read = (string) ($row['cnt_readdepth'] ?? '');
if ($read === '0') {
    $read = '';
}
?>
<div class="d-flex flex-column gap-md">
    <?php \UC\FormBlockTitle::render('Материалы'); ?>
    <?php \UC\FormCard::open(); ?>
        <?php if ((int) ($row['view_diagnoz'] ?? 0) === AnalysisService::DI_YAR): ?>
            <?php omgoLabMaterial(
                'Блок с опухолевой тканью',
                'stat_materialgisto',
                !empty($row['stat_materialgisto']),
                !empty($row['stat_materialgisto']),
                'Номер блока',
                'varc_markgisto',
                (string) ($row['varc_markgisto'] ?? ''),
                AnalysisService::MATERIAL_GISTO,
                'radio',
                $materials
            ); ?>
        <?php endif; ?>
        <?php omgoLabMaterial(
            'Кровь',
            'stat_materialblood',
            !empty($row['stat_materialblood']),
            !empty($row['stat_materialblood']),
            'Маркировка',
            'varc_markblood',
            (string) ($row['varc_markblood'] ?? ''),
            AnalysisService::MATERIAL_BLOOD,
            'radio',
            $materials
        ); ?>
    <?php \UC\FormCard::close(); ?>
</div>

<div class="d-flex flex-column gap-md">
    <?php \UC\FormBlockTitle::render('Анализ'); ?>
    <?php \UC\FormCard::open(); ?>
        <?php omgoAssayHead(
            (string) ($card['type_name'] ?? ''),
            (string) AnalysisService::METHOD_NGS,
            $methodName,
            $opts['method'],
            $row['view_method'] ?? '',
            $opts['reagent'],
            $row['view_reagent'] ?? ''
        ); ?>
        <?php omgoRadio('Результат анализа', 'fields[view_result]', [
            '2' => 'Патогенные варианты <span class="text-color-green">не обнаружены</span>',
            '1' => 'Патогенные варианты <span class="text-color-red">обнаружены</span>',
            '3' => 'Невозможно провести анализ',
        ], (string) $result, ['1' => 'result'], true, true); ?>
        <div data-wrapper="result" class="d-flex gap-md" style="flex-wrap: wrap">
            <div style="flex: 1 1 12rem"><?php omgoField('Название варианта', 'fields[varc_variant]', (string) ($row['varc_variant'] ?? ''), true); ?></div>
            <div style="flex: 1 1 10rem"><?php omgoField('Частота аллеля', 'fields[varc_allel]', (string) ($row['varc_allel'] ?? '')); ?></div>
            <div style="flex: 1 1 10rem"><?php omgoField('Глубина прочтения варианта', 'fields[cnt_vardepth]', $depth); ?></div>
            <div style="flex: 1 1 12rem"><?php omgoSelect('Класс патогенности', 'fields[view_patogen]', $opts['patogen'], $row['view_patogen'] ?? '', true); ?></div>
        </div>
    <?php \UC\FormCard::close(); ?>
</div>

<div class="d-flex flex-column gap-md">
    <?php \UC\FormBlockTitle::render('Метрики качества'); ?>
    <?php \UC\FormCard::open(); ?>
        <?php omgoField('Средняя глубина прочтений', 'fields[cnt_readdepth]', $read); ?>
    <?php \UC\FormCard::close(); ?>
</div>
