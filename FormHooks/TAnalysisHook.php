<?php

declare(strict_types=1);

namespace Project\FormHooks;

use Core\Container;
use Helpers\Crypt;
use Services\AnalysisService;
use Services\CodeService;

/**
 * Легаси `project/logic/aftersave/t_analysis.php`.
 * op[step]=code — ввод проверочного кода.
 * op[step]=card — карточка черновика (пациент, диагноз, материалы). Форма лаборатории сюда ещё не входит.
 */
class TAnalysisHook
{
    /**
     * @param array<string, mixed> $dataToSave
     * @param array<string, mixed> $context
     */
    public function beforeSave(string $table, array &$dataToSave, array $context = []): void
    {
        if ($this->isCardStep($context)) {
            $this->prepareCard($dataToSave, $context);
            return;
        }
        if (!$this->isCodeStep($context)) {
            return;
        }
        if (!empty($context['id'])) {
            throw new \RuntimeException('Код вводится только для новой заявки');
        }

        $raw = is_array($context['rawFields'] ?? null) ? $context['rawFields'] : [];
        $codes = Container::getInstance()->get(CodeService::class);
        $code = $codes->findIdleCode((string) ($raw['_code'] ?? ''));
        if ($code === null) {
            throw new \RuntimeException('Код неверный либо уже был задействован [#0000]');
        }

        $auth = function_exists('auth') ? auth() : null;
        $user = $auth ? $auth->getUser() : null;
        $profile = is_array($user['profile'] ?? null) ? $user['profile'] : [];
        $profileLab = (int) ($profile['view_lab'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);
        if ($profileLab <= 0 || $userId <= 0) {
            throw new \RuntimeException('Проверьте прикрепление к лаборатории!');
        }

        $stored = (string) ($code['varc_code'] ?? '');
        $typeChecked = trim((string) ($raw['typechecked'] ?? ''));
        $typeId = (int) ($code['view_analiz'] ?? 0);
        if ($typeChecked === '' || $typeChecked !== $stored) {
            $analysis = Container::getInstance()->get(AnalysisService::class);
            throw new PacientCodeConfirmException($typeId, $analysis->typeName($typeId), $stored);
        }

        $analysis = Container::getInstance()->get(AnalysisService::class);
        $labId = $analysis->routeLabId($userId, $typeId) ?? $profileLab;
        $guid = Crypt::generateGuid();

        $dataToSave['author_add'] = $userId;
        $dataToSave['view_code'] = (int) $code['cat'];
        $dataToSave['view_test'] = $typeId;
        $dataToSave['user_doctor'] = $userId;
        $dataToSave['view_lab'] = $labId;
        $dataToSave['varc_guid'] = $guid;
        $dataToSave['view_status'] = 0;
        $dataToSave['view_pacient'] = 0;
        // Эти cnt_* в t_analysis NOT NULL без DEFAULT. При STRICT_TRANS_TABLES пустой INSERT падает.
        foreach ([
            'cnt_vardepth', 'cnt_genom', 'cnt_snpdepth', 'cnt_brcadepth', 'cnt_readdepth',
            'cnt_analysislinked', 'cnt_late', 'cnt_agediagnoz', 'cnt_noexpression',
        ] as $column) {
            $dataToSave[$column] = 0;
        }
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed>|null $formApiResponse
     */
    public function afterSave(string $table, $record, array $context = [], ?array &$formApiResponse = null): void
    {
        if ($this->isCardStep($context)) {
            $this->finishCard($record, $context, $formApiResponse);
            return;
        }
        if (!$this->isCodeStep($context)) {
            return;
        }

        $attrs = $this->recordAttributes($record);
        $phase = (string) ($context['__phase'] ?? '');

        if ($phase === 'record_saved') {
            $this->activateOrDrop($attrs);
            return;
        }

        if ($phase === 'form_api_success' && is_array($formApiResponse)) {
            $guid = trim((string) ($attrs['varc_guid'] ?? ''));
            if ($guid === '') {
                throw new \RuntimeException('Ошибка сохранения пациента [#0001]');
            }
            $formApiResponse['redirect'] = AnalysisService::pacientFormUrl($guid);
            $formApiResponse['message'] = 'Код использован! Заполните карточку пациента.';
        }
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function activateOrDrop(array $attrs): void
    {
        $analysisId = (int) ($attrs['cat'] ?? 0);
        $codeId = (int) ($attrs['view_code'] ?? 0);
        $userId = (int) ($attrs['author_add'] ?? 0);
        $codes = Container::getInstance()->get(CodeService::class);

        try {
            $codes->activate($codeId, $userId);
        } catch (\Throwable $e) {
            if ($analysisId > 0) {
                $db = Container::getInstance()->get(\Core\Database\QueryBuilder::class);
                $db->reset()->execute(
                    'DELETE FROM ' . AnalysisService::TBL_ANALYSIS . ' WHERE cat = ?',
                    [$analysisId]
                );
            }
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $dataToSave
     * @param array<string, mixed> $context
     */
    private function prepareCard(array &$dataToSave, array $context): void
    {
        $id = (int) ($context['id'] ?? 0);
        if ($id <= 0) {
            throw new \RuntimeException('Карточка сохраняется только у уже созданной заявки');
        }

        $auth = function_exists('auth') ? auth() : null;
        $user = $auth ? $auth->getUser() : null;
        if (!is_array($user)) {
            throw new \RuntimeException('Карточка отправлена, редактирование невозможно.');
        }

        $raw = is_array($context['rawFields'] ?? null) ? $context['rawFields'] : [];
        Container::getInstance()->get(AnalysisService::class)->prepareDraftSave($dataToSave, $raw, $id, $user);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed>|null $formApiResponse
     */
    private function finishCard($record, array $context, ?array &$formApiResponse): void
    {
        $attrs = $this->recordAttributes($record);
        $phase = (string) ($context['__phase'] ?? '');
        $analysis = Container::getInstance()->get(AnalysisService::class);
        $raw = is_array($context['rawFields'] ?? null) ? $context['rawFields'] : [];

        if ($phase === 'record_saved') {
            $auth = function_exists('auth') ? auth() : null;
            $user = $auth ? $auth->getUser() : null;
            $userId = is_array($user) ? (int) ($user['id'] ?? 0) : 0;
            $analysis->storeDraftExtras((int) ($attrs['cat'] ?? 0), $raw, $userId);
            return;
        }

        if ($phase === 'form_api_success' && is_array($formApiResponse)) {
            $guid = trim((string) ($attrs['varc_guid'] ?? ''));
            if ($guid === '') {
                throw new \RuntimeException('Ошибка сохранения пациента [#0008]');
            }
            $formApiResponse['redirect'] = AnalysisService::passportUrl($guid);
            $formApiResponse['message'] = 'Анкета перешла в статус черновик.';
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function isCardStep(array $context): bool
    {
        return $this->operationStep($context) === 'card';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function isCodeStep(array $context): bool
    {
        return $this->operationStep($context) === 'code';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function operationStep(array $context): string
    {
        $op = $context['operationFields'] ?? null;
        if (!is_array($op)) {
            $formData = $context['formData'] ?? null;
            $op = is_array($formData) && is_array($formData['op'] ?? null) ? $formData['op'] : null;
        }

        return is_array($op) ? (string) ($op['step'] ?? '') : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function recordAttributes($record): array
    {
        if (is_object($record) && method_exists($record, 'getAttributes')) {
            $attrs = $record->getAttributes();

            return is_array($attrs) ? $attrs : [];
        }

        return is_array($record) ? $record : [];
    }
}
