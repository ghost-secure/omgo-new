<?php

/**
 * Простой интерфейс (легаси join/addqueryselect пустые).
 * Было: Dyndata blockid=42 + Instant. Стало: нативный base по tblfields.
 */
$dyn = new \UC\DynBlock('t_chem', 't_chem/base', 20, 1, [
    'short' => true,
]);
echo $dyn->render();
