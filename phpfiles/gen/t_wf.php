<?php

$dyn = new \UC\DynBlock('t_wf', 't_wf/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
