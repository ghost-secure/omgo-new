<?php

/**
 * Управление меню. Каталог: /manager/admin/menu/.
 * Грид справочника t_menu (запись admin_left и другие меню).
 */
$menuDynBlock = new \UC\DynBlock('t_menu', 't_menu/base', 20, 1, [
    'sort' => ['cat' => 'asc'],
    'short' => true,
]);
echo $menuDynBlock->render();
