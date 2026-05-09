<?php

namespace TCG\Platform\Services;

use TCG\Platform\Models\User;
use TCG\Platform\Models\UserInventory;
use TCG\Platform\Models\BoosterPack;
use TCG\Platform\Services\PackOpeningService;

class DailyRewardService {
    private PackOpeningService $packService;

    public function __construct(PackOpeningService $packService) {
        $this->packService = $packService;
    }

    public function canClaimDailyPack(int $userId): bool {
        return User::canClaimDailyPack($userId);
    }

    public function getTimeUntilNextPack(int $userId): int {
        $user = User::findById($userId);

        if (!$user || !$user->last_daily_pack) {
            return 0;
        }

        $lastPack = strtotime($user->last_daily_pack);
        $nextPack = $lastPack + (24 * 3600);
        $now = time();

        return max(0, $nextPack - $now);
    }

    public function claimDailyPack(int $userId): array {
        if (!$this->canClaimDailyPack($userId)) {
            $waitTime = $this->getTimeUntilNextPack($userId);
            throw new \RuntimeException("Daily pack not available yet. Wait " . $this->formatTime($waitTime));
        }

        $pack = $this->getDefaultDailyPack();

        if (!$pack) {
            throw new \RuntimeException("No daily pack configured");
        }

        User::updateLastDailyPack($userId);

        $result = $this->packService->openPack($userId, $pack->id);

        return [
            "success" => true,
            "pack" => $result["pack"],
            "cards" => $result["cards"],
            "next_available_at" => date("Y-m-d H:i:s", time() + (24 * 3600)),
        ];
    }

    public function getDailyPackStatus(int $userId): array {
        $canClaim = $this->canClaimDailyPack($userId);
        $waitTime = $this->getTimeUntilNextPack($userId);
        $pack = $this->getDefaultDailyPack();

        return [
            "can_claim" => $canClaim,
            "wait_time_seconds" => $waitTime,
            "wait_time_formatted" => $this->formatTime($waitTime),
            "next_available_at" => $canClaim ? "Now" : date("Y-m-d H:i:s", time() + $waitTime),
            "pack" => $pack ? $pack->toArray() : null,
        ];
    }

    private function getDefaultDailyPack(): ?BoosterPack {
        $stmt = \TCG\Platform\Database\Database::getConnection()->query(
            "SELECT * FROM booster_packs WHERE pack_type = daily LIMIT 1"
        );
        $data = $stmt->fetch();

        if (!$data) {
            $stmt = \TCG\Platform\Database\Database::getConnection()->query(
                "SELECT * FROM booster_packs ORDER BY price ASC LIMIT 1"
            );
            $data = $stmt->fetch();
        }

        if (!$data) {
            return null;
        }

        return BoosterPack::findById((int) $data["id"]);
    }

    private function formatTime(int $seconds): string {
        if ($seconds < 60) {
            return "$seconds seconds";
        }

        $minutes = floor($seconds / 60);

        if ($minutes < 60) {
            return "$minutes minute" . ($minutes > 1 ? "s" : "");
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($hours < 24) {
            return "$hours hour" . ($hours > 1 ? "s" : "") . 
                   ($remainingMinutes > 0 ? " $remainingMinutes minute" . ($remainingMinutes > 1 ? "s" : "") : "");
        }

        $days = floor($hours / 24);
        $remainingHours = $hours % 24;

        return "$days day" . ($days > 1 ? "s" : "") . 
               ($remainingHours > 0 ? " $remainingHours hour" . ($remainingHours > 1 ? "s" : "") : "");
    }
}
