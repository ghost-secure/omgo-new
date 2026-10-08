<?php

$dyn = new \UC\DynBlock('t_landing', 't_landing/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
