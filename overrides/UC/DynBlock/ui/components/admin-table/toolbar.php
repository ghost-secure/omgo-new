<?php
/**
 * Тулбар админской таблицы. Проектный пробный слой.
 * Слева — кнопки текущего экрана. Справа — нативные кнопки вида и «Фильтры».
 * Флаги toolbar_settings и toolbar_excel = false прячут «Настроить таблицу» и «Выгрузить в Excel».
 *
 * @var \UC\DynBlock $dynBlock
 * @var \UC\DynBlock\AdminTableView $view
 * @var string $tableName
 */

$filterFields = $view->getFilterFields();
$primary = $view->getConfig()['filter_primary'] ?? [];
if (!is_array($primary)) {
    $primary = [];
}
$gridConfig = $view->getConfig();
$showTableSettings = ($gridConfig['toolbar_settings'] ?? true) !== false;
$showExcel = ($gridConfig['toolbar_excel'] ?? true) !== false;
$primaryList = [];
foreach ($primary as $column) {
    $column = trim((string) $column);
    if ($column !== '') {
        $primaryList[] = $column;
    }
}
?>
<div class="dynblock-toolbar"
     data-plugin="dynblock-toolbar"
     data-table="<?= htmlspecialchars($tableName) ?>"
     data-template-alias="<?= htmlspecialchars($dynBlock->getTemplateAlias()) ?>"
     data-enable-table-fullscreen="true">
    <div class="dynblock-toolbar__left">
        <?php
        $toolbarLeft = $view->toolbarLeftHtml();
        if ($toolbarLeft !== '') {
            echo $toolbarLeft;
        }
        ?>
        <?php if ($view->allowsCreate()): ?>
            <a href="<?= htmlspecialchars($dynBlock->getCreateUrl()) ?>"
               class="btn btn-primary"
               id="dynblock-add-btn">
                <span class="nav-item__icon" data-icon="System/add-line"></span>
                <span class="dynblock-toolbar__btn-label">Добавить запись</span>
            </a>
        <?php endif; ?>
    </div>
    <div class="dynblock-toolbar__right">
        <?php if ($filterFields !== []): ?>
            <span data-plugin="omgo-grid-chrome"
                  data-filter-primary="<?= htmlspecialchars(implode(',', $primaryList), ENT_QUOTES, 'UTF-8') ?>"
                  hidden></span>
            <button type="button"
                    class="btn btn-outline-secondary dynblock-toolbar__icon-btn dynblock-filters-toolbar-toggle"
                    data-plugin="tooltip"
                    data-tooltip="Фильтры"
                    aria-label="Фильтры">
                <span class="nav-item__icon" data-icon="System/filter-line"></span>
                <span class="dynblock-toolbar__btn-label">Фильтры</span>
            </button>
        <?php endif; ?>
        <?php
        $toolbarExtra = $view->toolbarExtraHtml();
        if ($toolbarExtra !== '') {
            echo $toolbarExtra;
        }
        ?>
        <button type="button"
                class="btn btn-outline-secondary dynblock-toolbar__icon-btn"
                data-toolbar-action="toggle-table-fullscreen"
                data-plugin="tooltip"
                data-tooltip="На весь экран"
                aria-label="На весь экран"
                aria-pressed="false">
            <span class="nav-item__icon" data-icon="Arrows/expand-diagonal-2-line"></span>
            <span class="dynblock-toolbar__btn-label">На экран</span>
        </button>
        <?php if ($showTableSettings): ?>
        <button type="button"
                class="btn btn-outline-secondary dynblock-toolbar__icon-btn"
                data-toolbar-action="table-settings"
                data-plugin="tooltip"
                data-tooltip="Настроить таблицу"
                aria-label="Настроить таблицу">
            <span class="nav-item__icon" data-icon="System/settings-line"></span>
            <span class="dynblock-toolbar__btn-label">Настроить таблицу</span>
        </button>
        <?php endif; ?>
        <?php if ($showExcel): ?>
        <button type="button"
                class="btn btn-outline-secondary dynblock-toolbar__icon-btn"
                data-toolbar-action="export-excel"
                data-plugin="tooltip"
                data-tooltip="Выгрузить в Excel"
                aria-label="Выгрузить в Excel">
            <span class="nav-item__icon" data-icon="Document/file-excel-line"></span>
            <span class="dynblock-toolbar__btn-label">Выгрузить в Excel</span>
        </button>
        <?php endif; ?>
    </div>
</div>
