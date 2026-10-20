<?php
/**
 * Заголовок грида. У списков одной таблицы t_users имя справочника общее
 * («1. Пользователи»), поэтому эти шаблоны берут meta.title.
 *
 * @var \UC\DynBlock $dynBlock
 * @var \UC\DynBlock\AdminTableView $view
 */

$alias = $dynBlock->getTemplateAlias();
$ownTitle = in_array($alias, ['t_users/doctors', 't_users/doctors_lab', 't_users/base'], true);
$title = $view->getTitle();
if ($ownTitle) {
    $meta = $view->getConfig()['meta'] ?? [];
    $custom = trim((string) (is_array($meta) ? ($meta['title'] ?? '') : ''));
    if ($custom !== '') {
        $title = $custom;
    }
}
?>
<div>
    <h3><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h3>
</div>
