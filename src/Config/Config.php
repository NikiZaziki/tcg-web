<?php

namespace TCG\Platform\Config;

use Dotenv\Dotenv;

class Config {
    private static array $config = [];

    public static function load(string $path = __DIR__ . "/../../.env"): void {
        if (file_exists($path)) {
            $dotenv = Dotenv::createImmutable(dirname($path));
            $dotenv->load();
        }

        self::$config = [
            "database" => [
                "host" => $_ENV["DB_HOST"] ?? "localhost",
                "name" => $_ENV["DB_NAME"] ?? "tcg_platform",
                "user" => $_ENV["DB_USER"] ?? "root",
                "pass" => $_ENV["DB_PASS"] ?? "",
            ],
            "jwt" => [
                "secret" => $_ENV["JWT_SECRET"] ?? "change-me-in-production",
                "algorithm" => "HS256",
                "expiry" => 86400, // 24 hours
            ],
            "redis" => [
                "host" => $_ENV["REDIS_HOST"] ?? "localhost",
                "port" => $_ENV["REDIS_PORT"] ?? 6379,
            ],
            "websocket" => [
                "host" => $_ENV["WS_HOST"] ?? "0.0.0.0",
                "port" => $_ENV["WS_PORT"] ?? 8080,
            ],
            "app" => [
                "env" => $_ENV["APP_ENV"] ?? "development",
                "debug" => ($_ENV["APP_DEBUG"] ?? "true") === "true",
            ],
        ];
    }

    public static function get(string $key, mixed $default = null): mixed {
        $keys = explode(".", $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void {
        $keys = explode(".", $key);
        $config = &self::$config;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $config[$k] = $value;
            } else {
                if (!isset($config[$k])) {
                    $config[$k] = [];
                }
                $config = &$config[$k];
            }
        }
    }

    public static function isDevelopment(): bool {
        return self::get("app.env") === "development";
    }

    public static function isProduction(): bool {
        return self::get("app.env") === "production";
    }
}
