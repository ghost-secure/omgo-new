<?php

namespace Services\Pdf;

/**
 * Одноразовый пропуск для сервиса печати.
 * Файл в runtime/pdf-tokens, внутри GUID заявки. После чтения файл удаляется.
 */
class ConclusionPrintToken
{
    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? (defined('RUNTIME_PATH') ? RUNTIME_PATH . '/pdf-tokens' : sys_get_temp_dir() . '/omgo-pdf-tokens');
    }

    public function issue(string $guid): string
    {
        $guid = strtolower(trim($guid));
        $token = $this->randomToken(64);
        if (!is_dir($this->directory) && !mkdir($this->directory, 0755, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('pdf token directory is not writable');
        }
        $path = $this->path($token);
        if (file_put_contents($path, $guid) === false) {
            throw new \RuntimeException('pdf token was not written');
        }

        return $token;
    }

    public function consume(string $token, string $guid): bool
    {
        if (preg_match('/^[0-9A-Za-z]{16,255}$/', $token) !== 1) {
            return false;
        }
        $path = $this->path($token);
        if (!is_file($path)) {
            return false;
        }
        $stored = strtolower(trim((string) file_get_contents($path)));
        @unlink($path);

        return $stored !== '' && hash_equals($stored, strtolower(trim($guid)));
    }

    private function path(string $token): string
    {
        return $this->directory . '/' . $token;
    }

    private function randomToken(int $length): string
    {
        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $max = strlen($alphabet) - 1;
        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= $alphabet[random_int(0, $max)];
        }

        return $token;
    }
}
