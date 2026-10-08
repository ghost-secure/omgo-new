<?php

$dyn = new \UC\DynBlock('t_feedback', 't_feedback/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
