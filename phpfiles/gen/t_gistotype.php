<?php

$dyn = new \UC\DynBlock('t_gistotype', 't_gistotype/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
