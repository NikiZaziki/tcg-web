<?php

namespace TCG\Platform\Models;

use TCG\Platform\Database\Database;
use PDO;

class User {
    public int $id;
    public string $username;
    public string $email;
    public string $password_hash;
    public int $elo_rating;
    public string $rank_tier;
    public string $created_at;
    public ?string $last_login;
    public ?string $last_daily_pack;

    private static array $rankTiers = [
        ["name" => "Bronze", "min_elo" => 0, "max_elo" => 1199],
        ["name" => "Silver", "min_elo" => 1200, "max_elo" => 1599],
        ["name" => "Gold", "min_elo" => 1600, "max_elo" => 1999],
        ["name" => "Platinum", "min_elo" => 2000, "max_elo" => 2399],
        ["name" => "Diamond", "min_elo" => 2400, "max_elo" => 2799],
        ["name" => "Master", "min_elo" => 2800, "max_elo" => 3199],
        ["name" => "Grandmaster", "min_elo" => 3200, "max_elo" => PHP_INT_MAX],
    ];

    public static function findById(int $id): ?self {
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM users WHERE id = :id"
        );
        $stmt->execute(["id" => $id]);
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        return self::fromArray($data);
    }

    public static function findByEmail(string $email): ?self {
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM users WHERE email = :email"
        );
        $stmt->execute(["email" => $email]);
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        return self::fromArray($data);
    }

    public static function findByUsername(string $username): ?self {
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM users WHERE username = :username"
        );
        $stmt->execute(["username" => $username]);
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        return self::fromArray($data);
    }

    public static function create(string $username, string $email, string $password): self {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = Database::getConnection()->prepare(
            "INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :password_hash)"
        );
        $stmt->execute([
            "username" => $username,
            "email" => $email,
            "password_hash" => $password_hash,
        ]);

        $id = (int) Database::getConnection()->lastInsertId();

        return self::findById($id);
    }

    public static function updateElo(int $userId, int $newElo): void {
        $rankTier = self::calculateRankTier($newElo);

        $stmt = Database::getConnection()->prepare(
            "UPDATE users SET elo_rating = :elo, rank_tier = :rank WHERE id = :id"
        );
        $stmt->execute([
            "elo" => $newElo,
            "rank" => $rankTier,
            "id" => $userId,
        ]);
    }

    public static function updateLastLogin(int $userId): void {
        $stmt = Database::getConnection()->prepare(
            "UPDATE users SET last_login = NOW() WHERE id = :id"
        );
        $stmt->execute(["id" => $userId]);
    }

    public static function updateLastDailyPack(int $userId): void {
        $stmt = Database::getConnection()->prepare(
            "UPDATE users SET last_daily_pack = NOW() WHERE id = :id"
        );
        $stmt->execute(["id" => $userId]);
    }

    public static function canClaimDailyPack(int $userId): bool {
        $stmt = Database::getConnection()->prepare(
            "SELECT last_daily_pack FROM users WHERE id = :id"
        );
        $stmt->execute(["id" => $userId]);
        $result = $stmt->fetch();

        if (!$result || !$result["last_daily_pack"]) {
            return true;
        }

        $lastPack = strtotime($result["last_daily_pack"]);
        $now = time();
        $hoursSince = ($now - $lastPack) / 3600;

        return $hoursSince >= 24;
    }

    public static function getLeaderboard(int $limit = 100, int $offset = 0): array {
        $stmt = Database::getConnection()->prepare(
            "SELECT id, username, elo_rating, rank_tier FROM users ORDER BY elo_rating DESC LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private static function calculateRankTier(int $elo): string {
        foreach (self::$rankTiers as $tier) {
            if ($elo >= $tier["min_elo"] && $elo <= $tier["max_elo"]) {
                return $tier["name"];
            }
        }
        return "Bronze";
    }

    private static function fromArray(array $data): self {
        $user = new self();
        $user->id = (int) $data["id"];
        $user->username = $data["username"];
        $user->email = $data["email"];
        $user->password_hash = $data["password_hash"];
        $user->elo_rating = (int) $data["elo_rating"];
        $user->rank_tier = $data["rank_tier"];
        $user->created_at = $data["created_at"];
        $user->last_login = $data["last_login"];
        $user->last_daily_pack = $data["last_daily_pack"];

        return $user;
    }

    public function verifyPassword(string $password): bool {
        return password_verify($password, $this->password_hash);
    }

    public function toArray(): array {
        return [
            "id" => $this->id,
            "username" => $this->username,
            "email" => $this->email,
            "elo_rating" => $this->elo_rating,
            "rank_tier" => $this->rank_tier,
            "created_at" => $this->created_at,
            "last_login" => $this->last_login,
        ];
    }
}
