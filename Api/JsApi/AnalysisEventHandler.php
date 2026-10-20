<?php

declare(strict_types=1);

namespace Api\JsApi;

use Core\Api\AbstractApiHandler;
use Core\Container;
use Core\Request;
use Core\Response;
use Services\AnalysisWorkflowService;
use UC\FlowSignature;

/**
 * POST /jsapi/admin/analysis-event — событие воркфлоу заявки или чекбокс заключения/экспресса.
 */
class AnalysisEventHandler extends AbstractApiHandler
{
    public function getEndpoint(): string
    {
        return 'admin.analysis-event';
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
        $flowAction = trim((string) $request->input('flow_action', ''));
        $flowDataId = trim((string) $request->input('flow_dataid', ''));
        $flowTable = trim((string) $request->input('flow_table', ''));
        $flowHash = trim((string) $request->input('flow_hash', ''));

        if ($flowHash === '' || !FlowSignature::verify($flowId, $flowAction, $flowDataId, $flowTable, $flowHash)) {
            return $this->jsonError('Неверная подпись параметров', 403);
        }
        if ($flowId !== AnalysisWorkflowService::FLOW_ID || $flowTable !== AnalysisWorkflowService::TABLE) {
            return $this->jsonError('Неверный сценарий', 400);
        }

        $analysisId = (int) $flowDataId;
        $auth = $container->has('auth') ? $container->get('auth') : null;
        $user = $auth && method_exists($auth, 'getUser') ? $auth->getUser() : null;
        $user = is_array($user) ? $user : null;

        try {
            $workflow = $container->get(AnalysisWorkflowService::class);
            if ($flowAction === 'stat_conclusion' || $flowAction === 'stat_express') {
                $message = $workflow->setFlag($analysisId, $flowAction, (int) $request->input('value', 0), $user);
            } else {
                $message = $workflow->apply($analysisId, (int) $flowAction, [
                    'comment' => (string) $request->input('comment', ''),
                    'doc' => (string) $request->input('doc', ''),
                    'lab' => (int) $request->input('lab', 0),
                    'status' => (int) $request->input('status', 0),
                    'pacient' => [
                        'surname' => (string) $request->input('pacient_surname', ''),
                        'name' => (string) $request->input('pacient_name', ''),
                        'patron' => (string) $request->input('pacient_patron', ''),
                        'birthday' => (string) $request->input('pacient_birthday', ''),
                        'male' => (int) $request->input('pacient_male', 0),
                        'region' => (int) $request->input('pacient_region', 0),
                    ],
                ], $user);
            }

            return new Response(
                json_encode([
                    'success' => true,
                    'status' => 'ok',
                    'message' => $message,
                ], JSON_UNESCAPED_UNICODE),
                200,
                ['Content-Type' => 'application/json; charset=UTF-8']
            );
        } catch (\RuntimeException $e) {
            return $this->jsonError($e->getMessage(), 400);
        } catch (\Throwable $e) {
            error_log('AnalysisEventHandler: ' . $e->getMessage());

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
                'error' => $message,
            ], JSON_UNESCAPED_UNICODE),
            $code,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }
}
