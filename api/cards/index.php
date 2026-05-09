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
            handleListCards();
        } elseif (preg_match("#^/(\d+)$#", $path, $matches)) {
            handleGetCard((int) $matches[1]);
        } elseif ($path === "/search") {
            handleSearchCards();
        } else {
            sendError(404, "Not found");
        }
        break;

    default:
        sendError(405, "Method not allowed");
}

function handleListCards(): void {
    $tcgId = isset($_GET["tcg_id"]) ? (int) $_GET["tcg_id"] : null;
    $type = $_GET["type"] ?? null;
    $rarityId = isset($_GET["rarity_id"]) ? (int) $_GET["rarity_id"] : null;

    if ($tcgId === null) {
        sendError(400, "tcg_id is required");
    }

    $cards = \TCG\Platform\Models\Card::findByTcg($tcgId, $type, $rarityId);

    $result = array_map(function($card) {
        return $card->toArray();
    }, $cards);

    sendJson($result);
}

function handleGetCard(int $cardId): void {
    $card = \TCG\Platform\Models\Card::findById($cardId);

    if (!$card) {
        sendError(404, "Card not found");
    }

    sendJson($card->toArray());
}

function handleSearchCards(): void {
    $query = $_GET["q"] ?? "";

    if (empty($query)) {
        sendError(400, "Query parameter q is required");
    }

    $tcgId = isset($_GET["tcg_id"]) ? (int) $_GET["tcg_id"] : null;
    $type = $_GET["type"] ?? null;
    $rarityId = isset($_GET["rarity_id"]) ? (int) $_GET["rarity_id"] : null;

    $cards = \TCG\Platform\Models\Card::search($query, $tcgId, $type, $rarityId);

    $result = array_map(function($card) {
        return $card->toArray();
    }, $cards);

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
