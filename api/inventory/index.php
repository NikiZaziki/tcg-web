<?php

require_once __DIR__ . "/../../vendor/autoload.php";

use TCG\Platform\Config\Config;
use TCG\Platform\Database\Database;
use TCG\Platform\Auth\AuthService;
use TCG\Platform\Middleware\AuthMiddleware;

Config::load();
Database::init(Config::get("database"));
AuthService::init();

header("Content-Type: application/json");

$method = $_SERVER["REQUEST_METHOD"];
$path = $_SERVER["PATH_INFO"] ?? "/";

switch ($method) {
    case "GET":
        if ($path === "/") {
            handleGetInventory();
        } elseif (preg_match("#^/card/(\d+)$#", $path, $matches)) {
            handleGetCardQuantity((int) $matches[1]);
        } else {
            sendError(404, "Not found");
        }
        break;

    default:
        sendError(405, "Method not allowed");
}

function handleGetInventory(): void {
    $userId = AuthMiddleware::requireUserId();
    $tcgId = isset($_GET["tcg_id"]) ? (int) $_GET["tcg_id"] : null;

    $inventory = \TCG\Platform\Models\UserInventory::getUserInventory($userId, $tcgId);

    $stats = [
        "total_cards" => \TCG\Platform\Models\UserInventory::getTotalCards($userId),
        "unique_cards" => \TCG\Platform\Models\UserInventory::getUniqueCards($userId),
    ];

    sendJson([
        "stats" => $stats,
        "cards" => $inventory,
    ]);
}

function handleGetCardQuantity(int $cardId): void {
    $userId = AuthMiddleware::requireUserId();

    $quantity = \TCG\Platform\Models\UserInventory::getCardQuantity($userId, $cardId);

    sendJson([
        "card_id" => $cardId,
        "quantity" => $quantity,
        "owned" => $quantity > 0,
    ]);
}

function sendJson(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
}

function sendError(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(["error" => $message]);
}
