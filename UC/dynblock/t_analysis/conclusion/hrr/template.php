<?php

/**
 * Заключение HRR.
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

if ($result > 0) {
    echo '<div><strong>Мутационный статус: </strong>' . V::name($record, 'view_result') . '</div>';
}
if ($result === 3) {
    echo '<div><strong>' . V::name($record, 'view_result') . '</strong></div>';
}
if ($result === 2) {
    echo '<div><strong>' . V::text($record, 'txt_patogenno') . '</strong></div>';
}
if ((int) ($record['stat_patogen'] ?? 0) === 1) {
    echo '<div class="text-weight-800">' . V::text($record, 'txt_patogenyes') . '</div>';
}
echo V::when($record, 'varc_variant', '<div><strong>Название варианта: </strong> ' . V::text($record, 'varc_variant') . '</div>');
echo V::when($record, 'txt_conclusion', '<div><strong>Комментарий: </strong> ' . V::text($record, 'txt_conclusion') . '</div>');
echo V::signature($record);
echo V::end();
