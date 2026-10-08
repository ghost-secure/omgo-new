<?php

$dyn = new \UC\DynBlock('t_result', 't_result/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
