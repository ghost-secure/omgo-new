<?php

/**
 * PDF заключения. /pdf/{guid}/ — стратегия печати, не страница каталога.
 * Ответ целиком из этого файла, оболочки кабинета нет.
 */

use Core\Container;
use Services\Pdf\ConclusionHttp;
use Services\Pdf\ConclusionPrint;

$guid = trim((string) ($attributes['analysis_guid'] ?? ''));
$auth = function_exists('auth') ? auth() : null;
$user = $auth && method_exists($auth, 'getUser') ? $auth->getUser() : null;
$user = is_array($user) ? $user : null;

/** @var ConclusionPrint $print */
$print = Container::getInstance()->get(ConclusionPrint::class);
$result = $print->respond($guid, $request, $user);
if (!$result instanceof ConclusionHttp) {
    $result = ConclusionHttp::text('Заявка не найдена.');
}

http_response_code($result->status);
header('Content-Type: ' . $result->contentType);
echo $result->body;
