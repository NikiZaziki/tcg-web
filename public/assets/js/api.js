const API_BASE = "/api";

class TCGAPI {
    constructor() {
        this.token = localStorage.getItem("tcg_token");
    }

    setToken(token) {
        this.token = token;
        localStorage.setItem("tcg_token", token);
    }

    clearToken() {
        this.token = null;
        localStorage.removeItem("tcg_token");
    }

    async request(endpoint, options = {}) {
        const url = API_BASE + endpoint;
        const headers = {
            "Content-Type": "application/json",
            ...options.headers,
        };

        if (this.token) {
            headers["Authorization"] = `Bearer ${this.token}`;
        }

        const response = await fetch(url, {
            ...options,
            headers,
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || "Request failed");
        }

        return data;
    }

    async get(endpoint) {
        return this.request(endpoint, { method: "GET" });
    }

    async post(endpoint, body) {
        return this.request(endpoint, {
            method: "POST",
            body: JSON.stringify(body),
        });
    }

    async put(endpoint, body) {
        return this.request(endpoint, {
            method: "PUT",
            body: JSON.stringify(body),
        });
    }

    async delete(endpoint) {
        return this.request(endpoint, { method: "DELETE" });
    }

    async login(email, password) {
        const result = await this.post("/auth/login", { email, password });
        this.setToken(result.token);
        return result;
    }

    async register(username, email, password) {
        const result = await this.post("/auth/register", { username, email, password });
        this.setToken(result.token);
        return result;
    }

    async getMe() {
        return this.get("/auth/me");
    }

    async getInventory(tcgId = null) {
        const params = new URLSearchParams();
        if (tcgId) params.append("tcg_id", tcgId);
        return this.get(`/inventory/?${params.toString()}`);
    }

    async getDecks(tcgId = null) {
        const params = new URLSearchParams();
        if (tcgId) params.append("tcg_id", tcgId);
        return this.get(`/decks/?${params.toString()}`);
    }

    async createDeck(name, tcgId) {
        return this.post("/decks/", { name, tcg_id: tcgId });
    }

    async updateDeck(deckId, name) {
        return this.put(`/decks/${deckId}`, { name });
    }

    async deleteDeck(deckId) {
        return this.delete(`/decks/${deckId}`);
    }

    async addCardToDeck(deckId, cardId, quantity = 1) {
        return this.post(`/decks/${deckId}/cards`, { card_id: cardId, quantity });
    }

    async removeCardFromDeck(deckId, cardId, quantity = 1) {
        const params = new URLSearchParams();
        params.append("quantity", quantity);
        return this.delete(`/decks/${deckId}/cards/${cardId}?${params.toString()}`);
    }

    async validateDeck(deckId) {
        return this.get(`/decks/${deckId}/validate`);
    }

    async getPacks() {
        return this.get("/packs/");
    }

    async openPack(packId) {
        return this.post(`/packs/${packId}/open`);
    }

    async getPackOpening(openingId) {
        return this.get(`/packs/openings/${openingId}`);
    }

    async getMyPackOpenings(limit = 20, offset = 0) {
        const params = new URLSearchParams();
        params.append("limit", limit);
        params.append("offset", offset);
        return this.get(`/packs/my-openings?${params.toString()}`);
    }

    async findMatch(deckId, mode = "normal") {
        return this.post("/matches/find", { deck_id: deckId, mode });
    }

    async getMatch(matchId) {
        return this.get(`/matches/${matchId}`);
    }

    async getMatches(status = null) {
        const params = new URLSearchParams();
        if (status) params.append("status", status);
        return this.get(`/matches/?${params.toString()}`);
    }

    async getMatchHistory(limit = 20, offset = 0) {
        const params = new URLSearchParams();
        params.append("limit", limit);
        params.append("offset", offset);
        return this.get(`/matches/history?${params.toString()}`);
    }

    async endMatch(matchId, winnerId) {
        return this.post(`/matches/${matchId}/end`, { winner_id: winnerId });
    }

    async abandonMatch(matchId) {
        return this.post(`/matches/${matchId}/abandon`);
    }

    async getDailyPackStatus() {
        return this.get("/users/daily/status");
    }

    async claimDailyPack() {
        return this.post("/users/daily/claim");
    }

    async getGames() {
        return this.get("/users/games");
    }

    async getLeaderboard(limit = 100, offset = 0) {
        const params = new URLSearchParams();
        params.append("limit", limit);
        params.append("offset", offset);
        return this.get(`/users/leaderboard?${params.toString()}`);
    }

    async getCards(tcgId, type = null, rarityId = null) {
        const params = new URLSearchParams();
        params.append("tcg_id", tcgId);
        if (type) params.append("type", type);
        if (rarityId) params.append("rarity_id", rarityId);
        return this.get(`/cards/?${params.toString()}`);
    }

    async searchCards(query, tcgId = null, type = null, rarityId = null) {
        const params = new URLSearchParams();
        params.append("q", query);
        if (tcgId) params.append("tcg_id", tcgId);
        if (type) params.append("type", type);
        if (rarityId) params.append("rarity_id", rarityId);
        return this.get(`/cards/search?${params.toString()}`);
    }

    async getTrades(status = null) {
        const params = new URLSearchParams();
        if (status) params.append("status", status);
        return this.get(`/trades/?${params.toString()}`);
    }

    async getTrade(tradeId) {
        return this.get(`/trades/${tradeId}`);
    }

    async createTrade(receiverId) {
        return this.post("/trades/", { receiver_id: receiverId });
    }

    async acceptTrade(tradeId) {
        return this.post(`/trades/${tradeId}/accept`);
    }

    async rejectTrade(tradeId) {
        return this.post(`/trades/${tradeId}/reject`);
    }

    async cancelTrade(tradeId) {
        return this.post(`/trades/${tradeId}/cancel`);
    }

    async addCardToTrade(tradeId, cardId, quantity = 1) {
        return this.post(`/trades/${tradeId}/cards`, { card_id: cardId, quantity });
    }
}

const api = new TCGAPI();
