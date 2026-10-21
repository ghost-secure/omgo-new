<?php

namespace Services\Report;

use Core\Database\QueryBuilder;

/**
 * Сборка xlsx по всем заявкам. Легаси: cron/reglament/report-alltest-new.php.
 * Архив до SPLIT_AT пишется один раз. Актуальный срез — каждый запуск, хранятся 30 файлов.
 */
class AnalysisReportExporter
{
    public const PAGE = 2000;

    public function __construct(
        private readonly AnalysisReportQuery $query,
        private readonly AnalysisReportFiles $files,
        private readonly QueryBuilder $db
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $started = microtime(true);
        $run = [
            'started' => date('Y-m-d H:i:s'),
            'split' => AnalysisReportColumns::SPLIT_AT,
            'dir' => $this->files->directory(),
            'reports' => [],
        ];

        foreach ($this->options() as $option => $params) {
            $archive = $this->files->archivePath($option);
            if (!is_file($archive)) {
                $run['reports'][] = $this->export(
                    $option,
                    AnalysisReportColumns::LABEL_BEFORE,
                    $this->filter($params, true),
                    $archive
                );
            } else {
                echo 'SKIP: ' . $option . ' / архив — файл уже есть: ' . basename($archive) . "\n";
                $run['reports'][] = [
                    'option' => $option,
                    'segment' => AnalysisReportColumns::LABEL_BEFORE,
                    'file' => basename($archive),
                    'status' => 'skip',
                    'seconds' => 0,
                    'rows' => 0,
                ];
            }

            $rolling = $this->files->rollingPath($option, date('Ymd'));
            $run['reports'][] = $this->export(
                $option,
                AnalysisReportColumns::LABEL_AFTER,
                $this->filter($params, false),
                $rolling
            );
            $this->files->pruneRolling($option);
            gc_collect_cycles();
        }

        $run['finished'] = date('Y-m-d H:i:s');
        $run['seconds'] = round(microtime(true) - $started, 3);
        $this->writeLog($run);

        return $run;
    }

    /**
     * @return array<string, array{code: string, where?: array<string, int>}>
     */
    private function options(): array
    {
        $options = [
            'sponsor' => ['code' => AnalysisReportColumns::SPONSOR],
            'auditor' => ['code' => AnalysisReportColumns::AUDITOR],
            'lab' => ['code' => AnalysisReportColumns::LAB],
        ];
        $labs = $this->db->reset()->select(['cat', 'stat_exportallpacient'])->from('t_lab')->get();
        foreach ($labs as $lab) {
            if ((int) ($lab['stat_exportallpacient'] ?? 0) === 0) {
                $id = (int) ($lab['cat'] ?? 0);
                if ($id > 0) {
                    $options['lab' . $id] = [
                        'code' => AnalysisReportColumns::LAB,
                        'where' => ['v.view_lab' => $id],
                    ];
                }
            }
        }

        return $options;
    }

    /**
     * @param array{code: string, where?: array<string, int>} $params
     * @return array<string, mixed>
     */
    private function filter(array $params, bool $before): array
    {
        $filter = ['code' => $params['code']];
        if (!empty($params['where'])) {
            $filter['where'] = $params['where'];
        }
        if ($before) {
            $filter['createdBefore'] = AnalysisReportColumns::SPLIT_AT;
        } else {
            $filter['createdFrom'] = AnalysisReportColumns::SPLIT_AT;
        }

        return $filter;
    }

    /**
     * @param array<string, mixed> $filter
     * @return array<string, mixed>
     */
    private function export(string $option, string $segment, array $filter, string $path): array
    {
        $started = microtime(true);
        $offset = 0;
        $page = 0;
        $written = 0;
        $sheet = null;
        $code = (string) $filter['code'];
        unset($filter['code']);

        try {
            $titles = AnalysisReportColumns::titles($code);
            while (true) {
                $rows = $this->query->rows($code, $filter, self::PAGE, $offset);
                $page++;
                if ($rows === []) {
                    if ($page === 1) {
                        echo 'SKIP: ' . $option . ' / ' . $segment . " — нет строк\n";
                    }
                    break;
                }
                if ($sheet === null) {
                    $sheet = $this->openSheet($titles, $path);
                    echo 'START: ' . $option . ' / ' . $segment . "\n";
                }
                foreach ($rows as $row) {
                    $line = [];
                    foreach ($this->query->cells($code, $row) as $cell) {
                        $line[] = ($cell === '' || $cell === null) ? '-' : (string) $cell;
                    }
                    $sheet->data($line);
                    $written++;
                }
                echo ' └ page: ' . $page . ' / rows ' . $written . "\n";
                if (count($rows) < self::PAGE) {
                    break;
                }
                $offset += self::PAGE;
                unset($rows);
                if ($page % 5 === 0) {
                    gc_collect_cycles();
                }
            }

            if ($sheet === null) {
                return $this->result($option, $segment, $path, 'ok', $started, 0);
            }

            $sheet->finish();
            $sheet = null;
            gc_collect_cycles();
            echo '└ Saved: ' . basename($path) . ' / rows ' . $written . "\n";

            return $this->result($option, $segment, $path, 'ok', $started, $written);
        } catch (\Throwable $e) {
            if ($sheet !== null) {
                $sheet->abort();
            }
            echo 'ERROR: ' . $option . ' / ' . $segment . ' / ' . $e->getMessage() . "\n";

            return $this->result($option, $segment, $path, 'error', $started, $written, $e->getMessage());
        }
    }

    /**
     * @param list<string> $titles
     */
    private function openSheet(array $titles, string $path): AnalysisReportSheet
    {
        $sheet = new AnalysisReportSheet($path);
        try {
            $sheet->begin(count($titles));
            $sheet->title('Отчет по всем пациентам на дату ' . date('d.m.Y H:i:s'));
            $sheet->header($titles);
        } catch (\Throwable $e) {
            $sheet->abort();
            throw $e;
        }

        return $sheet;
    }

    /**
     * @return array<string, mixed>
     */
    private function result(
        string $option,
        string $segment,
        string $path,
        string $status,
        float $started,
        int $rows,
        ?string $error = null
    ): array {
        $result = [
            'option' => $option,
            'segment' => $segment,
            'file' => basename($path),
            'status' => $status,
            'seconds' => round(microtime(true) - $started, 3),
            'rows' => $rows,
        ];
        if ($error !== null) {
            $result['error'] = $error;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $run
     */
    private function writeLog(array $run): void
    {
        $path = rtrim(LOG_PATH, '/\\') . DIRECTORY_SEPARATOR . 'report.log';
        $json = json_encode($run, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            echo "LOG: не удалось собрать json\n";

            return;
        }
        if (file_put_contents($path, $json . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            echo 'LOG: не удалось записать ' . $path . "\n";

            return;
        }
        echo 'LOG: ' . $path . "\n";
    }
}
