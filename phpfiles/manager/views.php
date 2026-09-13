<?php

$addSelect = [
    '(SELECT COUNT(*) FROM tblview AS children WHERE children.parent = tblview.id) AS children_count',
    '(SELECT COUNT(*) FROM tblfields WHERE tblfields.tablename = tblview.tablename AND tblfields.del = 0) AS fields_count',
    '(SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tblview.tablename) AS table_exists',
];

$viewsDynBlock = new \UC\DynBlock('tblview', 'tblview/base', 20, 1, [
    'sort' => ['id' => 'DESC'],
    'joins' => [],
    'addSelect' => $addSelect,
    'short' => true,
]);

echo $viewsDynBlock->render();
?>
<div style="display: flex; justify-content: flex-end; margin-top: var(--space-md);">
    <button class="btn btn-outline-primary"
            onclick="generateInterface('tblview')"
            title="Перегенерировать интерфейс для таблицы tblview">
        <span class="nav-item__icon" data-icon="Design/magic-line"></span>
        Перегенерировать интерфейс
    </button>
</div>
