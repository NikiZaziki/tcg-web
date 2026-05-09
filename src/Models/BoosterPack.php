<?php

namespace TCG\Platform\Models;

use TCG\Platform\Database\Database;
use PDO;

class BoosterPack {
    public int $id;
    public int $tcg_id;
    public string $name;
    public float $price;
    public int $cards_per_pack;
    public string $pack_type;

    public static function findById(int $id): ?self {
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM booster_packs WHERE id = :id"
        );
        $stmt->execute(["id" => $id]);
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        return self::fromArray($data);
    }

    public static function findByTcg(int $tcgId): array {
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM booster_packs WHERE tcg_id = :tcg_id ORDER BY name"
        );
        $stmt->execute(["tcg_id" => $tcgId]);

        return array_map(fn($data) => self::fromArray($data), $stmt->fetchAll());
    }

    public static function findAll(): array {
        $stmt = Database::getConnection()->query(
            "SELECT * FROM booster_packs ORDER BY name"
        );

        return array_map(fn($data) => self::fromArray($data), $stmt->fetchAll());
    }

    public static function create(int $tcgId, string $name, float $price, int $cardsPerPack, string $packType = "standard"): self {
        $stmt = Database::getConnection()->prepare(
            "INSERT INTO booster_packs (tcg_id, name, price, cards_per_pack, pack_type) 
             VALUES (:tcg_id, :name, :price, :cards_per_pack, :pack_type)"
        );
        $stmt->execute([
            "tcg_id" => $tcgId,
            "name" => $name,
            "price" => $price,
            "cards_per_pack" => $cardsPerPack,
            "pack_type" => $packType,
        ]);

        $id = (int) Database::getConnection()->lastInsertId();

        return self::findById($id);
    }

    public function getDropTable(): array {
        $stmt = Database::getConnection()->prepare(
            "SELECT pdt.*, cr.name as rarity_name, cr.color as rarity_color 
             FROM pack_drop_tables pdt 
             JOIN card_rarity cr ON pdt.rarity_id = cr.id 
             WHERE pdt.pack_id = :pack_id 
             ORDER BY pdt.probability DESC"
        );
        $stmt->execute(["pack_id" => $this->id]);

        return $stmt->fetchAll();
    }

    public function setDropTable(array $dropTable): void {
        Database::beginTransaction();

        try {
            $deleteStmt = Database::getConnection()->prepare(
                "DELETE FROM pack_drop_tables WHERE pack_id = :pack_id"
            );
            $deleteStmt->execute(["pack_id" => $this->id]);

            $insertStmt = Database::getConnection()->prepare(
                "INSERT INTO pack_drop_tables (pack_id, rarity_id, probability) 
                 VALUES (:pack_id, :rarity_id, :probability)"
            );

            foreach ($dropTable as $drop) {
                $insertStmt->execute([
                    "pack_id" => $this->id,
                    "rarity_id" => $drop["rarity_id"],
                    "probability" => $drop["probability"],
                ]);
            }

            Database::commit();
        } catch (\Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    private static function fromArray(array $data): self {
        $pack = new self();
        $pack->id = (int) $data["id"];
        $pack->tcg_id = (int) $data["tcg_id"];
        $pack->name = $data["name"];
        $pack->price = (float) $data["price"];
        $pack->cards_per_pack = (int) $data["cards_per_pack"];
        $pack->pack_type = $data["pack_type"];

        return $pack;
    }

    public function toArray(): array {
        return [
            "id" => $this->id,
            "tcg_id" => $this->tcg_id,
            "name" => $this->name,
            "price" => $this->price,
            "cards_per_pack" => $this->cards_per_pack,
            "pack_type" => $this->pack_type,
        ];
    }
}
