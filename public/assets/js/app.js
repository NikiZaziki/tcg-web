document.addEventListener("DOMContentLoaded", function() {
    const token = localStorage.getItem("tcg_token");

    if (!token) {
        window.location.href = "/login.php";
        return;
    }

    api.setToken(token);

    loadUserInfo();
    setupNavigation();
    loadPage("dashboard");
});

async function loadUserInfo() {
    try {
        const user = await api.getMe();
        document.getElementById("username").textContent = user.username;
    } catch (error) {
        console.error("Failed to load user info:", error);
        logout();
    }
}

function setupNavigation() {
    const navLinks = document.querySelectorAll(".nav-link");
    const logoutBtn = document.getElementById("logoutBtn");

    navLinks.forEach(link => {
        link.addEventListener("click", function(e) {
            e.preventDefault();
            const page = this.dataset.page;
            loadPage(page);
        });
    });

    logoutBtn.addEventListener("click", logout);
}

async function loadPage(page) {
    const content = document.getElementById("page-content");

    navLinks.forEach(link => {
        link.classList.remove("active");
        if (link.dataset.page === page) {
            link.classList.add("active");
        }
    });

    content.innerHTML = "<div class=\"loading\">Loading...</div>";

    try {
        switch (page) {
            case "dashboard":
                await loadDashboard();
                break;
            case "collection":
                await loadCollection();
                break;
            case "decks":
                await loadDecks();
                break;
            case "shop":
                await loadShop();
                break;
            case "matches":
                await loadMatches();
                break;
            case "leaderboard":
                await loadLeaderboard();
                break;
            case "trades":
                await loadTrades();
                break;
            default:
                content.innerHTML = "<p>Page not found</p>";
        }
    } catch (error) {
        content.innerHTML = "<div class=\"alert alert-danger\">Error: " + error.message + "</div>";
    }
}

async function loadDashboard() {
    const content = document.getElementById("page-content");

    try {
        const [inventory, dailyStatus, matches] = await Promise.all([
            api.getInventory(),
            api.getDailyPackStatus(),
            api.getMatches("active"),
        ]);

        const stats = inventory.stats;

        content.innerHTML = `
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="value">${stats.total_cards}</div>
                    <div class="label">Total Cards</div>
                </div>
                <div class="stat-card">
                    <div class="value">${stats.unique_cards}</div>
                    <div class="label">Unique Cards</div>
                </div>
                <div class="stat-card">
                    <div class="value">${matches.length}</div>
                    <div class="label">Active Matches</div>
                </div>
            </div>

            <div class="card">
                <h3>Daily Pack</h3>
                ${dailyStatus.can_claim 
                    ? "<button class=\"btn btn-primary\" onclick=\"claimDailyPack()\">Claim Free Pack</button>"
                    : "<p>Next pack available in: " + dailyStatus.wait_time_formatted + "</p>"
                }
            </div>

            <div class="card">
                <h3>Recent Cards</h3>
                <div class="grid">
                    ${inventory.cards.slice(0, 8).map(card => createCardHTML(card)).join("")}
                </div>
            </div>
        `;
    } catch (error) {
        throw error;
    }
}

async function loadCollection() {
    const content = document.getElementById("page-content");

    try {
        const games = await api.getGames();
        const tcgId = games[0]?.id;

        let inventory = { stats: { total_cards: 0, unique_cards: 0 }, cards: [] };
        if (tcgId) {
            inventory = await api.getInventory(tcgId);
        }

        content.innerHTML = `
            <div class="card">
                <h3>Collection</h3>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="value">${inventory.stats.total_cards}</div>
                        <div class="label">Total Cards</div>
                    </div>
                    <div class="stat-card">
                        <div class="value">${inventory.stats.unique_cards}</div>
                        <div class="label">Unique Cards</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3>Your Cards</h3>
                <div class="grid">
                    ${inventory.cards.map(card => createCardHTML(card)).join("")}
                </div>
            </div>
        `;
    } catch (error) {
        throw error;
    }
}

async function loadDecks() {
    const content = document.getElementById("page-content");

    try {
        const decks = await api.getDecks();

        content.innerHTML = `
            <div class="card">
                <h3>Your Decks</h3>
                <button class="btn btn-primary" onclick="showCreateDeckModal()">Create New Deck</button>
            </div>

            <div class="grid">
                ${decks.map(deck => createDeckHTML(deck)).join("")}
            </div>
        `;
    } catch (error) {
        throw error;
    }
}

async function loadShop() {
    const content = document.getElementById("page-content");

    try {
        const packs = await api.getPacks();

        content.innerHTML = `
            <div class="card">
                <h3>Booster Packs</h3>
                <div class="grid">
                    ${packs.map(pack => createPackHTML(pack)).join("")}
                </div>
            </div>
        `;
    } catch (error) {
        throw error;
    }
}

async function loadMatches() {
    const content = document.getElementById("page-content");

    try {
        const [activeMatches, history] = await Promise.all([
            api.getMatches("active"),
            api.getMatchHistory(10),
        ]);

        content.innerHTML = `
            <div class="card">
                <h3>Active Matches</h3>
                ${activeMatches.length === 0 
                    ? "<p>No active matches</p>"
                    : activeMatches.map(match => createMatchHTML(match)).join("")
                }
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
                        </tr>
                    </thead>
                    <tbody>
                        ${history.map(match => createMatchHistoryRow(match)).join("")}
                    </tbody>
                </table>
            </div>
        `;
    } catch (error) {
        throw error;
    }
}

async function loadLeaderboard() {
    const content = document.getElementById("page-content");

    try {
        const leaderboard = await api.getLeaderboard(50);

        content.innerHTML = `
            <div class="card">
                <h3>Leaderboard</h3>
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
                        ${leaderboard.map(entry => createLeaderboardRow(entry)).join("")}
                    </tbody>
                </table>
            </div>
        `;
    } catch (error) {
        throw error;
    }
}

async function loadTrades() {
    const content = document.getElementById("page-content");

    try {
        const trades = await api.getTrades();

        content.innerHTML = `
            <div class="card">
                <h3>Your Trades</h3>
                ${trades.length === 0 
                    ? "<p>No trades yet</p>"
                    : trades.map(trade => createTradeHTML(trade)).join("")
                }
            </div>
        `;
    } catch (error) {
        throw error;
    }
}

function createCardHTML(card) {
    const rarityClass = getRarityClass(card.rarity_name);
    return `
        <div class="card-item">
            <img src="${card.image_url || "/assets/images/placeholder.png"}" alt="${card.card_name}">
            <div class="card-item-info">
                <div class="card-item-name">${card.card_name}</div>
                <div class="card-item-type">${card.card_type}</div>
                <div class="card-item-stats">
                    <span>ATK: ${card.attack}</span>
                    <span>DEF: ${card.defense}</span>
                </div>
                <span class="card-item-rarity ${rarityClass}">${card.rarity_name}</span>
            </div>
        </div>
    `;
}

function createDeckHTML(deck) {
    return `
        <div class="card">
            <h3>${deck.name}</h3>
            <p>Cards: ${deck.card_count}</p>
            <div class="btn-group">
                <a href="/deck-builder.php?id=${deck.id}" class="btn btn-primary btn-sm">Edit</a>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete_deck">
                    <input type="hidden" name="deck_id" value="${deck.id}">
                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this deck?');">Delete</button>
                </form>
            </div>
        </div>
    `;
}

function createPackHTML(pack) {
    return `
        <div class="card">
            <h3>${pack.name}</h3>
            <p>Cards: ${pack.cards_per_pack}</p>
            <p>Price: $${pack.price}</p>
            <form method="POST">
                <input type="hidden" name="action" value="open_pack">
                <input type="hidden" name="pack_id" value="${pack.id}">
                <button type="submit" class="btn btn-primary">Open Pack</button>
            </form>
        </div>
    `;
}

function createMatchHTML(match) {
    return `
        <div class="card">
            <h3>Match #${match.id}</h3>
            <p>Mode: ${match.mode}</p>
            <p>Status: ${match.status}</p>
            <a href="/match.php?id=${match.id}" class="btn btn-primary">Join Match</a>
        </div>
    `;
}

function createMatchHistoryRow(match) {
    const user = JSON.parse(localStorage.getItem("tcg_user") || "{}");
    const isPlayer1 = match.player1_id === user.id;
    const opponent = isPlayer1 ? match.player2_name : match.player1_name;
    const won = match.winner_id === user.id;
    const resultClass = won ? "text-success" : "text-danger";
    const resultText = won ? "Won" : "Lost";

    return `
        <tr>
            <td>${new Date(match.created_at).toLocaleDateString()}</td>
            <td>${match.mode}</td>
            <td class="${resultClass}">${resultText}</td>
            <td>${opponent}</td>
        </tr>
    `;
}

function createLeaderboardRow(entry) {
    return `
        <tr>
            <td>${entry.rank}</td>
            <td>${entry.username}</td>
            <td>${entry.elo_rating}</td>
            <td>${entry.rank_tier}</td>
        </tr>
    `;
}

function createTradeHTML(trade) {
    const isSender = trade.sender_id === JSON.parse(localStorage.getItem("tcg_user") || "{}").id;
    return `
        <div class="card">
            <h3>Trade #${trade.id}</h3>
            <p>Status: ${trade.status}</p>
            <p>With: ${isSender ? trade.receiver_name : trade.sender_name}</p>
            <div class="btn-group">
                ${isSender ? 
                    `<form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="cancel_trade">
                        <input type="hidden" name="trade_id" value="${trade.id}">
                        <button type="submit" class="btn btn-danger btn-sm">Cancel</button>
                    </form>` 
                    : 
                    `<form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="accept_trade">
                        <input type="hidden" name="trade_id" value="${trade.id}">
                        <button type="submit" class="btn btn-success btn-sm">Accept</button>
                    </form>`
                }
            </div>
        </div>
    `;
}

function getRarityClass(rarityName) {
    const rarityMap = {
        "Common": "rarity-common",
        "Uncommon": "rarity-uncommon",
        "Rare": "rarity-rare",
        "Ultra Rare": "rarity-ultra-rare",
    };
    return rarityMap[rarityName] || "";
}

async function claimDailyPack() {
    try {
        const result = await api.claimDailyPack();
        alert("Daily pack claimed! You got:");
        result.cards.forEach(card => {
            alert("- " + card.name + " (" + card.rarity.name + ")");
        });
        loadPage("dashboard");
    } catch (error) {
        alert("Failed to claim daily pack: " + error.message);
    }
}

async function openPack(packId) {
    try {
        const result = await api.openPack(packId);
        alert("Pack opened! You got:");
        result.cards.forEach(card => {
            alert("- " + card.name + " (" + card.rarity.name + ")");
        });
        loadPage("dashboard");
    } catch (error) {
        alert("Failed to open pack: " + error.message);
    }
}

function logout() {
    localStorage.removeItem("tcg_token");
    localStorage.removeItem("tcg_user");
    window.location.href = "/login.php";
}

function showCreateDeckModal() {
    alert("Deck creation modal would open here");
}

function editDeck(deckId) {
    alert("Edit deck " + deckId);
}

function deleteDeck(deckId) {
    if (confirm("Are you sure you want to delete this deck?")) {
        api.deleteDeck(deckId).then(() => {
            loadPage("decks");
        }).catch(error => {
            alert("Failed to delete deck: " + error.message);
        });
    }
}

function joinMatch(matchId) {
    alert("Join match " + matchId);
}
