<?php

/**
 * СЛОЖНЫЙ интерфейс — на ядро не собран, править отдельной итерацией.
 * join + addqueryselect; роли LAB/DOCTOR/SPONSOR/ADMIN и разные blockid (54/44/59/69)
 * Легаси: omgo-lu/project/phpfiles/gen/t_pacient.php
 */

global $roles;

use Object\Analysis\Analysis;
use Object\Role\Role;
use UC\Forms;

?>
<?php
$add_button = false;
$has_draft = false;
$check_draft = $this->query ("SELECT * FROM ". Analysis::TBL_ANALYSIS." WHERE view_status = 0 AND view_pacient = 0 AND author_add = ".$this->uid);
if (!empty ($check_draft['varc_guid'])) {
    echo UC\Errors::FormError(['detail' => "У вас есть одна незавершенная заявка, на которую вы потратили код пациента. Перейдите <a href='".Analysis::getLinkPacient($check_draft['varc_guid'])."'>в форму</a> и завершите редактирование карточки пациента"]);
    echo '<div class="mt2"></div>';
    $has_draft = true;
}

/** настройки динблока по умолчанию */
    $block_id = 54;
    $tbl = Analysis::TBL_ANALYSIS;
//    $filter = 'v.cnt_analysislinked = 0';
//if ($this->user['role'] == 1)
        $filter = 1;

    $toolbardisable = ['add' => 1, 'template' => 1, 'excel' => 1];

/** download */
    $download = [
        '<div class="uk-button-dropdown" data-uk-dropdown="{mode:\'click\'}">
            <a href="javascript:;" class="css-controls text-color-blue _svg100 d-flex align-items-center fg1">
                <div>Отчет</div>
                <div>'. \UC\SVG::Code('download') .'</div>
            </a>
            <div class="uk-dropdown _shadow _border-radius pd2">
                <div class="d-flex flex-column fg1">
                    <a href="project/phpfiles/report/pacient.php" target="_blank" class="text-color-black _svg100 css-controls d-flex align-items-center fg1">До 1 октября 2025 ' . \Core\UC\SVG::Code('download') . '</a>
                    <a href="project/phpfiles/report/pacient.php?target=new" target="_blank" class="text-color-black _svg100 css-controls d-flex align-items-center fg1">После 1 октября 2025 ' . \Core\UC\SVG::Code('download') . '</a>
                </div>
            </div>
        </div>',
        '<span class="ml4"></span>',
    ];


/** фильтры */
    $filters_late = [];
    $filters_late[] = '<label class="pd1 text-size-75" for="express"><input type="checkbox" id="express" data-md-icheck value="1" data-filter="stat_express"  />&nbsp;Экспресс</label>';
    $res = (new \Core\Object\Api\Instance())
        ->select(['*'])
        ->from('t_late_filter')
        ->get();
    foreach ($res as $row) {
        $filters_late[] = '<label class="pd1 text-size-75" for="late' . $row['cat'] . '"><input type="checkbox" id="late' . $row['cat'] . '" data-md-icheck value="' . $row['cat'] . '" data-filter="view_late"  />&nbsp;' . $row['varc_name'] . '</label>';
    }
   // $filters_late[] = '<label class="pd1 text-size-75" for="mlpa"><input type="checkbox" id="mlpa" data-md-icheck value="mlpa" data-filter="custom_templateparam_mlpa"  />&nbsp;HRD+MLPA</label>';

/** лаборатория - убираем только создание, оно не нативное */
if ($roles->isRole (Role::ROLE_LAB)) {
    $filter = 'a.cat IS NOT NULL AND v.view_lab = '.$this->profile['view_lab'];
    unset ($toolbardisable['template']);
    $add_button = true;
    $controls = array_merge ($download, $filters_late);
}

/** доктор - фильтруем по доктору */
if ($roles->isRole (Role::ROLE_DOCTOR)) {
    $block_id = 44;
    if ($this->mobile)
        $block_id = 'doctor-mobile';
    $filter = 'a.cat IS NOT NULL AND v.user_doctor = '.$this->uid;
    $add_button = true;
}

/** спонсор - эксель вернем, другой динблок, все видит */
if ($roles->isRole (Role::ROLE_SPONSOR)) {
    $block_id = 59;
    $filter = '1';
    $add_button = false;
    $controls = $download;
}

/** спонсор - эксель вернем, другой динблок, все видит */
if ($roles->isRole (Role::ROLE_SPONSOR_AUDIT)) {
    $block_id = 69;
    $filter = '1';
    $add_button = false;
    $controls = $download;
}

/** админ - все видит */
if ($roles->isRole (Role::ROLE_ADMIN)) {
    $filter = '1';
    unset ($toolbardisable['excel']);
    //unset ($toolbardisable['template']);
    $add_button = true;
    $controls = array_merge ($download, $filters_late);
}

if ($this->user['role'] == 1) {
    $toolbardisable['template'] = 0;
    $controls = array_merge ($download, $filters_late);
}

?>

<?php
if ($this->mobile):?>
    <div class="d-flex justify-content-between align-items-center mt4">
        <div class="text-header text-size-200">Пациенты</div>
        <?php if ($add_button): ?>
            <div class="">
                <a href="<?= $this->url (Analysis::PAGE_PACIENT) ?>" class="md-btn bg-blue text-color-white" data-add-pacient>
                    <i class="material-icons text-color-white">add</i> Добавить пациента
                </a>
            </div>
        <?php endif ?>
    </div>
    <div class="mt3" style="border-bottom: 1px solid var(--color-lgrey)"></div>
    <div class="d-flex align-items-center justify-content-between mt4">
        <div class="d-flex align-items-bottom">
            <div class="d-flex fg2 align-items-center" style="width: 60vw">
                <div class="d-flex fg2 align-items-center" style="width: 60vw">
                    <div class="w-100">
                        <?= Forms::Field(['label' => '', 'name' => 'search', 'attributes' => 'placeholder="Поиск по ФИО" data-filter="custom_templateparam_name"']) ?>
                    </div>
                    <div>
                        <a class="_svg100 md-btn bg-promo text-color-white align-items-center justify-content-center d-flex" href="javascript:;" data-filter-search>
                            <?= \Core\UC\SVG::Code('search') ?>
                        </a>
                    </div>
                </div>

            </div>
        </div>
        <div class="d-flex fg2 align-items-center">
            <a class="text-color-lgrey _svg100  d-flex align-items-center fg1" href="javascript:;" data-filter-reset="">
                <?= \Core\UC\SVG::Code('sync') ?> <span>Сбросить все</span>
            </a>
        </div>
    </div>
<?php endif ?>

<?php
/** параметры вызова динблока */
$params = array(
    'join' => '

    ',
    'sort' => NULL,
    'addqueryselect' => '
        , (SELECT GROUP_CONCAT(varc_variant) FROM t_analysis_onko WHERE view_analysis = v.cat) _varc_variant_group
        , CASE 
            WHEN EXISTS (SELECT 1 FROM t_analysis WHERE view_pacient > 0 AND view_pacient = v.view_pacient AND view_test = v.view_test AND cat <> v.cat AND view_lab <> v.view_lab) THEN 2
            WHEN EXISTS (SELECT 1 FROM t_analysis WHERE view_pacient > 0 AND view_pacient = v.view_pacient AND view_test = v.view_test AND cat <> v.cat) THEN 1 
            ELSE 0 
          END custom_templateparam_double
    ',
    'addgroupby' => null,
    'filter' => $filter,
    'blockid' => $block_id,
    'table' => $tbl,
    'title' => $this->meta['header'] ? $this->meta['header'] : $this->meta['title'],
    'toolbardisable' => $toolbardisable
);
$content = $this->Dyndata($params['blockid'], NULL, NULL, NULL,
    array (
        'filter' => $params['filter'], 
        'addqueryselect' => $params['addqueryselect'], 
        'groupby' => $params['addgroupby']
    ), 
    $params['sort'], NULL, NULL, $params['join']
);
if ($this->mobile)
    echo $content;
else
    echo \Core\UC\Common::tabledataWrapper($content, $params, $controls ?? null);
?>

<?php if ($add_button && !$this->mobile): ?>
<div class="mt2 pdv2 bg-white w-100 table-footer">
    <a href="<?= $this->url (Analysis::PAGE_PACIENT) ?>" class="md-btn bg-promo text-color-white" data-add-pacient>
        Добавить пациента
    </a>
</div>

<?php endif ?>
<?php
$this->js[] = '
<script src="/theme/bower_components/enjoyhint/enjoyhint.js"></script>
<script src="/project/web/js/tour.js"></script>
<link href="/project/web/css/tour.css" rel="stylesheet">
';
?>
