<?php

$dyn = new \UC\DynBlock('t_progressing', 't_progressing/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
