<?php

$dyn = new \UC\DynBlock('t_events', 't_events/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
