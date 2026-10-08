<?php

$dyn = new \UC\DynBlock('t_notify', 't_notify/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
