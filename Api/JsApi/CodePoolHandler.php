<?php

declare(strict_types=1);

namespace Api\JsApi;

use Core\Api\AbstractApiHandler;
use Core\Container;
use Core\Request;
use Core\Response;
use Services\CodeService;
use UC\FlowSignature;

/**
 * POST /jsapi/admin/code-pool — пул проверочных кодов (легаси WF_CODEPULL).
 */
class CodePoolHandler extends AbstractApiHandler
{
    public function getEndpoint(): string
    {
        return 'admin.code-pool';
    }

    public function getAllowedMethods(): array
    {
        return ['POST', 'OPTIONS'];
    }

    protected function execute(Request $request, array $pathParts): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            return new Response('', 204);
        }

        $container = Container::getInstance();
        if ($container->has('auth')) {
            $auth = $container->get('auth');
            if ($auth && method_exists($auth, 'isAuthenticated') && !$auth->isAuthenticated()) {
                return $this->jsonError('Требуется авторизация', 403);
            }
        }

        $flowId = trim((string) $request->input('flow_id', ''));
        $flowAction = trim((string) $request->input('flow_action', 'default'));
        if ($flowAction === '') {
            $flowAction = 'default';
        }
        $flowDataId = trim((string) $request->input('flow_dataid', ''));
        $flowTable = trim((string) $request->input('flow_table', ''));
        $flowHash = trim((string) $request->input('flow_hash', ''));

        if ($flowHash === '' || !FlowSignature::verify($flowId, $flowAction, $flowDataId, $flowTable, $flowHash)) {
            return $this->jsonError('Неверная подпись параметров', 403);
        }
        if ($flowId !== 'codes' || $flowAction !== 'WF_CODEPULL') {
            return $this->jsonError('Неверный сценарий', 400);
        }
        if ($flowTable !== CodeService::TBL || $flowDataId !== '0') {
            return $this->jsonError('Неверный контекст сценария', 400);
        }

        $typeId = (int) $request->input('type', 0);
        $count = (int) $request->input('number', 0);

        try {
            $created = $container->get(CodeService::class)->generatePool($typeId, $count);

            return new Response(
                json_encode([
                    'success' => true,
                    'status' => 'ok',
                    'message' => 'Создано кодов: ' . $created,
                    'created' => $created,
                ], JSON_UNESCAPED_UNICODE),
                200,
                ['Content-Type' => 'application/json; charset=UTF-8']
            );
        } catch (\RuntimeException $e) {
            return $this->jsonError($e->getMessage(), 400);
        } catch (\Throwable $e) {
            error_log('CodePoolHandler: ' . $e->getMessage());

            return $this->jsonError(
                defined('APP_DEBUG') && APP_DEBUG ? $e->getMessage() : 'Ошибка сохранения',
                500
            );
        }
    }

    private function jsonError(string $message, int $code): Response
    {
        return new Response(
            json_encode([
                'success' => false,
                'status' => 'error',
                'error' => $message,
            ], JSON_UNESCAPED_UNICODE),
            $code,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }
}
