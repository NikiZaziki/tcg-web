<?php

require_once __DIR__ . "/../../vendor/autoload.php";

use TCG\Platform\Config\Config;
use TCG\Platform\Database\Database;
use TCG\Platform\Auth\AuthService;
use TCG\Platform\Middleware\AuthMiddleware;
use TCG\Platform\Services\PackOpeningService;

Config::load();
Database::init(Config::get("database"));
AuthService::init();

header("Content-Type: application/json");

$method = $_SERVER["REQUEST_METHOD"];
$path = $_SERVER["PATH_INFO"] ?? "/";

switch ($method) {
    case "GET":
        if ($path === "/") {
            handleListPacks();
        } elseif (preg_match("#^/(\d+)$#", $path, $matches)) {
            handleGetPack((int) $matches[1]);
        } elseif (preg_match("#^/(\d+)/open$#", $path, $matches)) {
            handleOpenPack((int) $matches[1]);
        } elseif (preg_match("#^/openings/(\d+)$#", $path, $matches)) {
            handleGetOpening((int) $matches[1]);
        } elseif ($path === "/my-openings") {
            handleMyOpenings();
        } else {
            sendError(404, "Not found");
        }
        break;

    default:
        sendError(405, "Method not allowed");
}

function handleListPacks(): void {
    $packs = \TCG\Platform\Models\BoosterPack::findAll();

    $result = array_map(function($pack) {
        $data = $pack->toArray();
        $data["drop_table"] = $pack->getDropTable();
        return $data;
    }, $packs);

    sendJson($result);
}

function handleGetPack(int $packId): void {
    $pack = \TCG\Platform\Models\BoosterPack::findById($packId);

    if (!$pack) {
        sendError(404, "Pack not found");
    }

    $data = $pack->toArray();
    $data["drop_table"] = $pack->getDropTable();

    sendJson($data);
}

function handleOpenPack(int $packId): void {
    $userId = AuthMiddleware::requireUserId();

    $pack = \TCG\Platform\Models\BoosterPack::findById($packId);

    if (!$pack) {
        sendError(404, "Pack not found");
    }

    try {
        $service = new PackOpeningService();
        $result = $service->openPack($userId, $packId);
        sendJson($result);
    } catch (\Exception $e) {
        sendError(400, $e->getMessage());
    }
}

function handleGetOpening(int $openingId): void {
    $userId = AuthMiddleware::requireUserId();

    $service = new PackOpeningService();
    $result = $service->getOpeningResult($openingId);

    if (!$result) {
        sendError(404, "Opening not found");
    }

    if ($result["user_id"] !== $userId) {
        sendError(403, "Access denied");
    }

    sendJson($result);
}

function handleMyOpenings(): void {
    $userId = AuthMiddleware::requireUserId();
    $limit = (int) ($_GET["limit"] ?? 20);
    $offset = (int) ($_GET["offset"] ?? 0);

    $service = new PackOpeningService();
    $result = $service->getUserOpenings($userId, $limit, $offset);

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
