<?php

declare(strict_types=1);

namespace Services;

use Core\Auth\UserAccount;
use Core\Database\QueryBuilder;
use Core\Reference\TableMetadataManager;
use Helpers\Crypt;

/**
 * Воркфлоу заявки: какие операции видны в колонке «Действия» и что они меняют.
 * Легаси: `Analysis::getAvailEvents`, `Replace::AnalysisWorkflow`, `Replace::AnalysisOperatation`,
 * `logic/aftersave/t_events.php`.
 *
 * Роль меню — первая в `roles_map` (легаси `$roles->role`), не «последняя роль» фильтра списка.
 * Письма уходят после сохранения. Сбой почты статус не откатывает.
 */
class AnalysisWorkflowService
{
    public const FLOW_ID = 'analysis';
    public const TABLE = 't_analysis';

    public const EVENT_INIT = 1;
    public const EVENT_SELFDELIVERY = 2;
    public const EVENT_MOVING = 3;
    public const EVENT_RECEIVED = 4;
    public const EVENT_ACCEPTABLE = 5;
    public const EVENT_REJECTED_BADMATERIALNGS = 6;
    public const EVENT_REJECTED_BADDELIVERY = 7;
    public const EVENT_RETURN_CALLED = 9;
    public const EVENT_RETURN_MOVING = 10;
    public const EVENT_RETURN_RECEIVED = 11;
    public const EVENT_SPENT = 12;
    public const EVENT_REJECTED_BADMATERIAL = 13;
    public const EVENT_CHANGESTATUS = 14;
    public const EVENT_RETURN_SELF = 15;
    public const EVENT_CHANGELAB = 16;
    public const EVENT_MLPA = 20;
    public const EVENT_READY = 21;
    public const EVENT_PTEN = 23;
    public const EVENT_NGS = 24;
    public const EVENT_CHANGEPACIENT = 25;

    /** Легаси Instant: без модалки комментария, сразу запись события. */
    public const INSTANT = [
        self::EVENT_RECEIVED,
        self::EVENT_ACCEPTABLE,
        self::EVENT_SPENT,
        self::EVENT_RETURN_CALLED,
        self::EVENT_RETURN_SELF,
        self::EVENT_READY,
    ];

    private const NOTIFY_INIT = 7;
    private const NOTIFY_MOVING = 1;
    private const NOTIFY_BADMATERIAL = 2;
    private const NOTIFY_BADDELIVERY = 3;
    private const NOTIFY_DONE = 4;
    private const NOTIFY_RETURN_CALLED = 5;
    private const NOTIFY_SPENT = 6;
    private const NOTIFY_BADMATERIALNGS = 9;
    private const NOTIFY_PTEN = 12;
    private const NOTIFY_CHANGE = 13;

    /** @var array<string, list<array<string, mixed>>> */
    private array $rulesCache = [];

    public function __construct(
        private QueryBuilder $db,
        private AnalysisService $analysis,
    ) {
    }

    /**
     * Пункты меню воркфлоу для строки грида.
     *
     * @param array<string, mixed> $record
     * @param array<string, mixed>|null $user
     * @return list<array{id:int,title:string,instant:bool,status:?int,danger:bool}>
     */
    public function menuItems(array $record, ?array $user): array
    {
        $id = (int) ($record['cat'] ?? 0);
        if ($id <= 0 || $user === null) {
            return [];
        }

        $manual = $this->manualReturn($id, $user);
        if ($manual !== null) {
            return [[
                'id' => self::EVENT_CHANGESTATUS,
                'title' => 'Завершить редактирование',
                'instant' => true,
                'status' => (int) $manual['cnt_oldvalue'],
                'danger' => true,
            ]];
        }

        $items = [];
        foreach ($this->visibleRules($this->rowContext($record), $user) as $rule) {
            $eventId = (int) $rule['view_operation'];
            $items[] = [
                'id' => $eventId,
                'title' => (string) $rule['operation'],
                'instant' => in_array($eventId, self::INSTANT, true),
                'status' => null,
                'danger' => false,
            ];
        }

        return $items;
    }

    /**
     * Ссылки колонки «Действия» (легаси `{{ operation }}` на списке, без иконок паспорта).
     *
     * @param array<string, mixed> $record
     * @param array<string, mixed>|null $user
     * @return list<array{href:string,title:string,label:string,blank:bool}>
     */
    public function operationLinks(array $record, ?array $user): array
    {
        if ($user === null) {
            return [];
        }

        $status = (int) ($record['view_status'] ?? 0);
        $guid = trim((string) ($record['varc_guid'] ?? ''));
        $links = [];
        $isLab = $this->hasRole($user, RoleService::ROLE_LAB);
        $isDoctor = $this->hasRole($user, RoleService::ROLE_DOCTOR);
        $isAdmin = $this->hasRole($user, RoleService::ROLE_ADMIN);

        if ($guid !== '' && $isLab && in_array($status, [
            AnalysisService::STATUS_ACCEPTABLE,
            AnalysisService::STATUS_REJECTED_BADMATERIALNGS,
            AnalysisService::STATUS_WORK,
        ], true)) {
            $links[] = [
                'href' => '/manager/analysis/' . rawurlencode($guid) . '/',
                'title' => 'Ввод результатов анализа',
                'label' => 'анализ',
                'blank' => false,
            ];
        }

        if ($guid !== '' && $status === AnalysisService::STATUS_DRAFT && ($isLab || $isDoctor || $isAdmin)) {
            $links[] = [
                'href' => AnalysisService::pacientFormUrl($guid),
                'title' => 'Редактировать профиль пациента',
                'label' => 'пациент',
                'blank' => false,
            ];
        }

        $pdfStatuses = [
            AnalysisService::STATUS_DONE,
            AnalysisService::STATUS_RETURN_CALLED,
            AnalysisService::STATUS_RETURN_MOVING,
            AnalysisService::STATUS_RETURN_RECEIVED,
            AnalysisService::STATUS_SPENT,
            AnalysisService::STATUS_PTEN,
            AnalysisService::STATUS_MLPA,
        ];
        $pdf = false;
        if ($isLab && in_array($status, array_merge([AnalysisService::STATUS_WORK], $pdfStatuses), true)) {
            $pdf = true;
        }
        if (($isDoctor || $isAdmin) && (int) ($record['stat_conclusion'] ?? 0) === 1 && in_array($status, $pdfStatuses, true)) {
            $pdf = true;
        }
        if ($pdf && $guid !== '') {
            $links[] = [
                'href' => '/pdf/' . rawurlencode($guid) . '/',
                'title' => 'Заключение',
                'label' => 'заключение',
                'blank' => true,
            ];
        }

        $archive = trim((string) ($record['doc_archive'] ?? ''));
        if ($archive !== '' && $status === AnalysisService::STATUS_ARCHIVE) {
            $links[] = [
                'href' => $archive,
                'title' => 'Заключение архив',
                'label' => 'заключение',
                'blank' => true,
            ];
        }

        return $links;
    }

    /**
     * Три операции только паспорта. В списке их нет.
     * Статус: суперадмин, администратор или лаборатория, пока нет ожидания отката ручного статуса.
     * Лаборатория и ФИО: суперадмин или администратор.
     *
     * @param array<string, mixed> $record
     * @param array<string, mixed>|null $user
     * @return list<array{id:int,label:string,title:string}>
     */
    public function passportOperations(array $record, ?array $user): array
    {
        $id = (int) ($record['cat'] ?? 0);
        if ($id <= 0 || $user === null) {
            return [];
        }

        $super = (int) ($user['role'] ?? 0) === UserAccount::SUPERUSER;
        $admin = $this->hasRole($user, RoleService::ROLE_ADMIN);
        $items = [];
        if ($super || $admin || ($this->hasRole($user, RoleService::ROLE_LAB) && $this->manualReturn($id, $user) === null)) {
            $items[] = [
                'id' => self::EVENT_CHANGESTATUS,
                'label' => 'статус пациента',
                'title' => 'Принудительно изменить статус пациента',
            ];
        }
        if ($super || $admin) {
            $items[] = [
                'id' => self::EVENT_CHANGELAB,
                'label' => 'изменить лаб-ю',
                'title' => 'Принудительно изменить лабораторию',
            ];
            $items[] = [
                'id' => self::EVENT_CHANGEPACIENT,
                'label' => 'изменить ФИО или дату рождения',
                'title' => 'Изменить ФИО или дату рождения',
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed>|null $user
     * @return list<array{id:int,label:string,checked:bool}>
     */
    public function statusChoices(int $analysisId, ?array $user): array
    {
        if ($user === null || !$this->passportAllows($analysisId, self::EVENT_CHANGESTATUS, $user)) {
            return [];
        }

        $row = $this->analysisRow($analysisId);
        $current = (int) ($row['view_status'] ?? 0);
        if ($this->fullStatusList($analysisId, $user, $row)) {
            $rows = $this->db->reset()
                ->select(['cat', 'varc_name'])
                ->from('t_analiz_status')
                ->order(['cnt_ord' => 'ASC'])
                ->get();
            $options = [];
            foreach (is_array($rows) ? $rows : [] as $item) {
                $item = (array) $item;
                $id = (int) ($item['cat'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $options[$id] = trim((string) ($item['varc_name'] ?? ''));
            }

            return $this->choiceList($options, $current);
        }

        $options = [
            AnalysisService::STATUS_DRAFT => 'Изменить данные пациента',
        ];
        if ($this->hasEvent($analysisId, [self::EVENT_READY])) {
            $options[AnalysisService::STATUS_WORK] = 'Внести изменения в заключение (врач получит уведомление о внесении изменения)';
        }
        $row['_mlpa'] = $this->childId($analysisId);
        $final = $this->hasEvent($analysisId, [
            self::EVENT_SPENT,
            self::EVENT_RETURN_CALLED,
            self::EVENT_RETURN_SELF,
            self::EVENT_MLPA,
            self::EVENT_PTEN,
        ]);
        if ($final && $this->visibleRules($row, $user) === []) {
            $options[AnalysisService::STATUS_ARCHIVE] = 'Перевести в архив';
        }

        return $this->choiceList($options, 0);
    }

    /**
     * @param array<string, mixed>|null $user
     * @return list<array{id:int,name:string,checked:bool}>
     */
    public function labChoices(int $analysisId, ?array $user): array
    {
        if ($user === null || !$this->passportAllows($analysisId, self::EVENT_CHANGELAB, $user)) {
            return [];
        }

        $current = (int) ($this->analysisRow($analysisId)['view_lab'] ?? 0);
        $rows = $this->db->reset()
            ->select(['cat', 'varc_name', 'varc_fullname'])
            ->from('t_lab')
            ->order(['cat' => 'ASC'])
            ->get();
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $item) {
            $item = (array) $item;
            $id = (int) ($item['cat'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $name = trim((string) ($item['varc_name'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($item['varc_fullname'] ?? ''));
            }
            $out[] = [
                'id' => $id,
                'name' => $name !== '' ? $name : ('ID ' . $id),
                'checked' => $id === $current,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed>|null $user
     * @return array{surname:string,name:string,patron:string,birthday:string,male:int,region:int}|null
     */
    public function pacientEditor(int $analysisId, ?array $user): ?array
    {
        if ($user === null || !$this->passportAllows($analysisId, self::EVENT_CHANGEPACIENT, $user)) {
            return null;
        }

        $stmt = $this->db->execute(
            'SELECT p.varc_surname, p.varc_name, p.varc_patron, p.datetext_bd, p.view_male, p.view_region
             FROM t_analysis a
             LEFT JOIN t_pacient p ON p.cat = a.view_pacient
             WHERE a.cat = ?
             LIMIT 1',
            [$analysisId]
        );
        $row = $stmt instanceof \PDOStatement ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
        $row = is_array($row) ? $row : [];
        $birthday = trim((string) ($row['datetext_bd'] ?? ''));
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $birthday, $match)) {
            $birthday = $match[1];
        } else {
            $birthday = '';
        }

        return [
            'surname' => trim((string) ($row['varc_surname'] ?? '')),
            'name' => trim((string) ($row['varc_name'] ?? '')),
            'patron' => trim((string) ($row['varc_patron'] ?? '')),
            'birthday' => $birthday,
            'male' => (int) ($row['view_male'] ?? 0),
            'region' => (int) ($row['view_region'] ?? 0),
        ];
    }

    /**
     * Чекбоксы суперадмина и администратора. null — не показывать.
     *
     * @param array<string, mixed> $record
     * @param array<string, mixed>|null $user
     * @return array{conclusion:?int,express:?int}
     */
    public function operationFlags(array $record, ?array $user): array
    {
        $flags = ['conclusion' => null, 'express' => null];
        if ($user === null || (int) ($record['cat'] ?? 0) <= 0) {
            return $flags;
        }
        if ((int) ($user['role'] ?? 0) === UserAccount::SUPERUSER) {
            $flags['conclusion'] = (int) ($record['stat_conclusion'] ?? 0) === 1 ? 1 : 0;
        }
        if ($this->hasRole($user, RoleService::ROLE_ADMIN)) {
            $flags['express'] = (int) ($record['stat_express'] ?? 0) === 1 ? 1 : 0;
        }

        return $flags;
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public function setFlag(int $analysisId, string $flag, int $value, ?array $user): string
    {
        if ($analysisId <= 0 || $user === null) {
            throw new \RuntimeException('Заявка не найдена');
        }
        $value = $value === 1 ? 1 : 0;
        $column = match ($flag) {
            'stat_conclusion' => (int) ($user['role'] ?? 0) === UserAccount::SUPERUSER ? 'stat_conclusion' : '',
            'stat_express' => $this->hasRole($user, RoleService::ROLE_ADMIN) ? 'stat_express' : '',
            default => '',
        };
        if ($column === '') {
            throw new \RuntimeException('Недостаточно прав');
        }
        $this->analysisRow($analysisId);
        $this->updateAnalysis($analysisId, [$column => $value]);

        return 'Сохранено';
    }

    /**
     * Записать событие и сменить статус. Событие должно быть в меню этой роли.
     *
     * @param array{comment?:string,doc?:string,lab?:int,status?:int,pacient?:array<string,mixed>} $input
     * @param array<string, mixed>|null $user
     */
    public function apply(int $analysisId, int $eventId, array $input, ?array $user): string
    {
        if ($analysisId <= 0 || $user === null) {
            throw new \RuntimeException('Заявка не найдена');
        }
        $uid = (int) ($user['id'] ?? 0);
        if ($uid <= 0) {
            throw new \RuntimeException('Требуется авторизация');
        }

        $comment = trim((string) ($input['comment'] ?? ''));
        $doc = trim((string) ($input['doc'] ?? ''));
        $lab = (int) ($input['lab'] ?? 0);
        $status = (int) ($input['status'] ?? 0);
        if (mb_strlen($comment) > 4000 || mb_strlen($doc) > 200) {
            throw new \RuntimeException('Слишком длинный комментарий');
        }

        $manual = $this->manualReturn($analysisId, $user);
        if ($manual !== null) {
            if ($eventId !== self::EVENT_CHANGESTATUS || $status !== (int) $manual['cnt_oldvalue']) {
                throw new \RuntimeException('Действие недоступно');
            }

            return $this->applyStatusChange($analysisId, $status, $comment, $uid);
        }

        if (in_array($eventId, [self::EVENT_CHANGESTATUS, self::EVENT_CHANGELAB, self::EVENT_CHANGEPACIENT], true)) {
            return $this->applyPassportOperation($analysisId, $eventId, $input, $user, $uid);
        }

        $row = $this->analysisRow($analysisId);
        $row['_mlpa'] = $this->childId($analysisId);
        $allowed = [];
        foreach ($this->visibleRules($row, $user) as $rule) {
            $allowed[(int) $rule['view_operation']] = true;
        }
        if (!isset($allowed[$eventId])) {
            throw new \RuntimeException('Действие недоступно');
        }

        $this->requireComment($eventId, $comment);
        $this->requireDoc($eventId, $doc);

        $details = $this->details($analysisId);
        $message = 'Операция выполнена успешно';

        $this->db->beginTransaction();
        try {
            $eventComment = $comment;
            if (in_array($eventId, [self::EVENT_MOVING, self::EVENT_RETURN_MOVING], true)) {
                $eventComment = 'Номер накладной: ' . $doc . '<br>' . $comment;
            }
            $this->insertEvent($analysisId, $eventId, $uid, $eventComment, null);

            switch ($eventId) {
                case self::EVENT_INIT:
                    $this->updateAnalysis($analysisId, ['view_status' => AnalysisService::STATUS_INIT]);
                    break;
                case self::EVENT_SELFDELIVERY:
                    $this->updateAnalysis($analysisId, ['view_status' => AnalysisService::STATUS_SELFDELIVERY]);
                    break;
                case self::EVENT_MOVING:
                    $this->updateAnalysis($analysisId, ['view_status' => AnalysisService::STATUS_MOVING]);
                    break;
                case self::EVENT_RECEIVED:
                    $this->updateAnalysis($analysisId, [
                        'view_status' => AnalysisService::STATUS_RECEIVED,
                        'date_accept' => time(),
                    ]);
                    break;
                case self::EVENT_ACCEPTABLE:
                    $this->updateAnalysis($analysisId, [
                        'view_status' => AnalysisService::STATUS_ACCEPTABLE,
                        'date_correctmaterial' => time(),
                    ]);
                    break;
                case self::EVENT_REJECTED_BADMATERIAL:
                    $this->updateAnalysis($analysisId, ['view_status' => AnalysisService::STATUS_REJECTED_BADMATERIAL]);
                    break;
                case self::EVENT_REJECTED_BADMATERIALNGS:
                    $this->applyBrca($analysisId, $row);
                    break;
                case self::EVENT_REJECTED_BADDELIVERY:
                    $this->updateAnalysis($analysisId, ['view_status' => AnalysisService::STATUS_REJECTED_BADDELIVERY]);
                    break;
                case self::EVENT_RETURN_CALLED:
                    $this->updateAnalysis($analysisId, ['view_status' => AnalysisService::STATUS_RETURN_CALLED]);
                    break;
                case self::EVENT_RETURN_MOVING:
                case self::EVENT_RETURN_RECEIVED:
                case self::EVENT_RETURN_SELF:
                    $this->updateAnalysis($analysisId, ['view_status' => AnalysisService::STATUS_RETURN_RECEIVED]);
                    break;
                case self::EVENT_SPENT:
                    $this->updateAnalysis($analysisId, ['view_status' => AnalysisService::STATUS_SPENT]);
                    break;
                case self::EVENT_MLPA:
                    $this->applyMlpa($analysisId, $row, $uid);
                    break;
                case self::EVENT_READY:
                    $message = $this->applyReady($analysisId, $row);
                    break;
                case self::EVENT_PTEN:
                    $this->applyPten($analysisId, $row, $uid, $lab, $status);
                    break;
                case self::EVENT_NGS:
                    $this->applyNgs($analysisId, $row, $details, $uid);
                    break;
                default:
                    throw new \RuntimeException('Действие недоступно');
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e instanceof \RuntimeException ? $e : new \RuntimeException('Ошибка сохранения');
        }

        $this->notify($eventId, $details, $comment, $doc);

        return $message;
    }

    /**
     * Лаборатории радио ИГХ PTEN. Легаси `Dataprovider::getPtenLabs`.
     *
     * @return list<array{id:int,name:string}>
     */
    public function ptenLabs(): array
    {
        $rows = $this->db->reset()
            ->select(['cat', 'varc_fullname', 'varc_name'])
            ->from('t_lab')
            ->whereIn('cat', [AnalysisService::LAB_GBUZ62, AnalysisService::LAB_REFERENCE])
            ->get();

        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $id = (int) ($row['cat'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $name = trim((string) ($row['varc_fullname'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($row['varc_name'] ?? ''));
            }
            $out[] = ['id' => $id, 'name' => $name !== '' ? $name : ('ID ' . $id)];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function rowContext(array $record): array
    {
        return [
            'cat' => (int) ($record['cat'] ?? 0),
            'view_status' => (int) ($record['view_status'] ?? 0),
            'view_test' => (int) ($record['view_test'] ?? 0),
            'varc_result' => (string) ($record['varc_result'] ?? ''),
            '_mlpa' => (int) ($record['_mlpa'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $user
     * @return list<array<string, mixed>>
     */
    private function visibleRules(array $row, array $user): array
    {
        $roleId = $this->currentRoleId($user);
        if ($roleId <= 0) {
            return [];
        }

        $status = (int) ($row['view_status'] ?? 0);
        $labId = $this->labId($user);
        $isLab = $this->hasRole($user, RoleService::ROLE_LAB);
        $rules = $this->rules($roleId, $status);
        $test = (int) ($row['view_test'] ?? 0);
        $result = (string) ($row['varc_result'] ?? '');
        $hasChild = (int) ($row['_mlpa'] ?? 0) > 0;
        $out = [];

        foreach ($rules as $rule) {
            $eventId = (int) ($rule['view_operation'] ?? 0);
            if ($isLab && $this->labExcluded((string) ($rule['multiselect_labs'] ?? ''), $labId)) {
                continue;
            }
            if ($eventId === self::EVENT_MLPA && $test !== AnalysisService::TYPE_HRD) {
                continue;
            }
            if ($eventId === self::EVENT_PTEN && $test !== AnalysisService::TYPE_AKT) {
                continue;
            }
            if ($eventId === self::EVENT_MLPA && $hasChild) {
                continue;
            }
            if ($eventId === self::EVENT_REJECTED_BADMATERIALNGS && $test === AnalysisService::TYPE_BRCA) {
                continue;
            }
            $ngsOk = $test === AnalysisService::TYPE_PTEN && $result !== AnalysisService::PTEN_EXPRESSION_LOSS;
            if ($eventId === self::EVENT_NGS && !$ngsOk) {
                continue;
            }
            $out[] = $rule;
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rules(int $roleId, int $status): array
    {
        $key = $roleId . ':' . $status;
        if (isset($this->rulesCache[$key])) {
            return $this->rulesCache[$key];
        }

        $rows = $this->db->reset()
            ->select(['f.view_operation', 'f.multiselect_labs', 't.varc_name AS operation'])
            ->from('t_events_type t')
            ->join('t_wf f', 't.cat = f.view_operation')
            ->where([
                'f.view_role' => $roleId,
                'f.view_status' => $status,
            ])
            ->order(['f.view_operation' => 'ASC'])
            ->get();

        $this->rulesCache[$key] = is_array($rows) ? $rows : [];

        return $this->rulesCache[$key];
    }

    private function labExcluded(string $labs, int $labId): bool
    {
        $labs = trim($labs);
        if ($labs === '' || $labId <= 0) {
            return false;
        }
        foreach (explode(',', $labs) as $part) {
            if ((int) trim($part) === $labId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Красная кнопка возврата: последняя операция — ручная смена, текущий статус черновик или «в работе»,
     * старый статус есть и это не черновик и не «в работе». Только обычная лаборатория.
     *
     * @param array<string, mixed> $user
     * @return array{cnt_oldvalue:int}|null
     */
    private function manualReturn(int $analysisId, array $user): ?array
    {
        if ($this->fullStatusChange($user)) {
            return null;
        }

        $event = $this->db->reset()
            ->select(['e.view_type', 'e.cnt_oldvalue', 'a.view_status'])
            ->from('t_events e')
            ->join('t_analysis a', 'a.cat = e.cnt_object')
            ->where(['a.cat' => $analysisId])
            ->order(['e.cat' => 'DESC'])
            ->limit(1)
            ->getOne();
        if ($event === null) {
            return null;
        }

        $current = (int) ($event->view_status ?? 0);
        $old = (int) ($event->cnt_oldvalue ?? 0);
        $isManual = (int) ($event->view_type ?? 0) === self::EVENT_CHANGESTATUS
            && in_array($current, [AnalysisService::STATUS_DRAFT, AnalysisService::STATUS_WORK], true);
        if (!$isManual || $old <= 0) {
            return null;
        }
        if (in_array($old, [AnalysisService::STATUS_DRAFT, AnalysisService::STATUS_WORK], true)) {
            return null;
        }

        return ['cnt_oldvalue' => $old];
    }

    /**
     * @param array<string, mixed> $user
     */
    private function fullStatusChange(array $user): bool
    {
        if (!$this->hasRole($user, RoleService::ROLE_LAB)) {
            return true;
        }

        return $this->labId($user) === AnalysisService::LAB_REFERENCE;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function currentRoleId(array $user): int
    {
        $roles = $user['roles_map'] ?? [];
        if (!is_array($roles) || $roles === []) {
            return 0;
        }

        return (int) array_key_first($roles);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function labId(array $user): int
    {
        $profile = is_array($user['profile'] ?? null) ? $user['profile'] : [];

        return (int) ($profile['view_lab'] ?? 0);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function hasRole(array $user, int $roleId): bool
    {
        $roles = $user['roles_map'] ?? [];

        return is_array($roles) && array_key_exists($roleId, $roles);
    }

    private function requireComment(int $eventId, string $comment): void
    {
        $required = [
            self::EVENT_REJECTED_BADMATERIAL,
            self::EVENT_REJECTED_BADMATERIALNGS,
            self::EVENT_REJECTED_BADDELIVERY,
            self::EVENT_MLPA,
        ];
        if (in_array($eventId, $required, true) && $comment === '') {
            throw new \RuntimeException('Нужен комментарий');
        }
    }

    private function requireDoc(int $eventId, string $doc): void
    {
        if (in_array($eventId, [self::EVENT_MOVING, self::EVENT_RETURN_MOVING], true) && $doc === '') {
            throw new \RuntimeException('Нужен номер накладной');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function analysisRow(int $id): array
    {
        $row = $this->db->reset()
            ->select(['*'])
            ->from(AnalysisService::TBL_ANALYSIS)
            ->where(['cat' => $id])
            ->limit(1)
            ->getOne();
        if ($row === null) {
            throw new \RuntimeException('Заявка не найдена');
        }

        return (array) $row;
    }

    private function childId(int $analysisId): int
    {
        $row = $this->db->reset()
            ->select(['cat'])
            ->from(AnalysisService::TBL_ANALYSIS)
            ->where(['cnt_analysislinked' => $analysisId])
            ->order(['cat' => 'DESC'])
            ->limit(1)
            ->getOne();

        return $row ? (int) ($row->cat ?? 0) : 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function details(int $id): array
    {
        $stmt = $this->db->execute(
            'SELECT a.cat, a.view_status, a.view_test, a.user_doctor, a.view_lab,
                TRIM(CONCAT(IFNULL(p.varc_surname, \'\'), \' \', IFNULL(p.varc_name, \'\'), \' \', IFNULL(p.varc_patron, \'\'))) AS pacient_name,
                TRIM(CONCAT(IFNULL(d.varc_surname, \'\'), \' \', IFNULL(d.varc_name, \'\'), \' \', IFNULL(d.varc_patron, \'\'))) AS doctor_name,
                d.varc_name AS doctor_varc_name,
                d.varc_patron AS doctor_varc_patron,
                d.view_lab AS doctor_lab,
                l.varc_name AS lab_name,
                st.varc_name AS status_name,
                ty.varc_name AS type_name,
                u.email AS doctor_email
             FROM t_analysis a
             LEFT JOIN t_pacient p ON p.cat = a.view_pacient
             LEFT JOIN t_users d ON d.cat = a.user_doctor
             LEFT JOIN t_lab l ON l.cat = a.view_lab
             LEFT JOIN t_analiz_status st ON st.cat = a.view_status
             LEFT JOIN t_analiztype ty ON ty.cat = a.view_test
             LEFT JOIN tblusers u ON u.id = a.user_doctor
             WHERE a.cat = ?
             LIMIT 1',
            [$id]
        );
        if (!$stmt instanceof \PDOStatement) {
            return [];
        }
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }

    private function applyStatusChange(int $analysisId, int $newStatus, string $comment, int $uid): string
    {
        $name = $this->statusName($newStatus);
        if ($name === '') {
            throw new \RuntimeException('Ошибка сохранения');
        }
        $row = $this->analysisRow($analysisId);
        $oldStatus = (int) ($row['view_status'] ?? 0);
        $oldName = $this->statusName($oldStatus);
        $stored = $oldName . '->' . $name . '<br>' . $comment;
        $details = $this->details($analysisId);

        $this->db->beginTransaction();
        try {
            $this->insertEvent($analysisId, self::EVENT_CHANGESTATUS, $uid, $stored, $oldStatus);
            $this->updateAnalysis($analysisId, ['view_status' => $newStatus]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e instanceof \RuntimeException ? $e : new \RuntimeException('Ошибка сохранения');
        }

        if ($oldStatus === AnalysisService::STATUS_WORK) {
            $this->sendNotify(self::NOTIFY_CHANGE, $this->doctorRecipient($details), [
                'pacient' => (string) ($details['pacient_name'] ?? ''),
                'type' => (string) ($details['type_name'] ?? ''),
            ]);
        }

        return 'Операция выполнена успешно';
    }

    /**
     * @param array<string, mixed> $user
     */
    private function passportAllows(int $analysisId, int $eventId, array $user): bool
    {
        if ($analysisId <= 0) {
            return false;
        }
        try {
            $row = $this->analysisRow($analysisId);
        } catch (\RuntimeException) {
            return false;
        }
        foreach ($this->passportOperations($row, $user) as $item) {
            if ($item['id'] === $eventId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{comment?:string,doc?:string,lab?:int,status?:int,pacient?:array<string,mixed>} $input
     * @param array<string, mixed> $user
     */
    private function applyPassportOperation(int $analysisId, int $eventId, array $input, array $user, int $uid): string
    {
        if (!$this->passportAllows($analysisId, $eventId, $user)) {
            throw new \RuntimeException('Действие недоступно');
        }
        $comment = trim((string) ($input['comment'] ?? ''));
        if ($comment === '') {
            throw new \RuntimeException('Нужен комментарий');
        }
        if ($eventId === self::EVENT_CHANGESTATUS) {
            $status = (int) ($input['status'] ?? 0);
            $allowed = [];
            foreach ($this->statusChoices($analysisId, $user) as $choice) {
                $allowed[$choice['id']] = true;
            }
            if (!isset($allowed[$status])) {
                throw new \RuntimeException('Ошибка сохранения');
            }

            return $this->applyStatusChange($analysisId, $status, $comment, $uid);
        }
        if ($eventId === self::EVENT_CHANGELAB) {
            return $this->applyLabChange($analysisId, (int) ($input['lab'] ?? 0), $comment, $uid);
        }

        $pacient = is_array($input['pacient'] ?? null) ? $input['pacient'] : [];

        return $this->applyPacientChange($analysisId, $pacient, $comment, $uid);
    }

    private function applyLabChange(int $analysisId, int $labId, string $comment, int $uid): string
    {
        $row = $labId > 0
            ? $this->db->reset()->select(['varc_name'])->from('t_lab')->where(['cat' => $labId])->limit(1)->getOne()
            : null;
        $name = trim((string) ($row?->varc_name ?? ''));
        if ($name === '') {
            throw new \RuntimeException('Ошибка сохранения');
        }

        $this->db->beginTransaction();
        try {
            $this->insertEvent($analysisId, self::EVENT_CHANGELAB, $uid, 'Новая лаборатория: ' . $name . '<br>' . $comment, null);
            $this->updateAnalysis($analysisId, ['view_lab' => $labId]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e instanceof \RuntimeException ? $e : new \RuntimeException('Ошибка сохранения');
        }

        return 'Операция выполнена успешно';
    }

    /**
     * @param array<string, mixed> $pacient
     */
    private function applyPacientChange(int $analysisId, array $pacient, string $comment, int $uid): string
    {
        $surname = trim((string) ($pacient['surname'] ?? ''));
        $name = trim((string) ($pacient['name'] ?? ''));
        $patron = trim((string) ($pacient['patron'] ?? ''));
        $birthday = trim((string) ($pacient['birthday'] ?? ''));
        if ($surname === '' || $name === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
            throw new \RuntimeException('Заполните фамилию, имя и дату рождения');
        }
        $male = (int) ($pacient['male'] ?? 0);
        $region = (int) ($pacient['region'] ?? 0);
        $code = AnalysisService::codePacient($surname, $name, $patron, $birthday);
        $found = $this->db->execute(
            'SELECT cat FROM `t_pacient` WHERE varc_surname = ? AND varc_name = ? AND varc_patron = ? AND DATE(datetext_bd) = ? LIMIT 1',
            [$surname, $name, $patron, $birthday]
        );
        $existing = $found instanceof \PDOStatement ? $found->fetch(\PDO::FETCH_ASSOC) : false;
        $existing = is_array($existing) ? $existing : null;

        $this->db->beginTransaction();
        try {
            if ($existing) {
                $pacientId = (int) ($existing['cat'] ?? 0);
                $updated = $this->db->execute(
                    'UPDATE `t_pacient` SET `stat_patron` = ?, `varc_code` = ? WHERE cat = ?',
                    [$patron !== '' ? 1 : 0, $code, $pacientId]
                );
                if (!$updated instanceof \PDOStatement || $pacientId <= 0) {
                    throw new \RuntimeException('Ошибка сохранения пациента [#0017]');
                }
            } else {
                $metadata = new TableMetadataManager();
                $record = $metadata->createActiveRecord(AnalysisService::TBL_PACIENT, [
                    'varc_surname' => $surname,
                    'varc_name' => $name,
                    'varc_patron' => $patron,
                    'stat_patron' => $patron !== '' ? 1 : 0,
                    'view_male' => $male,
                    'view_region' => $region,
                    'varc_code' => $code,
                    'datetext_bd' => $birthday,
                    'varc_guid' => Crypt::generateGuid(),
                ]);
                if (!$record->save()) {
                    throw new \RuntimeException('Ошибка сохранения пациента [#0017]');
                }
                $pacientId = (int) ($record->cat ?? $record->id ?? 0);
                if ($pacientId <= 0) {
                    throw new \RuntimeException('Ошибка сохранения пациента [#0019]');
                }
            }
            $this->updateAnalysis($analysisId, ['view_pacient' => $pacientId]);
            $this->insertEvent($analysisId, self::EVENT_CHANGEPACIENT, $uid, $comment, null);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e instanceof \RuntimeException ? $e : new \RuntimeException('Ошибка сохранения пациента [#0018]');
        }

        return 'Операция выполнена успешно';
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $user
     */
    private function fullStatusList(int $analysisId, array $user, array $row): bool
    {
        if ($this->fullStatusChange($user)) {
            return true;
        }

        $event = $this->db->reset()
            ->select(['view_type'])
            ->from('t_events')
            ->where(['cnt_object' => $analysisId])
            ->order(['cat' => 'DESC'])
            ->limit(1)
            ->getOne();
        $current = (int) ($row['view_status'] ?? 0);
        $isReturn = $event !== null
            && (int) ($event->view_type ?? 0) === self::EVENT_CHANGESTATUS
            && in_array($current, [AnalysisService::STATUS_DRAFT, AnalysisService::STATUS_WORK], true);

        return $isReturn && $this->manualReturn($analysisId, $user) === null;
    }

    /**
     * @param array<int, string> $options
     * @return list<array{id:int,label:string,checked:bool}>
     */
    private function choiceList(array $options, int $current): array
    {
        $out = [];
        $marked = false;
        foreach ($options as $id => $label) {
            $checked = (int) $id === $current;
            $marked = $marked || $checked;
            $out[] = ['id' => (int) $id, 'label' => $label, 'checked' => $checked];
        }
        if (!$marked && $out !== []) {
            $out[0]['checked'] = true;
        }

        return $out;
    }

    /**
     * @param list<int> $types
     */
    private function hasEvent(int $analysisId, array $types): bool
    {
        if ($types === []) {
            return false;
        }
        $marks = implode(',', array_fill(0, count($types), '?'));
        $stmt = $this->db->execute(
            'SELECT cat FROM t_events WHERE cnt_object = ? AND view_type IN (' . $marks . ') LIMIT 1',
            array_merge([$analysisId], $types)
        );
        if (!$stmt instanceof \PDOStatement) {
            return false;
        }

        return is_array($stmt->fetch(\PDO::FETCH_ASSOC));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function applyBrca(int $analysisId, array $row): void
    {
        $map = [
            AnalysisService::TYPE_HRD => AnalysisService::TYPE_NGS,
            AnalysisService::TYPE_AKT => AnalysisService::TYPE_AKTBRCA,
        ];
        $next = $map[(int) ($row['view_test'] ?? 0)] ?? 0;
        if ($next <= 0) {
            throw new \RuntimeException('Для данного анализа нельзя провести тест BRCA1/2 на крови');
        }
        $this->updateAnalysis($analysisId, [
            'view_test' => $next,
            'view_status' => AnalysisService::STATUS_REJECTED_BADMATERIALNGS,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function applyMlpa(int $analysisId, array $row, int $uid): void
    {
        if ((int) ($row['view_test'] ?? 0) !== AnalysisService::TYPE_HRD) {
            throw new \RuntimeException('Для данного анализа нельзя провести тест MLPA');
        }
        if ($this->childId($analysisId) > 0) {
            throw new \RuntimeException('Анализ MLPA уже проведен');
        }
        $this->updateAnalysis($analysisId, ['view_status' => AnalysisService::STATUS_MLPA]);
        $this->insertAnalysis([
            'view_test' => AnalysisService::TYPE_MLPA,
            'cnt_analysislinked' => $analysisId,
            'author_add' => $uid,
            'view_diagnoz' => (int) ($row['view_diagnoz'] ?? 0),
            'view_pacient' => (int) ($row['view_pacient'] ?? 0),
            'view_lab' => (int) ($row['view_lab'] ?? 0),
            'user_doctor' => (int) ($row['user_doctor'] ?? 0),
            'view_status' => AnalysisService::STATUS_WORK,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function applyReady(int $analysisId, array $row): string
    {
        $now = time();
        $prev = (int) ($row['date_execauto'] ?? 0);
        $dateExec = $now;
        $note = 'Дата завершения анализа установлена в ' . date('d.m.Y H:i');
        if ($prev > 0 && date('Ym', $prev) !== date('Ym')) {
            $dateExec = $prev;
            $note = 'Дата завершения анализа уже установлена ранее и не будет изменена';
        }
        $this->updateAnalysis($analysisId, [
            'view_status' => AnalysisService::STATUS_DONE,
            'stat_conclusion' => 1,
            'date_execauto' => $dateExec,
        ]);

        return 'Анализ успешно завершен. ' . $note;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function applyPten(int $analysisId, array $row, int $uid, int $lab, int $status): void
    {
        if ((int) ($row['view_test'] ?? 0) !== AnalysisService::TYPE_AKT) {
            throw new \RuntimeException('Для данного анализа нельзя провести тест');
        }
        if (!in_array($lab, [AnalysisService::LAB_GBUZ62, AnalysisService::LAB_REFERENCE], true)) {
            throw new \RuntimeException('Выберите лабораторию');
        }
        if (!in_array($status, [AnalysisService::STATUS_SELFDELIVERY, AnalysisService::STATUS_MOVING], true)) {
            throw new \RuntimeException('Укажите, как отправим материал');
        }
        $this->updateAnalysis($analysisId, ['view_status' => AnalysisService::STATUS_PTEN]);
        $this->insertAnalysis([
            'view_test' => AnalysisService::TYPE_PTEN,
            'author_add' => $uid,
            'view_diagnoz' => (int) ($row['view_diagnoz'] ?? 0),
            'view_pacient' => (int) ($row['view_pacient'] ?? 0),
            'stat_materialgisto' => (int) ($row['stat_materialgisto'] ?? 0),
            'varc_markgisto' => (string) ($row['varc_markgisto'] ?? ''),
            'view_lab' => $lab,
            'view_status' => $status,
            'user_doctor' => (int) ($row['user_doctor'] ?? 0),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $details
     */
    private function applyNgs(int $analysisId, array $row, array $details, int $uid): void
    {
        if ((int) ($row['view_test'] ?? 0) !== AnalysisService::TYPE_PTEN
            || (string) ($row['varc_result'] ?? '') === AnalysisService::PTEN_EXPRESSION_LOSS
        ) {
            throw new \RuntimeException('Действие недоступно');
        }
        $this->updateAnalysis($analysisId, ['view_status' => AnalysisService::STATUS_PTEN]);
        $copy = [
            'view_diagnoz', 'view_gistotype', 'gallery_gisto', 'gallery_epicris', 'doc_gisto',
            'view_myonkoan', 'varc_myonkoan', 'view_chem', 'stat_chem', 'view_linetherapy',
            'stat_hasprogress', 'view_confirmprogress', 'view_teraphyduration',
            'stat_materialgisto', 'varc_markgisto', 'stat_materialblood', 'varc_markblood',
            'stat_materialnormal', 'varc_marknormal',
        ];
        $fields = [
            'view_test' => AnalysisService::TYPE_AKT,
            'author_add' => $uid,
            'view_pacient' => (int) ($row['view_pacient'] ?? 0),
            'view_lab' => (int) ($details['doctor_lab'] ?? 0),
            'view_status' => AnalysisService::STATUS_INIT,
            'user_doctor' => (int) ($row['user_doctor'] ?? 0),
            'cnt_analysislinked' => $analysisId,
        ];
        foreach ($copy as $column) {
            $fields[$column] = $row[$column] ?? null;
        }
        $this->insertAnalysis($fields);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function insertAnalysis(array $fields): void
    {
        $ints = [
            'view_pacient', 'view_code', 'stat_chem', 'view_status', 'view_lab', 'view_method',
            'view_reagent', 'view_result', 'cnt_vardepth', 'view_patogen', 'cnt_genom', 'view_hrd',
            'cnt_snpdepth', 'cnt_brcadepth', 'view_materialaction', 'view_test', 'view_chem',
            'author_add', 'stat_materialblood', 'stat_materialgisto', 'stat_genom', 'stat_materialnormal',
            'stat_patogen', 'stat_onkogen', 'cnt_readdepth', 'stat_copiynost', 'cnt_analysislinked',
            'view_late', 'cnt_late', 'view_progressing', 'stat_conclusion', 'view_linetherapy',
            'stat_hasprogress', 'view_confirmprogress', 'view_teraphyduration', 'cnt_agediagnoz',
            'cnt_noexpression', 'stat_express', 'view_diagnoz', 'view_gistotype', 'view_myonkoan',
            'user_doctor',
        ];
        foreach ($ints as $column) {
            if (!array_key_exists($column, $fields)) {
                $fields[$column] = 0;
            }
        }
        if (!isset($fields['created_add'])) {
            $fields['created_add'] = date('Y-m-d H:i:s');
        }
        if (!isset($fields['varc_guid']) || trim((string) $fields['varc_guid']) === '') {
            $fields['varc_guid'] = Crypt::generateGuid();
        }
        $this->insertRow(AnalysisService::TBL_ANALYSIS, $fields);
    }

    private function insertEvent(int $analysisId, int $eventId, int $uid, string $comment, ?int $oldStatus): void
    {
        $this->insertRow('t_events', [
            'author_add' => $uid,
            'view_type' => $eventId,
            'cnt_object' => $analysisId,
            'txt_comment' => $comment,
            'cnt_oldvalue' => $oldStatus,
            'created_add' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function updateAnalysis(int $id, array $fields): void
    {
        if ($fields === []) {
            return;
        }
        $sets = [];
        $params = [];
        foreach ($fields as $column => $value) {
            $this->assertColumn($column);
            $sets[] = '`' . $column . '` = ?';
            $params[] = $value;
        }
        $params[] = $id;
        $result = $this->db->execute(
            'UPDATE `t_analysis` SET ' . implode(', ', $sets) . ' WHERE cat = ?',
            $params
        );
        if (!$result instanceof \PDOStatement) {
            throw new \RuntimeException('Ошибка сохранения');
        }
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function insertRow(string $table, array $fields): void
    {
        $this->assertColumn($table);
        $cols = [];
        $marks = [];
        $params = [];
        foreach ($fields as $column => $value) {
            $this->assertColumn($column);
            $cols[] = '`' . $column . '`';
            $marks[] = '?';
            $params[] = $value;
        }
        $result = $this->db->execute(
            'INSERT INTO `' . $table . '` (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $marks) . ')',
            $params
        );
        if (!$result instanceof \PDOStatement) {
            throw new \RuntimeException('Ошибка сохранения');
        }
    }

    private function assertColumn(string $name): void
    {
        if (preg_match('/^[a-z0-9_]+$/', $name) !== 1) {
            throw new \RuntimeException('Ошибка сохранения');
        }
    }

    private function statusName(int $id): string
    {
        if ($id <= 0) {
            return '';
        }
        $row = $this->db->reset()
            ->select(['varc_name'])
            ->from('t_analiz_status')
            ->where(['cat' => $id])
            ->limit(1)
            ->getOne();

        return trim((string) ($row->varc_name ?? ''));
    }

    /**
     * @param array<string, mixed> $details
     */
    private function notify(int $eventId, array $details, string $comment, string $doc): void
    {
        $pacient = (string) ($details['pacient_name'] ?? '');
        $doctor = $this->doctorRecipient($details);
        $admins = (new NotifyService($this->db))->adminRecipients();
        $varsDoctor = ['pacient' => $pacient, 'comment' => $comment];

        switch ($eventId) {
            case self::EVENT_INIT:
                $this->sendNotify(self::NOTIFY_INIT, $admins, [
                    'pacient' => $pacient,
                    'lab' => (string) ($details['lab_name'] ?? ''),
                    'doc' => (string) ($details['doctor_name'] ?? ''),
                ]);
                break;
            case self::EVENT_MOVING:
                $this->sendNotify(self::NOTIFY_MOVING, $doctor, [
                    'pacient' => $pacient,
                    'number' => $doc,
                ]);
                break;
            case self::EVENT_REJECTED_BADMATERIAL:
                $this->sendNotify(self::NOTIFY_BADMATERIAL, $doctor, $varsDoctor);
                break;
            case self::EVENT_REJECTED_BADMATERIALNGS:
                $this->sendNotify(self::NOTIFY_BADMATERIALNGS, $doctor, $varsDoctor);
                break;
            case self::EVENT_REJECTED_BADDELIVERY:
                $this->sendNotify(self::NOTIFY_BADDELIVERY, $doctor, $varsDoctor);
                break;
            case self::EVENT_RETURN_CALLED:
                $this->sendNotify(self::NOTIFY_RETURN_CALLED, $admins, [
                    'pacient' => $pacient,
                    'lab' => (string) ($details['lab_name'] ?? ''),
                    'doc' => (string) ($details['doctor_name'] ?? ''),
                ]);
                break;
            case self::EVENT_SPENT:
                $this->sendNotify(self::NOTIFY_SPENT, $doctor, $varsDoctor);
                break;
            case self::EVENT_READY:
                $this->sendNotify(self::NOTIFY_DONE, $doctor, $varsDoctor);
                break;
            case self::EVENT_PTEN:
                $this->sendNotify(self::NOTIFY_PTEN, $doctor, $varsDoctor);
                break;
        }
    }

    /**
     * @param array<string, mixed> $details
     * @return list<array{id:int,email:string,varc_name:string,varc_patron:string}>
     */
    private function doctorRecipient(array $details): array
    {
        $id = (int) ($details['user_doctor'] ?? 0);
        if ($id <= 0) {
            return [];
        }

        return [[
            'id' => $id,
            'email' => (string) ($details['doctor_email'] ?? ''),
            'varc_name' => (string) ($details['doctor_varc_name'] ?? ''),
            'varc_patron' => (string) ($details['doctor_varc_patron'] ?? ''),
        ]];
    }

    /**
     * @param list<array{id?:int,email?:string,varc_name?:string,varc_patron?:string}> $recipients
     * @param array<string, scalar|null> $vars
     */
    private function sendNotify(int $typeId, array $recipients, array $vars): void
    {
        if ($recipients === []) {
            return;
        }
        try {
            (new NotifyService($this->db))->sendType($typeId, $recipients, $vars);
        } catch (\Throwable $e) {
            error_log('AnalysisWorkflowService notify: ' . $e->getMessage());
        }
    }

    private function rollback(): void
    {
        try {
            $this->db->rollback();
        } catch (\Throwable) {
        }
    }
}
