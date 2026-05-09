<?php

namespace TCG\Platform\Models;

use TCG\Platform\Database\Database;
use PDO;

class UserInventory {
    public int $id;
    public int $user_id;
    public int $card_id;
    public int $quantity;
    public string $obtained_at;
    public string $source;

    public static function getUserInventory(int $userId, ?int $tcgId = null): array {
        $sql = "SELECT ui.*, c.name as card_name, c.type as card_type, c.attack, c.defense, 
                       cr.name as rarity_name, cr.color as rarity_color, c.image_url
                FROM user_inventory ui
                JOIN cards c ON ui.card_id = c.id
                JOIN card_rarity cr ON c.rarity_id = cr.id
                WHERE ui.user_id = :user_id";
        $params = ["user_id" => $userId];

        if ($tcgId !== null) {
            $sql .= " AND c.tcg_id = :tcg_id";
            $params["tcg_id"] = $tcgId;
        }

        $sql .= " ORDER BY ui.quantity DESC, c.name";

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function getCardQuantity(int $userId, int $cardId): int {
        $stmt = Database::getConnection()->prepare(
            "SELECT quantity FROM user_inventory WHERE user_id = :user_id AND card_id = :card_id"
        );
        $stmt->execute(["user_id" => $userId, "card_id" => $cardId]);
        $result = $stmt->fetch();

        return $result ? (int) $result["quantity"] : 0;
    }

    public static function addCard(int $userId, int $cardId, int $quantity = 1, string $source = "pack"): void {
        Database::beginTransaction();

        try {
            $stmt = Database::getConnection()->prepare(
                "INSERT INTO user_inventory (user_id, card_id, quantity, source) 
                 VALUES (:user_id, :card_id, :quantity, :source)
                 ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)"
            );
            $stmt->execute([
                "user_id" => $userId,
                "card_id" => $cardId,
                "quantity" => $quantity,
                "source" => $source,
            ]);

            Database::commit();
        } catch (\Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    public static function removeCard(int $userId, int $cardId, int $quantity = 1): bool {
        Database::beginTransaction();

        try {
            $currentQuantity = self::getCardQuantity($userId, $cardId);

            if ($currentQuantity < $quantity) {
                Database::rollback();
                return false;
            }

            if ($currentQuantity === $quantity) {
                $stmt = Database::getConnection()->prepare(
                    "DELETE FROM user_inventory WHERE user_id = :user_id AND card_id = :card_id"
                );
                $stmt->execute(["user_id" => $userId, "card_id" => $cardId]);
            } else {
                $stmt = Database::getConnection()->prepare(
                    "UPDATE user_inventory SET quantity = quantity - :quantity 
                     WHERE user_id = :user_id AND card_id = :card_id"
                );
                $stmt->execute([
                    "quantity" => $quantity,
                    "user_id" => $userId,
                    "card_id" => $cardId,
                ]);
            }

            Database::commit();
            return true;
        } catch (\Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    public static function transferCard(int $fromUserId, int $toUserId, int $cardId, int $quantity = 1): bool {
        Database::beginTransaction();

        try {
            if (!self::removeCard($fromUserId, $cardId, $quantity)) {
                Database::rollback();
                return false;
            }

            self::addCard($toUserId, $cardId, $quantity, "trade");

            Database::commit();
            return true;
        } catch (\Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    public static function hasCard(int $userId, int $cardId, int $minQuantity = 1): bool {
        return self::getCardQuantity($userId, $cardId) >= $minQuantity;
    }

    public static function getTotalCards(int $userId): int {
        $stmt = Database::getConnection()->prepare(
            "SELECT SUM(quantity) as total FROM user_inventory WHERE user_id = :user_id"
        );
        $stmt->execute(["user_id" => $userId]);
        $result = $stmt->fetch();

        return $result ? (int) $result["total"] : 0;
    }

    public static function getUniqueCards(int $userId): int {
        $stmt = Database::getConnection()->prepare(
            "SELECT COUNT(*) as total FROM user_inventory WHERE user_id = :user_id"
        );
        $stmt->execute(["user_id" => $userId]);
        $result = $stmt->fetch();

        return $result ? (int) $result["total"] : 0;
    }
}
