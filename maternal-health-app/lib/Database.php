<?php

class Database
{
    private static ?PDO $pdo = null;

    public static function getConnection(): PDO
    {
        if (self::$pdo === null) {
            $configPath = __DIR__ . '/../config/config.php';
            if (!file_exists($configPath)) {
                $configPath = __DIR__ . '/../config/config.sample.php';
            }
            $config = require $configPath;
            $db = $config['db'];
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], $db['port'], $db['database'], $db['charset']);
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            self::$pdo = new PDO($dsn, $db['user'], $db['pass'], $options);
        }
        return self::$pdo;
    }
}