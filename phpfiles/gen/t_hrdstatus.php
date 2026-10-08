<?php

$dyn = new \UC\DynBlock('t_hrdstatus', 't_hrdstatus/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
