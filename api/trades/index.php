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
            handleListTrades();
        } elseif (preg_match("#^/(\d+)$#", $path, $matches)) {
            handleGetTrade((int) $matches[1]);
        } else {
            sendError(404, "Not found");
        }
        break;

    case "POST":
        if ($path === "/") {
            handleCreateTrade();
        } elseif (preg_match("#^/(\d+)/accept$#", $path, $matches)) {
            handleAcceptTrade((int) $matches[1]);
        } elseif (preg_match("#^/(\d+)/reject$#", $path, $matches)) {
            handleRejectTrade((int) $matches[1]);
        } elseif (preg_match("#^/(\d+)/cancel$#", $path, $matches)) {
            handleCancelTrade((int) $matches[1]);
        } elseif (preg_match("#^/(\d+)/cards$#", $path, $matches)) {
            handleAddCard((int) $matches[1]);
        } else {
            sendError(404, "Not found");
        }
        break;

    default:
        sendError(405, "Method not allowed");
}

function handleListTrades(): void {
    $userId = AuthMiddleware::requireUserId();
    $status = $_GET["status"] ?? null;

    $trades = \TCG\Platform\Models\Trade::findByUser($userId, $status);

    $result = array_map(function($trade) use ($userId) {
        $data = $trade->toArray();
        $data["is_sender"] = $trade->sender_id === $userId;
        $data["my_cards"] = $trade->getCards($trade->sender_id === $userId ? $trade->sender_id : $trade->receiver_id);
        $data["other_cards"] = $trade->getCards($trade->sender_id === $userId ? $trade->receiver_id : $trade->sender_id);
        return $data;
    }, $trades);

    sendJson($result);
}

function handleGetTrade(int $tradeId): void {
    $userId = AuthMiddleware::requireUserId();

    $trade = \TCG\Platform\Models\Trade::findById($tradeId);

    if (!$trade) {
        sendError(404, "Trade not found");
    }

    if (!$trade->isParticipant($userId)) {
        sendError(403, "Access denied");
    }

    $data = $trade->toArray();
    $data["is_sender"] = $trade->sender_id === $userId;
    $data["sender_cards"] = $trade->getCards($trade->sender_id);
    $data["receiver_cards"] = $trade->getCards($trade->receiver_id);

    sendJson($data);
}

function handleCreateTrade(): void {
    $userId = AuthMiddleware::requireUserId();
    $input = getInput();

    if (empty($input["receiver_id"])) {
        sendError(400, "Missing receiver_id");
    }

    if ($input["receiver_id"] == $userId) {
        sendError(400, "Cannot trade with yourself");
    }

    try {
        $trade = \TCG\Platform\Models\Trade::create($userId, (int) $input["receiver_id"]);
        sendJson($trade->toArray(), 201);
    } catch (\Exception $e) {
        sendError(400, $e->getMessage());
    }
}

function handleAcceptTrade(int $tradeId): void {
    $userId = AuthMiddleware::requireUserId();

    $trade = \TCG\Platform\Models\Trade::findById($tradeId);

    if (!$trade) {
        sendError(404, "Trade not found");
    }

    if ($trade->receiver_id !== $userId) {
        sendError(403, "Only receiver can accept trade");
    }

    if ($trade->status !== "pending") {
        sendError(400, "Trade is not pending");
    }

    try {
        $success = $trade->execute();

        if ($success) {
            sendJson(["success" => true]);
        } else {
            sendError(400, "Trade failed - insufficient cards");
        }
    } catch (\Exception $e) {
        sendError(400, $e->getMessage());
    }
}

function handleRejectTrade(int $tradeId): void {
    $userId = AuthMiddleware::requireUserId();

    $trade = \TCG\Platform\Models\Trade::findById($tradeId);

    if (!$trade) {
        sendError(404, "Trade not found");
    }

    if ($trade->receiver_id !== $userId) {
        sendError(403, "Only receiver can reject trade");
    }

    if ($trade->status !== "pending") {
        sendError(400, "Trade is not pending");
    }

    \TCG\Platform\Models\Trade::updateStatus($tradeId, "rejected");

    sendJson(["success" => true]);
}

function handleCancelTrade(int $tradeId): void {
    $userId = AuthMiddleware::requireUserId();

    $trade = \TCG\Platform\Models\Trade::findById($tradeId);

    if (!$trade) {
        sendError(404, "Trade not found");
    }

    if ($trade->sender_id !== $userId) {
        sendError(403, "Only sender can cancel trade");
    }

    if ($trade->status !== "pending") {
        sendError(400, "Trade is not pending");
    }

    \TCG\Platform\Models\Trade::updateStatus($tradeId, "cancelled");

    sendJson(["success" => true]);
}

function handleAddCard(int $tradeId): void {
    $userId = AuthMiddleware::requireUserId();
    $input = getInput();

    if (empty($input["card_id"])) {
        sendError(400, "Missing card_id");
    }

    $trade = \TCG\Platform\Models\Trade::findById($tradeId);

    if (!$trade) {
        sendError(404, "Trade not found");
    }

    if (!$trade->isParticipant($userId)) {
        sendError(403, "Not a participant in this trade");
    }

    if ($trade->status !== "pending") {
        sendError(400, "Trade is not pending");
    }

    $quantity = (int) ($input["quantity"] ?? 1);

    if (!\TCG\Platform\Models\UserInventory::hasCard($userId, (int) $input["card_id"], $quantity)) {
        sendError(400, "Insufficient cards");
    }

    $trade->addCard($userId, (int) $input["card_id"], $quantity);

    sendJson(["success" => true]);
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
