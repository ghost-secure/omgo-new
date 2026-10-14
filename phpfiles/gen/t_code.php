<?php

$dyn = new \UC\DynBlock('t_code', 't_code/base', 20, 1, [
    'short' => true,
    'addSelect' => [
        \Services\CodeService::userFioSelect('t_code', 'author_add'),
        \Services\CodeService::userFioSelect('t_code', 'user_activate'),
    ],
    'filterColumnExpressions' => [
        'code_only_idle' => [
            'where' => '({cond} AND ' . \Services\CodeService::idleSql('t_code') . ')',
            'column_sql' => '1',
        ],
        'code_only_used' => [
            'where' => '({cond} AND ' . \Services\CodeService::activatedSql('t_code') . ')',
            'column_sql' => '1',
        ],
    ],
]);
echo $dyn->render();
