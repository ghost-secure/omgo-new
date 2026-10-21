<?php

/**
 * Форма лаборатории. catalog.php = form/analysis.php, Helpers\Page::ANALYSIS.
 * Адрес /manager/analysis/{guid}/ открывает LegacyUrlStrategy.
 * Сохранение: ядерный POST /jsapi/form, table=t_analysis, хук Project\FormHooks\TAnalysisHook, op[step]=analysis.
 */

use Services\AnalysisService;
use UC\Form;

$analysis = \Core\Container::getInstance()->get(AnalysisService::class);
$guid = trim((string) ($attributes['analysis_guid'] ?? ''));
$title = isset($page) && is_object($page) && isset($page->name) ? (string) $page->name : 'Форма анализа';
$card = $guid === '' ? null : $analysis->draftByGuid($guid);
$authUser = function_exists('auth') && auth() ? auth()->getUser() : null;
$authUser = is_array($authUser) ? $authUser : null;
?>
<h1 class="page-title"><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>

<?php
if ($card === null) {
    echo \UC\Errors::formError(['detail' => 'Заявка не найдена.']);
    return;
}

$deny = $analysis->labFormDeny($authUser, $card['analysis']);
if ($deny !== null) {
    echo \UC\Errors::formError(['detail' => $deny]);
    return;
}

require_once __DIR__ . '/pacient/_fields.php';

$row = $card['analysis'];
$pacient = $card['pacient'];
$typeId = (int) ($row['view_test'] ?? 0);
$analysisId = (int) ($row['cat'] ?? 0);
$slug = (string) $card['slug'];
$fio = trim(implode(' ', array_filter([
    (string) ($pacient['varc_surname'] ?? ''),
    (string) ($pacient['varc_name'] ?? ''),
    (string) ($pacient['varc_patron'] ?? ''),
], static fn (string $part): bool => $part !== '')));
$accept = (int) ($row['date_accept'] ?? 0);
$acceptDate = $accept > 0 ? date('Y-m-d', $accept) : '';
$materials = array_values(array_filter(array_map(
    'intval',
    explode(',', (string) ($row['multiselect_materialexec'] ?? ''))
)));
$defaults = $analysis->conclusionDefaults((int) ($row['view_lab'] ?? 0), $typeId);
$fill = static function (string $column) use ($row, $defaults): string {
    $current = trim((string) ($row[$column] ?? ''));

    return $current !== '' ? $current : trim((string) ($defaults[$column] ?? ''));
};
$lab = $analysis->getLabInfo((int) ($row['view_lab'] ?? 0));
$labName = trim((string) ($lab->varc_name ?? ''));
$opts = [
    'method' => $analysis->columnOptions('view_method'),
    'reagent' => $analysis->reagents($typeId),
    'result' => $analysis->columnOptions('view_result'),
    'patogen' => $analysis->columnOptions('view_patogen'),
    'hrd' => $analysis->columnOptions('view_hrd'),
    'onko' => $analysis->onkoClasses(),
];
$onko = $analysis->onkoRows($analysisId);
$typeFile = __DIR__ . '/analysis/' . $slug . '.php';
$methodName = $opts['method'][AnalysisService::METHOD_NGS] ?? 'NGS';
if ($slug === '' || !is_file($typeFile)) {
    echo \UC\Errors::formError(['detail' => 'Для этого типа анализа форма лаборатории ещё не собрана.']);
    return;
}
?>
<form method="post" action="/jsapi/form" novalidate data-plugin="pacient-card" data-pacient-card data-save-error="Не удалось сохранить анализ" class="d-flex flex-column gap-lg">
    <div class="d-flex flex-column gap-md">
        <?php \UC\FormBlockTitle::render('Общие сведения'); ?>
        <?php \UC\FormCard::open(); ?>
            <?php omgoPlain('Лаборатория', $labName); ?>
            <?php omgoPlain('ФИО врача', $analysis->userFio((int) ($row['user_doctor'] ?? 0))); ?>
            <?php omgoDate('Дата поступления', 'fields[date_accept]', $acceptDate, true); ?>
        <?php \UC\FormCard::close(); ?>
    </div>

    <?php if ($fio !== ''): ?>
        <?= \UC\Errors::formInfo(['detail' => 'Пациент: <b>' . omgoH($fio) . '</b>']) ?>
    <?php endif; ?>

    <?php include $typeFile; ?>

    <?php if ($slug !== 'hrr'): ?>
        <div class="d-flex flex-column gap-md">
            <?php \UC\FormBlockTitle::render('Комментарий'); ?>
            <?php \UC\FormCard::open(); ?>
                <?php omgoArea('Комментарий', 'fields[txt_conclusion]', (string) ($row['txt_conclusion'] ?? '')); ?>
            <?php \UC\FormCard::close(); ?>
        </div>
    <?php endif; ?>

    <div hidden>
        <input type="hidden" name="fields[txt_gen]" value="<?= omgoH($fill('txt_gen')) ?>">
        <input type="hidden" name="fields[txt_limit]" value="<?= omgoH($fill('txt_limit')) ?>">
        <input type="hidden" name="fields[txt_metodica]" value="<?= omgoH($fill('txt_metodica')) ?>">
    </div>

    <div data-pacient-card-error hidden>
        <?= \UC\Errors::formError(['detail' => '']) ?>
    </div>
    <div>
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </div>
    <input type="hidden" name="op[step]" value="analysis">
    <input type="hidden" name="id" value="<?= $analysisId ?>">
    <input type="hidden" name="table" value="<?= htmlspecialchars(AnalysisService::TBL_ANALYSIS, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="hash" value="<?= htmlspecialchars(Form::makeHash(AnalysisService::TBL_ANALYSIS, (string) $analysisId), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="_csrf_token" value="">
</form>
