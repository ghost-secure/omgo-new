<?php

namespace Services;

use Core\Database\QueryBuilder;
use Core\Reference\TableMetadataManager;
use Helpers\Crypt;

/**
 * Анализы и связанные сущности кабинета.
 * Легаси: `Object\Analysis\Analysis`.
 */
class AnalysisService
{
    public const TBL_ANALYSIS = 't_analysis';
    public const TBL_ROUTE = 't_analysis_route';
    public const TBL_PACIENT = 't_pacient';
    public const TBL_ANAMNES = 't_analysis_anamnes';
    public const TBL_COMM = 't_comm';

    /** Диагнозы, которые легаси фиксирует в карточке. */
    public const DI_YA = 2;
    public const DI_RM = 3;
    public const DI_RPZ = 6;
    public const DI_YAR = 11;
    public const DI_PRED = 12;

    /**
     * Колонки t_analysis, которые карточка черновика имеет право записать.
     * Код, тип, лаборатория и GUID сюда не входят.
     *
     * @var list<string>
     */
    public const CARD_COLUMNS = [
        'user_doctor',
        'view_diagnoz', 'view_gistotype', 'varc_gistotype',
        'view_illness', 'view_stage', 'view_chem', 'view_localization',
        'view_linetherapy', 'view_confirmprogress', 'view_teraphyduration',
        'stat_hasprogress', 'view_myonkoan', 'varc_myonkoan',
        'stat_materialgisto', 'stat_materialblood', 'stat_materialnormal',
        'varc_markgisto', 'varc_markblood', 'varc_marknormal',
        'cnt_agediagnoz', 'cnt_glisson', 'varc_spread', 'varc_hormonesensitive',
    ];

    /** Легаси `Object\Analysis\Analysis::TYPE_*`. В радио пула кодов — HRD, AKT, BRCA, PTEN, HRR. */
    public const TYPE_HRD = 1;
    public const TYPE_NGS = 2;
    public const TYPE_AKT = 3;
    public const TYPE_AKTBRCA = 4;
    public const TYPE_MLPA = 5;
    public const TYPE_BRCA = 6;
    public const TYPE_PTEN = 7;
    public const TYPE_HRR = 8;

    /** Легаси `Analysis::LAB_REFERENCE`: лаборатория PTEN, если у врача нет маршрута. */
    public const LAB_REFERENCE = 3;

    /** Легаси `Analysis::LAB_GBUZ62`: лаборатория ИГХ PTEN по умолчанию. */
    public const LAB_GBUZ62 = 9;

    /** Легаси `Analysis::STATUS_*`. Черновик — карточка сохранена, в лабораторию ещё не ушла. */
    public const STATUS_INIT = 1;
    public const STATUS_SELFDELIVERY = 2;
    public const STATUS_MOVING = 3;
    public const STATUS_RECEIVED = 4;
    public const STATUS_ACCEPTABLE = 5;
    public const STATUS_REJECTED_BADMATERIAL = 6;
    public const STATUS_REJECTED_BADDELIVERY = 7;
    public const STATUS_DONE = 8;
    public const STATUS_RETURN_CALLED = 9;
    public const STATUS_RETURN_MOVING = 10;
    public const STATUS_RETURN_RECEIVED = 11;
    public const STATUS_SPENT = 12;
    public const STATUS_DRAFT = 13;
    public const STATUS_REJECTED_BADMATERIALNGS = 14;
    public const STATUS_ARCHIVE = 15;
    public const STATUS_WORK = 16;
    public const STATUS_PTEN = 17;
    public const STATUS_MLPA = 18;

    /** Текст отказа, когда роль не открывает заявку. */
    public const VIEW_DENIED = 'Нет доступа для вашей учетной записи.';

    /** Легаси `Analysis::PTEN_EXPRESSION_*`: текст результата ИГХ. */
    public const PTEN_EXPRESSION_LOSS = 'Выпадение экспрессии белка PTEN ОБНАРУЖЕНО';
    public const PTEN_EXPRESSION_PRESENT = 'Выпадение экспрессии белка PTEN НЕ ОБНАРУЖЕНО';

    /** Страница ввода кода и карточки: `/manager/pacient/` и `/manager/pacient/{guid}/`. */
    public const PACIENT_FORM_URL = '/manager/pacient/';

    /** Грид `/manager/mant_pacient/` для всех ролей, кроме врача на телефоне. Легаси-блок 54. */
    public const DYN_LIST = 't_analysis/list';

    /** Карточки врача на телефоне. Легаси alias `doctor-mobile`, tbldynelements id 66. */
    public const DYN_DOCTOR_MOBILE = 't_analysis/doctor-mobile';

    /** @var array<string, array<string, mixed>>|null */
    private ?array $analysisColumns = null;

    public function __construct(private QueryBuilder $db)
    {
    }

    /**
     * Строка лаборатории `t_lab` по cat.
     */
    public function getLabInfo(int $labId): ?object
    {
        if ($labId <= 0) {
            return null;
        }

        return $this->db->reset()
            ->select(['*'])
            ->from('t_lab')
            ->where(['cat' => $labId])
            ->getOne();
    }

    /**
     * Легаси `stat_alldoctors = 1`: лаборатория видит всех врачей, без фильтра по своей лабе.
     * Колонки в дампе может не быть — тогда считаем, что флага нет.
     */
    public function labSeesAllDoctors(?object $lab): bool
    {
        if ($lab === null) {
            return false;
        }

        return (int) ($lab->stat_alldoctors ?? 0) === 1;
    }

    /**
     * Лаборатории для селекта маршрута. Легаси `Dataprovider::getAllLabs()`.
     *
     * @return list<array{id:int,name:string}>
     */
    public function getLabs(): array
    {
        $rows = $this->db->reset()
            ->select(['cat', 'varc_name'])
            ->from('t_lab')
            ->order(['cat' => 'ASC'])
            ->get();

        $out = [];
        if (!is_array($rows)) {
            return $out;
        }

        foreach ($rows as $row) {
            $arr = is_object($row) ? (array) $row : (is_array($row) ? $row : []);
            $id = (int) ($arr['cat'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $name = trim((string) ($arr['varc_name'] ?? ''));
            $out[] = [
                'id' => $id,
                'name' => $name !== '' ? $name : ('ID ' . $id),
            ];
        }

        return $out;
    }

    /** Мнемоники фона строки: var(--color-bg-*). Совпадают с легаси uikit. */
    public const ROW_BG_TOKENS = ['green', 'blue', 'yellow', 'red', 'lgrey'];

    /**
     * Мнемоники сплошного цвета: класс bg-{token}.
     * pink — переменная --pink, остальные — --color-{token}.
     */
    public const SOLID_COLOR_TOKENS = [
        'green', 'blue', 'yellow', 'red', 'lgrey', 'purple', 'deepgreen', 'pink',
    ];

    /**
     * Грид заявок t_analysis: столбцы легаси блока 54 (код, ФИО, дубль, регионы).
     * Фильтр по врачу или роли сюда не входит — его добавляет страница.
     * Цвета: t_analiz_status.varc_color и t_analiztype.varc_color — мнемоники, не hex.
     *
     * @return array{joins: list<array<string, string>>, addSelect: list<string>, sort: array<string, string>, sortColumnExpressions: array<string, string>, filterColumnExpressions: array<string, array<string, string>>}
     */
    public function analysisGridQuery(): array
    {
        $pacientName = "CONCAT(pac.varc_surname, ' ', pac.varc_name, ' ', pac.varc_patron)";
        $doctorName = "CONCAT(doc.varc_surname, ' ', doc.varc_name, ' ', doc.varc_patron)";
        $doctorRegion = "(SELECT CONCAT(r.varc_name, ' ', r.varc_type) FROM t_geo_region r WHERE r.cat = doc.view_region)";
        $pacientRegion = "(SELECT CONCAT(r.varc_name, ' ', r.varc_type) FROM t_geo_region r WHERE r.cat = pac.view_region)";
        $pacientMale = '(SELECT male.varc_name FROM t_male male WHERE male.cat = pac.view_male)';
        $analysisDouble = 'CASE
    WHEN EXISTS (
        SELECT 1 FROM t_analysis dup
        WHERE dup.view_pacient > 0
          AND dup.view_pacient = t_analysis.view_pacient
          AND dup.view_test = t_analysis.view_test
          AND dup.cat <> t_analysis.cat
          AND dup.view_lab <> t_analysis.view_lab
    ) THEN 2
    WHEN EXISTS (
        SELECT 1 FROM t_analysis dup
        WHERE dup.view_pacient > 0
          AND dup.view_pacient = t_analysis.view_pacient
          AND dup.view_test = t_analysis.view_test
          AND dup.cat <> t_analysis.cat
    ) THEN 1
    ELSE 0
END';

        $statusColor = '(SELECT st.varc_color FROM t_analiz_status st WHERE st.cat = t_analysis.view_status)';
        $testColor = '(SELECT ty.varc_color FROM t_analiztype ty WHERE ty.cat = t_analysis.view_test)';
        $like = static function (string $columnSql): array {
            return [
                'where' => '{cond}',
                'column_sql' => $columnSql,
                'compare' => 'like',
            ];
        };

        return [
            'joins' => [
                [
                    'type' => 'LEFT',
                    'table' => 't_pacient',
                    'alias' => 'pac',
                    'on' => 'pac.cat = t_analysis.view_pacient',
                ],
                [
                    'type' => 'LEFT',
                    'table' => 't_users',
                    'alias' => 'doc',
                    'on' => 'doc.cat = t_analysis.user_doctor',
                ],
            ],
            'addSelect' => [
                'pac.varc_code AS pacient_code',
                $pacientName . ' AS pacient_name',
                'pac.datetext_bd AS pacient_bd',
                $pacientMale . ' AS pacient_male',
                $doctorRegion . ' AS doctor_region',
                $doctorName . ' AS doctor_name',
                $pacientRegion . ' AS pacient_region',
                $analysisDouble . ' AS analysis_double',
                $statusColor . ' AS status_color',
                $testColor . ' AS test_color',
                '(SELECT child.cat FROM t_analysis child WHERE child.cnt_analysislinked = t_analysis.cat ORDER BY child.cat DESC LIMIT 1) AS _mlpa',
                '0 AS _actions',
            ],
            'sort' => ['created_add' => 'desc'],
            'sortColumnExpressions' => [
                'pacient_code' => 'pac.varc_code',
                'pacient_name' => $pacientName,
                'pacient_bd' => 'pac.datetext_bd',
                'pacient_male' => $pacientMale,
                'doctor_region' => $doctorRegion,
                'doctor_name' => $doctorName,
                'pacient_region' => $pacientRegion,
                'analysis_double' => $analysisDouble,
                '_actions' => '0',
            ],
            'filterColumnExpressions' => [
                'pacient_code' => $like('pac.varc_code'),
                'pacient_name' => $like($pacientName),
                'pacient_bd' => [
                    'where' => '{cond}',
                    'column_sql' => 'pac.datetext_bd',
                    'compare' => 'date',
                ],
                'pacient_male' => $like($pacientMale),
                'doctor_region' => $like($doctorRegion),
                'doctor_name' => $like($doctorName),
                'pacient_region' => $like($pacientRegion),
                'analysis_double' => [
                    'column_sql' => $analysisDouble,
                ],
                'pacient_surname' => $like('pac.varc_surname'),
                'doctor_surname' => $like('doc.varc_surname'),
                'date_correctmaterial' => [
                    'where' => '{cond}',
                    'column_sql' => 't_analysis.date_correctmaterial',
                    'compare' => 'unix',
                ],
                'date_execauto' => [
                    'where' => '{cond}',
                    'column_sql' => 't_analysis.date_execauto',
                    'compare' => 'unix',
                ],
            ],
        ];
    }

    /**
     * Мнемоника цвета из справочника: только [a-z0-9_-], иначе пусто.
     */
    public static function colorToken(string $raw): string
    {
        $token = strtolower(trim($raw));
        if ($token === '' || preg_match('/^[a-z0-9_-]+$/', $token) !== 1) {
            return '';
        }

        return $token;
    }

    /**
     * Подпись справочника view_*. Id 0 — пусто: в ячейке это прочерк, не цифра.
     */
    public static function viewLabel(array $record, string $column): string
    {
        if ((int) ($record[$column] ?? 0) <= 0) {
            return '';
        }

        return trim((string) ($record[$column . '_name'] ?? ''));
    }

    public static function viewLabelHtml(array $record, string $column): string
    {
        $label = self::viewLabel($record, $column);
        if ($label === '') {
            return '<span class="text-muted">—</span>';
        }

        return htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Класс бейджа как в легаси: bg-deepgreen, bg-pink, …
     */
    public static function badgeClass(string $raw): string
    {
        $token = self::colorToken($raw);
        if ($token === '' || !in_array($token, self::SOLID_COLOR_TOKENS, true)) {
            return '';
        }

        return 'bg-' . $token;
    }

    /**
     * Раскраска строк списка по статусу анализа и легенда.
     * Класс bg-* и переменные --row-tone / --row-accent красят строку и образец легенды.
     *
     * @return list<array{condition: string, class: string, style: string, legend: string, title: string}>
     */
    public function statusRowStyling(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $rows = $this->db->reset()
            ->select(['cat', 'varc_name', 'varc_color'])
            ->from('t_analiz_status')
            ->order(['cat' => 'ASC'])
            ->get();

        $out = [];
        if (!is_array($rows)) {
            $cache = $out;

            return $out;
        }

        foreach ($rows as $row) {
            $arr = is_object($row) ? (array) $row : (is_array($row) ? $row : []);
            $id = (int) ($arr['cat'] ?? 0);
            $token = self::colorToken((string) ($arr['varc_color'] ?? ''));
            $name = trim((string) ($arr['varc_name'] ?? ''));
            if ($id <= 0 || $name === '' || $token === '' || !in_array($token, self::ROW_BG_TOKENS, true)) {
                continue;
            }
            $out[] = [
                'condition' => '(int) ($record[\'view_status\'] ?? 0) === ' . $id,
                'class' => 'row-tone bg-' . $token,
                'style' => '--row-tone: var(--color-bg-' . $token . '); --row-accent: var(--color-' . $token . ');',
                'legend' => $name,
                'title' => $name,
            ];
        }

        $cache = $out;

        return $out;
    }

    public static function addPacientButtonHtml(): string
    {
        return '<a href="'
            . htmlspecialchars(self::pacientFormUrl(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '" class="btn btn-primary" data-add-pacient>Добавить пациента</a>';
    }

    public static function pacientFormUrl(?string $guid = null): string
    {
        $guid = trim((string) $guid);
        if ($guid === '') {
            return self::PACIENT_FORM_URL;
        }

        return self::PACIENT_FORM_URL . rawurlencode($guid) . '/';
    }

    /**
     * Кто видит строки списка и кому показывать «Добавить пациента».
     * Порядок ролей как в легаси `gen/t_pacient.php`: лаборатория, врач, спонсор, спонсор-аудит, админ.
     * Последняя подходящая роль задаёт и фильтр, и кнопку.
     * У лаборатории и врача в списке только заявки с пациентом (`t_pacient`), как `a.cat IS NOT NULL`.
     *
     * @param array<string, mixed>|null $user auth()->getUser()
     * `doctor` — последняя подходящая роль именно врач. Тогда на телефоне страница берёт карточки, не таблицу.
     *
     * @return array{add_button: bool, doctor: bool, where: ?array{sql: string, bind: list<int>}}
     */
    public function pacientListScope(?array $user): array
    {
        $add = false;
        $doctor = false;
        $where = null;
        if ($user === null) {
            return ['add_button' => false, 'doctor' => false, 'where' => null];
        }

        $roles = is_array($user['roles_map'] ?? null) ? $user['roles_map'] : [];
        $uid = (int) ($user['id'] ?? 0);
        $profile = is_array($user['profile'] ?? null) ? $user['profile'] : [];
        $labId = (int) ($profile['view_lab'] ?? 0);
        $has = static fn (int $role): bool => array_key_exists($role, $roles);
        $withPacient = 'pac.cat IS NOT NULL';

        if ($has(RoleService::ROLE_LAB)) {
            $add = true;
            $doctor = false;
            $where = [
                'sql' => $withPacient . ' AND t_analysis.view_lab = ?',
                'bind' => [$labId],
            ];
        }
        if ($has(RoleService::ROLE_DOCTOR)) {
            $add = true;
            $doctor = true;
            $where = [
                'sql' => $withPacient . ' AND t_analysis.user_doctor = ?',
                'bind' => [$uid],
            ];
        }
        if ($has(RoleService::ROLE_SPONSOR) || $has(RoleService::ROLE_SPONSOR_AUDIT)) {
            $add = false;
            $doctor = false;
            $where = null;
        }
        if ($has(RoleService::ROLE_ADMIN)) {
            $add = true;
            $doctor = false;
            $where = null;
        }

        return ['add_button' => $add, 'doctor' => $doctor, 'where' => $where];
    }

    /**
     * Кто открывает заявку: паспорт, PDF и тот же просмотр дальше.
     * Врач — только свою (`user_doctor`). Спонсор и спонсор-аудит — нет.
     * Лаборатория, администратор и суперадмин проходят.
     *
     * @param array<string, mixed>|null $user auth()->getUser()
     * @param array<string, mixed> $analysis строка t_analysis
     */
    public function denyReason(?array $user, array $analysis): ?string
    {
        if ($user === null) {
            return self::VIEW_DENIED;
        }

        $roles = is_array($user['roles_map'] ?? null) ? $user['roles_map'] : [];
        $uid = (int) ($user['id'] ?? 0);
        $doctorId = (int) ($analysis['user_doctor'] ?? 0);
        $has = static fn (int $role): bool => array_key_exists($role, $roles);

        if ($has(RoleService::ROLE_DOCTOR) && $uid !== $doctorId) {
            return self::VIEW_DENIED;
        }
        if ($has(RoleService::ROLE_SPONSOR) || $has(RoleService::ROLE_SPONSOR_AUDIT)) {
            return self::VIEW_DENIED;
        }

        return null;
    }

    /**
     * Код уже потрачен, карточка пациента ещё не сохранена.
     * Легаси: `view_status = 0 AND view_pacient = 0 AND author_add = uid`.
     */
    public function unfinishedCodeDraftGuid(int $userId): ?string
    {
        if ($userId <= 0) {
            return null;
        }

        $row = $this->db->reset()
            ->select(['varc_guid'])
            ->from(self::TBL_ANALYSIS)
            ->where([
                'view_status' => 0,
                'view_pacient' => 0,
                'author_add' => $userId,
            ])
            ->limit(1)
            ->getOne();
        if (!$row) {
            return null;
        }

        $guid = trim((string) ($row->varc_guid ?? ''));

        return $guid !== '' ? $guid : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByGuid(string $guid): ?array
    {
        $guid = trim($guid);
        if ($guid === '') {
            return null;
        }

        $row = $this->db->reset()
            ->select(['cat', 'varc_guid', 'view_status', 'view_test', 'view_pacient', 'author_add'])
            ->from(self::TBL_ANALYSIS)
            ->where(['varc_guid' => $guid])
            ->limit(1)
            ->getOne();
        if (!$row) {
            return null;
        }

        return (array) $row;
    }

    public function typeName(int $typeId): string
    {
        if ($typeId <= 0) {
            return '';
        }

        $row = $this->db->reset()
            ->select(['varc_name'])
            ->from('t_analiztype')
            ->where(['cat' => $typeId])
            ->limit(1)
            ->getOne();

        return trim((string) ($row->varc_name ?? ''));
    }

    /**
     * Лаборатория заявки при трате кода.
     * Маршрут врача перебивает профиль. Для PTEN без маршрута — LAB_REFERENCE.
     * null — брать `t_users.view_lab` профиля.
     */
    public function routeLabId(int $doctorId, int $typeId): ?int
    {
        if ($doctorId > 0 && $typeId > 0) {
            $route = $this->db->reset()
                ->select(['view_lab'])
                ->from(self::TBL_ROUTE)
                ->where([
                    'user_doctor' => $doctorId,
                    'view_type' => $typeId,
                ])
                ->order(['cat' => 'DESC'])
                ->limit(1)
                ->getOne();
            $lab = (int) ($route->view_lab ?? 0);
            if ($lab > 0) {
                return $lab;
            }
        }

        if ($typeId === self::TYPE_PTEN) {
            return self::LAB_REFERENCE;
        }

        return null;
    }

    /**
     * Имя файла варианта карточки. Легаси `Dataprovider::getAnalysis`, поле `analyz`.
     * HRD и NGS — hrd, AKT и AKT BRCA — akt. У MLPA отдельного файла пациента в легаси нет.
     */
    public function typeSlug(int $typeId): string
    {
        return match ($typeId) {
            self::TYPE_HRD, self::TYPE_NGS => 'hrd',
            self::TYPE_AKT, self::TYPE_AKTBRCA => 'akt',
            self::TYPE_MLPA => 'mlpa',
            self::TYPE_BRCA => 'brca',
            self::TYPE_PTEN => 'pten',
            self::TYPE_HRR => 'hrr',
            default => '',
        };
    }

    /**
     * @return array<int, string>
     */
    public function males(int $typeId): array
    {
        if ($typeId === self::TYPE_HRR) {
            return [2 => 'Мужской'];
        }

        return [1 => 'Женский', 2 => 'Мужской'];
    }

    public static function passportUrl(string $guid): string
    {
        return '/manager/passport/' . rawurlencode($guid) . '/';
    }

    /**
     * Карточка паспорта: тот же набор, что у списка, плюс лаборатория и полное имя типа.
     * Легаси блок 49 / 61, пресет pacientdata.
     *
     * @return array{joins: list<array<string, string>>, addSelect: list<string>, sort: array<string, string>, sortColumnExpressions: array<string, string>, filterColumnExpressions: array<string, array<string, string>>}
     */
    public function passportQuery(): array
    {
        $query = $this->analysisGridQuery();
        $query['joins'][] = [
            'type' => 'LEFT',
            'table' => 't_lab',
            'alias' => 'lab',
            'on' => 'lab.cat = t_analysis.view_lab',
        ];
        $query['addSelect'][] = 'lab.varc_name AS lab_name';
        $query['addSelect'][] = '(SELECT ty.varc_fullname FROM t_analiztype ty WHERE ty.cat = t_analysis.view_test) AS type_fullname';

        return $query;
    }

    /**
     * Семейный анамнез, комментарии и история статуса паспорта.
     * Легаси DETAIL: блоки 55, 47 и 56.
     *
     * @return array{anamnes: list<array<string, mixed>>, comments: list<array<string, mixed>>, events: list<array<string, mixed>>}
     */
    public function passportDetails(int $analysisId): array
    {
        $empty = ['anamnes' => [], 'comments' => [], 'events' => []];
        if ($analysisId <= 0) {
            return $empty;
        }

        $author = "TRIM(CONCAT(COALESCE(u.varc_surname,''), ' ', COALESCE(u.varc_name,''), ' ', COALESCE(u.varc_patron,'')))";
        $anamnes = $this->db->reset()
            ->select(['view_anamnes', 'varc_anamnes', 'varc_kin'])
            ->from(self::TBL_ANAMNES)
            ->where(['view_analysis' => $analysisId])
            ->order(['cat' => 'ASC'])
            ->get();
        $comments = $this->db->reset()
            ->select(['c.txt_comm', 'c.created_add', $author . ' AS author_name'])
            ->from(self::TBL_COMM . ' c')
            ->join('t_users u', 'u.cat = c.author_add', 'LEFT')
            ->where(['c.view_analysis' => $analysisId])
            ->order(['c.cat' => 'ASC'])
            ->get();
        $events = $this->db->reset()
            ->select([
                'e.created_add',
                'e.txt_comment',
                't.varc_name AS type_name',
                $author . ' AS author_name',
            ])
            ->from('t_events e')
            ->join('t_events_type t', 't.cat = e.view_type', 'LEFT')
            ->join('t_users u', 'u.cat = e.author_add', 'LEFT')
            ->where(['e.cnt_object' => $analysisId])
            ->order(['e.cat' => 'DESC'])
            ->get();

        return [
            'anamnes' => array_map(static fn ($row) => (array) $row, $anamnes ?: []),
            'comments' => array_map(static fn ($row) => (array) $row, $comments ?: []),
            'events' => array_map(static fn ($row) => (array) $row, $events ?: []),
        ];
    }

    public static function codePacient(string $surname, string $name, string $patron, string $birthday): string
    {
        $digits = preg_replace('/\D+/', '', $birthday) ?? '';

        return mb_substr($surname, 0, 1) . mb_substr($name, 0, 1) . mb_substr($patron, 0, 1) . $digits;
    }

    public function birthdayTooYoung(string $isoDate): bool
    {
        $stamp = strtotime($isoDate . ' 00:00:00');
        if ($stamp === false) {
            return true;
        }

        return $stamp > time() - 18 * 365 * 86400;
    }

    /**
     * Заявка, пациент и семейный анамнез для карточки.
     *
     * @return array{analysis: array<string, mixed>, pacient: array<string, mixed>, anamnes: list<array<string, mixed>>, slug: string, type_name: string}|null
     */
    public function draftByGuid(string $guid): ?array
    {
        $guid = trim($guid);
        if ($guid === '') {
            return null;
        }

        $row = $this->db->reset()
            ->select(['*'])
            ->from(self::TBL_ANALYSIS)
            ->where(['varc_guid' => $guid])
            ->limit(1)
            ->getOne();
        if (!$row) {
            return null;
        }

        return $this->draftBundle((array) $row);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findAnalysis(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = $this->db->reset()
            ->select(['*'])
            ->from(self::TBL_ANALYSIS)
            ->where(['cat' => $id])
            ->limit(1)
            ->getOne();

        return $row ? (array) $row : null;
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public function canEditDraft(array $analysis, ?array $user): bool
    {
        $status = (int) ($analysis['view_status'] ?? -1);
        if (!in_array($status, [0, self::STATUS_DRAFT], true)) {
            return false;
        }
        if ($user === null) {
            return false;
        }

        $uid = (int) ($user['id'] ?? 0);
        if ($uid <= 0) {
            return false;
        }

        $roles = is_array($user['roles_map'] ?? null) ? $user['roles_map'] : [];
        if (array_key_exists(RoleService::ROLE_ADMIN, $roles)) {
            return true;
        }
        if ((int) ($analysis['author_add'] ?? 0) === $uid || (int) ($analysis['user_doctor'] ?? 0) === $uid) {
            return true;
        }

        $profile = is_array($user['profile'] ?? null) ? $user['profile'] : [];
        $labId = (int) ($profile['view_lab'] ?? 0);

        return array_key_exists(RoleService::ROLE_LAB, $roles)
            && $labId > 0
            && $labId === (int) ($analysis['view_lab'] ?? 0);
    }

    /**
     * Варианты справочника для столбца t_analysis (table_ext / column_ext).
     *
     * @return array<int, string>
     */
    public function columnOptions(string $column): array
    {
        $field = $this->analysisColumn($column);
        if ($field === null) {
            return [];
        }

        $table = trim((string) ($field['table_ext'] ?? ''));
        $labelColumn = trim((string) ($field['column_ext'] ?? ''));
        if ($table === '' || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return [];
        }
        if ($labelColumn === '' || !preg_match('/^[A-Za-z0-9_]+$/', $labelColumn)) {
            $labelColumn = 'varc_name';
        }

        $rows = $this->db->reset()
            ->select(['cat', $labelColumn . ' AS name'])
            ->from($table)
            ->order(['cat' => 'ASC'])
            ->get();

        return $this->optionMap($rows);
    }

    /**
     * @return array<int, string>
     */
    public function gistotypes(int $typeId): array
    {
        if ($typeId <= 0) {
            return [];
        }

        $rows = $this->db->reset()
            ->select(['cat', 'varc_name AS name'])
            ->from('t_gistotype')
            ->where('FIND_IN_SET(?, multiselect_analiztype)', [$typeId])
            ->order(['cat' => 'ASC'])
            ->get();

        return $this->optionMap($rows);
    }

    /**
     * @return array<int, string>
     */
    public function regions(): array
    {
        $rows = $this->db->reset()
            ->select(["cat", "CONCAT(COALESCE(varc_name,''), ' ', COALESCE(varc_type,'')) AS name"])
            ->from('t_geo_region')
            ->order(['varc_name' => 'ASC'])
            ->get();

        return $this->optionMap($rows);
    }

    /**
     * @return array<int, string>
     */
    public function doctorOptions(): array
    {
        $rows = $this->db->reset()
            ->select([
                't_users.cat',
                "CONCAT(COALESCE(t_users.varc_surname,''), ' ', COALESCE(t_users.varc_name,''), ' ', COALESCE(t_users.varc_patron,'')) AS name",
            ])
            ->from('t_users')
            ->join('t_access_matrix m', 'm.user_use = t_users.cat AND m.view_role = ' . RoleService::ROLE_DOCTOR)
            ->join('tblusers a', 'a.id = t_users.cat AND a.status = 1')
            ->order(['t_users.varc_surname' => 'ASC'])
            ->get();

        return $this->optionMap($rows);
    }

    /**
     * Легаси `aftersave/t_analysis.php`, ветка `$form->pacient`.
     * Оставляет в сохранении только колонки карточки, создаёт или находит пациента, ставит черновик.
     *
     * @param array<string, mixed> $dataToSave
     * @param array<string, mixed> $raw
     * @param array<string, mixed> $user
     */
    public function prepareDraftSave(array &$dataToSave, array $raw, int $analysisId, array $user): void
    {
        $analysis = $this->findAnalysis($analysisId);
        if ($analysis === null || !$this->canEditDraft($analysis, $user)) {
            throw new \RuntimeException('Карточка отправлена, редактирование невозможно.');
        }

        $pacient = is_array($raw['pacient'] ?? null) ? $raw['pacient'] : [];
        $surname = trim((string) ($pacient['varc_surname'] ?? ''));
        $name = trim((string) ($pacient['varc_name'] ?? ''));
        $hasPatron = !empty($pacient['stat_patron']);
        $patron = $hasPatron ? trim((string) ($pacient['varc_patron'] ?? '')) : '';
        $this->assertRussianName($name, 'имени');
        $this->assertRussianName($surname, 'фамилии');
        if ($hasPatron) {
            if ($patron === '') {
                throw new \RuntimeException('Заполните отчество');
            }
            $this->assertRussianName($patron, 'отчестве');
        }
        if ($surname === '' || $name === '') {
            throw new \RuntimeException('Заполните все обязательные поля');
        }

        $birthday = trim((string) ($pacient['datetext_bd'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday) || $this->birthdayTooYoung($birthday)) {
            throw new \RuntimeException('Введена некорректная дата: участие в тестировании от 18 лет [#0004]');
        }

        $male = (int) ($pacient['view_male'] ?? 0);
        $region = (int) ($pacient['view_region'] ?? 0);
        if ($male <= 0 || $region <= 0) {
            throw new \RuntimeException('Заполните все обязательные поля');
        }

        $slug = $this->typeSlug((int) ($analysis['view_test'] ?? 0));
        $flags = [];
        foreach (['stat_materialgisto', 'stat_materialblood', 'stat_materialnormal'] as $flag) {
            $flags[$flag] = !empty($raw[$flag]) ? 1 : 0;
        }
        if ($slug === 'pten') {
            $flags['stat_materialgisto'] = 1;
        }
        if ($slug !== 'mlpa' && $flags['stat_materialgisto'] + $flags['stat_materialblood'] + $flags['stat_materialnormal'] < 1) {
            throw new \RuntimeException('Надо указать хотя бы один материал');
        }
        if ($flags['stat_materialgisto'] === 1 && trim((string) ($raw['varc_markgisto'] ?? '')) === '') {
            throw new \RuntimeException('Заполните номер блока');
        }
        if ($flags['stat_materialblood'] === 1 && trim((string) ($raw['varc_markblood'] ?? '')) === '') {
            throw new \RuntimeException('Заполните маркировку крови');
        }
        if ($flags['stat_materialnormal'] === 1 && trim((string) ($raw['varc_marknormal'] ?? '')) === '') {
            throw new \RuntimeException('Заполните номер блока нормальной ткани');
        }

        $scope = $this->pacientListScope($user);
        $kept = [];
        foreach (self::CARD_COLUMNS as $column) {
            if (array_key_exists($column, $raw) && !is_array($raw[$column])) {
                $kept[$column] = $raw[$column];
            }
        }
        foreach ($flags as $column => $value) {
            $kept[$column] = $value;
        }
        if ($flags['stat_materialgisto'] !== 1) {
            $kept['varc_markgisto'] = '';
        }
        if ($flags['stat_materialblood'] !== 1) {
            $kept['varc_markblood'] = '';
        }
        if ($flags['stat_materialnormal'] !== 1) {
            $kept['varc_marknormal'] = '';
        }
        if (!empty($scope['doctor'])) {
            unset($kept['user_doctor']);
        } elseif ((int) ($kept['user_doctor'] ?? 0) <= 0) {
            throw new \RuntimeException('Укажите врача');
        }

        $this->assertDraftColumns($slug, $kept);
        foreach ($kept as $column => $value) {
            $kept[$column] = $this->cardScalar($column, $value);
        }

        $kept['view_pacient'] = $this->savePacient($surname, $name, $patron, $hasPatron, $male, $region, $birthday);
        $kept['view_status'] = self::STATUS_DRAFT;
        $dataToSave = $kept;
    }

    /**
     * Семейный анамнез и комментарий пишутся после строки анализа.
     *
     * @param array<string, mixed> $raw
     */
    public function storeDraftExtras(int $analysisId, array $raw, int $userId): void
    {
        if ($analysisId <= 0) {
            return;
        }

        $this->db->reset()->execute(
            'DELETE FROM ' . self::TBL_ANAMNES . ' WHERE view_analysis = ?',
            [$analysisId]
        );

        $anamnes = is_array($raw['anamnes'] ?? null) ? $raw['anamnes'] : [];
        $kinds = is_array($anamnes['view_anamnes'] ?? null) ? $anamnes['view_anamnes'] : [];
        $names = is_array($anamnes['varc_anamnes'] ?? null) ? $anamnes['varc_anamnes'] : [];
        $kin = is_array($anamnes['varc_kin'] ?? null) ? $anamnes['varc_kin'] : [];
        $metadata = new TableMetadataManager();
        foreach ($kinds as $index => $kind) {
            $kindId = (int) $kind;
            if ($kindId <= 0) {
                continue;
            }
            $record = $metadata->createActiveRecord(self::TBL_ANAMNES, [
                'view_analysis' => $analysisId,
                'view_anamnes' => $kindId,
                'varc_anamnes' => trim((string) ($names[$index] ?? '')),
                'varc_kin' => trim((string) ($kin[$index] ?? '')),
            ]);
            if (!$record->save()) {
                throw new \RuntimeException('Ошибка сохранения пациента [#0005]');
            }
        }

        $comment = trim((string) ($raw['_comment'] ?? ''));
        if ($comment === '' || $userId <= 0) {
            return;
        }

        $record = $metadata->createActiveRecord(self::TBL_COMM, [
            'author_add' => $userId,
            'created_add' => date('Y-m-d H:i:s'),
            'txt_comm' => strip_tags($comment),
            'view_analysis' => $analysisId,
        ]);
        if (!$record->save()) {
            throw new \RuntimeException('Ошибка сохранения пациента [#0006]');
        }
    }

    /**
     * @param array<string, mixed> $analysis
     * @return array{analysis: array<string, mixed>, pacient: array<string, mixed>, anamnes: list<array<string, mixed>>, slug: string, type_name: string}
     */
    private function draftBundle(array $analysis): array
    {
        $pacient = [];
        $pacientId = (int) ($analysis['view_pacient'] ?? 0);
        if ($pacientId > 0) {
            $row = $this->db->reset()
                ->select(['*'])
                ->from(self::TBL_PACIENT)
                ->where(['cat' => $pacientId])
                ->limit(1)
                ->getOne();
            if ($row) {
                $pacient = (array) $row;
            }
        }

        $anamnes = [];
        $analysisId = (int) ($analysis['cat'] ?? 0);
        if ($analysisId > 0) {
            $rows = $this->db->reset()
                ->select(['*'])
                ->from(self::TBL_ANAMNES)
                ->where(['view_analysis' => $analysisId])
                ->order(['cat' => 'ASC'])
                ->get();
            foreach ($rows as $row) {
                $anamnes[] = (array) $row;
            }
        }

        $typeId = (int) ($analysis['view_test'] ?? 0);

        return [
            'analysis' => $analysis,
            'pacient' => $pacient,
            'anamnes' => $anamnes,
            'slug' => $this->typeSlug($typeId),
            'type_name' => $this->typeName($typeId),
        ];
    }

    /**
     * Пустой список приходит строкой ''. Колонки view_/cnt_/user_/stat_ — целые, пустая строка в них не пишется.
     */
    private function cardScalar(string $column, mixed $value): mixed
    {
        $integer = str_starts_with($column, 'view_')
            || str_starts_with($column, 'cnt_')
            || str_starts_with($column, 'user_')
            || str_starts_with($column, 'stat_');
        if (!$integer) {
            return is_string($value) ? trim($value) : $value;
        }
        if ($value === '' || $value === null || !is_numeric($value)) {
            return 0;
        }

        return (int) $value;
    }

    /**
     * @param array<string, mixed> $kept
     */
    private function assertDraftColumns(string $slug, array $kept): void
    {
        $required = match ($slug) {
            'hrd' => ['view_diagnoz', 'view_gistotype', 'view_localization', 'view_illness', 'view_stage', 'view_myonkoan'],
            'hrr' => ['view_diagnoz', 'cnt_agediagnoz', 'cnt_glisson', 'view_myonkoan'],
            'brca' => ['view_diagnoz', 'view_gistotype', 'view_illness', 'view_stage', 'view_myonkoan'],
            'akt', 'pten' => ['view_diagnoz', 'view_gistotype', 'view_linetherapy', 'view_myonkoan'],
            default => [],
        };
        foreach ($required as $column) {
            if (trim((string) ($kept[$column] ?? '')) === '') {
                throw new \RuntimeException('Заполните все обязательные поля');
            }
        }
        if (in_array($slug, ['akt', 'pten'], true)) {
            $progress = (int) ($kept['stat_hasprogress'] ?? 0);
            if ($progress === 1 && (int) ($kept['view_confirmprogress'] ?? 0) <= 0) {
                throw new \RuntimeException('Укажите подтверждение прогрессирования');
            }
            if ($progress === 2 && (int) ($kept['view_teraphyduration'] ?? 0) <= 0) {
                throw new \RuntimeException('Укажите длительность терапии');
            }
            if ($progress !== 1 && $progress !== 2) {
                throw new \RuntimeException('Укажите наличие признаков прогрессирования');
            }
        }
        if ($slug === 'hrr') {
            $age = (int) ($kept['cnt_agediagnoz'] ?? 0);
            if ($age < 18 || $age > 64) {
                throw new \RuntimeException('Возраст до 18 или более 64 не соответствует критериям анализа');
            }
        }
        if ((int) ($kept['view_gistotype'] ?? 0) === 7 && trim((string) ($kept['varc_gistotype'] ?? '')) === '') {
            throw new \RuntimeException('Укажите своё название гистологического подтипа');
        }
        if ((int) ($kept['view_myonkoan'] ?? 0) === 6 && trim((string) ($kept['varc_myonkoan'] ?? '')) === '') {
            throw new \RuntimeException('Укажите своё название личного анамнеза');
        }
    }

    private function assertRussianName(string $value, string $part): void
    {
        if ($value !== '' && preg_match('/[a-zA-Z0-9]/', $value)) {
            throw new \RuntimeException('В ' . $part . ' пациента обнаружены английские буквы');
        }
    }

    private function savePacient(
        string $surname,
        string $name,
        string $patron,
        bool $hasPatron,
        int $male,
        int $region,
        string $birthday
    ): int {
        $code = self::codePacient($surname, $name, $patron, $birthday);
        $existing = $this->db->reset()
            ->select(['cat'])
            ->from(self::TBL_PACIENT)
            ->where([
                'varc_surname' => $surname,
                'varc_name' => $name,
                'varc_patron' => $patron,
                'datetext_bd' => $birthday,
            ])
            ->limit(1)
            ->getOne();
        if ($existing) {
            $id = (int) ($existing->cat ?? 0);
            $this->db->reset()->execute(
                'UPDATE ' . self::TBL_PACIENT . ' SET view_male = ?, view_region = ?, stat_patron = ?, varc_code = ? WHERE cat = ?',
                [$male, $region, $hasPatron ? 1 : 0, $code, $id]
            );

            return $id;
        }

        $metadata = new TableMetadataManager();
        $record = $metadata->createActiveRecord(self::TBL_PACIENT, [
            'varc_surname' => $surname,
            'varc_name' => $name,
            'varc_patron' => $patron,
            'stat_patron' => $hasPatron ? 1 : 0,
            'view_male' => $male,
            'varc_code' => $code,
            'datetext_bd' => $birthday,
            'view_region' => $region,
            'varc_guid' => Crypt::generateGuid(),
        ]);
        if (!$record->save()) {
            throw new \RuntimeException('Ошибка сохранения пациента [#0007]');
        }

        $id = (int) ($record->cat ?? $record->id ?? 0);
        if ($id <= 0) {
            throw new \RuntimeException('Ошибка сохранения пациента [#0007]');
        }

        return $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function analysisColumn(string $column): ?array
    {
        if ($this->analysisColumns === null) {
            $this->analysisColumns = [];
            $metadata = new TableMetadataManager();
            foreach ($metadata->getTableMetadata(self::TBL_ANALYSIS) as $field) {
                $name = (string) ($field['column'] ?? '');
                if ($name !== '') {
                    $this->analysisColumns[$name] = $field;
                }
            }
        }

        return $this->analysisColumns[$column] ?? null;
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<int, string>
     */
    private function optionMap(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $item = (array) $row;
            $id = (int) ($item['cat'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[$id] = trim((string) ($item['name'] ?? ''));
        }

        return $out;
    }
}
