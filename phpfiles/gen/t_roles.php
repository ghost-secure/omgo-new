<?php

$dyn = new \UC\DynBlock('t_roles', 't_roles/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
