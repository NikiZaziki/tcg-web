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
$selectedGameId = isset($_GET["tcg_id"]) ? (int) $_GET["tcg_id"] : ($games[0]["id"] ?? null);
$selectedRarity = $_GET["rarity"] ?? null;
$selectedType = $_GET["type"] ?? null;
$searchQuery = $_GET["search"] ?? null;

$inventory = \TCG\Platform\Models\UserInventory::getUserInventory($user->id, $selectedGameId);

// Filter inventory
if ($selectedRarity || $selectedType || $searchQuery) {
    $inventory = array_filter($inventory, function($card) use ($selectedRarity, $selectedType, $searchQuery) {
        if ($selectedRarity && $card["rarity_name"] !== $selectedRarity) {
            return false;
        }
        if ($selectedType && $card["card_type"] !== $selectedType) {
            return false;
        }
        if ($searchQuery && stripos($card["card_name"], $searchQuery) === false) {
            return false;
        }
        return true;
    });
}

$totalCards = array_sum(array_column($inventory, "quantity"));
$uniqueCards = count($inventory);

// Get all rarities and types for filters
$rarities = ["Common", "Uncommon", "Rare", "Ultra Rare"];
$types = ["Creature", "Spell", "Trap", "Item", "Field"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collection - TCG Platform</title>
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
                <li><a href="/collection.php" class="nav-link active">Collection</a></li>
                <li><a href="/decks.php" class="nav-link">Decks</a></li>
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
            <div class="card">
                <h3>Collection Stats</h3>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="value"><?php echo $totalCards; ?></div>
                        <div class="label">Total Cards</div>
                    </div>
                    <div class="stat-card">
                        <div class="value"><?php echo $uniqueCards; ?></div>
                        <div class="label">Unique Cards</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3>Filters</h3>
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <label for="tcg_id">Game</label>
                        <select id="tcg_id" name="tcg_id">
                            <?php foreach ($games as $game): ?>
                                <option value="<?php echo $game["id"]; ?>" <?php echo $selectedGameId == $game["id"] ? "selected" : ""; ?>>
                                    <?php echo htmlspecialchars($game["name"]); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="rarity">Rarity</label>
                        <select id="rarity" name="rarity">
                            <option value="">All Rarities</option>
                            <?php foreach ($rarities as $rarity): ?>
                                <option value="<?php echo $rarity; ?>" <?php echo $selectedRarity === $rarity ? "selected" : ""; ?>>
                                    <?php echo $rarity; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="type">Type</label>
                        <select id="type" name="type">
                            <option value="">All Types</option>
                            <?php foreach ($types as $type): ?>
                                <option value="<?php echo $type; ?>" <?php echo $selectedType === $type ? "selected" : ""; ?>>
                                    <?php echo $type; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search cards...">
                    </div>
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="/collection.php" class="btn btn-secondary">Clear</a>
                </form>
            </div>

            <div class="card">
                <h3>Your Cards (<?php echo count($inventory); ?>)</h3>
                <?php if (empty($inventory)): ?>
                    <p>No cards found matching your filters.</p>
                    <a href="/shop.php" class="btn btn-primary">Visit Shop</a>
                <?php else: ?>
                    <div class="grid">
                        <?php foreach ($inventory as $card): 
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
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
