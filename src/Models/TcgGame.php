<?php

namespace TCG\Platform\Models;

use TCG\Platform\Database\Database;

class TcgGame {
    public int $id;
    public string $name;
    public int $deck_size;
    public int $max_card_copies;
    public string $ruleset_version;
    public string $created_at;

    public static function findById(int $id): ?self {
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM tcg_games WHERE id = :id"
        );
        $stmt->execute(["id" => $id]);
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        return self::fromArray($data);
    }

    public static function findAll(): array {
        $stmt = Database::getConnection()->query(
            "SELECT * FROM tcg_games ORDER BY name"
        );

        return array_map(fn($data) => self::fromArray($data), $stmt->fetchAll());
    }

    public static function create(string $name, int $deckSize, int $maxCardCopies, string $rulesetVersion = "1.0"): self {
        $stmt = Database::getConnection()->prepare(
            "INSERT INTO tcg_games (name, deck_size, max_card_copies, ruleset_version) 
             VALUES (:name, :deck_size, :max_card_copies, :ruleset_version)"
        );
        $stmt->execute([
            "name" => $name,
            "deck_size" => $deckSize,
            "max_card_copies" => $maxCardCopies,
            "ruleset_version" => $rulesetVersion,
        ]);

        $id = (int) Database::getConnection()->lastInsertId();

        return self::findById($id);
    }

    private static function fromArray(array $data): self {
        $game = new self();
        $game->id = (int) $data["id"];
        $game->name = $data["name"];
        $game->deck_size = (int) $data["deck_size"];
        $game->max_card_copies = (int) $data["max_card_copies"];
        $game->ruleset_version = $data["ruleset_version"];
        $game->created_at = $data["created_at"];

        return $game;
    }

    public function toArray(): array {
        return [
            "id" => $this->id,
            "name" => $this->name,
            "deck_size" => $this->deck_size,
            "max_card_copies" => $this->max_card_copies,
            "ruleset_version" => $this->ruleset_version,
            "created_at" => $this->created_at,
        ];
    }
}
