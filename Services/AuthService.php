<?php

namespace Services;

use Core\Auth\AuthManager;
use Core\Auth\UserAccount;
use Core\Log\ModeratorLog;
use Core\Request;
use Core\Security\Crypto;
use Helpers\Session;

/**
 * Вход по tblusers: сессия и кука AUTH_KEY до 1 января 2030, как на test.
 * Пароль: bcrypt (новое ядро) или легаси sha1(sha1(salt)+sha1(pass)) / generateDbPassword.
 * Лимит попыток — ядро, AuthManager::login(). Ниже пустой контракт, если проект захочет другие числа или выключить лимит.
 */
class AuthService extends AuthManager
{
    private const SESSION_UID = 'omgo_auth_uid';
    private const SESSION_IMPERSONATOR = 'omgo_auth_impersonator';
    private const AUTH_COOKIE = 'AUTH_KEY';

    public function isAuthenticated(): bool
    {
        if ($this->user !== null) {
            return true;
        }

        $uid = (int)Session::getValue(self::SESSION_UID);
        if ($uid > 0) {
            $row = $this->loadAccount($uid);
            if ($row !== null) {
                $this->user = $row;
                return true;
            }
            Session::clearValue(self::SESSION_UID);
            Session::clearValue(self::SESSION_IMPERSONATOR);
        }

        return $this->authenticateByCookie();
    }

    /**
     * Пустой контракт лимита входа: свои числа не задаём, действует ядро и env.
     * Вернуть null — выключить лимит. Вернуть max и window — подменить env.
     *
     * @return array{max:int,window:int}|null
     */
    protected function loginAttemptLimit(): ?array
    {
        return parent::loginAttemptLimit();
    }

    public function getUser(): ?array
    {
        if (!$this->initialized) {
            $this->isAuthenticated();
            $this->initialized = true;
        }

        return $this->user;
    }

    public function getUserId(): ?int
    {
        $user = $this->getUser();
        return isset($user['id']) ? (int)$user['id'] : null;
    }

    public function checkPassword(string $username, string $password): bool
    {
        $row = $this->findAccountByLogin($username);
        if ($row === null) {
            return false;
        }

        return $this->passwordMatches($row, $password);
    }

    protected function authenticate(string $username, string $password): bool
    {
        $row = $this->findAccountByLogin($username);
        if ($row === null || !$this->passwordMatches($row, $password)) {
            return false;
        }

        Session::setValue(self::SESSION_UID, (int)$row['id']);
        $this->user = $row;
        $this->initialized = true;
        $this->setAuthCookie($row, $username);
        ModeratorLog::onAuth((int)$row['id']);
        return true;
    }

    public function logout(): void
    {
        Session::clearValue(self::SESSION_UID);
        Session::clearValue(self::SESSION_IMPERSONATOR);
        $this->clearAuthCookie();
        $this->user = null;
        $this->initialized = true;
    }

    public function isImpersonating(): bool
    {
        return $this->getImpersonatorId() !== null;
    }

    public function getImpersonatorId(): ?int
    {
        $id = (int) Session::getValue(self::SESSION_IMPERSONATOR);

        return $id > 0 ? $id : null;
    }

    /**
     * Легаси: панель входа видна при tblusers.role &lt; 3.
     */
    public function canStartImpersonation(): bool
    {
        if ($this->isImpersonating()) {
            return false;
        }

        $user = $this->getUser();
        if ($user === null) {
            return false;
        }

        $role = (int) ($user['role'] ?? 0);

        return $role > 0 && $role < 3;
    }

    public function loginAsUser(int $targetUserId): bool
    {
        if ($targetUserId <= 0) {
            return false;
        }

        $currentId = $this->getUserId();
        if ($currentId === null) {
            return false;
        }

        $impersonatorId = $this->getImpersonatorId();
        if ($impersonatorId === null) {
            if (!$this->canStartImpersonation()) {
                return false;
            }
            $impersonatorId = $currentId;
        }

        if ($targetUserId === $currentId) {
            return true;
        }

        if ($targetUserId === $impersonatorId) {
            return $this->exitImpersonation();
        }

        $target = $this->loadAccount($targetUserId);
        if ($target === null) {
            return false;
        }

        Session::setValue(self::SESSION_IMPERSONATOR, $impersonatorId);
        Session::setValue(self::SESSION_UID, $targetUserId);
        $this->user = $target;
        $this->initialized = true;

        return true;
    }

    public function exitImpersonation(): bool
    {
        $initiator = $this->getImpersonatorId();
        if ($initiator === null) {
            return false;
        }

        $account = $this->loadAccount($initiator);
        Session::clearValue(self::SESSION_IMPERSONATOR);
        if ($account === null) {
            $this->logout();

            return false;
        }

        Session::setValue(self::SESSION_UID, $initiator);
        $this->user = $account;
        $this->initialized = true;

        return true;
    }

    private function findAccountByLogin(string $username): ?array
    {
        $username = trim($username);
        if ($username === '') {
            return null;
        }

        foreach ($this->loginCandidates($username) as $candidate) {
            foreach (['login', 'email', 'phone'] as $field) {
                $row = $this->db->reset()
                    ->select(['*'])
                    ->from('tblusers')
                    ->where([$field => $candidate])
                    ->getOne();

                $account = $this->normalizeAccount($row);
                if ($account !== null) {
                    return $account;
                }
            }
        }

        return null;
    }

    /**
     * Смена пароля в формате легаси: salt + base64(sha1(pass.salt)).
     * $activate — как AddUser при восстановлении: status становится 1.
     */
    public function setPassword(int $userId, string $password, bool $activate = false): bool
    {
        if ($userId <= 0 || $password === '') {
            return false;
        }

        $salt = $this->generateSalt();
        $passwd = $this->generateDbPassword($password, $salt);
        $sql = 'UPDATE `tblusers` SET `salt` = ?, `passwd` = ?';
        $params = [$salt, $passwd];
        if ($activate) {
            $sql .= ', `status` = 1';
        }
        $sql .= ' WHERE `id` = ?';
        $params[] = $userId;
        $stmt = $this->db->execute($sql, $params);

        return $stmt instanceof \PDOStatement && $stmt->rowCount() > 0;
    }

    /**
     * Новая учётка с явным id = t_users.cat (FK tblusers.id → t_users.cat).
     * status 0 — до подтверждения администратором вход закрыт.
     */
    public function insertCredentials(int $id, string $email, string $phone, string $password, int $role, int $status): bool
    {
        if ($id <= 0 || $email === '' || $password === '') {
            return false;
        }

        $salt = $this->generateSalt();
        $passwd = $this->generateDbPassword($password, $salt);
        $hash = substr(sha1($salt . uniqid('', true)), 0, 32);
        $now = time();
        $stmt = $this->db->execute(
            'INSERT INTO `tblusers` (`id`, `login`, `email`, `phone`, `passwd`, `salt`, `hash`, `regdate`, `visdate`, `status`, `role`)
             VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $email, $phone, $passwd, $salt, $hash, $now, $now, $status, $role]
        );

        return $stmt instanceof \PDOStatement;
    }

    /**
     * Логин как в легаси: строка как есть и телефон 7/8 + 10 цифр.
     *
     * @return list<string>
     */
    private function loginCandidates(string $username): array
    {
        $username = trim($username);
        if ($username === '') {
            return [];
        }

        $list = [$username];
        if (strpos($username, '@') !== false) {
            return $list;
        }

        $digits = preg_replace('/\D+/', '', $username);
        if (!is_string($digits)) {
            return $list;
        }

        if (strlen($digits) === 11) {
            $tail = substr($digits, 1, 10);
            $list[] = '7' . $tail;
            $list[] = '8' . $tail;
        } elseif (strlen($digits) === 10) {
            $list[] = '7' . $digits;
            $list[] = '8' . $digits;
        }

        return array_values(array_unique($list));
    }

    private function loadAccount(int $id): ?array
    {
        $row = $this->db->reset()
            ->select(['*'])
            ->from('tblusers')
            ->where(['id' => $id])
            ->getOne();

        return $this->normalizeAccount($row);
    }

    private function normalizeAccount($row): ?array
    {
        if ($row === null) {
            return null;
        }

        $row = (array)$row;
        if (empty($row['id'])) {
            return null;
        }
        if (array_key_exists('status', $row) && (int)$row['status'] === 0) {
            return null;
        }

        return (new UserAccount($row))->toArray();
    }

    private function passwordMatches(array $row, string $password): bool
    {
        $stored = (string)($row['passwd'] ?? '');
        if ($stored !== '' && preg_match('/^\$2[ayb]\$/', $stored) && password_verify($password, $stored)) {
            return true;
        }

        $salt = (string)($row['salt'] ?? '');
        if ($salt !== '' && $stored !== '' && hash_equals($this->hashPassword($salt, $password), $stored)) {
            return true;
        }

        $legacyDb = $this->generateDbPassword($password, $salt);
        if ($stored !== '' && hash_equals($legacyDb, $stored)) {
            return true;
        }

        return false;
    }

    /**
     * Кука как на test: логин, хэш пароля из базы и IP. Срок — 1 января 2030.
     * Вход под пользователем сюда не пишется, он остаётся в сессии.
     *
     * @param array<string, mixed> $user
     */
    private function setAuthCookie(array $user, string $username): void
    {
        $dbPass = (string)($user['passwd'] ?? '');
        if ($username === '' || $dbPass === '') {
            return;
        }

        $json = json_encode([
            'ip' => Request::createFromGlobals()->ip(),
            'username' => $username,
            'db_pass' => $dbPass,
        ], JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return;
        }

        $encrypted = Crypto::encrypt($json, 'auth_cookie');
        if ($encrypted === null) {
            return;
        }

        $this->writeAuthCookie($encrypted, mktime(0, 0, 0, 1, 1, 2030));
    }

    private function clearAuthCookie(): void
    {
        $this->writeAuthCookie('', time() - 3600);
        unset($_COOKIE[self::AUTH_COOKIE]);
    }

    private function writeAuthCookie(string $value, int $expires): void
    {
        setcookie(self::AUTH_COOKIE, $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function authenticateByCookie(): bool
    {
        $token = (string)($_COOKIE[self::AUTH_COOKIE] ?? '');
        if ($token === '') {
            return false;
        }

        $plain = Crypto::decrypt($token, 'auth_cookie');
        if ($plain === null) {
            return false;
        }

        $data = json_decode($plain, true);
        if (!is_array($data)) {
            return false;
        }

        $username = (string)($data['username'] ?? '');
        $dbPass = (string)($data['db_pass'] ?? '');
        $ip = (string)($data['ip'] ?? '');
        if ($username === '' || $dbPass === '') {
            return false;
        }

        $currentIp = Request::createFromGlobals()->ip();
        $remoteAddr = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if ($ip !== $currentIp && $ip !== $remoteAddr) {
            return false;
        }

        $row = $this->findAccountByLogin($username);
        if ($row === null) {
            $this->clearAuthCookie();
            return false;
        }

        $stored = (string)($row['passwd'] ?? '');
        if ($stored === '' || strlen($stored) !== strlen($dbPass) || !hash_equals($stored, $dbPass)) {
            $this->clearAuthCookie();
            return false;
        }

        Session::setValue(self::SESSION_UID, (int)$row['id']);
        $this->user = $row;
        $this->initialized = true;

        return true;
    }
}
