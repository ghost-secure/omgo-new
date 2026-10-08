<?php

$dyn = new \UC\DynBlock('t_confirmprogress', 't_confirmprogress/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
