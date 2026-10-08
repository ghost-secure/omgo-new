<?php

$dyn = new \UC\DynBlock('t_illness', 't_illness/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
