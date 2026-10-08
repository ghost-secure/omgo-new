<?php

$dyn = new \UC\DynBlock('t_pacientstatus', 't_pacientstatus/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
