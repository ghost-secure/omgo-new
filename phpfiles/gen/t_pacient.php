<?php

/**
 * Список заявок. catalog.php = gen/t_pacient.php, /manager/mant_pacient/.
 * Пациент и анализ — одна строка t_analysis. Грид t_analysis/list.
 * Роль режет строки и кнопку «Добавить пациента», как легаси gen/t_pacient.php.
 * Врач на телефоне — карточки t_analysis/doctor-mobile (легаси id 66).
 * Колонка «Действия»: ссылки operation и меню воркфлоу (dropdown).
 * «Настроить таблицу» и «Выгрузить в Excel» скрыты. Слева кнопка «Отчет».
 * Наборы столбцов 44, 59, 69 ещё не перенесены.
 */

use Services\AnalysisService;

$analysis = \Core\Container::getInstance()->get(AnalysisService::class);
$auth = function_exists('auth') ? auth() : null;
$user = $auth ? $auth->getUser() : null;
$user = is_array($user) ? $user : null;
$scope = $analysis->pacientListScope($user);
$uid = (int) ($user['id'] ?? 0);
$draftGuid = $uid > 0 ? $analysis->unfinishedCodeDraftGuid($uid) : null;

$mobile = isset($request) && $request instanceof \Core\Request && $request->isMobile();

echo '<div class="d-flex flex-column gap-md flex-grow-1 min-h-0" data-plugin="analysis-event">';

if ($draftGuid !== null) {
    $draftHref = htmlspecialchars(AnalysisService::pacientFormUrl($draftGuid), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo \UC\Errors::formError([
        'detail' => 'У вас есть одна незавершенная заявка, на которую вы потратили код пациента. Перейдите <a class="text-white" href="'
            . $draftHref
            . '">в форму</a> и завершите редактирование карточки пациента.',
    ]);
}

if ($scope['add_button'] && $mobile) {
    echo '<div class="d-flex">' . AnalysisService::addPacientButtonHtml() . '</div>';
}

$alias = !empty($scope['doctor']) && $mobile
    ? AnalysisService::DYN_DOCTOR_MOBILE
    : AnalysisService::DYN_LIST;

$query = $analysis->analysisGridQuery();
$query['short'] = true;
if (is_array($scope['where'])) {
    $query['staticWhere'] = [$scope['where']];
}
if ($alias === AnalysisService::DYN_DOCTOR_MOBILE) {
    $query['addSelect'][] = '(SELECT mlpa.cat FROM t_analysis mlpa WHERE mlpa.cnt_analysislinked = t_analysis.cat LIMIT 1) AS mlpa_cat';
}

$dyn = new \UC\DynBlock(AnalysisService::TBL_ANALYSIS, $alias, 20, 1, $query);
echo $dyn->render();

echo '</div>';
