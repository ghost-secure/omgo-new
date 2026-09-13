<?php

namespace Services;

use Core\Auth\AuthManager;
use Helpers\Session;

/**
 * Сессионный вход по tblusers. Магазинную куку AUTH_KEY не используем.
 * Пароль: bcrypt (новое ядро) или легаси sha1(sha1(salt)+sha1(pass)) / generateDbPassword.
 */
class AuthService extends AuthManager
{
    private const SESSION_UID = 'omgo_auth_uid';

    public function isAuthenticated(): bool
    {
        if ($this->user !== null) {
            return true;
        }

        $uid = (int)Session::getValue(self::SESSION_UID);
        if ($uid <= 0) {
            return false;
        }

        $row = $this->loadAccount($uid);
        if ($row === null) {
            Session::clearValue(self::SESSION_UID);
            return false;
        }

        $this->user = $row;
        return true;
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

    public function login(string $username, string $password): bool
    {
        $row = $this->findAccountByLogin($username);
        if ($row === null || !$this->passwordMatches($row, $password)) {
            return false;
        }

        Session::setValue(self::SESSION_UID, (int)$row['id']);
        $this->user = $row;
        $this->initialized = true;
        return true;
    }

    public function logout(): void
    {
        Session::clearValue(self::SESSION_UID);
        $this->user = null;
        $this->initialized = true;
    }

    private function findAccountByLogin(string $username): ?array
    {
        $username = trim($username);
        if ($username === '') {
            return null;
        }

        foreach (['login', 'email', 'phone'] as $field) {
            $row = $this->db->reset()
                ->select(['*'])
                ->from('tblusers')
                ->where([$field => $username])
                ->getOne();

            $account = $this->normalizeAccount($row);
            if ($account !== null) {
                return $account;
            }
        }

        return null;
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

        return $row;
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
}
