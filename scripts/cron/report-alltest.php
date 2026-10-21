<?php

declare(strict_types=1);

/**
 * Готовые Excel-отчёты по заявкам: архив до 1 января 2026 и актуальный срез.
 * Файлы: runtime/cache/reports.
 *
 * php project/scripts/cron/report-alltest.php
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Core\Container;
use Services\Report\AnalysisReportExporter;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Только CLI.\n");
    exit(1);
}

ini_set('memory_limit', '2048M');
set_time_limit(0);

/** @var AnalysisReportExporter $exporter */
$exporter = Container::getInstance()->get(AnalysisReportExporter::class);
$exporter->run();
