<?php

$dyn = new \UC\DynBlock('t_feedback_type', 't_feedback_type/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
