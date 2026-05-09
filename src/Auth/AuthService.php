<?php

namespace TCG\Platform\Auth;

use TCG\Platform\Models\User;
use TCG\Platform\Config\Config;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthService {
    private static ?string $secret = null;

    public static function init(): void {
        self::$secret = Config::get("jwt.secret", "change-me-in-production");
    }

    public static function register(string $username, string $email, string $password): array {
        if (self::validateUsername($username)) {
            throw new \InvalidArgumentException("Username already exists");
        }

        if (self::validateEmail($email)) {
            throw new \InvalidArgumentException("Email already exists");
        }

        if (!self::validatePasswordStrength($password)) {
            throw new \InvalidArgumentException("Password must be at least 8 characters");
        }

        $user = User::create($username, $email, $password);

        return [
            "user" => $user->toArray(),
            "token" => self::generateToken($user),
        ];
    }

    public static function login(string $email, string $password): array {
        $user = User::findByEmail($email);

        if (!$user) {
            throw new \InvalidArgumentException("Invalid credentials");
        }

        if (!$user->verifyPassword($password)) {
            throw new \InvalidArgumentException("Invalid credentials");
        }

        User::updateLastLogin($user->id);

        return [
            "user" => $user->toArray(),
            "token" => self::generateToken($user),
        ];
    }

    public static function verifyToken(string $token): ?array {
        try {
            $decoded = JWT::decode($token, new Key(self::$secret, "HS256"));

            return (array) $decoded;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getUserFromToken(string $token): ?User {
        $payload = self::verifyToken($token);

        if (!$payload || !isset($payload["user_id"])) {
            return null;
        }

        return User::findById((int) $payload["user_id"]);
    }

    public static function generateToken(User $user): string {
        $payload = [
            "user_id" => $user->id,
            "username" => $user->username,
            "email" => $user->email,
            "iat" => time(),
            "exp" => time() + Config::get("jwt.expiry", 86400),
        ];

        return JWT::encode($payload, self::$secret, "HS256");
    }

    public static function validateUsername(string $username): bool {
        return User::findByUsername($username) !== null;
    }

    public static function validateEmail(string $email): bool {
        return User::findByEmail($email) !== null;
    }

    public static function validatePasswordStrength(string $password): bool {
        return strlen($password) >= 8;
    }

    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
}
