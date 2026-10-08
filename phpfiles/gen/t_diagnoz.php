<?php

$dyn = new \UC\DynBlock('t_diagnoz', 't_diagnoz/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
