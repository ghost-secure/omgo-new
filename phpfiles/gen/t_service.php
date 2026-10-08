<?php

$dyn = new \UC\DynBlock('t_service', 't_service/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
