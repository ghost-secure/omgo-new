<?php

$dyn = new \UC\DynBlock('t_analiz_status', 't_analiz_status/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
