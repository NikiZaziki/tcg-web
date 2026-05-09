<?php

namespace TCG\Platform\Database;

use PDO;
use PDOException;

class Database {
    private static ?PDO $instance = null;
    private static array $config = [];

    public static function init(array $config): void {
        self::$config = $config;
    }

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    "mysql:host=%s;dbname=%s;charset=utf8mb4",
                    self::$config["host"],
                    self::$config["name"]
                );

                self::$instance = new PDO(
                    $dsn,
                    self::$config["user"],
                    self::$config["pass"],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
            } catch (PDOException $e) {
                throw new \RuntimeException("Database connection failed: " . $e->getMessage());
            }
        }

        return self::$instance;
    }

    public static function beginTransaction(): void {
        self::getConnection()->beginTransaction();
    }

    public static function commit(): void {
        self::getConnection()->commit();
    }

    public static function rollback(): void {
        self::getConnection()->rollBack();
    }

    public static function inTransaction(): bool {
        return self::getConnection()->inTransaction();
    }
}
