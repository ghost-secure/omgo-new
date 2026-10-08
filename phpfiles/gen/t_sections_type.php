<?php

$dyn = new \UC\DynBlock('t_sections_type', 't_sections_type/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
