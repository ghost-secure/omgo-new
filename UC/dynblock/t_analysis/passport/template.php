<?php

/**
 * Паспорт заявки. Легаси блоки 49 и 61.
 *
 * @var array $records
 * @var string|null $error
 */

use Core\Container;
use Services\AnalysisService;
use Services\RoleService;

if (!empty($error)) {
    echo \UC\Errors::formError(['detail' => htmlspecialchars((string) $error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')]);
    return;
}

$record = $records[0] ?? null;
if (!is_array($record)) {
    echo \UC\Errors::formError(['detail' => 'Заявка не найдена.']);
    return;
}

$h = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$when = static function ($value, bool $dateOnly = false): string {
    if ($value === null || $value === '' || $value === 0 || $value === '0') {
        return '';
    }
    if (is_numeric($value)) {
        $stamp = (int) $value;
        return $stamp > 0 ? date($dateOnly ? 'd.m.Y' : 'd.m.Y H:i', $stamp) : '';
    }
    $stamp = strtotime((string) $value);
    if ($stamp === false) {
        return trim((string) $value);
    }
    return date($dateOnly ? 'd.m.Y' : 'd.m.Y H:i', $stamp);
};
$line = static function (string $label, string $value): void {
    \UC\PropLine::render($label, $value);
};
$fileLine = static function (string $label, string $path) use ($h): void {
    $path = trim($path);
    if ($path === '') {
        \UC\PropLine::render($label, '');
        return;
    }
    $safe = $h($path);
    \UC\PropLine::renderHtml(
        $label,
        '<a href="' . $safe . '" target="_blank" rel="noopener">' . $h(basename($path)) . '</a>'
    );
};

$analysis = Container::getInstance()->get(AnalysisService::class);
$names = [];
$dict = static function (string $column, $id) use ($analysis, &$names): string {
    $id = (int) $id;
    if ($id <= 0) {
        return '';
    }
    if (!isset($names[$column])) {
        $names[$column] = $analysis->columnOptions($column);
    }
    return trim((string) ($names[$column][$id] ?? ''));
};

$typeId = (int) ($record['view_test'] ?? 0);
$akt = in_array($typeId, [AnalysisService::TYPE_AKT, AnalysisService::TYPE_AKTBRCA], true);
$statusId = (int) ($record['view_status'] ?? 0);
$mlpaId = (int) ($record['_mlpa'] ?? 0);
$details = $analysis->passportDetails((int) ($record['cat'] ?? 0));
$anamnesNames = $analysis->columnOptions('view_myonkoan');

$auth = function_exists('auth') ? auth() : null;
$user = $auth && method_exists($auth, 'getUser') ? $auth->getUser() : null;
$roles = is_array($user['roles_map'] ?? null) ? $user['roles_map'] : [];
$firstRole = $roles === [] ? 0 : (int) array_key_first($roles);
$hidePatientCode = $firstRole === RoleService::ROLE_DOCTOR;

$gistotype = $dict('view_gistotype', $record['view_gistotype'] ?? 0);
$gistotypeOther = trim((string) ($record['varc_gistotype'] ?? ''));
if ($gistotypeOther !== '') {
    $gistotype = trim($gistotype . ' / ' . $gistotypeOther);
}
$progress = (int) ($record['stat_hasprogress'] ?? 0);
$progressText = $progress === 1 ? 'да' : ($progress === 2 ? 'нет' : '');

$galleryLines = static function (string $label, $raw) use ($line, $fileLine): void {
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($decoded) || $decoded === []) {
        return;
    }
    foreach ($decoded as $file) {
        if (!is_array($file)) {
            continue;
        }
        $path = trim((string) ($file['url'] ?? $file['path'] ?? $file['fileName'] ?? ''));
        if ($path !== '') {
            $fileLine($label, $path);
        }
    }
};
?>
<div class="d-flex flex-column gap-lg">
    <div class="d-flex flex-wrap align-center gap-sm">
        <?php \UC\FormBlockTitle::render(trim((string) ($record['pacient_name'] ?? '')) !== '' ? (string) $record['pacient_name'] : 'Паспорт'); ?>
        <?php if ((int) ($record['stat_express'] ?? 0) === 1): ?>
            <span class="font-medium text-blue">экспресс анализ</span>
        <?php endif; ?>
    </div>

    <div class="d-flex flex-wrap gap-lg">
        <div class="d-flex flex-column gap-xs" style="flex: 1 1 16rem">
            <?php
            $typeTitle = trim((string) ($record['type_fullname'] ?? ''));
            if ($typeTitle === '') {
                $typeTitle = $dict('view_test', $typeId);
            }
            $line('Анализ', $typeTitle);
            if (!$hidePatientCode) {
                $line('Код пациента', trim((string) ($record['pacient_code'] ?? '')));
            }
            $line('Лаборатория', trim((string) ($record['lab_name'] ?? '')));
            $line('Регион врача', trim((string) ($record['doctor_region'] ?? '')));
            $line('Врач', trim((string) ($record['doctor_name'] ?? '')));
            $line('Статус', $dict('view_status', $statusId));
            $line('Дата создания', $when($record['created_add'] ?? ''));
            $line('Дата принятия материала', $when($record['date_correctmaterial'] ?? 0));
            ?>
        </div>
        <div class="d-flex flex-column gap-xs" style="flex: 1 1 14rem">
            <div class="font-bold text-blue">Действия</div>
            <?= \UC\AnalysisActions::render($record, 'buttons', 'workflow') ?>
        </div>
        <div class="d-flex flex-column gap-xs" style="flex: 1 1 14rem">
            <div class="font-bold text-blue">Операции</div>
            <?= \UC\AnalysisActions::render($record, 'buttons', 'operations', true) ?>
        </div>
    </div>

    <?php
    $hasMaterial = (int) ($record['stat_materialblood'] ?? 0) === 1 || (int) ($record['stat_materialgisto'] ?? 0) === 1;
    $tabs = new \UC\TabSet('passport');
    $tabs->add('general', 'Общие сведения');
    $tabs->add('materials', 'Материалы', $hasMaterial);
    $tabs->add('analysis', 'Анализ', (int) ($record['view_result'] ?? 0) > 0);
    if (!$akt && $mlpaId > 0) {
        $tabs->add('mlpa', 'Анализ MLPA');
    }
    $tabs->add('history', 'История статуса');
    $tabs->begin();
    $tabs->panel('general');
    ?>
    <?php \UC\FormCard::open(); ?>
        <?php \UC\PropGroup::open('Информация о пациенте'); ?>
            <?php
            $line('Регион пациента', trim((string) ($record['pacient_region'] ?? '')));
            $line('Дата рождения', $when($record['pacient_bd'] ?? '', true));
            $line('Пол', trim((string) ($record['pacient_male'] ?? '')));
            $fileLine('Информированное согласие', (string) ($record['doc_agree'] ?? ''));
            $galleryLines('Выписной эпикриз', $record['gallery_epicris'] ?? '');
            ?>
        <?php \UC\PropGroup::close(); ?>
        <?php \UC\PropGroup::open('Информация о диагнозе'); ?>
            <?php
            $line('Диагноз', $dict('view_diagnoz', $record['view_diagnoz'] ?? 0));
            $line('Гистологический подтип', $gistotype);
            $fileLine('Гистологическое заключение', (string) ($record['doc_gisto'] ?? ''));
            $galleryLines('Гистологическое заключение', $record['gallery_gisto'] ?? '');
            $line('Локализация', $dict('view_localization', $record['view_localization'] ?? 0));
            $line('Заболевание', $dict('view_illness', $record['view_illness'] ?? 0));
            $line('Стадия', $dict('view_stage', $record['view_stage'] ?? 0));
            if ($akt) {
                $line('Прогрессирование', $dict('view_progressing', $record['view_progressing'] ?? 0));
            }
            $line('Перед взятием образцов опухолевой ткани проводилось химиотерапевтическое лечение', $dict('view_chem', $record['view_chem'] ?? 0));
            $line('Линия терапии', $dict('view_linetherapy', $record['view_linetherapy'] ?? 0));
            $line('Дата начала терапии на линии', $when($record['date_starttherapy'] ?? 0, true));
            $line('Наличие признаков прогрессирования', $progressText);
            $line('Подтверждение прогрессирования', $dict('view_confirmprogress', $record['view_confirmprogress'] ?? 0));
            $line('Длительность терапии', $dict('view_teraphyduration', $record['view_teraphyduration'] ?? 0));
            if (!$akt) {
                $line('Проводилось ли тестирование на PIK3CA/AKT/PTEN (PCR/NGS)', trim((string) ($record['varc_testpten'] ?? '')));
                $age = (int) ($record['cnt_agediagnoz'] ?? 0);
                $line('Возраст пациента на момент постановки диагноза', $age > 0 ? (string) $age : '');
                $glisson = (int) ($record['cnt_glisson'] ?? 0);
                $line('Индекс по шкале Глисона', $glisson > 0 ? (string) $glisson : '');
                $line('Распространенность', trim((string) ($record['varc_spread'] ?? '')));
                $line('Чувствительность к гормональной терапии', trim((string) ($record['varc_hormonesensitive'] ?? '')));
            }
            $personal = $dict('view_myonkoan', $record['view_myonkoan'] ?? 0);
            $personalOther = trim((string) ($record['varc_myonkoan'] ?? ''));
            if ($personalOther !== '') {
                $personal = trim($personal . ' / ' . $personalOther);
            }
            $line('Личный онкологический анамнез', $personal);
            $index = 0;
            foreach ($details['anamnes'] as $row) {
                $index++;
                $kindId = (int) ($row['view_anamnes'] ?? 0);
                if ($kindId <= 0) {
                    continue;
                }
                $kind = trim((string) ($anamnesNames[$kindId] ?? ''));
                $extra = trim((string) ($row['varc_anamnes'] ?? ''));
                if ($extra !== '') {
                    $kind = trim($kind . ' / ' . $extra);
                }
                $kin = trim((string) ($row['varc_kin'] ?? ''));
                $line('Семейный онкологический анамнез (' . $index . ')', trim($kin . ($kin !== '' && $kind !== '' ? ': ' : '') . $kind));
            }
            ?>
        <?php \UC\PropGroup::close(); ?>
        <?php \UC\PropGroup::open('Комментарий'); ?>
            <?php if ($details['comments'] === []): ?>
                <div>—</div>
            <?php else: ?>
                <?php foreach ($details['comments'] as $comment): ?>
                    <div><?= $h(trim((string) ($comment['author_name'] ?? ''))) ?>
                        <?= $h($when($comment['created_add'] ?? '')) ?>
                        <?= $h(trim((string) ($comment['txt_comm'] ?? ''))) ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php \UC\PropGroup::close(); ?>
    <?php \UC\FormCard::close(); ?>
    <?php $tabs->panel('materials'); ?>
    <?php \UC\FormCard::open(); ?>
        <?php \UC\PropGroup::open('Материалы'); ?>
            <?php if ($akt): ?>
                <?php
                $line('Блок с опухолевой тканью', trim((string) ($record['varc_markgisto'] ?? '')));
                $line('Блок с нормальной тканью', trim((string) ($record['varc_marknormal'] ?? '')));
                $line('Кровь', trim((string) ($record['varc_markblood'] ?? '')));
                if ((int) ($record['stat_materialblood'] ?? 0) === 0) {
                    $line('Кровь', 'отсутствует');
                }
                ?>
            <?php elseif ($typeId === AnalysisService::TYPE_MLPA): ?>
                <?php $line('Кровь', 'Периферическая венозная'); ?>
            <?php else: ?>
                <?php
                $line('Кровь', (int) ($record['stat_materialblood'] ?? 0) === 0 ? 'отсутствует' : '');
                $line('Кровь', trim((string) ($record['varc_markblood'] ?? '')));
                $line('Гистологический материал', trim((string) ($record['varc_markgisto'] ?? '')));
                ?>
            <?php endif; ?>
        <?php \UC\PropGroup::close(); ?>
    <?php \UC\FormCard::close(); ?>
    <?php $tabs->panel('analysis'); ?>
    <?php \UC\FormCard::open(); ?>
        <?php \UC\PropGroup::open('Информация об анализе'); ?>
            <?php if ($statusId === AnalysisService::STATUS_WORK && ($akt || $mlpaId === 0)): ?>
                <div class="text-danger">В настоящее время анализ выполняется..</div>
            <?php else: ?>
                <?php
                $line('Выполненный анализ', $typeTitle);
                $line('Дата поступления', $when($record['date_accept'] ?? 0, true));
                $line('Дата выполнения', $when($record['date_exec'] ?? 0, true));
                $line('Метод анализа', $dict('view_method', $record['view_method'] ?? 0));
                $line('Используемые реагенты', $dict('view_reagent', $record['view_reagent'] ?? 0));
                $line('Мутационный статус', $dict('view_result', $record['view_result'] ?? 0));
                $line('Название варианта', trim((string) ($record['varc_variant'] ?? '')));
                $line('Частота аллеля', trim((string) ($record['varc_allel'] ?? '')));
                $depth = (int) ($record['cnt_vardepth'] ?? 0);
                $line('Глубина прочтения варианта', $depth > 0 ? (string) $depth : '');
                $line('Класс патогенности', $dict('view_patogen', $record['view_patogen'] ?? 0));
                $line('Комментарий генетика', trim((string) ($record['txt_gencomment'] ?? '')));
                $genom = (int) ($record['cnt_genom'] ?? 0);
                $line('Показатель геномной нестабильности (GSS)', $genom > 0 ? (string) $genom : '');
                $line('Статус HRD', $dict('view_hrd', $record['view_hrd'] ?? 0));
                $snp = (int) ($record['cnt_snpdepth'] ?? 0);
                $line('Средняя глубина прочтений SNP', $snp > 0 ? (string) $snp : '');
                $brcaDepth = (int) ($record['cnt_brcadepth'] ?? 0);
                $line('Средняя глубина прочтений BRCA1/2', $brcaDepth > 0 ? (string) $brcaDepth : '');
                $line('Результат', trim((string) ($record['varc_result'] ?? '')));
                $noexpr = (int) ($record['cnt_noexpression'] ?? 0);
                $line('Процент клеток с отсутствием экспрессии', $noexpr > 0 ? (string) $noexpr : '');
                $line('Используемое антитело/клон', trim((string) ($record['varc_antitelo'] ?? '')));
                $line('Причина невозможности оценки', trim((string) ($record['varc_noresultreason'] ?? '')));
                $line('Комментарий', trim((string) ($record['txt_conclusion'] ?? '')));
                ?>
            <?php endif; ?>
        <?php \UC\PropGroup::close(); ?>
    <?php \UC\FormCard::close(); ?>

    <?php if (!$akt && $mlpaId > 0): ?>
        <?php $tabs->panel('mlpa'); ?>
        <?php \UC\FormCard::open(); ?>
            <?php \UC\PropGroup::open('MLPA'); ?>
                <?php if ($statusId === AnalysisService::STATUS_WORK): ?>
                    <div class="text-danger">В настоящее время анализ выполняется..</div>
                <?php else: ?>
                    <?php
                    $line('Изменение копийности', (int) ($record['stat_copiynost'] ?? 0) === 1 ? 'да' : '');
                    $line('Затрагивает экзоны', trim((string) ($record['varc_ecozone'] ?? '')));
                    $line('Координаты', trim((string) ($record['varc_coords'] ?? '')));
                    ?>
                <?php endif; ?>
            <?php \UC\PropGroup::close(); ?>
        <?php \UC\FormCard::close(); ?>
    <?php endif; ?>

    <?php $tabs->panel('history'); ?>
    <?php \UC\FormCard::open(); ?>
        <?php \UC\PropGroup::open('История статуса'); ?>
            <?php if ($details['events'] === []): ?>
                <div>—</div>
            <?php else: ?>
                <?php foreach ($details['events'] as $event): ?>
                    <div class="d-flex flex-column gap-xs">
                        <div class="text-muted"><?= $h($when($event['created_add'] ?? '')) ?></div>
                        <div class="text-blue"><?= $h(trim((string) ($event['type_name'] ?? ''))) ?></div>
                        <div><?= $h(trim((string) ($event['author_name'] ?? ''))) ?></div>
                        <?php $comment = trim((string) ($event['txt_comment'] ?? '')); ?>
                        <?php if ($comment !== ''): ?>
                            <div><?= $h($comment) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php \UC\PropGroup::close(); ?>
    <?php \UC\FormCard::close(); ?>
    <?php $tabs->finish(); ?>
</div>
