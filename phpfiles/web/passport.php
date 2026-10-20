<?php

/**
 * Паспорт анализа. catalog.php = web/passport.php (Helpers\Page::PASSPORT).
 * Легаси web/passport.php: врач только свою заявку, спонсор и спонсор-аудит — нет.
 * AKT и AKT BRCA — блок 61, остальные — блок 49. Ветка внутри t_analysis/passport.
 */

use Core\Container;
use Services\AnalysisService;

$guid = trim((string) ($attributes['passport_guid'] ?? ''));
$auth = function_exists('auth') ? auth() : null;
$user = $auth && method_exists($auth, 'getUser') ? $auth->getUser() : null;
$user = is_array($user) ? $user : null;

$analysis = Container::getInstance()->get(AnalysisService::class);
$card = $analysis->draftByGuid($guid);
if ($card === null) {
    echo \UC\Errors::formError(['detail' => 'Заявка не найдена.']);
    return;
}

$denied = $analysis->denyReason($user, $card['analysis']);
if ($denied !== null) {
    echo \UC\Errors::formError(['detail' => htmlspecialchars($denied, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')]);
    return;
}

$query = $analysis->passportQuery();
$query['short'] = false;
$query['staticWhere'] = [
    [
        'sql' => 't_analysis.varc_guid = ?',
        'bind' => [$guid],
    ],
];

echo '<div data-plugin="analysis-event" class="d-flex flex-column gap-lg">';
echo (new \UC\DynBlock('t_analysis', 't_analysis/passport', 1, 1, $query))->render();
echo '</div>';
