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
            handleListGames();
        } elseif (preg_match("#^/(\d+)$#", $path, $matches)) {
            handleGetGame((int) $matches[1]);
        } else {
            sendError(404, "Not found");
        }
        break;

    default:
        sendError(405, "Method not allowed");
}

function handleListGames(): void {
    $games = \TCG\Platform\Models\TcgGame::findAll();

    $result = array_map(function($game) {
        return $game->toArray();
    }, $games);

    sendJson($result);
}

function handleGetGame(int $gameId): void {
    $game = \TCG\Platform\Models\TcgGame::findById($gameId);

    if (!$game) {
        sendError(404, "Game not found");
    }

    sendJson($game->toArray());
}

function sendJson(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
}

function sendError(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(["error" => $message]);
}
