<?php

$dyn = new \UC\DynBlock('t_notify_type', 't_notify_type/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
