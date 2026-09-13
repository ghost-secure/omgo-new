<?php

if (!function_exists('env_or_default')) {
    function env_or_default(string $key, $default = null)
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }
        return $value;
    }
}

return [
    'sql_host' => (string)env_or_default('DB_MYSQL_HOST', 'localhost'),
    'sql_db' => (string)env_or_default('DB_MYSQL_NAME', ''),
    'sql_login' => (string)env_or_default('DB_MYSQL_USER', ''),
    'sql_passwd' => (string)env_or_default('DB_MYSQL_PASSWORD', ''),
    'sql_port' => (int)env_or_default('DB_MYSQL_PORT', 3306),
    'charset' => (string)env_or_default('DB_MYSQL_CHARSET', 'utf8mb4'),
];
