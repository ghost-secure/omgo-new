<?php

use Services\AnalysisService;

/** @var array $row */
/** @var array $materials */
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
        <?php omgoArea('Результат молекулярно-генетического исследования', 'fields[txt_conclusion]', (string) ($row['txt_conclusion'] ?? '')); ?>
    <?php \UC\FormCard::close(); ?>
</div>
