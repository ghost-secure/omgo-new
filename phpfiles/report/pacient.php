<?php

/**
 * Скачивание готового отчёта по заявкам. /report/pacient/
 * ?target=new — актуальный срез, без параметра — архив до 1 января 2026.
 * Файл выбирает роль. Оболочки кабинета нет.
 */

use Core\Container;
use Services\Report\AnalysisReportFiles;

$auth = function_exists('auth') ? auth() : null;
$user = $auth && method_exists($auth, 'getUser') ? $auth->getUser() : null;
$user = is_array($user) ? $user : null;
$current = isset($request) && $request instanceof \Core\Request && (string) $request->get('target', '') === 'new';

/** @var AnalysisReportFiles $files */
$files = Container::getInstance()->get(AnalysisReportFiles::class);
$access = $files->audience($user);
$path = $access === null ? null : $files->pathFor($access['key'], $current);

if ($path === null || !is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'no data';
    return;
}

$suffix = $current ? '-Актуальный' : '-Архив';
$name = $access['title'] . $suffix . '.xlsx';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header(
    'Content-Disposition: attachment; filename="report.xlsx"; filename*=UTF-8\'\''
    . rawurlencode($name)
);
header('Content-Length: ' . (string) filesize($path));
header('Cache-Control: private, no-store');
readfile($path);
exit;
