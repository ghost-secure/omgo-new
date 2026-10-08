<?php

$dyn = new \UC\DynBlock('t_material', 't_material/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
