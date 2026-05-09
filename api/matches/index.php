<?php

require_once __DIR__ . "/../../vendor/autoload.php";

use TCG\Platform\Config\Config;
use TCG\Platform\Database\Database;
use TCG\Platform\Auth\AuthService;
use TCG\Platform\Middleware\AuthMiddleware;
use TCG\Platform\Services\MatchmakingService;

Config::load();
Database::init(Config::get("database"));
AuthService::init();

header("Content-Type: application/json");

$method = $_SERVER["REQUEST_METHOD"];
$path = $_SERVER["PATH_INFO"] ?? "/";

switch ($method) {
    case "GET":
        if ($path === "/") {
            handleListMatches();
        } elseif (preg_match("#^/(\d+)$#", $path, $matches)) {
            handleGetMatch((int) $matches[1]);
        } elseif ($path === "/history") {
            handleMatchHistory();
        } elseif ($path === "/active") {
            handleActiveMatches();
        } else {
            sendError(404, "Not found");
        }
        break;

    case "POST":
        if ($path === "/find") {
            handleFindMatch();
        } elseif (preg_match("#^/(\d+)/end$#", $path, $matches)) {
            handleEndMatch((int) $matches[1]);
        } elseif (preg_match("#^/(\d+)/abandon$#", $path, $matches)) {
            handleAbandonMatch((int) $matches[1]);
        } else {
            sendError(404, "Not found");
        }
        break;

    default:
        sendError(405, "Method not allowed");
}

function handleListMatches(): void {
    $userId = AuthMiddleware::requireUserId();
    $status = $_GET["status"] ?? null;

    $matches = \TCG\Platform\Models\Match::findByUser($userId, $status);

    $result = array_map(function($match) {
        return $match->toArray();
    }, $matches);

    sendJson($result);
}

function handleGetMatch(int $matchId): void {
    $userId = AuthMiddleware::requireUserId();

    $match = \TCG\Platform\Models\Match::findById($matchId);

    if (!$match) {
        sendError(404, "Match not found");
    }

    if (!$match->isPlayer($userId)) {
        sendError(403, "Access denied");
    }

    $data = $match->toArray();
    $data["is_player"] = true;
    $data["opponent_id"] = $match->getOpponentId($userId);
    $data["my_deck_id"] = $match->getDeckId($userId);

    sendJson($data);
}

function handleMatchHistory(): void {
    $userId = AuthMiddleware::requireUserId();
    $limit = (int) ($_GET["limit"] ?? 20);
    $offset = (int) ($_GET["offset"] ?? 0);

    $history = \TCG\Platform\Models\Match::getMatchHistory($userId, $limit, $offset);

    sendJson($history);
}

function handleActiveMatches(): void {
    $userId = AuthMiddleware::requireUserId();

    $matches = \TCG\Platform\Models\Match::findByUser($userId, "active");

    $result = array_map(function($match) {
        return $match->toArray();
    }, $matches);

    sendJson($result);
}

function handleFindMatch(): void {
    $userId = AuthMiddleware::requireUserId();
    $input = getInput();

    if (empty($input["deck_id"])) {
        sendError(400, "Missing deck_id");
    }

    $mode = $input["mode"] ?? "normal";

    if (!in_array($mode, ["normal", "ranked"])) {
        sendError(400, "Invalid mode");
    }

    try {
        $service = new MatchmakingService();
        $match = $service->findMatch($userId, (int) $input["deck_id"], $mode);

        if (!$match) {
            sendJson(["status" => "queued"], 202);
        }

        sendJson($match->toArray(), 201);
    } catch (\Exception $e) {
        sendError(400, $e->getMessage());
    }
}

function handleEndMatch(int $matchId): void {
    $userId = AuthMiddleware::requireUserId();
    $input = getInput();

    if (empty($input["winner_id"])) {
        sendError(400, "Missing winner_id");
    }

    try {
        $service = new MatchmakingService();
        $result = $service->endMatch($matchId, (int) $input["winner_id"]);
        sendJson($result);
    } catch (\Exception $e) {
        sendError(400, $e->getMessage());
    }
}

function handleAbandonMatch(int $matchId): void {
    $userId = AuthMiddleware::requireUserId();

    try {
        $service = new MatchmakingService();
        $service->abandonMatch($matchId, $userId);
        sendJson(["success" => true]);
    } catch (\Exception $e) {
        sendError(400, $e->getMessage());
    }
}

function getInput(): array {
    $input = file_get_contents("php://input");
    return json_decode($input, true) ?? [];
}

function sendJson(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
}

function sendError(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(["error" => $message]);
}
