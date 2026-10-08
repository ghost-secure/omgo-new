<?php

/**
 * СЛОЖНЫЙ интерфейс — на ядро не собран, править отдельной итерацией.
 * addqueryselect GROUP_CONCAT(t_analysis_onko); filter и blockid 38/45 по роли LAB/DOCTOR
 * Легаси: omgo-lu/project/phpfiles/gen/t_analysis.php
 */

global $roles;

$block_id = 38;
$tbl = \Object\Analysis\Analysis::TBL_ANALYSIS;
$filter = '1';

if ($roles->isRole (\Object\Role\Role::ROLE_LAB)) {
    $block_id = 45;
    $filter = ' d.view_lab = '.$this->profile['view_lab'];
}
if ($roles->isRole (\Object\Role\Role::ROLE_DOCTOR)) {
    $block_id = 45;
    $filter = ' v.user_doctor = '.$this->uid;
}


$params = array(
    'join' => '

    ',
    'sort' => NULL,
    'addqueryselect' => '
        , (SELECT GROUP_CONCAT(varc_variant) FROM t_analysis_onko WHERE view_analysis = v.cat) _varc_variant_group
    ',
    'addgroupby' => NULL,
    'filter' => $filter,
    'blockid' => $block_id,
    'table' => $tbl,
    'title' => $this->meta['header'] ? $this->meta['header'] : $this->meta['title'],   
);
$content = $this->Dyndata($params['blockid'], NULL, NULL, NULL,
    array (
        'filter' => $params['filter'], 
        'addqueryselect' => $params['addqueryselect'], 
        'groupby' => $params['addgroupby']
    ), 
    $params['sort'], NULL, NULL, $params['join']
);
echo \Core\UC\Common::tabledataWrapper($content, $params);
?>