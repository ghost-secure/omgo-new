<?php

$dyn = new \UC\DynBlock('t_method', 't_method/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
