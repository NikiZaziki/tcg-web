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

$deckId = isset($_GET["id"]) ? (int) $_GET["id"] : null;

if (!$deckId) {
    header("Location: /decks.php");
    exit;
}

$deck = \TCG\Platform\Models\Deck::findById($deckId);

if (!$deck || $deck->user_id !== $user->id) {
    header("Location: /decks.php");
    exit;
}

$game = \TCG\Platform\Models\TcgGame::findById($deck->tcg_id);
$deckCards = $deck->getCards();
$inventory = \TCG\Platform\Models\UserInventory::getUserInventory($user->id, $deck->tcg_id);

// Handle deck operations
$error = null;
$success = null;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    if ($_POST["action"] === "add_card" && isset($_POST["card_id"]) && isset($_POST["quantity"])) {
        try {
            $cardId = (int) $_POST["card_id"];
            $quantity = (int) $_POST["quantity"];
            
            // Check if user has enough cards
            $userQuantity = \TCG\Platform\Models\UserInventory::getCardQuantity($user->id, $cardId);
            if ($userQuantity < $quantity) {
                $error = "You don't have enough of this card.";
            } else {
                $deck->addCard($cardId, $quantity);
                $success = "Card added to deck!";
                $deckCards = $deck->getCards();
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($_POST["action"] === "remove_card" && isset($_POST["card_id"]) && isset($_POST["quantity"])) {
        try {
            $deck->removeCard((int) $_POST["card_id"], (int) $_POST["quantity"]);
            $success = "Card removed from deck!";
            $deckCards = $deck->getCards();
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($_POST["action"] === "clear_deck") {
        try {
            $deck->clearCards();
            $success = "Deck cleared!";
            $deckCards = [];
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if ($_POST["action"] === "save_deck") {
        try {
            $errors = $deck->validate($game);
            if (empty($errors)) {
                \TCG\Platform\Models\Deck::updateLastUsed($deck->id);
                $success = "Deck saved successfully!";
            } else {
                $error = "Deck validation failed: " . implode(", ", $errors);
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Validate deck
$validationErrors = $deck->validate($game);
$isValid = empty($validationErrors);
$cardCount = array_sum(array_column($deckCards, "quantity"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deck Builder - TCG Platform</title>
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
                <h3>Deck: <?php echo htmlspecialchars($deck->name); ?></h3>
                <p>Game: <?php echo htmlspecialchars($game->name); ?></p>
                <p>Required: <?php echo $game->deck_size; ?> cards (max <?php echo $game->max_card_copies; ?> copies per card)</p>
                <p>Current: <?php echo $cardCount; ?> cards</p>
                
                <div class="validation-status <?php echo $isValid ? "valid" : "invalid"; ?>">
                    <?php if ($isValid): ?>
                        <span class="text-success">✓ Deck is valid!</span>
                    <?php else: ?>
                        <span class="text-danger">✗ Deck is invalid:</span>
                        <ul>
                            <?php foreach ($validationErrors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                
                <div class="btn-group">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="save_deck">
                        <button type="submit" class="btn btn-success" <?php echo !$isValid ? "disabled" : ""; ?>>Save Deck</button>
                    </form>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="clear_deck">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to clear this deck?');">Clear Deck</button>
                    </form>
                    <a href="/decks.php" class="btn btn-secondary">Back to Decks</a>
                </div>
            </div>

            <div class="deck-builder">
                <div class="deck-builder-left">
                    <div class="card">
                        <h3>Your Collection</h3>
                        <div class="grid">
                            <?php foreach ($inventory as $card): 
                                $rarityClass = "";
                                switch ($card["rarity_name"]) {
                                    case "Common": $rarityClass = "rarity-common"; break;
                                    case "Uncommon": $rarityClass = "rarity-uncommon"; break;
                                    case "Rare": $rarityClass = "rarity-rare"; break;
                                    case "Ultra Rare": $rarityClass = "rarity-ultra-rare"; break;
                                }
                                $inDeck = false;
                                foreach ($deckCards as $deckCard) {
                                    if ($deckCard["card_id"] == $card["card_id"]) {
                                        $inDeck = true;
                                        break;
                                    }
                                }
                            ?>
                            <div class="card-item <?php echo $inDeck ? "in-deck" : ""; ?>">
                                <img src="<?php echo htmlspecialchars($card["image_url"] ?: "/assets/images/placeholder.png"); ?>" alt="<?php echo htmlspecialchars($card["card_name"]); ?>">
                                <div class="card-item-info">
                                    <div class="card-item-name"><?php echo htmlspecialchars($card["card_name"]); ?></div>
                                    <div class="card-item-type"><?php echo htmlspecialchars($card["card_type"]); ?></div>
                                    <div class="card-item-stats">
                                        <span>ATK: <?php echo $card["attack"]; ?></span>
                                        <span>DEF: <?php echo $card["defense"]; ?></span>
                                    </div>
                                    <span class="card-item-rarity <?php echo $rarityClass; ?>"><?php echo htmlspecialchars($card["rarity_name"]); ?></span>
                                    <div class="card-item-quantity">Owned: <?php echo $card["quantity"]; ?></div>
                                </div>
                                <form method="POST" class="add-to-deck-form">
                                    <input type="hidden" name="action" value="add_card">
                                    <input type="hidden" name="card_id" value="<?php echo $card["card_id"]; ?>">
                                    <input type="number" name="quantity" value="1" min="1" max="<?php echo $card["quantity"]; ?>" class="quantity-input">
                                    <button type="submit" class="btn btn-primary btn-sm">Add</button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="deck-builder-right">
                    <div class="card">
                        <h3>Current Deck (<?php echo $cardCount; ?> / <?php echo $game->deck_size; ?>)</h3>
                        <?php if (empty($deckCards)): ?>
                            <p>No cards in deck yet. Add cards from your collection.</p>
                        <?php else: ?>
                            <div class="grid">
                                <?php foreach ($deckCards as $card): 
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
                                    <form method="POST" class="remove-from-deck-form">
                                        <input type="hidden" name="action" value="remove_card">
                                        <input type="hidden" name="card_id" value="<?php echo $card["card_id"]; ?>">
                                        <input type="number" name="quantity" value="1" min="1" max="<?php echo $card["quantity"]; ?>" class="quantity-input">
                                        <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                    </form>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <style>
        .deck-builder {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .deck-builder-left,
        .deck-builder-right {
            min-height: 500px;
        }

        .add-to-deck-form,
        .remove-from-deck-form {
            margin-top: 10px;
            display: flex;
            gap: 5px;
            align-items: center;
        }

        .quantity-input {
            width: 60px;
            padding: 5px;
        }

        .card-item.in-deck {
            opacity: 0.5;
        }

        .validation-status {
            margin: 15px 0;
            padding: 10px;
            border-radius: 4px;
        }

        .validation-status.valid {
            background-color: #d4edda;
            color: #155724;
        }

        .validation-status.invalid {
            background-color: #f8d7da;
            color: #721c24;
        }

        .text-success {
            color: #28a745;
        }

        .text-danger {
            color: #dc3545;
        }

        @media (max-width: 768px) {
            .deck-builder {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>
