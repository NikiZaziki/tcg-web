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

$page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;
$leaderboard = \TCG\Platform\Models\User::getLeaderboard($limit, $offset);
$totalPlayers = count($leaderboard);
$totalPages = ceil($totalPlayers / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - TCG Platform</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <nav class="sidebar">
            <div class="sidebar-header">
                <h1>TCG Platform</h1>
            </div>
            <ul class="sidebar-nav">
                <li><a href="/index.php" class="nav-link">Dashboard</a></li>
                <li><a href="/collection.php" class="nav-link">Collection</a></li>
                <li><a href="/decks.php" class="nav-link">Decks</a></li>
                <li><a href="/shop.php" class="nav-link">Shop</a></li>
                <li><a href="/matches.php" class="nav-link">Matches</a></li>
                <li><a href="/leaderboard.php" class="nav-link active">Leaderboard</a></li>
                <li><a href="/trades.php" class="nav-link">Trades</a></li>
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
            <div class="card">
                <h3>Leaderboard</h3>
                <p>Your Rank: #<?php 
                    $userRank = 0;
                    foreach ($leaderboard as $index => $entry) {
                        if ($entry["id"] == $user->id) {
                            $userRank = $index + 1;
                            break;
                        }
                    }
                    echo $userRank ?: "N/A";
                ?></p>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Player</th>
                            <th>ELO Rating</th>
                            <th>Tier</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaderboard as $index => $entry): ?>
                        <tr class="<?php echo $entry["id"] == $user->id ? "highlight" : ""; ?>">
                            <td><?php echo $offset + $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($entry["username"]); ?></td>
                            <td><?php echo $entry["elo_rating"]; ?></td>
                            <td><?php echo htmlspecialchars($entry["rank_tier"]); ?></td>
                            <td><?php echo date("Y-m-d", strtotime($entry["created_at"])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="/leaderboard.php?page=<?php echo $page - 1; ?>" class="btn btn-secondary">Previous</a>
                    <?php endif; ?>
                    
                    <span>Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="/leaderboard.php?page=<?php echo $page + 1; ?>" class="btn btn-secondary">Next</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3>Rank Tiers</h3>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="value">Bronze</div>
                        <div class="label">0 - 1199 ELO</div>
                    </div>
                    <div class="stat-card">
                        <div class="value">Silver</div>
                        <div class="label">1200 - 1599 ELO</div>
                    </div>
                    <div class="stat-card">
                        <div class="value">Gold</div>
                        <div class="label">1600 - 1999 ELO</div>
                    </div>
                    <div class="stat-card">
                        <div class="value">Platinum</div>
                        <div class="label">2000 - 2399 ELO</div>
                    </div>
                    <div class="stat-card">
                        <div class="value">Diamond</div>
                        <div class="label">2400 - 2799 ELO</div>
                    </div>
                    <div class="stat-card">
                        <div class="value">Master</div>
                        <div class="label">2800 - 3199 ELO</div>
                    </div>
                    <div class="stat-card">
                        <div class="value">Grandmaster</div>
                        <div class="label">3200+ ELO</div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
