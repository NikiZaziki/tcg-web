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

$packs = \TCG\Platform\Models\BoosterPack::findAll();
$games = \TCG\Platform\Models\TcgGame::findAll();

// Handle pack opening
$error = null;
$success = null;
$openedCards = null;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    if ($_POST["action"] === "open_pack" && isset($_POST["pack_id"])) {
        try {
            $packService = new \TCG\Platform\Services\PackOpeningService();
            $result = $packService->openPack($user->id, (int) $_POST["pack_id"]);
            $success = "Pack opened successfully!";
            $openedCards = $result["cards"];
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
    <title>Shop - TCG Platform</title>
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
                <li><a href="/shop.php" class="nav-link active">Shop</a></li>
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

            <?php if ($openedCards): ?>
                <div class="card">
                    <h3>Cards You Got!</h3>
                    <div class="grid">
                        <?php foreach ($openedCards as $card): 
                            $rarityClass = "";
                            switch ($card["rarity"]["name"]) {
                                case "Common": $rarityClass = "rarity-common"; break;
                                case "Uncommon": $rarityClass = "rarity-uncommon"; break;
                                case "Rare": $rarityClass = "rarity-rare"; break;
                                case "Ultra Rare": $rarityClass = "rarity-ultra-rare"; break;
                            }
                        ?>
                        <div class="card-item">
                            <img src="<?php echo htmlspecialchars($card["image_url"] ?: "/assets/images/placeholder.png"); ?>" alt="<?php echo htmlspecialchars($card["name"]); ?>">
                            <div class="card-item-info">
                                <div class="card-item-name"><?php echo htmlspecialchars($card["name"]); ?></div>
                                <div class="card-item-type"><?php echo htmlspecialchars($card["type"]); ?></div>
                                <div class="card-item-stats">
                                    <span>ATK: <?php echo $card["attack"]; ?></span>
                                    <span>DEF: <?php echo $card["defense"]; ?></span>
                                </div>
                                <span class="card-item-rarity <?php echo $rarityClass; ?>"><?php echo htmlspecialchars($card["rarity"]["name"]); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="/collection.php" class="btn btn-primary">View Collection</a>
                </div>
            <?php endif; ?>

            <div class="card">
                <h3>Booster Packs</h3>
                <div class="grid">
                    <?php foreach ($packs as $pack): 
                        $game = \TCG\Platform\Models\TcgGame::findById($pack["tcg_id"]);
                        $dropTable = $pack->getDropTable();
                    ?>
                    <div class="card">
                        <h3><?php echo htmlspecialchars($pack["name"]); ?></h3>
                        <p>Game: <?php echo htmlspecialchars($game->name); ?></p>
                        <p>Cards: <?php echo $pack["cards_per_pack"]; ?></p>
                        <p>Price: $<?php echo number_format($pack["price"], 2); ?></p>
                        <p>Type: <?php echo htmlspecialchars($pack["pack_type"]); ?></p>
                        
                        <h4>Drop Rates:</h4>
                        <ul>
                            <?php foreach ($dropTable as $drop): ?>
                                <li><?php echo htmlspecialchars($drop["rarity_name"]); ?>: <?php echo ($drop["probability"] * 100); ?>%</li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="open_pack">
                            <input type="hidden" name="pack_id" value="<?php echo $pack["id"]; ?>">
                            <button type="submit" class="btn btn-primary">Open Pack</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card">
                <h3>Available Games</h3>
                <div class="stats-grid">
                    <?php foreach ($games as $game): ?>
                    <div class="stat-card">
                        <div class="value"><?php echo htmlspecialchars($game["name"]); ?></div>
                        <div class="label">Deck: <?php echo $game["deck_size"]; ?> cards</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
