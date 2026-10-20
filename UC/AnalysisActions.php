<?php

declare(strict_types=1);

namespace UC;

use Core\Container;
use Services\AnalysisWorkflowService;

/**
 * Колонка «Действия» заявки.
 * Легаси `{{ operation }}` и `{{ workflow || dropdown|buttons }}`.
 * В таблице воркфлоу — выпадающий список, в паспорте те же пункты отдельными кнопками.
 */
final class AnalysisActions
{
    /**
     * @param array<string, mixed> $record
     * @param 'dropdown'|'buttons' $workflow
     * @param 'all'|'workflow'|'operations' $part
     */
    public static function render(array $record, string $workflow = 'dropdown', string $part = 'all', bool $passport = false): string
    {
        $id = (int) ($record['cat'] ?? 0);
        if ($id <= 0) {
            return '';
        }

        $user = self::user();
        $service = Container::getInstance()->get(AnalysisWorkflowService::class);
        $links = $service->operationLinks($record, $user);
        $flags = $service->operationFlags($record, $user);
        $items = $service->menuItems($record, $user);

        $operations = self::operationsHtml($id, $links, $flags);
        if ($passport && $part !== 'workflow') {
            $operations .= self::passportHtml($id, $service->passportOperations($record, $user));
        }
        $menu = self::workflowHtml($id, $items, $workflow);
        if ($part === 'workflow') {
            return $menu;
        }
        if ($part === 'operations') {
            return $operations !== '' ? $operations : '<span class="text-muted">—</span>';
        }
        if ($operations === '' && $menu === '') {
            return '<span class="text-muted">—</span>';
        }

        return '<div class="d-flex flex-column align-items-start gap-sm">'
            . $operations
            . $menu
            . '</div>';
    }

    /**
     * @param list<array{href:string,title:string,label:string,blank:bool}> $links
     * @param array{conclusion:?int,express:?int} $flags
     */
    private static function operationsHtml(int $id, array $links, array $flags): string
    {
        $parts = [];
        foreach ($links as $link) {
            $href = htmlspecialchars($link['href'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $title = htmlspecialchars($link['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $label = htmlspecialchars($link['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $blank = $link['blank'] ? ' target="_blank" rel="noopener"' : '';
            $parts[] = '<a href="' . $href . '" title="' . $title . '"' . $blank . '>' . $label . '</a>';
        }
        if ($flags['conclusion'] !== null) {
            $parts[] = self::flagHtml($id, 'stat_conclusion', 'Статус активного заключения', $flags['conclusion']);
        }
        if ($flags['express'] !== null) {
            $parts[] = self::flagHtml($id, 'stat_express', 'Экспресс', $flags['express']);
        }
        if ($parts === []) {
            return '';
        }

        return '<div class="d-flex flex-column align-items-start gap-xs">' . implode('', $parts) . '</div>';
    }

    /**
     * @param list<array{id:int,label:string,title:string}> $items
     */
    private static function passportHtml(int $id, array $items): string
    {
        if ($items === []) {
            return '';
        }
        $parts = [];
        foreach ($items as $item) {
            $parts[] = Flow::button([
                'id' => AnalysisWorkflowService::FLOW_ID,
                'action' => (string) $item['id'],
                'dataid' => (string) $id,
                'table' => AnalysisWorkflowService::TABLE,
                'title' => $item['title'],
                'label' => $item['label'],
            ]);
        }

        return '<div class="d-flex flex-column align-items-start gap-xs">' . implode('', $parts) . '</div>';
    }

    private static function flagHtml(int $id, string $flag, string $label, int $checked): string
    {
        $hash = FlowSignature::sign(AnalysisWorkflowService::FLOW_ID, $flag, (string) $id, AnalysisWorkflowService::TABLE);
        $safeFlag = htmlspecialchars($flag, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeLabel = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeHash = htmlspecialchars($hash, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $inputId = 'analysis-stat-' . $safeFlag . '-' . $id;

        return '<div class="analysis-stat">'
            . '<input type="checkbox" id="' . $inputId . '" class="custom-control-input" data-analysis-stat'
            . ' data-flow-id="analysis"'
            . ' data-flow-action="' . $safeFlag . '"'
            . ' data-flow-dataid="' . $id . '"'
            . ' data-flow-table="t_analysis"'
            . ' data-flow-hash="' . $safeHash . '"'
            . ($checked === 1 ? ' checked' : '')
            . '>'
            . '<label for="' . $inputId . '" class="custom-control-label checkbox-label">' . $safeLabel . '</label>'
            . '</div>';
    }

    /**
     * @param list<array{id:int,title:string,instant:bool,status:?int,danger:bool}> $items
     */
    private static function workflowHtml(int $id, array $items, string $workflow): string
    {
        if ($items === []) {
            return $workflow === 'buttons' ? '<span class="text-muted">Нет доступных действий</span>' : '';
        }

        $buttons = [];
        foreach ($items as $item) {
            $buttons[] = $item['instant']
                ? self::instantButton($id, $item, $workflow)
                : self::modalButton($id, $item, $workflow);
        }
        $inner = implode('', $buttons);

        if ($workflow === 'buttons') {
            return '<div class="d-flex flex-column align-items-start gap-xs">' . $inner . '</div>';
        }

        return '<div data-plugin="dropdown">'
            . '<a href="#" class="analysis-ops" data-dropdown-toggle>операции'
            . '<span class="nav-item__icon analysis-ops__arrow" data-icon="Arrows/arrow-down-s-line"></span>'
            . '</a>'
            . '<div class="dropdown-menu dropdown-menu--fit" data-dropdown-menu hidden>'
            . $inner
            . '</div>'
            . '</div>';
    }

    /**
     * @param array{id:int,title:string,instant:bool,status:?int,danger:bool} $item
     */
    private static function modalButton(int $id, array $item, string $workflow): string
    {
        return Flow::button([
            'id' => AnalysisWorkflowService::FLOW_ID,
            'action' => (string) $item['id'],
            'dataid' => (string) $id,
            'table' => AnalysisWorkflowService::TABLE,
            'title' => $item['title'],
            'label' => $item['title'],
            'variant' => $workflow === 'dropdown' ? 'menu' : '',
        ]);
    }

    /**
     * @param array{id:int,title:string,instant:bool,status:?int,danger:bool} $item
     */
    private static function instantButton(int $id, array $item, string $workflow): string
    {
        $action = (string) $item['id'];
        $hash = FlowSignature::sign(AnalysisWorkflowService::FLOW_ID, $action, (string) $id, AnalysisWorkflowService::TABLE);
        $title = htmlspecialchars($item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $hashAttr = htmlspecialchars($hash, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $statusAttr = $item['status'] !== null
            ? ' data-flow-status="' . (int) $item['status'] . '"'
            : '';
        $menu = $workflow === 'dropdown';
        $class = $menu
            ? 'btn btn-sm btn-outline-secondary'
            : ($item['danger'] ? 'btn btn-sm btn-danger' : 'btn btn-sm btn-primary');
        $style = $menu ? ' style="text-align:left;white-space:nowrap;"' : '';
        $wrap = ' style="display:inline-block;"';

        return '<span' . $wrap . '>'
            . '<button type="button" class="' . $class . '" data-analysis-instant'
            . ' data-flow-id="analysis"'
            . ' data-flow-action="' . htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
            . ' data-flow-dataid="' . $id . '"'
            . ' data-flow-table="t_analysis"'
            . ' data-flow-hash="' . $hashAttr . '"'
            . $statusAttr
            . $style
            . '>' . $title . '</button></span>';
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function user(): ?array
    {
        $auth = function_exists('auth') ? auth() : null;
        $user = $auth && method_exists($auth, 'getUser') ? $auth->getUser() : null;

        return is_array($user) ? $user : null;
    }
}
