<?php

$dyn = new \UC\DynBlock('t_sections', 't_sections/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
