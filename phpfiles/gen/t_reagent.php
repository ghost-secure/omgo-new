<?php

$dyn = new \UC\DynBlock('t_reagent', 't_reagent/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
