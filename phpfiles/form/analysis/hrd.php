<?php

use Services\AnalysisService;

/** @var array $row */
/** @var array $opts */
/** @var array $materials */
/** @var array $card */
/** @var int $typeId */
/** @var callable $fill */

$isHrd = $typeId === AnalysisService::TYPE_HRD;
$depth = (string) ($row['cnt_vardepth'] ?? '');
if ($depth === '0') {
    $depth = '';
}
$snp = (string) ($row['cnt_snpdepth'] ?? '');
if ($snp === '0') {
    $snp = '';
}
$brcaDepth = (string) ($row['cnt_brcadepth'] ?? '');
if ($brcaDepth === '0') {
    $brcaDepth = '';
}
$genom = (string) ($row['cnt_genom'] ?? '');
if ($genom === '0') {
    $genom = '';
}
?>
<div class="d-flex flex-column gap-md">
    <?php \UC\FormBlockTitle::render('Материалы'); ?>
    <div class="font-bold">Предоставлены материалы</div>
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
    <?php \UC\FormCard::close(); ?>
</div>

<div class="d-flex flex-column gap-md">
    <?php \UC\FormBlockTitle::render('Анализ'); ?>
    <?php \UC\FormCard::open(); ?>
        <?php omgoAssayHead(
            (string) ($card['type_name'] ?? ''),
            null,
            '',
            $opts['method'],
            $row['view_method'] ?? '',
            $opts['reagent'],
            $row['view_reagent'] ?? ''
        ); ?>
        <?php omgoSelect('Мутационный статус', 'fields[view_result]', $opts['result'], $row['view_result'] ?? '', true, [
            ['w' => 'result', 'h' => ['', '2', '3']],
        ]); ?>
        <div data-wrapper="result" class="d-flex flex-column gap-md">
            <?php omgoField('Название варианта', 'fields[varc_variant]', (string) ($row['varc_variant'] ?? ''), true); ?>
            <?php omgoField('Частота аллеля', 'fields[varc_allel]', (string) ($row['varc_allel'] ?? '')); ?>
            <?php omgoField('Глубина прочтения варианта', 'fields[cnt_vardepth]', $depth); ?>
            <?php omgoSelect('Класс патогенности', 'fields[view_patogen]', $opts['patogen'], $row['view_patogen'] ?? '', true); ?>
            <?php omgoArea('Комментарий генетика', 'fields[txt_gencomment]', $fill('txt_gencomment'), true); ?>
        </div>
        <?php if ($isHrd): ?>
            <?php omgoCheck('Показатель геномной нестабильности (GSS) невозможно определить', 'fields[stat_genom]', !empty($row['stat_genom'])); ?>
            <?php omgoField('Показатель геномной нестабильности (GSS)', 'fields[cnt_genom]', $genom); ?>
            <?php omgoSelect('Статус HRD', 'fields[view_hrd]', $opts['hrd'], $row['view_hrd'] ?? '', true); ?>
        <?php endif; ?>
    <?php \UC\FormCard::close(); ?>
</div>

<div class="d-flex flex-column gap-md">
    <?php \UC\FormBlockTitle::render('Метрики качества'); ?>
    <?php \UC\FormCard::open(); ?>
        <div style="flex: 1 1 16rem"><?php omgoField('Средняя глубина прочтений SNP', 'fields[cnt_snpdepth]', $snp); ?></div>
        <div style="flex: 1 1 16rem"><?php omgoField('Средняя глубина прочтений BRCA1/2', 'fields[cnt_brcadepth]', $brcaDepth); ?></div>
    <?php \UC\FormCard::close(); ?>
</div>
