<?php

$dyn = new \UC\DynBlock('t_onkoan', 't_onkoan/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
