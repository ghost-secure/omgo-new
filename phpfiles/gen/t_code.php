<?php

$dyn = new \UC\DynBlock('t_code', 't_code/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
