<?php

$dyn = new \UC\DynBlock('t_patoclass', 't_patoclass/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
