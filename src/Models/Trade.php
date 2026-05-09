<?php

namespace TCG\Platform\Models;

use TCG\Platform\Database\Database;

class Trade {
    public int $id;
    public int $sender_id;
    public int $receiver_id;
    public string $status;
    public string $created_at;

    public static function findById(int $id): ?self {
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM trades WHERE id = :id"
        );
        $stmt->execute(["id" => $id]);
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        return self::fromArray($data);
    }

    public static function findByUser(int $userId, ?string $status = null): array {
        $sql = "SELECT * FROM trades 
                WHERE (sender_id = :user_id OR receiver_id = :user_id)";
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

    public static function create(int $senderId, int $receiverId): self {
        $stmt = Database::getConnection()->prepare(
            "INSERT INTO trades (sender_id, receiver_id, status) 
             VALUES (:sender_id, :receiver_id, pending)"
        );
        $stmt->execute([
            "sender_id" => $senderId,
            "receiver_id" => $receiverId,
        ]);

        $id = (int) Database::getConnection()->lastInsertId();

        return self::findById($id);
    }

    public static function updateStatus(int $tradeId, string $status): void {
        $stmt = Database::getConnection()->prepare(
            "UPDATE trades SET status = :status WHERE id = :id"
        );
        $stmt->execute(["status" => $status, "id" => $tradeId]);
    }

    public function addCard(int $userId, int $cardId, int $quantity = 1): void {
        $stmt = Database::getConnection()->prepare(
            "INSERT INTO trade_cards (trade_id, user_id, card_id, quantity) 
             VALUES (:trade_id, :user_id, :card_id, :quantity)
             ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)"
        );
        $stmt->execute([
            "trade_id" => $this->id,
            "user_id" => $userId,
            "card_id" => $cardId,
            "quantity" => $quantity,
        ]);
    }

    public function getCards(int $userId): array {
        $stmt = Database::getConnection()->prepare(
            "SELECT tc.*, c.name as card_name, c.type as card_type, 
                    cr.name as rarity_name, cr.color as rarity_color, c.image_url
             FROM trade_cards tc
             JOIN cards c ON tc.card_id = c.id
             JOIN card_rarity cr ON c.rarity_id = cr.id
             WHERE tc.trade_id = :trade_id AND tc.user_id = :user_id"
        );
        $stmt->execute(["trade_id" => $this->id, "user_id" => $userId]);

        return $stmt->fetchAll();
    }

    public function getAllCards(): array {
        $stmt = Database::getConnection()->prepare(
            "SELECT tc.*, c.name as card_name, c.type as card_type, 
                    cr.name as rarity_name, cr.color as rarity_color, c.image_url
             FROM trade_cards tc
             JOIN cards c ON tc.card_id = c.id
             JOIN card_rarity cr ON c.rarity_id = cr.id
             WHERE tc.trade_id = :trade_id"
        );
        $stmt->execute(["trade_id" => $this->id]);

        return $stmt->fetchAll();
    }

    public function execute(): bool {
        Database::beginTransaction();

        try {
            $cards = $this->getAllCards();
            $senderCards = [];
            $receiverCards = [];

            foreach ($cards as $card) {
                if ($card["user_id"] == $this->sender_id) {
                    $senderCards[] = $card;
                } else {
                    $receiverCards[] = $card;
                }
            }

            foreach ($senderCards as $card) {
                if (!UserInventory::removeCard($this->sender_id, $card["card_id"], $card["quantity"])) {
                    Database::rollback();
                    return false;
                }
                UserInventory::addCard($this->receiver_id, $card["card_id"], $card["quantity"], "trade");
            }

            foreach ($receiverCards as $card) {
                if (!UserInventory::removeCard($this->receiver_id, $card["card_id"], $card["quantity"])) {
                    Database::rollback();
                    return false;
                }
                UserInventory::addCard($this->sender_id, $card["card_id"], $card["quantity"], "trade");
            }

            self::updateStatus($this->id, "accepted");

            Database::commit();
            return true;
        } catch (\Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    public function isParticipant(int $userId): bool {
        return $this->sender_id === $userId || $this->receiver_id === $userId;
    }

    public function getOtherUserId(int $userId): int {
        return $this->sender_id === $userId ? $this->receiver_id : $this->sender_id;
    }

    private static function fromArray(array $data): self {
        $trade = new self();
        $trade->id = (int) $data["id"];
        $trade->sender_id = (int) $data["sender_id"];
        $trade->receiver_id = (int) $data["receiver_id"];
        $trade->status = $data["status"];
        $trade->created_at = $data["created_at"];

        return $trade;
    }

    public function toArray(): array {
        return [
            "id" => $this->id,
            "sender_id" => $this->sender_id,
            "receiver_id" => $this->receiver_id,
            "status" => $this->status,
            "created_at" => $this->created_at,
        ];
    }
}
