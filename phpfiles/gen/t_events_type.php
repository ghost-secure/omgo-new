<?php

$dyn = new \UC\DynBlock('t_events_type', 't_events_type/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
