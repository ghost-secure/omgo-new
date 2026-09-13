<?php

/**
 * Пересобрать t_menu.admin_left из catalog /manager/*.
 *
 *   php project/bin/rebuild-admin-menu.php
 *   php project/bin/rebuild-admin-menu.php --dry-run
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Services\AdminMenuCatalogBuilder;

$dryRun = in_array('--dry-run', $argv, true);
$keep = in_array('--keep', $argv, true);

$builder = new AdminMenuCatalogBuilder();
$nodes = $builder->buildNodes();

echo 'Узлов: ' . count($nodes) . PHP_EOL;

if ($dryRun) {
    echo json_encode($nodes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

$result = $builder->persist(AdminMenuCatalogBuilder::CODE, !$keep);
if ($result['saved']) {
    echo ($result['overwritten'] ? 'Перезаписано' : 'Записано') . " {$result['code']}: {$result['count']} узлов" . PHP_EOL;
} else {
    echo "Пропуск: {$result['code']} уже заполнен (запустите без --keep, чтобы перезаписать)" . PHP_EOL;
}
