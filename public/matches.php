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
$decks = \TCG\Platform\Models\Deck::findByUser($user->id);
$activeMatches = \TCG\Platform\Models\Match::findByUser($user->id, "active");
$matchHistory = \TCG\Platform\Models\Match::getMatchHistory($user->id, 20);

// Handle match finding
$error = null;
$success = null;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    if ($_POST["action"] === "find_match" && isset($_POST["deck_id"]) && isset($_POST["mode"])) {
        try {
            $service = new \TCG\Platform\Services\MatchmakingService();
            $match = $service->findMatch($user->id, (int) $_POST["deck_id"], $_POST["mode"]);
            
            if ($match) {
                $success = "Match found! Redirecting...";
                header("Location: /match.php?id=" . $match->id);
                exit;
            } else {
                $error = "No opponent found. You have been added to the queue.";
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($_POST["action"] === "end_match" && isset($_POST["match_id"]) && isset($_POST["winner_id"])) {
        try {
            $service = new \TCG\Platform\Services\MatchmakingService();
            $service->endMatch((int) $_POST["match_id"], (int) $_POST["winner_id"]);
            $success = "Match ended successfully!";
            $activeMatches = \TCG\Platform\Models\Match::findByUser($user->id, "active");
            $matchHistory = \TCG\Platform\Models\Match::getMatchHistory($user->id, 20);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($_POST["action"] === "abandon_match" && isset($_POST["match_id"])) {
        try {
            $service = new \TCG\Platform\Services\MatchmakingService();
            $service->abandonMatch((int) $_POST["match_id"], $user->id);
            $success = "Match abandoned successfully!";
            $activeMatches = \TCG\Platform\Models\Match::findByUser($user->id, "active");
            $matchHistory = \TCG\Platform\Models\Match::getMatchHistory($user->id, 20);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matches - TCG Platform</title>
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
                <li><a href="/matches.php" class="nav-link active">Matches</a></li>
                <li><a href="/leaderboard.php" class="nav-link">Leaderboard</a></li>
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
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="card">
                <h3>Find Match</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="find_match">
                    <div class="form-group">
                        <label for="deck_id">Select Deck</label>
                        <select id="deck_id" name="deck_id" required>
                            <option value="">-- Select Deck --</option>
                            <?php foreach ($decks as $deck): ?>
                                <option value="<?php echo $deck["id"]; ?>">
                                    <?php echo htmlspecialchars($deck["name"]); ?> (<?php echo $deck["card_count"]; ?> cards)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="mode">Mode</label>
                        <select id="mode" name="mode" required>
                            <option value="normal">Normal (No Risk)</option>
                            <option value="ranked">Ranked (Card Risk)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Find Match</button>
                </form>
            </div>

            <div class="card">
                <h3>Active Matches</h3>
                <?php if (empty($activeMatches)): ?>
                    <p>No active matches</p>
                <?php else: ?>
                    <div class="grid">
                        <?php foreach ($activeMatches as $match): ?>
                        <div class="card">
                            <h3>Match #<?php echo $match["id"]; ?></h3>
                            <p>Mode: <?php echo htmlspecialchars($match["mode"]); ?></p>
                            <p>Status: <?php echo htmlspecialchars($match["status"]); ?></p>
                            <a href="/match.php?id=<?php echo $match["id"]; ?>" class="btn btn-primary">Join Match</a>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="abandon_match">
                                <input type="hidden" name="match_id" value="<?php echo $match["id"]; ?>">
                                <button type="submit" class="btn btn-secondary btn-sm">Abandon</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3>Match History</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Mode</th>
                            <th>Result</th>
                            <th>Opponent</th>
                            <th>ELO Change</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($matchHistory as $match): 
                            $isPlayer1 = $match["player1_id"] == $user->id;
                            $opponent = $isPlayer1 ? $match["player2_name"] : $match["player1_name"];
                            $won = $match["winner_id"] == $user->id;
                            $resultClass = $won ? "text-success" : "text-danger";
                            $resultText = $won ? "Won" : "Lost";
                        ?>
                        <tr>
                            <td><?php echo date("Y-m-d H:i", strtotime($match["created_at"])); ?></td>
                            <td><?php echo htmlspecialchars($match["mode"]); ?></td>
                            <td class="<?php echo $resultClass; ?>"><?php echo $resultText; ?></td>
                            <td><?php echo htmlspecialchars($opponent); ?></td>
                            <td><?php echo $match["mode"] === "ranked" ? "+/- 32" : "0"; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
