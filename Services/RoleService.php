<?php

namespace Services;

/**
 * Бизнес-роли кабинета (`t_roles.cat` / `t_access_matrix.view_role`).
 * Легаси: `Object\Role\Role`.
 *
 * Не путать с `tblusers.role` (группа доступа ядра: суперюзер / админ / менеджер).
 */
class RoleService
{
    public const ROLE_LAB = 1;
    public const ROLE_DOCTOR = 2;
    public const ROLE_ADMIN = 3;
    public const ROLE_SPONSOR = 5;
    public const ROLE_SPONSOR_AUDIT = 6;
}
