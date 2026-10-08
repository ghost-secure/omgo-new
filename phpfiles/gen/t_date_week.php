<?php

$dyn = new \UC\DynBlock('t_date_week', 't_date_week/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
