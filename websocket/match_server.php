<?php

require_once __DIR__ . "/../../vendor/autoload.php";

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use TCG\Platform\Config\Config;
use TCG\Platform\Database\Database;
use TCG\Platform\Auth\AuthService;

Config::load();
Database::init(Config::get("database"));
AuthService::init();

class MatchServer implements MessageComponentInterface {
    private \SplObjectStorage $clients;
    private array $userConnections = [];
    private array $matchConnections = [];

    public function __construct() {
        $this->clients = new \SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn): void {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $conn, string $msg): void {
        echo "Message from {$conn->resourceId}: $msg\n";

        try {
            $data = json_decode($msg, true);

            if (!$data) {
                $this->sendError($conn, "Invalid JSON");
                return;
            }

            $action = $data["action"] ?? null;

            switch ($action) {
                case "authenticate":
                    $this->handleAuthenticate($conn, $data);
                    break;

                case "join_match":
                    $this->handleJoinMatch($conn, $data);
                    break;

                case "game_action":
                    $this->handleGameAction($conn, $data);
                    break;

                case "leave_match":
                    $this->handleLeaveMatch($conn, $data);
                    break;

                default:
                    $this->sendError($conn, "Unknown action");
            }
        } catch (\Exception $e) {
            $this->sendError($conn, $e->getMessage());
        }
    }

    public function onClose(ConnectionInterface $conn): void {
        $this->clients->detach($conn);

        foreach ($this->userConnections as $userId => $connection) {
            if ($connection === $conn) {
                unset($this->userConnections[$userId]);
                echo "User $userId disconnected\n";
                break;
            }
        }

        foreach ($this->matchConnections as $matchId => $connections) {
            foreach ($connections as $userId => $connection) {
                if ($connection === $conn) {
                    unset($this->matchConnections[$matchId][$userId]);
                    echo "User $userId left match $matchId\n";
                    break;
                }
            }
        }

        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }

    private function handleAuthenticate(ConnectionInterface $conn, array $data): void {
        $token = $data["token"] ?? null;

        if (!$token) {
            $this->sendError($conn, "Token required");
            return;
        }

        $payload = AuthService::verifyToken($token);

        if (!$payload) {
            $this->sendError($conn, "Invalid token");
            return;
        }

        $userId = (int) $payload["user_id"];
        $this->userConnections[$userId] = $conn;

        $this->sendSuccess($conn, [
            "action" => "authenticated",
            "user_id" => $userId,
        ]);
    }

    private function handleJoinMatch(ConnectionInterface $conn, array $data): void {
        $userId = $this->getUserIdFromConnection($conn);

        if (!$userId) {
            $this->sendError($conn, "Not authenticated");
            return;
        }

        $matchId = $data["match_id"] ?? null;

        if (!$matchId) {
            $this->sendError($conn, "match_id required");
            return;
        }

        $match = \TCG\Platform\Models\Match::findById((int) $matchId);

        if (!$match) {
            $this->sendError($conn, "Match not found");
            return;
        }

        if (!$match->isPlayer($userId)) {
            $this->sendError($conn, "Not a participant in this match");
            return;
        }

        if (!isset($this->matchConnections[$matchId])) {
            $this->matchConnections[$matchId] = [];
        }

        $this->matchConnections[$matchId][$userId] = $conn;

        $this->sendSuccess($conn, [
            "action" => "joined_match",
            "match_id" => $matchId,
        ]);

        $this->broadcastToMatch($matchId, [
            "action" => "player_joined",
            "match_id" => $matchId,
            "user_id" => $userId,
        ], $userId);
    }

    private function handleGameAction(ConnectionInterface $conn, array $data): void {
        $userId = $this->getUserIdFromConnection($conn);

        if (!$userId) {
            $this->sendError($conn, "Not authenticated");
            return;
        }

        $matchId = $data["match_id"] ?? null;

        if (!$matchId) {
            $this->sendError($conn, "match_id required");
            return;
        }

        if (!isset($this->matchConnections[$matchId][$userId])) {
            $this->sendError($conn, "Not in this match");
            return;
        }

        $actionType = $data["game_action"] ?? null;
        $actionData = $data["data"] ?? [];

        $this->broadcastToMatch($matchId, [
            "action" => "game_action",
            "match_id" => $matchId,
            "user_id" => $userId,
            "game_action" => $actionType,
            "data" => $actionData,
        ]);
    }

    private function handleLeaveMatch(ConnectionInterface $conn, array $data): void {
        $userId = $this->getUserIdFromConnection($conn);

        if (!$userId) {
            $this->sendError($conn, "Not authenticated");
            return;
        }

        $matchId = $data["match_id"] ?? null;

        if (!$matchId) {
            $this->sendError($conn, "match_id required");
            return;
        }

        if (isset($this->matchConnections[$matchId][$userId])) {
            unset($this->matchConnections[$matchId][$userId]);
        }

        $this->sendSuccess($conn, [
            "action" => "left_match",
            "match_id" => $matchId,
        ]);

        $this->broadcastToMatch($matchId, [
            "action" => "player_left",
            "match_id" => $matchId,
            "user_id" => $userId,
        ], $userId);
    }

    private function getUserIdFromConnection(ConnectionInterface $conn): ?int {
        foreach ($this->userConnections as $userId => $connection) {
            if ($connection === $conn) {
                return $userId;
            }
        }
        return null;
    }

    private function broadcastToMatch(int $matchId, array $data, ?int $excludeUserId = null): void {
        if (!isset($this->matchConnections[$matchId])) {
            return;
        }

        foreach ($this->matchConnections[$matchId] as $userId => $conn) {
            if ($excludeUserId !== null && $userId === $excludeUserId) {
                continue;
            }

            $conn->send(json_encode($data));
        }
    }

    private function sendSuccess(ConnectionInterface $conn, array $data): void {
        $data["success"] = true;
        $conn->send(json_encode($data));
    }

    private function sendError(ConnectionInterface $conn, string $message): void {
        $conn->send(json_encode([
            "success" => false,
            "error" => $message,
        ]));
    }
}

$host = Config::get("websocket.host", "0.0.0.0");
$port = Config::get("websocket.port", 8080);

$server = \Ratchet\Server\IoServer::factory(
    new \Ratchet\Http\HttpServer(
        new \Ratchet\WebSocket\WsServer(
            new MatchServer()
        )
    ),
    $port,
    $host
);

echo "WebSocket server running on $host:$port\n";
$server->run();
