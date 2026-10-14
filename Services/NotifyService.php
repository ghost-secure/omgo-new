<?php

namespace Services;

use Core\Config;
use Core\Container;
use Core\Database\QueryBuilder;
use Core\Mail\AbstractMailer;

/**
 * Письмо и колокольчик по строке t_notify_type (легаси Object\Notify\Notify).
 * Push не шлём.
 */
class NotifyService
{
    private QueryBuilder $db;

    public function __construct(?QueryBuilder $db = null)
    {
        $this->db = $db ?? Container::getInstance()->get(QueryBuilder::class);
    }

    /**
     * @param list<array{id?:int,email?:string,varc_name?:string,varc_patron?:string}> $recipients
     * @param array<string, scalar|null> $vars
     */
    public function sendType(int $typeId, array $recipients, array $vars): bool
    {
        if ($typeId <= 0 || $recipients === []) {
            return false;
        }

        $type = $this->loadType($typeId);
        if ($type === null || (int) ($type['stat_active'] ?? 0) !== 1) {
            return false;
        }

        $mailOn = (int) ($type['stat_mail'] ?? 0) === 1;
        $bellOn = (int) ($type['stat_notify'] ?? 0) === 1;
        $sent = false;

        foreach ($recipients as $recipient) {
            $email = trim((string) ($recipient['email'] ?? ''));
            $rendered = $this->render($type, $vars, $recipient);

            if ($mailOn) {
                if ($email === '' || !$this->mailer()->send($email, $rendered['theme'], $rendered['html'])) {
                    return false;
                }
                $sent = true;
            }

            $uid = (int) ($recipient['id'] ?? 0);
            if ($bellOn && $uid > 0) {
                $this->db->execute(
                    'INSERT INTO `t_notify` (`view_type`, `varc_message`, `user_recipient`, `created_add`) VALUES (?, ?, ?, ?)',
                    [$typeId, mb_substr($rendered['bell'], 0, 255), $uid, date('Y-m-d H:i:s')]
                );
            }
        }

        return !$mailOn || $sent;
    }

    /**
     * @return list<array{id:int,email:string,varc_name:string,varc_patron:string}>
     */
    public function adminRecipients(): array
    {
        $stmt = $this->db->execute(
            'SELECT u.id, u.email, p.varc_name, p.varc_patron
             FROM tblusers u
             JOIN t_users p ON p.cat = u.id
             WHERE u.email IS NOT NULL AND u.email <> \'\'
               AND EXISTS (
                   SELECT 1 FROM t_access_matrix m
                   WHERE m.user_use = u.id AND m.view_role = ?
               )',
            [RoleService::ROLE_ADMIN]
        );
        if (!$stmt instanceof \PDOStatement) {
            return [];
        }

        $rows = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $rows[] = [
                'id' => (int) $row['id'],
                'email' => (string) $row['email'],
                'varc_name' => (string) ($row['varc_name'] ?? ''),
                'varc_patron' => (string) ($row['varc_patron'] ?? ''),
            ];
        }

        return $rows;
    }

    private function loadType(int $typeId): ?array
    {
        $stmt = $this->db->execute('SELECT * FROM t_notify_type WHERE cat = ? LIMIT 1', [$typeId]);
        if (!$stmt instanceof \PDOStatement) {
            return null;
        }
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $type
     * @param array<string, scalar|null> $vars
     * @param array<string, mixed> $recipient
     * @return array{theme:string,html:string,bell:string}
     */
    private function render(array $type, array $vars, array $recipient): array
    {
        $accepter = trim(((string) ($recipient['varc_name'] ?? '')) . ' ' . ((string) ($recipient['varc_patron'] ?? '')));
        $vars['accepter'] = $accepter;

        $fields = array_filter(array_map('trim', explode(',', (string) ($type['varc_fields'] ?? ''))));
        $fields[] = 'accepter';

        $pattern = html_entity_decode((string) ($type['txt_pattern'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $theme = html_entity_decode((string) ($type['varc_theme'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $bell = html_entity_decode((string) ($type['varc_bell'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        foreach ($fields as $field) {
            $value = htmlspecialchars((string) ($vars[$field] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $pattern = str_replace('{' . $field . '}', $value, $pattern);
            $theme = str_replace('{' . $field . '}', $value, $theme);
            $bell = str_replace('{' . $field . '}', $value, $bell);
        }

        $hour = (int) date('H');
        $intro = 'Добрый день';
        if ($hour < 11) {
            $intro = 'Доброе утро';
        } elseif ($hour > 17) {
            $intro = 'Добрый вечер';
        }

        $config = Config::getInstance();
        $base = rtrim((string) $config->get('app.base_url', ''), '/');
        $company = (string) $config->get('site.title', 'OMGO');

        $html = '<p><strong>' . htmlspecialchars($intro, ENT_QUOTES, 'UTF-8') . ', '
            . htmlspecialchars($accepter !== '' ? $accepter : 'коллега', ENT_QUOTES, 'UTF-8')
            . '!</strong></p>'
            . $pattern
            . '<p><a href="' . htmlspecialchars($base !== '' ? $base : '/', ENT_QUOTES, 'UTF-8') . '">Перейти на сайт</a></p>'
            . '<p style="font-size:12px">' . htmlspecialchars($company, ENT_QUOTES, 'UTF-8')
            . '<br>Пожалуйста, не отвечайте на это письмо, оно сформировано системой автоматически.</p>';

        return [
            'theme' => $theme !== '' ? $theme : 'OMGO',
            'html' => $html,
            'bell' => $bell,
        ];
    }

    private function mailer(): AbstractMailer
    {
        $container = Container::getInstance();
        if ($container->has('mailer')) {
            $mailer = $container->get('mailer');
            if ($mailer instanceof AbstractMailer) {
                return $mailer;
            }
        }

        return new MailService();
    }
}
