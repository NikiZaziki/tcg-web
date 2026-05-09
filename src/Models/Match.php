<?php

namespace TCG\Platform\Models;

use TCG\Platform\Database\Database;
use PDO;

class Match {
    public int $id;
    public int $player1_id;
    public int $player2_id;
    public int $deck1_id;
    public int $deck2_id;
    public string $mode;
    public string $status;
    public ?int $winner_id;
    public string $created_at;
    public ?string $ended_at;

    public static function findById(int $id): ?self {
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM matches WHERE id = :id"
        );
        $stmt->execute(["id" => $id]);
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        return self::fromArray($data);
    }

    public static function findByUser(int $userId, ?string $status = null): array {
        $sql = "SELECT * FROM matches 
                WHERE (player1_id = :user_id OR player2_id = :user_id)";
        $params = ["user_id" => $userId];

        if ($status !== null) {
            $sql .= " AND status = :status";
            $params["status"] = $status;
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return array_map(fn($data) => self::fromArray($data), $stmt->fetchAll());
    }

    public static function create(int $player1Id, int $player2Id, int $deck1Id, int $deck2Id, string $mode = "normal"): self {
        $stmt = Database::getConnection()->prepare(
            "INSERT INTO matches (player1_id, player2_id, deck1_id, deck2_id, mode, status) 
             VALUES (:player1_id, :player2_id, :deck1_id, :deck2_id, :mode, pending)"
        );
        $stmt->execute([
            "player1_id" => $player1Id,
            "player2_id" => $player2Id,
            "deck1_id" => $deck1Id,
            "deck2_id" => $deck2Id,
            "mode" => $mode,
        ]);

        $id = (int) Database::getConnection()->lastInsertId();

        return self::findById($id);
    }

    public static function updateStatus(int $matchId, string $status): void {
        $stmt = Database::getConnection()->prepare(
            "UPDATE matches SET status = :status WHERE id = :id"
        );
        $stmt->execute(["status" => $status, "id" => $matchId]);
    }

    public static function setWinner(int $matchId, int $winnerId): void {
        Database::beginTransaction();

        try {
            $stmt = Database::getConnection()->prepare(
                "UPDATE matches SET winner_id = :winner_id, status = finished, ended_at = NOW() 
                 WHERE id = :id"
            );
            $stmt->execute(["winner_id" => $winnerId, "id" => $matchId]);

            Database::commit();
        } catch (\Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    public static function getActiveMatches(): array {
        $stmt = Database::getConnection()->query(
            "SELECT * FROM matches WHERE status IN (pending, active) ORDER BY created_at ASC"
        );

        return array_map(fn($data) => self::fromArray($data), $stmt->fetchAll());
    }

    public static function getMatchHistory(int $userId, int $limit = 20, int $offset = 0): array {
        $stmt = Database::getConnection()->prepare(
            "SELECT m.*, 
                    p1.username as player1_name, p2.username as player2_name,
                    d1.name as deck1_name, d2.name as deck2_name
             FROM matches m
             JOIN users p1 ON m.player1_id = p1.id
             JOIN users p2 ON m.player2_id = p2.id
             JOIN decks d1 ON m.deck1_id = d1.id
             JOIN decks d2 ON m.deck2_id = d2.id
             WHERE (m.player1_id = :user_id OR m.player2_id = :user_id)
             ORDER BY m.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(":user_id", $userId, PDO::PARAM_INT);
        $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function isPlayer(int $userId): bool {
        return $this->player1_id === $userId || $this->player2_id === $userId;
    }

    public function getOpponentId(int $userId): int {
        return $this->player1_id === $userId ? $this->player2_id : $this->player1_id;
    }

    public function getDeckId(int $userId): int {
        return $this->player1_id === $userId ? $this->deck1_id : $this->deck2_id;
    }

    private static function fromArray(array $data): self {
        $match = new self();
        $match->id = (int) $data["id"];
        $match->player1_id = (int) $data["player1_id"];
        $match->player2_id = (int) $data["player2_id"];
        $match->deck1_id = (int) $data["deck1_id"];
        $match->deck2_id = (int) $data["deck2_id"];
        $match->mode = $data["mode"];
        $match->status = $data["status"];
        $match->winner_id = $data["winner_id"] ? (int) $data["winner_id"] : null;
        $match->created_at = $data["created_at"];
        $match->ended_at = $data["ended_at"];

        return $match;
    }

    public function toArray(): array {
        return [
            "id" => $this->id,
            "player1_id" => $this->player1_id,
            "player2_id" => $this->player2_id,
            "deck1_id" => $this->deck1_id,
            "deck2_id" => $this->deck2_id,
            "mode" => $this->mode,
            "status" => $this->status,
            "winner_id" => $this->winner_id,
            "created_at" => $this->created_at,
            "ended_at" => $this->ended_at,
        ];
    }
}
