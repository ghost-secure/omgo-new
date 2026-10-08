<?php

$dyn = new \UC\DynBlock('t_geo_city', 't_geo_city/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
