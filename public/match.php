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

$matchId = isset($_GET["id"]) ? (int) $_GET["id"] : null;

if (!$matchId) {
    header("Location: /matches.php");
    exit;
}

$match = \TCG\Platform\Models\Match::findById($matchId);

if (!$match || !$match->isPlayer($user->id)) {
    header("Location: /matches.php");
    exit;
}

$deck1 = \TCG\Platform\Models\Deck::findById($match->deck1_id);
$deck2 = \TCG\Platform\Models\Deck::findById($match->deck2_id);
$player1 = \TCG\Platform\Models\User::findById($match->player1_id);
$player2 = \TCG\Platform\Models\User::findById($match->player2_id);

$isPlayer1 = $match->player1_id === $user->id;
$myDeck = $isPlayer1 ? $deck1 : $deck2;
$opponentDeck = $isPlayer1 ? $deck2 : $deck1;
$opponent = $isPlayer1 ? $player2 : $player1;

// Handle match operations
$error = null;
$success = null;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    if ($_POST["action"] === "end_match" && isset($_POST["winner_id"])) {
        try {
            $service = new \TCG\Platform\Services\MatchmakingService();
            $service->endMatch($match->id, (int) $_POST["winner_id"]);
            $success = "Match ended successfully!";
            $match = \TCG\Platform\Models\Match::findById($match->id);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($_POST["action"] === "abandon_match") {
        try {
            $service = new \TCG\Platform\Services\MatchmakingService();
            $service->abandonMatch($match->id, $user->id);
            $success = "Match abandoned successfully!";
            $match = \TCG\Platform\Models\Match::findById($match->id);
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
    <title>Match #<?php echo $match->id; ?> - TCG Platform</title>
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
                <h3>Match #<?php echo $match->id; ?></h3>
                <p>Mode: <?php echo htmlspecialchars($match["mode"]); ?></p>
                <p>Status: <?php echo htmlspecialchars($match["status"]); ?></p>
                <p>Started: <?php echo date("Y-m-d H:i:s", strtotime($match["created_at"])); ?></p>
            </div>

            <div class="match-players">
                <div class="card">
                    <h3>You</h3>
                    <p><?php echo htmlspecialchars($user->username); ?></p>
                    <p>ELO: <?php echo $user->elo_rating; ?></p>
                    <p>Deck: <?php echo htmlspecialchars($myDeck->name); ?> (<?php echo $myDeck->getCardCount(); ?> cards)</p>
                </div>

                <div class="card">
                    <h3>Opponent</h3>
                    <p><?php echo htmlspecialchars($opponent->username); ?></p>
                    <p>ELO: <?php echo $opponent->elo_rating; ?></p>
                    <p>Deck: <?php echo htmlspecialchars($opponentDeck->name); ?> (<?php echo $opponentDeck->getCardCount(); ?> cards)</p>
                </div>
            </div>

            <?php if ($match["mode"] === "ranked"): ?>
            <div class="card">
                <h3>Ranked Mode Rules</h3>
                <p>⚠️ The loser must transfer one card from their deck to the winner!</p>
                <p>Make sure to play strategically!</p>
            </div>
            <?php endif; ?>

            <?php if ($match["status"] === "active"): ?>
            <div class="card">
                <h3>Game Actions</h3>
                <div class="btn-group">
                    <form method="POST">
                        <input type="hidden" name="action" value="end_match">
                        <input type="hidden" name="winner_id" value="<?php echo $user->id; ?>">
                        <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you won this match?');">I Won</button>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="action" value="end_match">
                        <input type="hidden" name="winner_id" value="<?php echo $opponent->id; ?>">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you lost this match?');">I Lost</button>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="action" value="abandon_match">
                        <button type="submit" class="btn btn-secondary" onclick="return confirm('Are you sure you want to abandon this match? You will lose!');">Abandon Match</button>
                    </form>
                </div>
            </div>
            <?php elseif ($match["status"] === "finished"): ?>
            <div class="card">
                <h3>Match Result</h3>
                <?php if ($match["winner_id"] == $user->id): ?>
                    <p class="text-success">🎉 You won!</p>
                    <?php if ($match["mode"] === "ranked"): ?>
                        <p>You gained ELO and received a card from your opponent!</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-danger">😢 You lost!</p>
                    <?php if ($match["mode"] === "ranked"): ?>
                        <p>You lost ELO and transferred a card to your opponent.</p>
                    <?php endif; ?>
                <?php endif; ?>
                <p>Ended: <?php echo date("Y-m-d H:i:s", strtotime($match["ended_at"])); ?></p>
                <a href="/matches.php" class="btn btn-primary">Back to Matches</a>
            </div>
            <?php else: ?>
            <div class="card">
                <h3>Waiting for opponent...</h3>
                <p>Match status: <?php echo htmlspecialchars($match["status"]); ?></p>
                <p>Please wait for the match to start.</p>
                <a href="/matches.php" class="btn btn-secondary">Back to Matches</a>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <style>
        .match-players {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .text-success {
            color: #28a745;
            font-size: 18px;
            font-weight: bold;
        }

        .text-danger {
            color: #dc3545;
            font-size: 18px;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .match-players {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>
