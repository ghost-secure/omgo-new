<?php

namespace Services\Pdf;

use Core\Database\QueryBuilder;
use Services\AnalysisService;

/**
 * HTML заключения. Легаси Object\PDF\PDF.
 * Папку выбирает view_test. Разметка — template.php этой папки, запрос — conclusionQuery().
 * Роль папку не выбирает.
 */
class ConclusionDocument
{
    /** HRD, NGS и тип без своей папки. */
    public const TEMPLATE_DEFAULT = 'default';

    /** @var array<int, string> view_test => папка в UC/dynblock/t_analysis/conclusion */
    public const TEMPLATE_BY_TYPE = [
        AnalysisService::TYPE_AKT => 'akt',
        AnalysisService::TYPE_AKTBRCA => 'akt',
        AnalysisService::TYPE_MLPA => 'mlpa',
        AnalysisService::TYPE_BRCA => 'brca',
        AnalysisService::TYPE_HRR => 'hrr',
        AnalysisService::TYPE_PTEN => 'pten',
    ];

    public function __construct(private QueryBuilder $db)
    {
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

        $stmt = $this->db->execute(
            'SELECT * FROM t_analysis WHERE varc_guid = ? LIMIT 1',
            [$guid]
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Запрос строки заключения: пациент, врач, лаборатория, методика.
     *
     * @return array{short: bool, joins: list<array<string, string>>, addSelect: list<string>, staticWhere: list<array{sql: string, bind: list<string>}>}
     */
    public function conclusionQuery(string $guid): array
    {
        return [
            'short' => false,
            'joins' => [
                ['type' => 'LEFT', 'table' => 't_pacient', 'alias' => 'pac', 'on' => 'pac.cat = t_analysis.view_pacient'],
                ['type' => 'LEFT', 'table' => 't_users', 'alias' => 'doc', 'on' => 'doc.cat = t_analysis.user_doctor'],
                ['type' => 'INNER', 'table' => 't_analiztype', 'alias' => 'aty', 'on' => 'aty.cat = t_analysis.view_test'],
                ['type' => 'LEFT', 'table' => 't_lab', 'alias' => 'lab', 'on' => 'lab.cat = t_analysis.view_lab'],
                ['type' => 'LEFT', 'table' => 't_lab_metodic', 'alias' => 'lm', 'on' => 'lm.view_lab = lab.cat AND lm.view_analiztype = t_analysis.view_test'],
            ],
            'addSelect' => [
                'lab.varc_name AS lab_name',
                'lab.varc_resp AS lab_resp',
                'lab.varc_phone AS lab_phone',
                'lab.varc_email AS lab_email',
                'lab.varc_fullname AS lab_fullname',
                "CONCAT(doc.varc_surname, ' ', doc.varc_name, ' ', doc.varc_patron) AS doctor_name",
                "CONCAT(pac.varc_surname, ' ', pac.varc_name, ' ', pac.varc_patron) AS pacient_name",
                'pac.datetext_bd AS pacient_bd',
                'aty.varc_fullname AS type_fullname',
                'lm.txt_maxname AS type_maxname',
                'lm.txt_patogenyes AS txt_patogenyes',
                'lm.txt_patogenno AS txt_patogenno',
                'lm.txt_onkogenyes AS txt_onkogenyes',
                'lm.txt_geneticgisto AS txt_gisto',
                'lm.txt_geneticblood AS txt_blood',
            ],
            'staticWhere' => [
                [
                    'sql' => 't_analysis.varc_guid = ?',
                    'bind' => [$guid],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    public function html(array $row): string
    {
        $slug = self::templateSlug((int) ($row['view_test'] ?? 0));
        $guid = (string) ($row['varc_guid'] ?? '');
        $html = (new \UC\DynBlock(
            't_analysis',
            't_analysis/conclusion/' . $slug,
            1,
            1,
            $this->conclusionQuery($guid)
        ))->render();

        return self::clearHtml($html);
    }

    public static function templateSlug(int $typeId): string
    {
        return self::TEMPLATE_BY_TYPE[$typeId] ?? self::TEMPLATE_DEFAULT;
    }

    public static function clearHtml(string $html): string
    {
        $html = str_replace(["\n", "\r\n", "\r"], ' ', $html);
        $html = preg_replace('~\s*(<([^>]*)>[^<]*</\2>|<[^>]*>)\s*~', '$1', $html) ?? $html;

        return ltrim($html, "\x00..\x1F");
    }
}
