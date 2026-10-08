<?php

$dyn = new \UC\DynBlock('t_late', 't_late/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
