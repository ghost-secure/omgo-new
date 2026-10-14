<?php

/**
 * Паспорт врача. catalog.php = web/doctor-passport.php (узел 71, /manager/doctor/{id}/).
 * Легаси: dyndata 58 — карточка t_users; блок 54 — t_analysis, фильтр user_doctor.
 * Параметр doctor_id кладёт LegacyUrlStrategy.
 */

use Services\AnalysisService;

$doctorId = (int) ($attributes['doctor_id'] ?? 0);
if ($doctorId <= 0) {
    echo '<p>Врач не указан.</p>';
    return;
}

$card = new \UC\DynBlock('t_users', 't_users/doctor-card', 1, 1, [
    'short' => false,
    'staticWhere' => [
        [
            'sql' => 't_users.cat = ?',
            'bind' => [$doctorId],
        ],
    ],
]);
echo $card->render();

$analysis = \Core\Container::getInstance()->get(AnalysisService::class);
$query = $analysis->analysisGridQuery();
$query['short'] = false;
$query['staticWhere'] = [
    [
        'sql' => 't_analysis.user_doctor = ?',
        'bind' => [$doctorId],
    ],
];

$list = new \UC\DynBlock('t_analysis', 't_analysis/doctor-patients', 20, 1, $query);
echo $list->render();
