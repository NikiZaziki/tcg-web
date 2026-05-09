<?php

require_once __DIR__ . "/../../vendor/autoload.php";

use TCG\Platform\Config\Config;
use TCG\Platform\Database\Database;

Config::load();
Database::init(Config::get("database"));

header("Content-Type: application/json");

$method = $_SERVER["REQUEST_METHOD"];
$path = $_SERVER["PATH_INFO"] ?? "/";

switch ($method) {
    case "GET":
        if ($path === "/") {
            handleGetLeaderboard();
        } else {
            sendError(404, "Not found");
        }
        break;

    default:
        sendError(405, "Method not allowed");
}

function handleGetLeaderboard(): void {
    $limit = (int) ($_GET["limit"] ?? 100);
    $offset = (int) ($_GET["offset"] ?? 0);

    $leaderboard = \TCG\Platform\Models\User::getLeaderboard($limit, $offset);

    $result = array_map(function($entry, $index) use ($offset) {
        return [
            "rank" => $offset + $index + 1,
            "user_id" => $entry["id"],
            "username" => $entry["username"],
            "elo_rating" => $entry["elo_rating"],
            "rank_tier" => $entry["rank_tier"],
        ];
    }, $leaderboard, array_keys($leaderboard));

    sendJson($result);
}

function sendJson(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
}

function sendError(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(["error" => $message]);
}
