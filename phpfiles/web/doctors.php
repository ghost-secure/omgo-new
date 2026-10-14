<?php

/**
 * Сложный интерфейс: врачи (`catalog.php` = web/doctors.php).
 * Легаси Dyndata: blockid 53, для ROLE_LAB — 79 (без столбца «Маршрут»).
 * JOIN t_access_matrix → EXISTS; агрегаты — addSelect.
 */

use Services\AnalysisService;
use Services\RoleService;

$auth = auth();
$analysis = \Core\Container::getInstance()->get(AnalysisService::class);
$isLab = $auth && $auth->isRole(RoleService::ROLE_LAB);
$template = $isLab ? 't_users/doctors_lab' : 't_users/doctors';

$staticWhere = [
    [
        'sql' => 'EXISTS (
            SELECT 1 FROM t_access_matrix m
            WHERE m.user_use = t_users.cat AND m.view_role = ?
        )',
        'bind' => [RoleService::ROLE_DOCTOR],
    ],
];

if ($isLab) {
    $user = $auth->getUser() ?? [];
    $profile = is_array($user['profile'] ?? null) ? $user['profile'] : [];
    $labId = (int) ($profile['view_lab'] ?? 0);
    $lab = $analysis->getLabInfo($labId);
    if (!$analysis->labSeesAllDoctors($lab)) {
        $staticWhere[] = [
            'sql' => '(t_users.view_lab = ?
                OR EXISTS (
                    SELECT 1 FROM ' . AnalysisService::TBL_ANALYSIS . ' a
                    WHERE a.user_doctor = t_users.cat AND a.view_lab = ?
                )
            )',
            'bind' => [$labId, $labId],
        ];
    }
}

$testsSql = '(SELECT COUNT(cat) FROM ' . AnalysisService::TBL_ANALYSIS . ' WHERE user_doctor = t_users.cat)';
$routeSql = '(SELECT COUNT(cat) FROM ' . AnalysisService::TBL_ROUTE . ' WHERE user_doctor = t_users.cat)';

$addSelect = [
    $testsSql . ' AS custom_templateparam_tests',
];
$sortColumnExpressions = [
    'custom_templateparam_tests' => $testsSql,
    '_patients' => $testsSql,
];
if (!$isLab) {
    $addSelect[] = $routeSql . ' AS custom_templateparam_route';
    $sortColumnExpressions['custom_templateparam_route'] = $routeSql;
}

$dyn = new \UC\DynBlock('t_users', $template, 20, 1, [
    'short' => true,
    'staticWhere' => $staticWhere,
    'addSelect' => $addSelect,
    'sortColumnExpressions' => $sortColumnExpressions,
]);
echo $dyn->render();
