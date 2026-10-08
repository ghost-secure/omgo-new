<?php

$dyn = new \UC\DynBlock('t_med', 't_med/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
