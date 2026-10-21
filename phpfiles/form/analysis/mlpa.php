<?php

/** @var array $row */
/** @var array $card */

$copy = (string) ($row['stat_copiynost'] ?? '');
if ($copy !== '1' && $copy !== '2') {
    $copy = '';
}
?>
<div class="d-flex flex-column gap-md">
    <?php \UC\FormBlockTitle::render('Материалы'); ?>
    <?php \UC\FormCard::open(); ?>
        <?= \UC\Errors::formInfo(['detail' => 'В качестве материала используется периферическая венозная кровь']) ?>
    <?php \UC\FormCard::close(); ?>
</div>

<div class="d-flex flex-column gap-md">
    <?php \UC\FormBlockTitle::render('Анализ'); ?>
    <?php \UC\FormCard::open(); ?>
        <?php omgoPlain('Выполненный анализ', (string) ($card['type_name'] ?? '')); ?>
        <?php omgoRadio('Изменение копийности', 'fields[stat_copiynost]', [
            '1' => 'Выявлено',
            '2' => 'Не выявлено',
        ], $copy, [], true); ?>
        <?php omgoArea('Затрагивает экзоны', 'fields[varc_ecozone]', (string) ($row['varc_ecozone'] ?? '')); ?>
        <?php omgoArea('Координаты', 'fields[varc_coords]', (string) ($row['varc_coords'] ?? '')); ?>
    <?php \UC\FormCard::close(); ?>
</div>
