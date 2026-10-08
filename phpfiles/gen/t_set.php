<?php

$dyn = new \UC\DynBlock('t_set', 't_set/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
