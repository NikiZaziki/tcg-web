<?php

namespace TCG\Platform\Services;

use TCG\Platform\Models\Match;
use TCG\Platform\Models\User;
use TCG\Platform\Models\Deck;
use TCG\Platform\Models\RankedTransfer;
use TCG\Platform\Models\UserInventory;
use TCG\Platform\Database\Database;
use PDO;

class MatchmakingService {
    private const ELO_RANGE = 200;
    private const QUEUE_TIMEOUT = 300;

    public function findMatch(int $userId, int $deckId, string $mode = "normal"): ?Match {
        $user = User::findById($userId);

        if (!$user) {
            throw new \InvalidArgumentException("User not found");
        }

        $deck = Deck::findById($deckId);

        if (!$deck || $deck->user_id !== $userId) {
            throw new \InvalidArgumentException("Deck not found or does not belong to user");
        }

        $game = $this->getGameForDeck($deck);

        if (!$game) {
            throw new \RuntimeException("Game configuration not found for deck");
        }

        $errors = $deck->validate($game);

        if (!empty($errors)) {
            throw new \InvalidArgumentException("Deck validation failed: " . implode(", ", $errors));
        }

        $opponent = $this->findOpponent($userId, $game->id, $mode);

        if (!$opponent) {
            return null;
        }

        $opponentDeck = $this->getOpponentDeck($opponent["id"], $game->id);

        if (!$opponentDeck) {
            return null;
        }

        $match = Match::create($userId, $opponent["id"], $deckId, $opponentDeck["id"], $mode);

        Match::updateStatus($match->id, "active");

        return $match;
    }

    public function endMatch(int $matchId, int $winnerId): array {
        $match = Match::findById($matchId);

        if (!$match) {
            throw new \InvalidArgumentException("Match not found");
        }

        if ($match->status !== "active") {
            throw new \RuntimeException("Match is not active");
        }

        if (!$match->isPlayer($winnerId)) {
            throw new \InvalidArgumentException("Winner is not a participant in this match");
        }

        Database::beginTransaction();

        try {
            Match::setWinner($matchId, $winnerId);

            $loserId = $match->getOpponentId($winnerId);

            if ($match->mode === "ranked") {
                $this->processRankedMatch($match, $winnerId, $loserId);
            }

            Database::commit();

            return [
                "match_id" => $matchId,
                "winner_id" => $winnerId,
                "loser_id" => $loserId,
                "mode" => $match->mode,
            ];
        } catch (\Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    public function abandonMatch(int $matchId, int $userId): void {
        $match = Match::findById($matchId);

        if (!$match) {
            throw new \InvalidArgumentException("Match not found");
        }

        if (!$match->isPlayer($userId)) {
            throw new \InvalidArgumentException("User is not a participant in this match");
        }

        if ($match->status !== "active") {
            throw new \RuntimeException("Match is not active");
        }

        Database::beginTransaction();

        try {
            $opponentId = $match->getOpponentId($userId);
            Match::setWinner($matchId, $opponentId);

            if ($match->mode === "ranked") {
                $this->processRankedMatch($match, $opponentId, $userId);
            }

            Database::commit();
        } catch (\Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    public function getActiveMatches(): array {
        return Match::getActiveMatches();
    }

    private function findOpponent(int $userId, int $tcgId, string $mode): ?array {
        $user = User::findById($userId);
        $minElo = max(0, $user->elo_rating - self::ELO_RANGE);
        $maxElo = $user->elo_rating + self::ELO_RANGE;

        $stmt = Database::getConnection()->prepare(
            "SELECT u.id, u.username, u.elo_rating 
             FROM users u
             WHERE u.id != :user_id
             AND u.elo_rating BETWEEN :min_elo AND :max_elo
             AND u.id NOT IN (
                 SELECT DISTINCT CASE 
                     WHEN player1_id = :user_id THEN player2_id 
                     ELSE player1_id 
                 END
                 FROM matches 
                 WHERE (player1_id = :user_id OR player2_id = :user_id)
                 AND status IN (pending, active)
                 AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
             )
             ORDER BY ABS(u.elo_rating - :user_elo) ASC
             LIMIT 10"
        );
        $stmt->execute([
            "user_id" => $userId,
            "min_elo" => $minElo,
            "max_elo" => $maxElo,
            "user_elo" => $user->elo_rating,
        ]);

        $candidates = $stmt->fetchAll();

        if (empty($candidates)) {
            return null;
        }

        return $candidates[array_rand($candidates)];
    }

    private function getOpponentDeck(int $userId, int $tcgId): ?array {
        $stmt = Database::getConnection()->prepare(
            "SELECT d.* FROM decks d 
             WHERE d.user_id = :user_id AND d.tcg_id = :tcg_id
             ORDER BY d.last_used DESC, d.created_at DESC
             LIMIT 1"
        );
        $stmt->execute(["user_id" => $userId, "tcg_id" => $tcgId]);

        return $stmt->fetch();
    }

    private function getGameForDeck(Deck $deck): ?object {
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM tcg_games WHERE id = :id"
        );
        $stmt->execute(["id" => $deck->tcg_id]);

        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        return (object) [
            "id" => (int) $data["id"],
            "name" => $data["name"],
            "deck_size" => (int) $data["deck_size"],
            "max_card_copies" => (int) $data["max_card_copies"],
        ];
    }

    private function processRankedMatch(Match $match, int $winnerId, int $loserId): void {
        $winner = User::findById($winnerId);
        $loser = User::findById($loserId);

        $expectedWinner = $this->calculateExpectedScore($winner->elo_rating, $loser->elo_rating);
        $expectedLoser = 1 - $expectedWinner;

        $kFactor = 32;
        $winnerNewElo = $winner->elo_rating + $kFactor * (1 - $expectedWinner);
        $loserNewElo = $loser->elo_rating + $kFactor * (0 - $expectedLoser);

        User::updateElo($winnerId, (int) round($winnerNewElo));
        User::updateElo($loserId, (int) round($loserNewElo));

        $loserDeck = Deck::findById($match->getDeckId($loserId));

        if ($loserDeck) {
            $cardId = $loserDeck->getRandomCard();

            if ($cardId) {
                UserInventory::transferCard($loserId, $winnerId, $cardId, 1);

                RankedTransfer::create($match->id, $winnerId, $loserId, $cardId);
            }
        }
    }

    private function calculateExpectedScore(int $playerElo, int $opponentElo): float {
        return 1 / (1 + pow(10, ($opponentElo - $playerElo) / 400));
    }
}
