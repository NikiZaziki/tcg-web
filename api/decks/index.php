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
            handleListDecks();
        } elseif (preg_match("#^/(\d+)$#", $path, $matches)) {
            handleGetDeck((int) $matches[1]);
        } else {
            sendError(404, "Not found");
        }
        break;

    case "POST":
        if ($path === "/") {
            handleCreateDeck();
        } elseif (preg_match("#^/(\d+)/cards$#", $path, $matches)) {
            handleAddCard((int) $matches[1]);
        } elseif (preg_match("#^/(\d+)/validate$#", $path, $matches)) {
            handleValidateDeck((int) $matches[1]);
        } else {
            sendError(404, "Not found");
        }
        break;

    case "PUT":
        if (preg_match("#^/(\d+)$#", $path, $matches)) {
            handleUpdateDeck((int) $matches[1]);
        } else {
            sendError(404, "Not found");
        }
        break;

    case "DELETE":
        if (preg_match("#^/(\d+)$#", $path, $matches)) {
            handleDeleteDeck((int) $matches[1]);
        } elseif (preg_match("#^/(\d+)/cards/(\d+)$#", $path, $matches)) {
            handleRemoveCard((int) $matches[1], (int) $matches[2]);
        } else {
            sendError(404, "Not found");
        }
        break;

    default:
        sendError(405, "Method not allowed");
}

function handleListDecks(): void {
    $userId = AuthMiddleware::requireUserId();
    $tcgId = isset($_GET["tcg_id"]) ? (int) $_GET["tcg_id"] : null;

    $decks = \TCG\Platform\Models\Deck::findByUser($userId, $tcgId);

    $result = array_map(function($deck) {
        $data = $deck->toArray();
        $data["cards"] = $deck->getCards();
        return $data;
    }, $decks);

    sendJson($result);
}

function handleGetDeck(int $deckId): void {
    $userId = AuthMiddleware::requireUserId();

    $deck = \TCG\Platform\Models\Deck::findById($deckId);

    if (!$deck) {
        sendError(404, "Deck not found");
    }

    if ($deck->user_id !== $userId) {
        sendError(403, "Access denied");
    }

    $data = $deck->toArray();
    $data["cards"] = $deck->getCards();

    sendJson($data);
}

function handleCreateDeck(): void {
    $userId = AuthMiddleware::requireUserId();
    $input = getInput();

    if (empty($input["name"]) || empty($input["tcg_id"])) {
        sendError(400, "Missing required fields");
    }

    try {
        $deck = \TCG\Platform\Models\Deck::create($userId, (int) $input["tcg_id"], $input["name"]);
        sendJson($deck->toArray(), 201);
    } catch (\Exception $e) {
        sendError(400, $e->getMessage());
    }
}

function handleUpdateDeck(int $deckId): void {
    $userId = AuthMiddleware::requireUserId();
    $input = getInput();

    $deck = \TCG\Platform\Models\Deck::findById($deckId);

    if (!$deck) {
        sendError(404, "Deck not found");
    }

    if ($deck->user_id !== $userId) {
        sendError(403, "Access denied");
    }

    if (!empty($input["name"])) {
        \TCG\Platform\Models\Deck::update($deckId, $input["name"]);
    }

    sendJson(["success" => true]);
}

function handleDeleteDeck(int $deckId): void {
    $userId = AuthMiddleware::requireUserId();

    $deck = \TCG\Platform\Models\Deck::findById($deckId);

    if (!$deck) {
        sendError(404, "Deck not found");
    }

    if ($deck->user_id !== $userId) {
        sendError(403, "Access denied");
    }

    \TCG\Platform\Models\Deck::delete($deckId);

    sendJson(["success" => true]);
}

function handleAddCard(int $deckId): void {
    $userId = AuthMiddleware::requireUserId();
    $input = getInput();

    if (empty($input["card_id"])) {
        sendError(400, "Missing card_id");
    }

    $deck = \TCG\Platform\Models\Deck::findById($deckId);

    if (!$deck) {
        sendError(404, "Deck not found");
    }

    if ($deck->user_id !== $userId) {
        sendError(403, "Access denied");
    }

    $quantity = (int) ($input["quantity"] ?? 1);

    $deck->addCard((int) $input["card_id"], $quantity);

    sendJson(["success" => true]);
}

function handleRemoveCard(int $deckId, int $cardId): void {
    $userId = AuthMiddleware::requireUserId();

    $deck = \TCG\Platform\Models\Deck::findById($deckId);

    if (!$deck) {
        sendError(404, "Deck not found");
    }

    if ($deck->user_id !== $userId) {
        sendError(403, "Access denied");
    }

    $quantity = (int) ($_GET["quantity"] ?? 1);

    $deck->removeCard($cardId, $quantity);

    sendJson(["success" => true]);
}

function handleValidateDeck(int $deckId): void {
    $userId = AuthMiddleware::requireUserId();

    $deck = \TCG\Platform\Models\Deck::findById($deckId);

    if (!$deck) {
        sendError(404, "Deck not found");
    }

    if ($deck->user_id !== $userId) {
        sendError(403, "Access denied");
    }

    $game = \TCG\Platform\Models\TcgGame::findById($deck->tcg_id);

    if (!$game) {
        sendError(404, "Game not found");
    }

    $errors = $deck->validate($game);

    sendJson([
        "valid" => empty($errors),
        "errors" => $errors,
        "card_count" => $deck->getCardCount(),
        "required_size" => $game->deck_size,
    ]);
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
