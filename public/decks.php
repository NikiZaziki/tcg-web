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

// Handle deck operations
$error = null;
$success = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["action"])) {
        if ($_POST["action"] === "create_deck" && isset($_POST["name"]) && isset($_POST["tcg_id"])) {
            try {
                \TCG\Platform\Models\Deck::create($user->id, (int) $_POST["tcg_id"], $_POST["name"]);
                $success = "Deck created successfully!";
                $decks = \TCG\Platform\Models\Deck::findByUser($user->id);
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
        
        if ($_POST["action"] === "delete_deck" && isset($_POST["deck_id"])) {
            try {
                \TCG\Platform\Models\Deck::delete((int) $_POST["deck_id"]);
                $success = "Deck deleted successfully!";
                $decks = \TCG\Platform\Models\Deck::findByUser($user->id);
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
        
        if ($_POST["action"] === "update_deck" && isset($_POST["deck_id"]) && isset($_POST["name"])) {
            try {
                \TCG\Platform\Models\Deck::update((int) $_POST["deck_id"], $_POST["name"]);
                $success = "Deck updated successfully!";
                $decks = \TCG\Platform\Models\Deck::findByUser($user->id);
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

// Get deck details for editing
$editingDeck = null;
if (isset($_GET["edit"])) {
    $editingDeck = \TCG\Platform\Models\Deck::findById((int) $_GET["edit"]);
    if ($editingDeck && $editingDeck->user_id !== $user->id) {
        $editingDeck = null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Decks - TCG Platform</title>
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
                <li><a href="/decks.php" class="nav-link active">Decks</a></li>
                <li><a href="/shop.php" class="nav-link">Shop</a></li>
                <li><a href="/matches.php" class="nav-link">Matches</a></li>
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
                <h3>Create New Deck</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="create_deck">
                    <div class="form-group">
                        <label for="name">Deck Name</label>
                        <input type="text" id="name" name="name" required placeholder="My Awesome Deck">
                    </div>
                    <div class="form-group">
                        <label for="tcg_id">Game</label>
                        <select id="tcg_id" name="tcg_id" required>
                            <?php foreach ($games as $game): ?>
                                <option value="<?php echo $game["id"]; ?>">
                                    <?php echo htmlspecialchars($game["name"]); ?> (<?php echo $game["deck_size"]; ?> cards, max <?php echo $game["max_card_copies"]; ?> copies)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Create Deck</button>
                </form>
            </div>

            <div class="card">
                <h3>Your Decks (<?php echo count($decks); ?>)</h3>
                <?php if (empty($decks)): ?>
                    <p>You don't have any decks yet. Create one above!</p>
                <?php else: ?>
                    <div class="grid">
                        <?php foreach ($decks as $deck): 
                            $deckCards = \TCG\Platform\Models\Deck::findById($deck["id"])->getCards();
                            $cardCount = array_sum(array_column($deckCards, "quantity"));
                            $game = \TCG\Platform\Models\TcgGame::findById($deck["tcg_id"]);
                        ?>
                        <div class="card">
                            <h3><?php echo htmlspecialchars($deck["name"]); ?></h3>
                            <p>Game: <?php echo htmlspecialchars($game->name); ?></p>
                            <p>Cards: <?php echo $cardCount; ?> / <?php echo $game->deck_size; ?></p>
                            <p>Last Used: <?php echo $deck["last_used"] ? date("Y-m-d H:i", strtotime($deck["last_used"])) : "Never"; ?></p>
                            <div class="btn-group">
                                <a href="/deck-builder.php?id=<?php echo $deck["id"]; ?>" class="btn btn-primary btn-sm">Edit</a>
                                <a href="/decks.php?edit=<?php echo $deck["id"]; ?>" class="btn btn-secondary btn-sm">Rename</a>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_deck">
                                    <input type="hidden" name="deck_id" value="<?php echo $deck["id"]; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this deck?');">Delete</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($editingDeck): ?>
            <div class="card">
                <h3>Rename Deck: <?php echo htmlspecialchars($editingDeck->name); ?></h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_deck">
                    <input type="hidden" name="deck_id" value="<?php echo $editingDeck->id; ?>">
                    <div class="form-group">
                        <label for="new_name">New Name</label>
                        <input type="text" id="new_name" name="name" value="<?php echo htmlspecialchars($editingDeck->name); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Name</button>
                    <a href="/decks.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
