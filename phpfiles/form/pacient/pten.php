<?php

use Services\AnalysisService;

/** @var array $row */
/** @var array $opts */
/** @var array $anamnes */

$lines = [];
foreach ($opts['linetherapy'] as $id => $text) {
    if ((int) $id < 4) {
        $lines[$id] = $text;
    }
}
?>
<div class="d-flex flex-column gap-md">
    <?php \UC\FormBlockTitle::render('Информация о диагнозе'); ?>
    <?php \UC\FormCard::open(); ?>
        <?php omgoColOpen(); ?>
            <?php omgoFixed('Диагноз', 'fields[view_diagnoz]', (string) AnalysisService::DI_RM, $opts['diagnoz'][AnalysisService::DI_RM] ?? ''); ?>
            <?php omgoFixed('Гистологический подтип', 'fields[view_gistotype]', '8', $opts['gistotype'][8] ?? '8'); ?>
            <div data-wrapper="gisto" class="d-flex flex-column gap-md">
                <?php omgoFiles('gisto', 'Загрузите гистологическое заключение в формате PDF,JPG,PNG,HEIC', 'Дополнительные файлы гистологического заключения'); ?>
            </div>
            <?php omgoSelect('Линия терапии', 'fields[view_linetherapy]', $lines, $row['view_linetherapy'] ?? '', true, [
                ['w' => 'musthasprogress', 'v' => ['3'], 'h' => null],
            ]); ?>
            <div data-wrapper="view_linetherapy">
                <?php omgoRadio('Наличие признаков прогрессирования', 'fields[stat_hasprogress]', ['1' => 'Да', '2' => 'Нет'], $row['stat_hasprogress'] ?? '', [
                    '1' => 'view_confirmprogress',
                    '2' => 'view_teraphyduration',
                ]); ?>
            </div>
            <div data-wrapper="musthasprogress">
                <?= \UC\Errors::formWarning(['detail' => 'Тестирование возможно только для пациентов с признаками прогрессирования']) ?>
            </div>
            <div data-wrapper="view_confirmprogress">
                <?php omgoSelect('Подтверждение прогрессирования', 'fields[view_confirmprogress]', $opts['confirmprogress'], $row['view_confirmprogress'] ?? ''); ?>
            </div>
            <div data-wrapper="view_teraphyduration">
                <?php omgoSelect('Длительность терапии', 'fields[view_teraphyduration]', $opts['teraphyduration'], $row['view_teraphyduration'] ?? ''); ?>
            </div>
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
            <?php omgoCheck('Блок с опухолевой тканью', 'fields[stat_materialgisto]', true, 'stat_materialgisto', true); ?>
            <div data-wrapper="stat_materialgisto">
                <?php omgoField('Номер блока', 'fields[varc_markgisto]', (string) ($row['varc_markgisto'] ?? '')); ?>
            </div>
        <?php omgoColClose(); ?>
        <?php omgoColOpen(); ?>
            <?php omgoCheck('Кровь', 'fields[stat_materialblood]', !empty($row['stat_materialblood']), 'stat_materialblood'); ?>
            <div data-wrapper="stat_materialblood">
                <?php omgoField('Маркировка', 'fields[varc_markblood]', (string) ($row['varc_markblood'] ?? '')); ?>
            </div>
        <?php omgoColClose(); ?>
        <?php omgoColOpen(); ?>
            <?php omgoCheck('Блок с нормальной тканью', 'fields[stat_materialnormal]', !empty($row['stat_materialnormal']), 'stat_materialnormal'); ?>
            <div data-wrapper="stat_materialnormal">
                <?php omgoField('Номер блока', 'fields[varc_marknormal]', (string) ($row['varc_marknormal'] ?? '')); ?>
            </div>
        <?php omgoColClose(); ?>
    <?php \UC\FormCard::close(); ?>
</div>
