<?php

$dyn = new \UC\DynBlock('t_date_month', 't_date_month/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
