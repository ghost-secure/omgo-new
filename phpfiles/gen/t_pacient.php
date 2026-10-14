<?php

/**
 * Список всех анализов. catalog.php = gen/t_pacient.php, /manager/mant_pacient/.
 * Грид — копия паспорта врача (t_analysis/list), без фильтра по врачу и без ролей.
 * Черновик, отчёты, «Добавить пациента» и фильтр по роли — ещё легаси, разберём отдельно.
 */

use Services\AnalysisService;

$analysis = \Core\Container::getInstance()->get(AnalysisService::class);
$query = $analysis->analysisGridQuery();
$query['short'] = true;

$dyn = new \UC\DynBlock(AnalysisService::TBL_ANALYSIS, 't_analysis/list', 20, 1, $query);
echo $dyn->render();
