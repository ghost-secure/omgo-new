<?php

/**
 * Управление пользователями. Каталог: /manager/admin/users/.
 * Базовый DynBlock t_users/base. Врачи — отдельные шаблоны doctors / doctors_lab.
 */
$usersDynBlock = new \UC\DynBlock('t_users', 't_users/base', 20, 1, [
    'sort' => ['cat' => 'DESC'],
    'joins' => [],
    'addSelect' => [],
    'short' => true,
]);

echo $usersDynBlock->render();
