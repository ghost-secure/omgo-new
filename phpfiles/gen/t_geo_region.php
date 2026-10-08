<?php

$dyn = new \UC\DynBlock('t_geo_region', 't_geo_region/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
