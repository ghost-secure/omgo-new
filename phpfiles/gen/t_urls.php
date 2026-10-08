<?php

$dyn = new \UC\DynBlock('t_urls', 't_urls/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
