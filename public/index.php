<?php
require_once __DIR__ . "/../vendor/autoload.php";

use TCG\Platform\Config\Config;
use TCG\Platform\Database\Database;

Config::load();
Database::init(Config::get("database"));

$token = $_COOKIE["tcg_token"] ?? null;
$user = null;

if ($token) {
    try {
        $payload = \TCG\Platform\Auth\AuthService::verifyToken($token);
        if ($payload) {
            $user = \TCG\Platform\Models\User::findById((int) $payload["user_id"]);
        }
    } catch (Exception $e) {
        // Invalid token, ignore
    }
}

if (!$user) {
    header("Location: /login.html");
    exit;
}

$games = \TCG\Platform\Models\TcgGame::findAll();
$inventory = \TCG\Platform\Models\UserInventory::getUserInventory($user->id);
$dailyStatus = null;
$packService = new \TCG\Platform\Services\PackOpeningService();
$dailyService = new \TCG\Platform\Services\DailyRewardService($packService);
$dailyStatus = $dailyService->getDailyPackStatus($user->id);
$activeMatches = \TCG\Platform\Models\Match::findByUser($user->id, "active");
$leaderboard = \TCG\Platform\Models\User::getLeaderboard(10);
$packs = \TCG\Platform\Models\BoosterPack::findAll();
$decks = \TCG\Platform\Models\Deck::findByUser($user->id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TCG Platform - Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <nav class="sidebar">
            <div class="sidebar-header">
                <h1>TCG Platform</h1>
            </div>
            <ul class="sidebar-nav">
                <li><a href="#" class="nav-link active" data-page="dashboard">Dashboard</a></li>
                <li><a href="#" class="nav-link" data-page="collection">Collection</a></li>
                <li><a href="#" class="nav-link" data-page="decks">Decks</a></li>
                <li><a href="#" class="nav-link" data-page="shop">Shop</a></li>
                <li><a href="#" class="nav-link" data-page="matches">Matches</a></li>
                <li><a href="#" class="nav-link" data-page="leaderboard">Leaderboard</a></li>
                <li><a href="#" class="nav-link" data-page="trades">Trades</a></li>
            </ul>
            <div class="sidebar-footer">
                <div class="user-info">
                    <span><?php echo htmlspecialchars($user->username); ?></span>
                    <br>
                    <small>ELO: <?php echo $user->elo_rating; ?> (<?php echo htmlspecialchars($user->rank_tier); ?>)</small>
                </div>
                <form method="POST" action="/logout.php">
                    <button type="submit" class="btn btn-secondary">Logout</button>
                </form>
            </div>
        </nav>

        <main class="main-content">
            <div id="page-content">
                <!-- Dashboard Content -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="value"><?php echo count($inventory); ?></div>
                        <div class="label">Unique Cards</div>
                    </div>
                    <div class="stat-card">
                        <div class="value"><?php echo array_sum(array_column($inventory, "quantity")); ?></div>
                        <div class="label">Total Cards</div>
                    </div>
                    <div class="stat-card">
                        <div class="value"><?php echo count($decks); ?></div>
                        <div class="label">Decks</div>
                    </div>
                    <div class="stat-card">
                        <div class="value"><?php echo count($activeMatches); ?></div>
                        <div class="label">Active Matches</div>
                    </div>
                </div>

                <div class="card">
                    <h3>Daily Pack</h3>
                    <?php if ($dailyStatus["can_claim"]): ?>
                        <form method="POST" action="/api/users/daily/claim.php">
                            <button type="submit" class="btn btn-primary">Claim Free Pack</button>
                        </form>
                    <?php else: ?>
                        <p>Next pack available in: <?php echo htmlspecialchars($dailyStatus["wait_time_formatted"]); ?></p>
                        <p><small>Available at: <?php echo htmlspecialchars($dailyStatus["next_available_at"]); ?></small></p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h3>Recent Cards</h3>
                    <div class="grid">
                        <?php 
                        $recentCards = array_slice($inventory, 0, 8);
                        foreach ($recentCards as $card): 
                            $rarityClass = "";
                            switch ($card["rarity_name"]) {
                                case "Common": $rarityClass = "rarity-common"; break;
                                case "Uncommon": $rarityClass = "rarity-uncommon"; break;
                                case "Rare": $rarityClass = "rarity-rare"; break;
                                case "Ultra Rare": $rarityClass = "rarity-ultra-rare"; break;
                            }
                        ?>
                        <div class="card-item">
                            <img src="<?php echo htmlspecialchars($card["image_url"] ?: "/assets/images/placeholder.png"); ?>" alt="<?php echo htmlspecialchars($card["card_name"]); ?>">
                            <div class="card-item-info">
                                <div class="card-item-name"><?php echo htmlspecialchars($card["card_name"]); ?></div>
                                <div class="card-item-type"><?php echo htmlspecialchars($card["card_type"]); ?></div>
                                <div class="card-item-stats">
                                    <span>ATK: <?php echo $card["attack"]; ?></span>
                                    <span>DEF: <?php echo $card["defense"]; ?></span>
                                </div>
                                <span class="card-item-rarity <?php echo $rarityClass; ?>"><?php echo htmlspecialchars($card["rarity_name"]); ?></span>
                                <div class="card-item-quantity">x<?php echo $card["quantity"]; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card">
                    <h3>Active Matches</h3>
                    <?php if (empty($activeMatches)): ?>
                        <p>No active matches</p>
                        <a href="/matches.php" class="btn btn-primary">Find Match</a>
                    <?php else: ?>
                        <div class="grid">
                            <?php foreach ($activeMatches as $match): ?>
                            <div class="card">
                                <h3>Match #<?php echo $match["id"]; ?></h3>
                                <p>Mode: <?php echo htmlspecialchars($match["mode"]); ?></p>
                                <p>Status: <?php echo htmlspecialchars($match["status"]); ?></p>
                                <a href="/match.php?id=<?php echo $match["id"]; ?>" class="btn btn-primary">Join Match</a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h3>Leaderboard (Top 10)</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Player</th>
                                <th>ELO Rating</th>
                                <th>Tier</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leaderboard as $index => $entry): ?>
                            <tr class="<?php echo $entry["id"] == $user->id ? "highlight" : ""; ?>">
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($entry["username"]); ?></td>
                                <td><?php echo $entry["elo_rating"]; ?></td>
                                <td><?php echo htmlspecialchars($entry["rank_tier"]); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <a href="/leaderboard.php" class="btn btn-secondary">View Full Leaderboard</a>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/js/api.js"></script>
    <script>
        // Set token from PHP
        api.setToken("<?php echo $token; ?>");
        
        // Store user data
        localStorage.setItem("tcg_user", JSON.stringify(<?php echo json_encode($user->toArray()); ?>));
    </script>
</body>
</html>
