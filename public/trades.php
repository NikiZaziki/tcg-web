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

$trades = \TCG\Platform\Models\Trade::findByUser($user->id);

// Handle trade operations
$error = null;
$success = null;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    if ($_POST["action"] === "create_trade" && isset($_POST["receiver_id"])) {
        try {
            \TCG\Platform\Models\Trade::create($user->id, (int) $_POST["receiver_id"]);
            $success = "Trade created successfully!";
            $trades = \TCG\Platform\Models\Trade::findByUser($user->id);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($_POST["action"] === "accept_trade" && isset($_POST["trade_id"])) {
        try {
            $trade = \TCG\Platform\Models\Trade::findById((int) $_POST["trade_id"]);
            if ($trade && $trade->receiver_id === $user->id) {
                $trade->execute();
                $success = "Trade accepted successfully!";
                $trades = \TCG\Platform\Models\Trade::findByUser($user->id);
            } else {
                $error = "You can only accept trades sent to you.";
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($_POST["action"] === "reject_trade" && isset($_POST["trade_id"])) {
        try {
            $trade = \TCG\Platform\Models\Trade::findById((int) $_POST["trade_id"]);
            if ($trade && $trade->receiver_id === $user->id) {
                \TCG\Platform\Models\Trade::updateStatus((int) $_POST["trade_id"], "rejected");
                $success = "Trade rejected successfully!";
                $trades = \TCG\Platform\Models\Trade::findByUser($user->id);
            } else {
                $error = "You can only reject trades sent to you.";
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($_POST["action"] === "cancel_trade" && isset($_POST["trade_id"])) {
        try {
            $trade = \TCG\Platform\Models\Trade::findById((int) $_POST["trade_id"]);
            if ($trade && $trade->sender_id === $user->id) {
                \TCG\Platform\Models\Trade::updateStatus((int) $_POST["trade_id"], "cancelled");
                $success = "Trade cancelled successfully!";
                $trades = \TCG\Platform\Models\Trade::findByUser($user->id);
            } else {
                $error = "You can only cancel trades you created.";
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get all users for trade creation
$allUsers = \TCG\Platform\Models\User::findAll();
$otherUsers = array_filter($allUsers, function($u) use ($user) {
    return $u["id"] != $user->id;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trades - TCG Platform</title>
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
                <li><a href="/leaderboard.php" class="nav-link">Leaderboard</a></li>
                <li><a href="/trades.php" class="nav-link active">Trades</a></li>
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
                <h3>Create New Trade</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="create_trade">
                    <div class="form-group">
                        <label for="receiver_id">Trade With</label>
                        <select id="receiver_id" name="receiver_id" required>
                            <option value="">-- Select Player --</option>
                            <?php foreach ($otherUsers as $otherUser): ?>
                                <option value="<?php echo $otherUser["id"]; ?>">
                                    <?php echo htmlspecialchars($otherUser["username"]); ?> (ELO: <?php echo $otherUser["elo_rating"]; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Create Trade</button>
                </form>
            </div>

            <div class="card">
                <h3>Your Trades (<?php echo count($trades); ?>)</h3>
                <?php if (empty($trades)): ?>
                    <p>No trades yet. Create one above!</p>
                <?php else: ?>
                    <div class="grid">
                        <?php foreach ($trades as $trade): 
                            $isSender = $trade->sender_id === $user->id;
                            $otherUserId = $isSender ? $trade->receiver_id : $trade->sender_id;
                            $otherUser = \TCG\Platform\Models\User::findById($otherUserId);
                            $myCards = $trade->getCards($user->id);
                            $otherCards = $trade->getCards($otherUserId);
                        ?>
                        <div class="card">
                            <h3>Trade #<?php echo $trade["id"]; ?></h3>
                            <p>With: <?php echo htmlspecialchars($otherUser->username); ?></p>
                            <p>Status: <?php echo htmlspecialchars($trade["status"]); ?></p>
                            <p>Created: <?php echo date("Y-m-d H:i", strtotime($trade["created_at"])); ?></p>
                            
                            <h4>Your Cards:</h4>
                            <?php if (empty($myCards)): ?>
                                <p>No cards added yet</p>
                            <?php else: ?>
                                <ul>
                                    <?php foreach ($myCards as $card): ?>
                                        <li><?php echo htmlspecialchars($card["card_name"]); ?> x<?php echo $card["quantity"]; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            
                            <h4>Their Cards:</h4>
                            <?php if (empty($otherCards)): ?>
                                <p>No cards added yet</p>
                            <?php else: ?>
                                <ul>
                                    <?php foreach ($otherCards as $card): ?>
                                        <li><?php echo htmlspecialchars($card["card_name"]); ?> x<?php echo $card["quantity"]; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            
                            <div class="btn-group">
                                <?php if ($isSender): ?>
                                    <?php if ($trade["status"] === "pending"): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="cancel_trade">
                                            <input type="hidden" name="trade_id" value="<?php echo $trade["id"]; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Cancel</button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if ($trade["status"] === "pending"): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="accept_trade">
                                            <input type="hidden" name="trade_id" value="<?php echo $trade["id"]; ?>">
                                            <button type="submit" class="btn btn-success btn-sm">Accept</button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="reject_trade">
                                            <input type="hidden" name="trade_id" value="<?php echo $trade["id"]; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
