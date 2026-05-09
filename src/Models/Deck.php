<?php

namespace TCG\Platform\Models;

use TCG\Platform\Database\Database;
use PDO;

class Deck {
    public int $id;
    public int $user_id;
    public int $tcg_id;
    public string $name;
    public string $created_at;
    public ?string $last_used;

    public static function findById(int $id): ?self {
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM decks WHERE id = :id"
        );
        $stmt->execute(["id" => $id]);
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        return self::fromArray($data);
    }

    public static function findByUser(int $userId, ?int $tcgId = null): array {
        $sql = "SELECT * FROM decks WHERE user_id = :user_id";
        $params = ["user_id" => $userId];

        if ($tcgId !== null) {
            $sql .= " AND tcg_id = :tcg_id";
            $params["tcg_id"] = $tcgId;
        }

        $sql .= " ORDER BY last_used DESC, created_at DESC";

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return array_map(fn($data) => self::fromArray($data), $stmt->fetchAll());
    }

    public static function create(int $userId, int $tcgId, string $name): self {
        $stmt = Database::getConnection()->prepare(
            "INSERT INTO decks (user_id, tcg_id, name) VALUES (:user_id, :tcg_id, :name)"
        );
        $stmt->execute([
            "user_id" => $userId,
            "tcg_id" => $tcgId,
            "name" => $name,
        ]);

        $id = (int) Database::getConnection()->lastInsertId();

        return self::findById($id);
    }

    public static function update(int $deckId, string $name): void {
        $stmt = Database::getConnection()->prepare(
            "UPDATE decks SET name = :name WHERE id = :id"
        );
        $stmt->execute(["name" => $name, "id" => $deckId]);
    }

    public static function delete(int $deckId): void {
        Database::beginTransaction();

        try {
            $stmt = Database::getConnection()->prepare(
                "DELETE FROM deck_cards WHERE deck_id = :deck_id"
            );
            $stmt->execute(["deck_id" => $deckId]);

            $stmt = Database::getConnection()->prepare(
                "DELETE FROM decks WHERE id = :id"
            );
            $stmt->execute(["id" => $deckId]);

            Database::commit();
        } catch (\Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    public static function updateLastUsed(int $deckId): void {
        $stmt = Database::getConnection()->prepare(
            "UPDATE decks SET last_used = NOW() WHERE id = :id"
        );
        $stmt->execute(["id" => $deckId]);
    }

    public function getCards(): array {
        $stmt = Database::getConnection()->prepare(
            "SELECT dc.*, c.name as card_name, c.type as card_type, c.attack, c.defense, 
                    cr.name as rarity_name, cr.color as rarity_color, c.image_url
             FROM deck_cards dc
             JOIN cards c ON dc.card_id = c.id
             JOIN card_rarity cr ON c.rarity_id = cr.id
             WHERE dc.deck_id = :deck_id
             ORDER BY dc.quantity DESC, c.name"
        );
        $stmt->execute(["deck_id" => $this->id]);

        return $stmt->fetchAll();
    }

    public function getCardCount(): int {
        $stmt = Database::getConnection()->prepare(
            "SELECT SUM(quantity) as total FROM deck_cards WHERE deck_id = :deck_id"
        );
        $stmt->execute(["deck_id" => $this->id]);
        $result = $stmt->fetch();

        return $result ? (int) $result["total"] : 0;
    }

    public function addCard(int $cardId, int $quantity = 1): void {
        Database::beginTransaction();

        try {
            $stmt = Database::getConnection()->prepare(
                "INSERT INTO deck_cards (deck_id, card_id, quantity) 
                 VALUES (:deck_id, :card_id, :quantity)
                 ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)"
            );
            $stmt->execute([
                "deck_id" => $this->id,
                "card_id" => $cardId,
                "quantity" => $quantity,
            ]);

            Database::commit();
        } catch (\Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    public function removeCard(int $cardId, int $quantity = 1): void {
        Database::beginTransaction();

        try {
            $stmt = Database::getConnection()->prepare(
                "SELECT quantity FROM deck_cards WHERE deck_id = :deck_id AND card_id = :card_id"
            );
            $stmt->execute(["deck_id" => $this->id, "card_id" => $cardId]);
            $result = $stmt->fetch();

            if (!$result) {
                Database::rollback();
                return;
            }

            $currentQuantity = (int) $result["quantity"];

            if ($currentQuantity <= $quantity) {
                $stmt = Database::getConnection()->prepare(
                    "DELETE FROM deck_cards WHERE deck_id = :deck_id AND card_id = :card_id"
                );
                $stmt->execute(["deck_id" => $this->id, "card_id" => $cardId]);
            } else {
                $stmt = Database::getConnection()->prepare(
                    "UPDATE deck_cards SET quantity = quantity - :quantity 
                     WHERE deck_id = :deck_id AND card_id = :card_id"
                );
                $stmt->execute([
                    "quantity" => $quantity,
                    "deck_id" => $this->id,
                    "card_id" => $cardId,
                ]);
            }

            Database::commit();
        } catch (\Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    public function clearCards(): void {
        $stmt = Database::getConnection()->prepare(
            "DELETE FROM deck_cards WHERE deck_id = :deck_id"
        );
        $stmt->execute(["deck_id" => $this->id]);
    }

    public function validate(TcgGame $game): array {
        $errors = [];
        $cards = $this->getCards();
        $totalCards = array_sum(array_column($cards, "quantity"));

        if ($totalCards !== $game->deck_size) {
            $errors[] = "Deck must contain exactly {$game->deck_size} cards (currently: $totalCards)";
        }

        foreach ($cards as $card) {
            if ($card["quantity"] > $game->max_card_copies) {
                $errors[] = "Card \"{$card["card_name"]}\" exceeds maximum copies ({$card["quantity"]}/{$game->max_card_copies})";
            }
        }

        return $errors;
    }

    public function getRandomCard(): ?int {
        $stmt = Database::getConnection()->prepare(
            "SELECT card_id FROM deck_cards WHERE deck_id = :deck_id ORDER BY RAND() LIMIT 1"
        );
        $stmt->execute(["deck_id" => $this->id]);
        $result = $stmt->fetch();

        return $result ? (int) $result["card_id"] : null;
    }

    private static function fromArray(array $data): self {
        $deck = new self();
        $deck->id = (int) $data["id"];
        $deck->user_id = (int) $data["user_id"];
        $deck->tcg_id = (int) $data["tcg_id"];
        $deck->name = $data["name"];
        $deck->created_at = $data["created_at"];
        $deck->last_used = $data["last_used"];

        return $deck;
    }

    public function toArray(): array {
        return [
            "id" => $this->id,
            "user_id" => $this->user_id,
            "tcg_id" => $this->tcg_id,
            "name" => $this->name,
            "created_at" => $this->created_at,
            "last_used" => $this->last_used,
            "card_count" => $this->getCardCount(),
        ];
    }
}
