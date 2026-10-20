<?php

/**
 * Заключение MLPA.
 *
 * @var array $records
 */

use UC\ConclusionView as V;

$record = $records[0] ?? null;
if (!is_array($record)) {
    echo 'Заявка не найдена.';
    return;
}

$copy = (int) ($record['stat_copiynost'] ?? 0);

echo V::begin();
echo V::heading($record);
echo V::analysisTitle($record, false, false);
echo '<div class="mt"></div><div class="large">Результат молекулярно-генетического исследования</div>';

if ($copy === 1) {
    echo '<div class="bold">Изменение копийности выявлено </div>';
}
if ($copy === 2) {
    echo '<div class="bold">Изменение копийности не выявлено </div>';
}
echo V::when($record, 'varc_ecozone', '<div><strong>Затрагивает экзоны: </strong>' . V::text($record, 'varc_ecozone') . '</div>');
echo V::when($record, 'varc_coords', '<div><strong>Координаты: </strong>' . V::text($record, 'varc_coords') . '</div>');
echo V::when($record, 'txt_conclusion', '<div><b>Комментарии:</b></div><div>' . V::text($record, 'txt_conclusion') . '</div>');
echo V::signature($record);
echo V::end();
