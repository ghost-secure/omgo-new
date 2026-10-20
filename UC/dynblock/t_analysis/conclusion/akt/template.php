<?php

/**
 * Заключение AKT и AKT+BRCA.
 *
 * @var array $records
 */

use UC\ConclusionView as V;

$record = $records[0] ?? null;
if (!is_array($record)) {
    echo 'Заявка не найдена.';
    return;
}

$result = (int) ($record['view_result'] ?? 0);

echo V::begin();
echo V::heading($record);
echo V::analysisTitle($record, true, true);
echo '<div class="mt"></div><div class="large">Результат молекулярно-генетического исследования</div>';

if ($result === 3) {
    echo '<div><strong>' . V::name($record, 'view_result') . '</strong></div>';
}
if ($result === 2) {
    echo '<div><strong>' . V::text($record, 'txt_patogenno') . '</strong></div>';
}
if ((int) ($record['stat_patogen'] ?? 0) === 1) {
    echo '<div class="bold">' . V::text($record, 'txt_patogenyes') . '</div>';
}
echo V::when($record, 'varc_variant', '<div><strong>Название варианта: </strong>' . V::text($record, 'varc_variant') . '</div>');
echo V::when($record, 'varc_allel', '<div><b>Частота аллеля: </b>' . V::text($record, 'varc_allel') . '</div>');
echo V::when($record, 'cnt_vardepth', '<div><strong>Глубина прочтения варианта: </strong>' . V::number($record, 'cnt_vardepth') . '</div>');
echo V::when($record, 'view_patogen', '<div><strong>Класс патогенности: </strong>' . V::name($record, 'view_patogen') . '</div>');
if ((int) ($record['stat_onkogen'] ?? 0) === 1) {
    echo '<div class="bold">' . V::text($record, 'txt_onkogenyes') . '</div>';
}
echo V::onko((int) ($record['cat'] ?? 0));
echo V::hrd($record);
if ($result === 1) {
    echo V::when($record, 'txt_gencomment', '<div><b>Комментарии генетика:</b></div><div>' . V::text($record, 'txt_gencomment') . '</div>');
}
echo V::when($record, 'txt_conclusion', '<div><b>Комментарии:</b></div><div>' . V::text($record, 'txt_conclusion') . '</div>');
echo V::metrics($record);
echo V::signature($record);
echo V::end();
