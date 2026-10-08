<?php

$dyn = new \UC\DynBlock('t_lab', 't_lab/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
