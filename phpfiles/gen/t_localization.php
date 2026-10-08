<?php

$dyn = new \UC\DynBlock('t_localization', 't_localization/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
