<?php

$dyn = new \UC\DynBlock('t_materialaction', 't_materialaction/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
