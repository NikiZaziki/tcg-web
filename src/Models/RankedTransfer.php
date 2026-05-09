<?php

namespace TCG\Platform\Models;

use TCG\Platform\Database\Database;

class RankedTransfer {
    public int $id;
    public int $match_id;
    public int $winner_id;
    public int $loser_id;
    public int $card_id;
    public string $timestamp;

    public static function create(int $matchId, int $winnerId, int $loserId, int $cardId): self {
        $stmt = Database::getConnection()->prepare(
            "INSERT INTO ranked_transfers (match_id, winner_id, loser_id, card_id) 
             VALUES (:match_id, :winner_id, :loser_id, :card_id)"
        );
        $stmt->execute([
            "match_id" => $matchId,
            "winner_id" => $winnerId,
            "loser_id" => $loserId,
            "card_id" => $cardId,
        ]);

        $id = (int) Database::getConnection()->lastInsertId();

        return self::findById($id);
    }

    public static function findById(int $id): ?self {
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM ranked_transfers WHERE id = :id"
        );
        $stmt->execute(["id" => $id]);
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        return self::fromArray($data);
    }

    public static function findByMatch(int $matchId): array {
        $stmt = Database::getConnection()->prepare(
            "SELECT rt.*, c.name as card_name, cr.name as rarity_name 
             FROM ranked_transfers rt
             JOIN cards c ON rt.card_id = c.id
             JOIN card_rarity cr ON c.rarity_id = cr.id
             WHERE rt.match_id = :match_id"
        );
        $stmt->execute(["match_id" => $matchId]);

        return $stmt->fetchAll();
    }

    public static function findByUser(int $userId, bool $asWinner = null): array {
        $sql = "SELECT rt.*, c.name as card_name, cr.name as rarity_name 
                FROM ranked_transfers rt
                JOIN cards c ON rt.card_id = c.id
                JOIN card_rarity cr ON c.rarity_id = cr.id
                WHERE ";
        
        if ($asWinner === true) {
            $sql .= "rt.winner_id = :user_id";
        } elseif ($asWinner === false) {
            $sql .= "rt.loser_id = :user_id";
        } else {
            $sql .= "(rt.winner_id = :user_id OR rt.loser_id = :user_id)";
        }

        $sql .= " ORDER BY rt.timestamp DESC";

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute(["user_id" => $userId]);

        return $stmt->fetchAll();
    }

    private static function fromArray(array $data): self {
        $transfer = new self();
        $transfer->id = (int) $data["id"];
        $transfer->match_id = (int) $data["match_id"];
        $transfer->winner_id = (int) $data["winner_id"];
        $transfer->loser_id = (int) $data["loser_id"];
        $transfer->card_id = (int) $data["card_id"];
        $transfer->timestamp = $data["timestamp"];

        return $transfer;
    }

    public function toArray(): array {
        return [
            "id" => $this->id,
            "match_id" => $this->match_id,
            "winner_id" => $this->winner_id,
            "loser_id" => $this->loser_id,
            "card_id" => $this->card_id,
            "timestamp" => $this->timestamp,
        ];
    }
}
