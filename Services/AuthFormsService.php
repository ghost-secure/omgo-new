<?php

namespace Services;

use Core\Auth\UserAccount;
use Core\Container;
use Core\Database\QueryBuilder;
use Core\Security\RedirectValidator;
use Helpers\Formatter;

/**
 * Формы /login, /reg, /recover. Логика легаси auth_.php, reg_.php, recover_.php.
 * Токен и домен легаси здесь не проверяем: это CSRF ядра на JsAPI.
 */
class AuthFormsService
{
    public const TYPE_RECOVER = 8;
    public const TYPE_REG_NEW = 10;
    public const STATUS_MODERATE = 1;

    private QueryBuilder $db;
    private AuthService $auth;

    public function __construct(?QueryBuilder $db = null, ?AuthService $auth = null)
    {
        $container = Container::getInstance();
        $this->db = $db ?? $container->get(QueryBuilder::class);
        $resolved = $auth ?? ($container->has('auth') ? $container->get('auth') : null);
        if (!$resolved instanceof AuthService) {
            $resolved = new AuthService();
        }
        $this->auth = $resolved;
    }

    /**
     * @param array<string, mixed> $post
     * @return array{ok:bool,message:string,errors?:array<string,string>,redirect?:string}
     */
    public function login(array $post): array
    {
        $username = trim((string) ($post['username'] ?? $post['login'] ?? ''));
        $password = (string) ($post['password'] ?? '');
        $errors = [];
        if ($username === '') {
            $errors['username'] = 'Не указано значение';
        }
        if ($password === '') {
            $errors['password'] = 'Не указано значение';
        }
        if ($errors !== []) {
            return $this->fail('Не заполнены обязательные поля.', $errors);
        }

        if (!$this->auth->login($username, $password)) {
            return $this->fail('Неверный логин или пароль');
        }

        $redirect = RedirectValidator::validate((string) ($post['redirect'] ?? ''), '/manager/');

        return [
            'ok' => true,
            'message' => 'Вход выполнен',
            'redirect' => $redirect,
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array{ok:bool,message:string,errors?:array<string,string>}
     */
    public function recover(array $post): array
    {
        if (trim((string) ($post['email'] ?? '')) !== '') {
            return $this->fail('Ошибка восстановления пароля');
        }

        $email = Formatter::toEmail((string) ($post['semail'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->fail('Ошибка формата E-Mail', ['semail' => 'Неверный формат']);
        }

        $stmt = $this->db->execute(
            'SELECT id, login, email, phone, role FROM tblusers WHERE email = ? OR login = ? LIMIT 1',
            [$email, $email]
        );
        $row = $stmt instanceof \PDOStatement ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
        if (!is_array($row) || (int) ($row['id'] ?? 0) <= 0) {
            return $this->fail('E-Mail не найден', ['semail' => 'Не найден']);
        }

        $userId = (int) $row['id'];
        $password = random_int(1111, 9999) . '-' . random_int(1111, 9999);

        $this->db->beginTransaction();
        try {
            if (!$this->auth->setPassword($userId, $password, true)) {
                $this->db->rollback();

                return $this->fail('Не удалось обновить пароль');
            }

            $profile = $this->profileName($userId);
            $base = rtrim((string) \Core\Config::getInstance()->get('app.base_url', ''), '/');
            $sent = (new NotifyService($this->db))->sendType(self::TYPE_RECOVER, [[
                'id' => $userId,
                'email' => (string) ($row['email'] ?: $email),
                'varc_name' => $profile['varc_name'],
                'varc_patron' => $profile['varc_patron'],
            ]], [
                'password' => $password,
                'url' => ($base !== '' ? $base : '') . '/login',
            ]);
            if (!$sent) {
                $this->db->rollback();

                return $this->fail('Не удалось отправить письмо с новым паролем');
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->safeRollback();
            error_log('AuthFormsService recover: ' . $e->getMessage());

            return $this->fail('Не удалось обновить пароль');
        }

        return [
            'ok' => true,
            'message' => 'По указанному адресу отправлено письмо с новым паролем',
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array{ok:bool,message:string,errors?:array<string,string>}
     */
    public function register(array $post): array
    {
        if ($this->auth->isAuthenticated()) {
            return $this->fail('Вы уже авторизованы');
        }
        if (trim((string) ($post['email'] ?? '')) !== '') {
            return $this->fail('Ошибка регистрации');
        }

        $form = [
            'surname' => Formatter::toLetters((string) ($post['surname'] ?? '')),
            'name' => Formatter::toLetters((string) ($post['name'] ?? '')),
            'patron' => Formatter::toLetters((string) ($post['patron'] ?? '')),
            'phone' => $this->phoneDigits((string) ($post['phone'] ?? '')),
            'semail' => Formatter::toEmail((string) ($post['semail'] ?? '')),
            'med' => trim(strip_tags((string) ($post['med'] ?? ''))),
            'address' => str_replace(['–'], ['-'], trim(strip_tags((string) ($post['address'] ?? '')))),
        ];

        $errors = [];
        foreach (['surname', 'name', 'patron', 'semail', 'phone', 'address', 'med'] as $field) {
            if ($form[$field] === '') {
                $errors[$field] = 'Не указано значение';
            }
        }
        if ($errors !== []) {
            return $this->fail('Не заполнены обязательные поля.', $errors);
        }
        if (!filter_var($form['semail'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['semail']) > 50) {
            return $this->fail('Ошибка формата E-Mail', ['semail' => 'Неверный формат E-Mail']);
        }
        if (strlen($form['phone']) !== 11) {
            return $this->fail('Ошибка формата телефона', ['phone' => 'Укажите номер в формате +7']);
        }

        $taken = $this->db->execute(
            'SELECT id, email, phone FROM tblusers WHERE email = ? OR phone = ? LIMIT 1',
            [$form['semail'], $form['phone']]
        );
        $takenRow = $taken instanceof \PDOStatement ? $taken->fetch(\PDO::FETCH_ASSOC) : false;
        if (is_array($takenRow) && (int) ($takenRow['id'] ?? 0) > 0) {
            if ((string) ($takenRow['phone'] ?? '') === $form['phone']) {
                return $this->fail('Телефонный номер уже присутствует в системе', ['phone' => 'Укажите другое значение']);
            }
            if (strcasecmp((string) ($takenRow['email'] ?? ''), $form['semail']) === 0) {
                return $this->fail('E-Mail уже присутствует в системе', ['semail' => 'Укажите другое значение']);
            }
        }

        $address = (new AddressLookupService($this->db))->resolve($form['address']);
        if ($address === null || (int) $address['view_region'] <= 0) {
            return $this->fail('Не удалось распознать адрес', [
                'address' => 'Введите правильный адрес',
            ]);
        }

        $medId = $this->medId($form['med']);
        $medCheck = $medId > 0 ? '(есть в системе, #' . $medId . ')' : '(нет в системе)';

        $this->db->beginTransaction();
        try {
            $profileId = (int) $this->db->execute(
                'INSERT INTO `t_users`
                    (`varc_surname`, `varc_name`, `varc_patron`, `address_dadata`, `view_region`, `view_med`, `view_status`)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $form['surname'],
                    $form['name'],
                    $form['patron'],
                    $address['address_dadata'],
                    $address['view_region'],
                    $medId,
                    self::STATUS_MODERATE,
                ],
                true
            );
            if ($profileId <= 0) {
                throw new \RuntimeException('profile id empty');
            }

            $plain = bin2hex(random_bytes(8));
            if (!$this->auth->insertCredentials(
                $profileId,
                $form['semail'],
                $form['phone'],
                $plain,
                UserAccount::MANAGER,
                0
            )) {
                throw new \RuntimeException('credentials insert failed');
            }

            $this->db->execute(
                'INSERT INTO `t_access_matrix` (`created_add`, `user_use`, `view_role`) VALUES (?, ?, ?)',
                [date('Y-m-d H:i:s'), $profileId, RoleService::ROLE_DOCTOR]
            );
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->safeRollback();
            error_log('AuthFormsService register: ' . $e->getMessage());

            return $this->fail('Не получается создать пользователя');
        }

        try {
            $notify = new NotifyService($this->db);
            $notify->sendType(self::TYPE_REG_NEW, $notify->adminRecipients(), [
                'fio' => trim($form['surname'] . ' ' . $form['name'] . ' ' . $form['patron']),
                'email' => $form['semail'],
                'phone' => $form['phone'],
                'med' => $form['med'],
                'medcheck' => $medCheck,
                'address' => $form['address'],
            ]);
        } catch (\Throwable $e) {
            error_log('AuthFormsService register notify: ' . $e->getMessage());
        }

        return [
            'ok' => true,
            'message' => 'Спасибо за регистрацию! После подтверждения администратором на вашу почту будет отправлен пароль для входа',
        ];
    }

    /**
     * @param array<string, string> $errors
     * @return array{ok:bool,message:string,errors?:array<string,string>}
     */
    private function fail(string $message, array $errors = []): array
    {
        $result = ['ok' => false, 'message' => $message];
        if ($errors !== []) {
            $result['errors'] = $errors;
        }

        return $result;
    }

    private function phoneDigits(string $phone): string
    {
        $digits = Formatter::toPhoneDigits($phone, 0);
        if (strlen($digits) === 10) {
            $digits = '7' . $digits;
        }
        if (strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '7' . substr($digits, 1);
        }

        return $digits;
    }

    private function medId(string $name): int
    {
        $name = mb_substr(trim($name), 0, 255);
        if ($name === '') {
            return 0;
        }
        $stmt = $this->db->execute('SELECT cat FROM t_med WHERE varc_name = ? LIMIT 1', [$name]);
        $row = $stmt instanceof \PDOStatement ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;

        return is_array($row) ? (int) ($row['cat'] ?? 0) : 0;
    }

    /**
     * @return array{varc_name:string,varc_patron:string}
     */
    private function profileName(int $userId): array
    {
        $stmt = $this->db->execute(
            'SELECT varc_name, varc_patron FROM t_users WHERE cat = ? LIMIT 1',
            [$userId]
        );
        $row = $stmt instanceof \PDOStatement ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;

        return [
            'varc_name' => is_array($row) ? (string) ($row['varc_name'] ?? '') : '',
            'varc_patron' => is_array($row) ? (string) ($row['varc_patron'] ?? '') : '',
        ];
    }

    private function safeRollback(): void
    {
        try {
            $this->db->rollback();
        } catch (\Throwable $e) {
        }
    }
}
