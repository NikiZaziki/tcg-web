<?php
require_once __DIR__ . "/../../vendor/autoload.php";

use TCG\Platform\Config\Config;
use TCG\Platform\Database\Database;

Config::load();
Database::init(Config::get("database"));

header("Content-Type: application/json");

$method = $_SERVER["REQUEST_METHOD"];

if ($method === "POST") {
    $token = $_COOKIE["tcg_token"] ?? null;
    
    if (!$token) {
        echo json_encode(["error" => "Unauthorized"]);
        exit;
    }

    try {
        $payload = \TCG\Platform\Auth\AuthService::verifyToken($token);
        if (!$payload) {
            echo json_encode(["error" => "Invalid token"]);
            exit;
        }

        $userId = (int) $payload["user_id"];
        $packService = new \TCG\Platform\Services\PackOpeningService();
        $dailyService = new \TCG\Platform\Services\DailyRewardService($packService);

        if (!\TCG\Platform\Models\User::canClaimDailyPack($userId)) {
            echo json_encode(["error" => "Daily pack not available yet"]);
            exit;
        }

        $result = $dailyService->claimDailyPack($userId);
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(["error" => $e->getMessage()]);
    }
} else {
    echo json_encode(["error" => "Method not allowed"]);
}
