<?php

/**
 * Карточка заявки врача на телефоне.
 * Разметка легаси pBody блока doctor-mobile: id, ФИО, дата рождения, диагноз, бейдж анализа, MLPA, дата, статус.
 * Внизу карточки те же «Действия», что у таблицы: ссылки и меню воркфлоу.
 *
 * @var list<array<string, mixed>> $records
 * @var string|null $error
 * @var int $totalItems
 * @var int $totalPages
 * @var int $currentPage
 * @var int $limit
 * @var string $encryptedParams
 * @var bool|null $short
 */

use Core\Reference\Field;
use Core\Reference\View;
use Services\AnalysisService;

$h = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$view = View::findOne(['tablename' => AnalysisService::TBL_ANALYSIS]);
$title = $view !== null ? trim($view->getName()) : '';
if ($title === '') {
    $title = 'Анализы';
}

$params = $h((string) ($encryptedParams ?? ''));
$shortAttr = !empty($short) ? ' data-dynblock-short="true"' : '';
?>
<div class="d-flex flex-column gap-md">
    <h3 class="m-0"><?= $h($title) ?></h3>
    <div class="d-flex flex-column gap-md dynblock-container"
         data-plugin="dynblock"
         id="t_analysis-doctor-mobile"
         data-dynblock-params="<?= $params ?>"<?= $shortAttr ?>>
<?php if (!empty($error)): ?>
    <div class="d-flex align-center gap-sm p-md rounded bg-danger text-white"><?= $h((string) $error) ?></div>
<?php elseif ($records === []): ?>
    <div class="d-flex align-center gap-sm p-md rounded text-yellow" style="background: var(--color-bg-yellow)">
        В настоящее время записей нет. Нажмите кнопку <b>Добавить пациента</b> для перехода к форме создания пациента.
    </div>
<?php else: ?>
    <?php foreach ($records as $record): ?>
        <?php
        $token = AnalysisService::colorToken((string) ($record['status_color'] ?? ''));
        $bg = in_array($token, AnalysisService::ROW_BG_TOKENS, true)
            ? ' style="background: var(--color-bg-' . $token . ')"'
            : '';
        $name = trim((string) ($record['pacient_name'] ?? ''));
        $guid = trim((string) ($record['varc_guid'] ?? ''));
        $diag = trim((string) ($record['view_diagnoz_name'] ?? ''));
        $test = trim((string) ($record['view_test_name'] ?? ''));
        $status = trim((string) ($record['view_status_name'] ?? ''));
        $badge = AnalysisService::badgeClass((string) ($record['test_color'] ?? ''));
        $href = $guid !== '' ? '/manager/passport/' . rawurlencode($guid) . '/' : '';
        ?>
        <div class="d-flex flex-column gap-sm p-md shadow rounded"<?= $bg ?>>
            <div>ID <?= (int) ($record['cat'] ?? 0) ?></div>
            <div>
                <?php if ($name === ''): ?>
                    <span class="text-muted">—</span>
                <?php elseif ($href === ''): ?>
                    <?= $h($name) ?>
                <?php else: ?>
                    <a href="<?= $h($href) ?>"><?= $h($name) ?></a>
                <?php endif; ?>
            </div>
            <div>Дата рождения: <span class="whitespace-nowrap"><?= Field::formatValue($record['pacient_bd'] ?? null, 'date') ?></span></div>
            <div>Диагноз: <?= $diag !== '' ? $h($diag) : '<span class="text-muted">—</span>' ?></div>
            <div class="d-flex align-center justify-between gap-md">
                <div class="d-flex flex-column gap-sm">
                    <?php if ($test === ''): ?>
                        <span class="text-muted">—</span>
                    <?php elseif ($badge === ''): ?>
                        <?= $h($test) ?>
                    <?php else: ?>
                        <div class="<?= $h($badge) ?> rounded p-sm text-white text-center font-bold text-sm"><?= $h($test) ?></div>
                    <?php endif; ?>
                    <?php if ((int) ($record['mlpa_cat'] ?? 0) > 0): ?>
                        <div class="bg-blue rounded p-sm text-white text-center font-bold text-sm">MLPA</div>
                    <?php endif; ?>
                </div>
                <div><?= Field::formatValue($record['created_add'] ?? null, 'created') ?></div>
            </div>
            <div><?= $status !== '' ? $h($status) : '<span class="text-muted">—</span>' ?></div>
            <?= \UC\AnalysisActions::render($record, 'dropdown') ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
    </div>
<?php if ((int) $totalItems > 0): ?>
    <?php
    $paginator = new \UC\Paginator((int) $currentPage, (int) $totalPages, (int) $totalItems, (int) $limit);
    echo $paginator->render();
    ?>
<?php endif; ?>
</div>
