<?php

$dyn = new \UC\DynBlock('t_lab_metodic', 't_lab_metodic/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
