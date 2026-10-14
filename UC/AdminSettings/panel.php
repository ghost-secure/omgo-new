<?php
/**
 * Проектная панель Настройки: ядерная карта файлов + имперсонализация.
 *
 * @var array<string, mixed> $ctx
 */
$auth = function_exists('auth') ? auth() : null;
$ctx['impersonating'] = $auth && method_exists($auth, 'isImpersonating') && $auth->isImpersonating();
$ctx['can_impersonate'] = $auth && method_exists($auth, 'canStartImpersonation') && $auth->canStartImpersonation();
$ctx['impersonate_users'] = [];
$listFile = rtrim(PROJECT_PATH, '/\\') . '/config/admin_impersonate.php';
if (is_file($listFile)) {
    $list = require $listFile;
    $ctx['impersonate_users'] = is_array($list) ? $list : [];
}

include rtrim(CORE_PATH, '/\\') . '/UC/AdminSettings/panel.php';
include __DIR__ . '/impersonate.php';
