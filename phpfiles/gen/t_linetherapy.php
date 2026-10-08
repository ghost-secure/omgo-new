<?php

$dyn = new \UC\DynBlock('t_linetherapy', 't_linetherapy/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
