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
            <?php omgoFixed('Диагноз', 'fields[view_diagnoz]', (string) AnalysisService::DI_PRED, $opts['diagnoz'][AnalysisService::DI_PRED] ?? ''); ?>
            <?php omgoFiles('gisto', 'Загрузите гистологическое заключение в формате PDF,JPG,PNG,HEIC', 'Дополнительные файлы гистологического заключения'); ?>
            <div class="form-field d-flex flex-column gap-xs">
                <?php \UC\FormLabel::render('Возраст пациента на момент постановки диагноза', true); ?>
                <input type="number" name="fields[cnt_agediagnoz]" min="18" max="64" value="<?= omgoH((string) ($row['cnt_agediagnoz'] ?? '')) ?>" data-required>
                <div>Возраст до 18 или более 64 не соответствует критериям анализа</div>
            </div>
            <?php omgoFixed('Распространенность', 'fields[varc_spread]', 'Метастатический', 'Метастатический'); ?>
            <?php omgoRadio('Глисон', 'fields[varc_glison]', ['7' => '7', '8' => '8', '9' => '9', '10' => '10'], $row['varc_glison'] ?? '', [], true); ?>
            <?php omgoFixed('Чувствительность к гормонам', 'fields[varc_hormonesensitive]', 'Гормон-чувствительный', 'Гормон-чувствительный'); ?>
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
    <?php \UC\FormCard::close(); ?>
</div>
