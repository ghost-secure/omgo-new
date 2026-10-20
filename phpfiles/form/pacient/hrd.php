<?php

use Services\AnalysisService;

/** @var array $row */
/** @var array $opts */
/** @var array $anamnes */
?>
<div class="d-flex flex-column gap-md">
    <?php \UC\FormBlockTitle::render('Информация о диагнозе'); ?>
    <?php \UC\FormCard::open(); ?>
        <?php omgoColOpen(); ?>
            <?php omgoFixed('Диагноз', 'fields[view_diagnoz]', (string) AnalysisService::DI_YA, $opts['diagnoz'][AnalysisService::DI_YA] ?? ''); ?>
            <?php omgoSelect('Гистологический подтип', 'fields[view_gistotype]', $opts['gistotype'], $row['view_gistotype'] ?? '', true, [
                ['w' => 'gistoother', 'v' => ['7'], 'h' => null],
                ['w' => 'gisto', 'v' => null, 'h' => ['']],
            ]); ?>
            <div data-wrapper="gistoother">
                <?php omgoField('Укажите свое название', 'fields[varc_gistotype]', (string) ($row['varc_gistotype'] ?? '')); ?>
            </div>
            <div data-wrapper="gisto" class="d-flex flex-column gap-md">
                <?php omgoFiles('gisto', 'Загрузите гистологическое заключение в формате PDF,JPG,PNG,HEIC', 'Дополнительные файлы гистологического заключения'); ?>
            </div>
            <?php omgoSelect('Локализация', 'fields[view_localization]', $opts['localization'], $row['view_localization'] ?? '', true); ?>
            <?php omgoFixed('Заболевание', 'fields[view_illness]', '1', $opts['illness'][1] ?? ''); ?>
            <div>При направлении пациента с рецидивом рака яичников рекомендуется проведение анализа статуса мутаций генов BRCA1/2 (тестирование BRCA для РЯ)</div>
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
        <?php omgoColOpen(); ?>
            <?php omgoCheck('Кровь', 'fields[stat_materialblood]', !empty($row['stat_materialblood']), 'stat_materialblood'); ?>
            <div data-wrapper="stat_materialblood">
                <?php omgoField('Маркировка', 'fields[varc_markblood]', (string) ($row['varc_markblood'] ?? '')); ?>
            </div>
        <?php omgoColClose(); ?>
        <?php omgoColOpen(); ?>
            <?php omgoCheck('Блок с опухолевой тканью', 'fields[stat_materialgisto]', !empty($row['stat_materialgisto']), 'stat_materialgisto'); ?>
            <div data-wrapper="stat_materialgisto">
                <?php omgoField('Номер блока', 'fields[varc_markgisto]', (string) ($row['varc_markgisto'] ?? '')); ?>
            </div>
        <?php omgoColClose(); ?>
    <?php \UC\FormCard::close(); ?>
</div>
