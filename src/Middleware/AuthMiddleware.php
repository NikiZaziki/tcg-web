<?php

namespace TCG\Platform\Middleware;

use TCG\Platform\Auth\AuthService;

class AuthMiddleware {
    public static function authenticate(): ?array {
        $headers = getallheaders();
        $authHeader = $headers["Authorization"] ?? $headers["authorization"] ?? null;

        if (!$authHeader) {
            return null;
        }

        if (!preg_match("/Bearer\s+(.*)$/i", $authHeader, $matches)) {
            return null;
        }

        $token = $matches[1];

        return AuthService::verifyToken($token);
    }

    public static function requireAuth(): array {
        $user = self::authenticate();

        if (!$user) {
            http_response_code(401);
            echo json_encode(["error" => "Unauthorized"]);
            exit;
        }

        return $user;
    }

    public static function requireUserId(): int {
        $user = self::requireAuth();

        return (int) $user["user_id"];
    }
}
