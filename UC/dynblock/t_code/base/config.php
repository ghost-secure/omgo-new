<?php

use UC\DynBlock\AdminTableView;
use UC\Flow;

$columns = AdminTableView::columnsFromMetadata('t_code');
foreach (['author_add', 'user_activate'] as $userColumn) {
    if (!isset($columns[$userColumn])) {
        continue;
    }
    $columns[$userColumn]['cell_formatter'] = <<<CODE
<?php
echo \\Services\\CodeService::userFioHtml(\$record, '{$userColumn}');
CODE;
    $columns[$userColumn]['export_formatter'] = <<<CODE
<?php
echo \\Services\\CodeService::userFioPlain(\$record, '{$userColumn}');
CODE;
}

return [
    'meta' => [
        'table_name' => 't_code',
        'ui' => 'admin-table',
        'actions' => [
            'create' => true,
            'edit' => false,
            'delete' => true,
            'inline' => false,
        ],
    ],
    'columns' => $columns,
    'filter_fields' => [
        [
            'column' => 'code_only_idle',
            'label' => 'Только неактивированные',
            'type' => 'flag',
        ],
        [
            'column' => 'code_only_used',
            'label' => 'Только активированные',
            'type' => 'flag',
        ],
    ],
    'row_actions' => [
        'delete_if' => <<<'CODE'
!\Services\CodeService::isActivated($record)
CODE
        ,
    ],
    'toolbar_left' => static function (): string {
        return Flow::button([
            'id' => 'codes',
            'action' => 'WF_CODEPULL',
            'dataid' => '0',
            'table' => 't_code',
            'title' => 'Создать пул кодов',
            'label' => 'Создать пул кодов',
            'ico' => 'Device/barcode-line',
            'variant' => 'toolbar',
        ]);
    },
];
