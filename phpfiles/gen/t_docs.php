<?php

$dyn = new \UC\DynBlock('t_docs', 't_docs/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
