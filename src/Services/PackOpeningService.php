<?php

namespace TCG\Platform\Services;

use TCG\Platform\Models\BoosterPack;
use TCG\Platform\Models\Card;
use TCG\Platform\Models\UserInventory;
use TCG\Platform\Database\Database;
use PDO;

class PackOpeningService {
    private array $dropTable = [];

    public function openPack(int $userId, int $packId): array {
        $pack = BoosterPack::findById($packId);

        if (!$pack) {
            throw new \InvalidArgumentException("Pack not found");
        }

        $this->loadDropTable($pack);

        Database::beginTransaction();

        try {
            $openingId = $this->createPackOpening($userId, $packId);
            $cards = $this->generateCards($pack, $openingId);

            foreach ($cards as $card) {
                UserInventory::addCard($userId, $card["id"], 1, "pack");
            }

            Database::commit();

            return [
                "opening_id" => $openingId,
                "pack" => $pack->toArray(),
                "cards" => $cards,
            ];
        } catch (\Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    public function getOpeningResult(int $openingId): ?array {
        $stmt = Database::getConnection()->prepare(
            "SELECT po.*, bp.name as pack_name, bp.cards_per_pack 
             FROM pack_openings po
             JOIN booster_packs bp ON po.pack_id = bp.id
             WHERE po.id = :id"
        );
        $stmt->execute(["id" => $openingId]);
        $opening = $stmt->fetch();

        if (!$opening) {
            return null;
        }

        $stmt = Database::getConnection()->prepare(
            "SELECT poc.*, c.name as card_name, c.type as card_type, c.attack, c.defense, 
                    cr.name as rarity_name, cr.color as rarity_color, c.image_url
             FROM pack_opening_cards poc
             JOIN cards c ON poc.card_id = c.id
             JOIN card_rarity cr ON c.rarity_id = cr.id
             WHERE poc.opening_id = :opening_id"
        );
        $stmt->execute(["opening_id" => $openingId]);

        return [
            "id" => $opening["id"],
            "user_id" => $opening["user_id"],
            "pack" => [
                "id" => $opening["pack_id"],
                "name" => $opening["pack_name"],
                "cards_per_pack" => $opening["cards_per_pack"],
            ],
            "opened_at" => $opening["opened_at"],
            "cards" => $stmt->fetchAll(),
        ];
    }

    public function getUserOpenings(int $userId, int $limit = 20, int $offset = 0): array {
        $stmt = Database::getConnection()->prepare(
            "SELECT po.*, bp.name as pack_name 
             FROM pack_openings po
             JOIN booster_packs bp ON po.pack_id = bp.id
             WHERE po.user_id = :user_id
             ORDER BY po.opened_at DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(":user_id", $userId, PDO::PARAM_INT);
        $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function loadDropTable(BoosterPack $pack): void {
        $this->dropTable = $pack->getDropTable();

        if (empty($this->dropTable)) {
            throw new \RuntimeException("Pack has no drop table configured");
        }

        $totalProbability = array_sum(array_column($this->dropTable, "probability"));

        if (abs($totalProbability - 1.0) > 0.0001) {
            throw new \RuntimeException("Pack drop table probabilities must sum to 1.0");
        }
    }

    private function createPackOpening(int $userId, int $packId): int {
        $stmt = Database::getConnection()->prepare(
            "INSERT INTO pack_openings (user_id, pack_id) VALUES (:user_id, :pack_id)"
        );
        $stmt->execute(["user_id" => $userId, "pack_id" => $packId]);

        return (int) Database::getConnection()->lastInsertId();
    }

    private function generateCards(BoosterPack $pack, int $openingId): array {
        $cards = [];
        $cardsPerPack = $pack->cards_per_pack;

        for ($i = 0; $i < $cardsPerPack; $i++) {
            $rarityId = $this->rollRarity();
            $card = $this->getRandomCardByRarity($pack->tcg_id, $rarityId);

            if ($card) {
                $cards[] = $card->toArray();

                $stmt = Database::getConnection()->prepare(
                    "INSERT INTO pack_opening_cards (opening_id, card_id) VALUES (:opening_id, :card_id)"
                );
                $stmt->execute(["opening_id" => $openingId, "card_id" => $card->id]);
            }
        }

        return $cards;
    }

    private function rollRarity(): int {
        $roll = mt_rand(0, 999999) / 1000000;
        $cumulative = 0;

        foreach ($this->dropTable as $drop) {
            $cumulative += $drop["probability"];
            if ($roll < $cumulative) {
                return (int) $drop["rarity_id"];
            }
        }

        return (int) $this->dropTable[0]["rarity_id"];
    }

    private function getRandomCardByRarity(int $tcgId, int $rarityId): ?Card {
        $cards = Card::getRandomCardsByRarity($tcgId, $rarityId, 1);

        return $cards[0] ?? null;
    }
}
