<?php

/**
 * Заключение PTEN.
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
echo '<div class="mt"></div><div class="large">Результат ИГХ исследования</div>';

if (V::filled($record, 'varc_result')) {
    echo '<div><strong>' . V::text($record, 'varc_result') . '</strong></div>';
}
echo V::when($record, 'varc_variant', '<div><strong>Название варианта: </strong>' . V::text($record, 'varc_variant') . '</div>');
echo V::when($record, 'varc_allel', '<div class="text-size-75"><b>Частота аллеля: </b>' . V::text($record, 'varc_allel') . '</div>');
echo V::when($record, 'cnt_vardepth', '<div class="text-size-75"><strong>Глубина прочтения варианта: </strong>' . V::number($record, 'cnt_vardepth') . '</div>');
echo V::when($record, 'view_patogen', '<div><strong>Класс патогенности: </strong>' . V::name($record, 'view_patogen') . '</div>');
echo V::when($record, 'varc_antitelo', '<div><strong>Используемое антитело/клон: </strong>' . V::text($record, 'varc_antitelo') . '</div>');
echo V::when($record, 'varc_noresultreason', '<div><strong>Причина невозможности оценки: </strong>' . V::text($record, 'varc_noresultreason') . '</div>');
if ($result === 1) {
    echo V::when($record, 'txt_gencomment', '<div><b>Комментарии генетика:</b></div><div>' . V::text($record, 'txt_gencomment') . '</div>');
}
echo V::when($record, 'txt_conclusion', '<div><b>Комментарии:</b></div><div>' . V::text($record, 'txt_conclusion') . '</div>');
echo V::expressionProtein($record);
echo V::metrics($record);
echo V::signature($record);
echo V::end();
