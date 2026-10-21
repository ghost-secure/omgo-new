<?php

use Services\AnalysisService;

/** @var array $row */
/** @var array $opts */
/** @var array $materials */
/** @var array $card */
/** @var array $onko */
/** @var string $methodName */

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
    <div data-wrapper="aktmaterial" hidden>
        <?= \UC\Errors::formInfo(['detail' => 'Внимание!<br>При отправке <u>только</u> блока с опухолевой тканью в случае обнаружения патогенных мутаций в генах BRCA1/2 лаборатория потребует дополнительно дослать образец крови пациента или блок с нормальной тканью для верификации герминального типа обнаруженных мутаций.']) ?>
    </div>
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
            'radio',
            $materials
        ); ?>
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
        <?php omgoLabMaterial(
            'Блок с нормальной тканью',
            'stat_materialnormal',
            !empty($row['stat_materialnormal']),
            !empty($row['stat_materialnormal']),
            'Номер блока',
            'varc_marknormal',
            (string) ($row['varc_marknormal'] ?? ''),
            AnalysisService::MATERIAL_NORMA,
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
            '2' => 'Патогенные/онкогенные варианты в генах AKT1, BRCA1, BRCA2, PIK3CA, PTEN <span class="text-color-green">не обнаружены</span>',
            '1' => 'Патогенные/онкогенные варианты <span class="text-color-red">обнаружены</span>',
            '3' => 'Невозможно провести анализ',
        ], (string) $result, ['1' => 'result'], true, true); ?>
        <div data-wrapper="result" class="d-flex flex-column gap-md">
            <?php omgoCheck('Патогенный вариант обнаружен', 'fields[stat_patogen]', !empty($row['stat_patogen']), 'patogen'); ?>
            <div data-wrapper="patogen" class="d-flex gap-md" style="flex-wrap: wrap">
                <div style="flex: 1 1 12rem"><?php omgoField('Название варианта', 'fields[varc_variant]', (string) ($row['varc_variant'] ?? ''), true); ?></div>
                <div style="flex: 1 1 10rem"><?php omgoField('Частота аллеля', 'fields[varc_allel]', (string) ($row['varc_allel'] ?? '')); ?></div>
                <div style="flex: 1 1 10rem"><?php omgoField('Глубина прочтения варианта', 'fields[cnt_vardepth]', $depth); ?></div>
                <div style="flex: 1 1 12rem"><?php omgoSelect('Класс патогенности', 'fields[view_patogen]', $opts['patogen'], $row['view_patogen'] ?? '', true); ?></div>
            </div>
            <?php omgoCheck('Онкогенный вариант обнаружен', 'fields[stat_onkogen]', !empty($row['stat_onkogen']), 'onkogen'); ?>
            <div data-wrapper="onkogen" class="d-flex flex-column gap-md">
                <?php omgoOnko($onko, $opts['onko']); ?>
            </div>
        </div>
    <?php \UC\FormCard::close(); ?>
</div>

<div class="d-flex flex-column gap-md">
    <?php \UC\FormBlockTitle::render('Метрики качества'); ?>
    <?php \UC\FormCard::open(); ?>
        <?php omgoField('Средняя глубина прочтений', 'fields[cnt_readdepth]', $read); ?>
    <?php \UC\FormCard::close(); ?>
</div>
