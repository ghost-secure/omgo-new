<?php

$dyn = new \UC\DynBlock('t_teraphyduration', 't_teraphyduration/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
