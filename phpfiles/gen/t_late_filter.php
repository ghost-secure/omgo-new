<?php

$dyn = new \UC\DynBlock('t_late_filter', 't_late_filter/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
