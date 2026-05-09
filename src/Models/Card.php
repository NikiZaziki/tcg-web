<?php

namespace TCG\Platform\Models;

use TCG\Platform\Database\Database;
use PDO;

class Card {
    public int $id;
    public int $tcg_id;
    public string $name;
    public int $rarity_id;
    public string $rarity_name;
    public string $rarity_color;
    public string $type;
    public int $attack;
    public int $defense;
    public ?string $ability_text;
    public ?string $image_url;

    public static function findById(int $id): ?self {
        $stmt = Database::getConnection()->prepare(
            "SELECT c.*, r.name as rarity_name, r.color as rarity_color 
             FROM cards c 
             JOIN card_rarity r ON c.rarity_id = r.id 
             WHERE c.id = :id"
        );
        $stmt->execute(["id" => $id]);
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        return self::fromArray($data);
    }

    public static function findByTcg(int $tcgId, ?string $type = null, ?int $rarityId = null): array {
        $sql = "SELECT c.*, r.name as rarity_name, r.color as rarity_color 
                FROM cards c 
                JOIN card_rarity r ON c.rarity_id = r.id 
                WHERE c.tcg_id = :tcg_id";
        $params = ["tcg_id" => $tcgId];

        if ($type !== null) {
            $sql .= " AND c.type = :type";
            $params["type"] = $type;
        }

        if ($rarityId !== null) {
            $sql .= " AND c.rarity_id = :rarity_id";
            $params["rarity_id"] = $rarityId;
        }

        $sql .= " ORDER BY c.name";

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return array_map(fn($data) => self::fromArray($data), $stmt->fetchAll());
    }

    public static function getRandomCardsByRarity(int $tcgId, int $rarityId, int $count): array {
        $stmt = Database::getConnection()->prepare(
            "SELECT c.*, r.name as rarity_name, r.color as rarity_color 
             FROM cards c 
             JOIN card_rarity r ON c.rarity_id = r.id 
             WHERE c.tcg_id = :tcg_id AND c.rarity_id = :rarity_id 
             ORDER BY RAND() 
             LIMIT :count"
        );
        $stmt->bindValue(":tcg_id", $tcgId, PDO::PARAM_INT);
        $stmt->bindValue(":rarity_id", $rarityId, PDO::PARAM_INT);
        $stmt->bindValue(":count", $count, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(fn($data) => self::fromArray($data), $stmt->fetchAll());
    }

    public static function search(string $query, ?int $tcgId = null, ?string $type = null, ?int $rarityId = null): array {
        $sql = "SELECT c.*, r.name as rarity_name, r.color as rarity_color 
                FROM cards c 
                JOIN card_rarity r ON c.rarity_id = r.id 
                WHERE c.name LIKE :query";
        $params = ["query" => "%$query%"];

        if ($tcgId !== null) {
            $sql .= " AND c.tcg_id = :tcg_id";
            $params["tcg_id"] = $tcgId;
        }

        if ($type !== null) {
            $sql .= " AND c.type = :type";
            $params["type"] = $type;
        }

        if ($rarityId !== null) {
            $sql .= " AND c.rarity_id = :rarity_id";
            $params["rarity_id"] = $rarityId;
        }

        $sql .= " ORDER BY c.name LIMIT 100";

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return array_map(fn($data) => self::fromArray($data), $stmt->fetchAll());
    }

    private static function fromArray(array $data): self {
        $card = new self();
        $card->id = (int) $data["id"];
        $card->tcg_id = (int) $data["tcg_id"];
        $card->name = $data["name"];
        $card->rarity_id = (int) $data["rarity_id"];
        $card->rarity_name = $data["rarity_name"];
        $card->rarity_color = $data["rarity_color"];
        $card->type = $data["type"];
        $card->attack = (int) $data["attack"];
        $card->defense = (int) $data["defense"];
        $card->ability_text = $data["ability_text"];
        $card->image_url = $data["image_url"];

        return $card;
    }

    public function toArray(): array {
        return [
            "id" => $this->id,
            "tcg_id" => $this->tcg_id,
            "name" => $this->name,
            "rarity" => [
                "id" => $this->rarity_id,
                "name" => $this->rarity_name,
                "color" => $this->rarity_color,
            ],
            "type" => $this->type,
            "attack" => $this->attack,
            "defense" => $this->defense,
            "ability_text" => $this->ability_text,
            "image_url" => $this->image_url,
        ];
    }
}
