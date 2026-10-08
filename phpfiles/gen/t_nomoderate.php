<?php

$dyn = new \UC\DynBlock('t_nomoderate', 't_nomoderate/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
