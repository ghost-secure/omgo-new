<?php

$catalogDynBlock = new \UC\DynBlock('catalog', 'catalog/base', 20, 1, [
    'sort' => ['id' => 'desc', 'name' => 'asc'],
    'short' => true,
]);
echo $catalogDynBlock->render();
?>
<div style="display: flex; justify-content: flex-end; margin-top: var(--space-md);">
    <button class="btn btn-outline-primary"
            onclick="generateInterface('catalog')"
            title="Перегенерировать интерфейс для таблицы catalog">
        <span class="nav-item__icon" data-icon="Design/magic-line"></span>
        Перегенерировать интерфейс
    </button>
</div>
