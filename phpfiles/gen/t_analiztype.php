<?php

$dyn = new \UC\DynBlock('t_analiztype', 't_analiztype/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
