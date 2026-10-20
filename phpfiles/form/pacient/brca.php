<?php

use Services\AnalysisService;

/** @var array $row */
/** @var array $opts */
/** @var array $anamnes */

$allGisto = $opts['gistotype_all'];
$allIllness = $opts['illness'];
$pick = static function (array $source, array $ids): array {
    $out = [];
    foreach ($ids as $id) {
        if (isset($source[$id])) {
            $out[(string) $id] = $source[$id];
        }
    }
    return $out;
};
$maps = [
    'view_gistotype' => [
        (string) AnalysisService::DI_RM => $pick($allGisto, [8, 9]),
        (string) AnalysisService::DI_RPZ => $pick($allGisto, [10]),
        (string) AnalysisService::DI_YAR => $pick($allGisto, [1]),
    ],
    'view_illness' => [
        (string) AnalysisService::DI_RM => $pick($allIllness, [1]),
        (string) AnalysisService::DI_RPZ => $pick($allIllness, [2]),
        (string) AnalysisService::DI_YAR => $pick($allIllness, [1]),
    ],
];
$diagnozOptions = [];
foreach ([AnalysisService::DI_RM, AnalysisService::DI_RPZ, AnalysisService::DI_YAR] as $id) {
    if (isset($opts['diagnoz'][$id])) {
        $diagnozOptions[$id] = $opts['diagnoz'][$id];
    }
}
$extra = 'data-brca-diagnoz data-brca-maps="' . omgoH((string) json_encode($maps, JSON_UNESCAPED_UNICODE)) . '"';
?>
<div class="d-flex flex-column gap-md">
    <?php \UC\FormBlockTitle::render('Информация о диагнозе'); ?>
    <?php \UC\FormCard::open(); ?>
        <?php omgoColOpen(); ?>
            <?php omgoSelect(
                'Диагноз',
                'fields[view_diagnoz]',
                $diagnozOptions,
                $row['view_diagnoz'] ?? '',
                true,
                [['w' => 'materials', 'v' => [(string) AnalysisService::DI_YAR], 'h' => null]],
                '',
                $extra
            ); ?>
            <?php omgoSelect('Гистологический подтип', 'fields[view_gistotype]', $opts['gistotype'], $row['view_gistotype'] ?? '', true); ?>
            <?php omgoSelect('Заболевание', 'fields[view_illness]', $opts['illness'], $row['view_illness'] ?? '', true); ?>
            <?php omgoSelect('Стадия', 'fields[view_stage]', $opts['stage'], $row['view_stage'] ?? '', true); ?>
            <?php omgoSelect('Перед взятием образцов опухолевой ткани проводилось химиотерапевтическое лечение', 'fields[view_chem]', $opts['chem'], $row['view_chem'] ?? ''); ?>
        <?php omgoColClose(); ?>
        <?php omgoColOpen(); ?>
            <?php omgoSelect('Личный онкологический анамнез', 'fields[view_myonkoan]', $opts['myonkoan'], $row['view_myonkoan'] ?? '', true, [
                ['w' => 'anamnes', 'v' => ['6'], 'h' => null],
            ]); ?>
            <div data-wrapper="anamnes">
                <?php omgoField('Укажите свое название', 'fields[varc_myonkoan]', (string) ($row['varc_myonkoan'] ?? '')); ?>
            </div>
            <?php omgoAnamnes($anamnes, $opts['anamnes']); ?>
        <?php omgoColClose(); ?>
    <?php \UC\FormCard::close(); ?>
</div>
<div class="d-flex flex-column gap-md">
    <?php \UC\FormBlockTitle::render('Материалы'); ?>
    <?php \UC\FormCard::open(); ?>
        <div data-wrapper="materials" class="d-flex flex-column gap-md" style="flex: 1 1 18rem">
            <?php omgoCheck('Блок с опухолевой тканью', 'fields[stat_materialgisto]', !empty($row['stat_materialgisto']), 'stat_materialgisto'); ?>
            <div data-wrapper="stat_materialgisto">
                <?php omgoField('Номер блока', 'fields[varc_markgisto]', (string) ($row['varc_markgisto'] ?? '')); ?>
            </div>
        </div>
        <?php omgoColOpen(); ?>
            <?php omgoCheck('Кровь', 'fields[stat_materialblood]', !empty($row['stat_materialblood']), 'stat_materialblood'); ?>
            <div data-wrapper="stat_materialblood">
                <?php omgoField('Маркировка', 'fields[varc_markblood]', (string) ($row['varc_markblood'] ?? '')); ?>
            </div>
        <?php omgoColClose(); ?>
    <?php \UC\FormCard::close(); ?>
</div>
