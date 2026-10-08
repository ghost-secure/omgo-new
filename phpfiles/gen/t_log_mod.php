<?php

$dyn = new \UC\DynBlock('t_log_mod', 't_log_mod/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
