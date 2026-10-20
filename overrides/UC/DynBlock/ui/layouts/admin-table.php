<?php
/**
 * Проектная сборка admin-table: тулбар над фильтрами.
 * Пробный слой. В ядро не перенесён.
 *
 * @var \UC\DynBlock $dynBlock
 * @var \UC\DynBlock\AdminTableView $view
 * @var string $tableName
 * @var array $records
 * @var mixed $fields
 * @var string|null $error
 * @var int $totalItems
 * @var int $totalPages
 * @var int $currentPage
 * @var int $limit
 * @var array $queryParams
 * @var string $encryptedParams
 * @var bool|null $short
 */

use UC\DynBlockUi;

$uiVars = get_defined_vars();
?>
<div class="admin-table-section table-scroll-local">
    <?php if ($error): ?>
        <div class="alert alert-danger">
            Ошибка загрузки: <?= htmlspecialchars((string) $error) ?>
        </div>
    <?php else: ?>
        <?php DynBlockUi::render('components/admin-table/title', $uiVars); ?>
        <?php DynBlockUi::render('components/admin-table/toolbar', $uiVars); ?>
        <?php DynBlockUi::render('components/admin-table/filters', $uiVars); ?>
        <?php DynBlockUi::render('components/admin-table/status-legend', $uiVars); ?>
        <?php DynBlockUi::render('components/admin-table/debug-sql', $uiVars); ?>
        <?php DynBlockUi::render('components/admin-table/table', $uiVars); ?>
        <?php DynBlockUi::render('components/admin-table/pagination', $uiVars); ?>
    <?php endif; ?>
</div>
<?php DynBlockUi::render('components/admin-table/info-script', $uiVars); ?>
