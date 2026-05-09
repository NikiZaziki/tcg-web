# TCG Platform - API Documentation

## Base URL

All API endpoints are prefixed with `/api/`.

## Authentication

Most endpoints require authentication via JWT token. Include the token in the `Authorization` header:

```
Authorization: Bearer <your-jwt-token>
```

Or as a cookie: `tcg_token=<your-jwt-token>`

## Response Format

All responses are in JSON format:

```json
{
  "success": true,
  "data": { ... }
}
```

Error responses:

```json
{
  "error": "Error message"
}
```

## Endpoints

### Authentication

#### Register
Create a new user account.

**Endpoint:** `POST /api/auth/register`

**Request Body:**
```json
{
  "username": "player1",
  "email": "player1@example.com",
  "password": "securepassword123"
}
```

**Response:**
```json
{
  "user": {
    "id": 1,
    "username": "player1",
    "email": "player1@example.com",
    "elo_rating": 1000,
    "rank_tier": "Bronze",
    "created_at": "2024-01-01T00:00:00+00:00"
  },
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
}
```

#### Login
Authenticate with existing credentials.

**Endpoint:** `POST /api/auth/login`

**Request Body:**
```json
{
  "email": "player1@example.com",
  "password": "securepassword123"
}
```

**Response:**
```json
{
  "user": {
    "id": 1,
    "username": "player1",
    "email": "player1@example.com",
    "elo_rating": 1000,
    "rank_tier": "Bronze",
    "created_at": "2024-01-01T00:00:00+00:00"
  },
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
}
```

#### Get Current User
Get information about the authenticated user.

**Endpoint:** `GET /api/auth/me`

**Headers:** `Authorization: Bearer <token>`

**Response:**
```json
{
  "id": 1,
  "username": "player1",
  "email": "player1@example.com",
  "elo_rating": 1000,
  "rank_tier": "Bronze",
  "created_at": "2024-01-01T00:00:00+00:00"
}
```

### Cards

#### List Cards
Get all cards for a specific TCG game.

**Endpoint:** `GET /api/cards/?tcg_id=1&type=Creature&rarity_id=2`

**Query Parameters:**
- `tcg_id` (required): TCG game ID
- `type` (optional): Filter by card type
- `rarity_id` (optional): Filter by rarity ID

**Response:**
```json
[
  {
    "id": 1,
    "tcg_id": 1,
    "name": "Fire Dragon",
    "rarity": {
      "id": 4,
      "name": "Ultra Rare",
      "color": "#FFD700"
    },
    "type": "Creature",
    "attack": 10,
    "defense": 8,
    "ability_text": "Deal 2 damage to opponent",
    "image_url": "/assets/images/fire_dragon.jpg"
  }
]
```

#### Search Cards
Search for cards by name.

**Endpoint:** `GET /api/cards/search?q=dragon&tcg_id=1`

**Query Parameters:**
- `q` (required): Search query
- `tcg_id` (optional): Filter by TCG game
- `type` (optional): Filter by card type
- `rarity_id` (optional): Filter by rarity ID

**Response:**
```json
[
  {
    "id": 1,
    "name": "Fire Dragon",
    "rarity": { ... },
    "type": "Creature",
    "attack": 10,
    "defense": 8,
    "ability_text": "Deal 2 damage to opponent",
    "image_url": "/assets/images/fire_dragon.jpg"
  }
]
```

### Decks

#### List Decks
Get all decks for the authenticated user.

**Endpoint:** `GET /api/decks/?tcg_id=1`

**Headers:** `Authorization: Bearer <token>`

**Query Parameters:**
- `tcg_id` (optional): Filter by TCG game

**Response:**
```json
[
  {
    "id": 1,
    "user_id": 1,
    "tcg_id": 1,
    "name": "My Deck",
    "created_at": "2024-01-01T00:00:00+00:00",
    "last_used": "2024-01-02T12:00:00+00:00",
    "card_count": 40
  }
]
```

#### Create Deck
Create a new deck.

**Endpoint:** `POST /api/decks/`

**Headers:** `Authorization: Bearer <token>`

**Request Body:**
```json
{
  "name": "My Awesome Deck",
  "tcg_id": 1
}
```

**Response:**
```json
{
  "id": 1,
  "user_id": 1,
  "tcg_id": 1,
  "name": "My Awesome Deck",
  "created_at": "2024-01-01T00:00:00+00:00",
  "last_used": null,
  "card_count": 0
}
```

#### Get Deck
Get details of a specific deck.

**Endpoint:** `GET /api/decks/{deck_id}`

**Headers:** `Authorization: Bearer <token>`

**Response:**
```json
{
  "id": 1,
  "user_id": 1,
  "tcg_id": 1,
  "name": "My Deck",
  "created_at": "2024-01-01T00:00:00+00:00",
  "last_used": "2024-01-02T12:00:00+00:00",
  "card_count": 40,
  "cards": [
    {
      "card_id": 1,
      "quantity": 3,
      "card_name": "Fire Dragon",
      "card_type": "Creature",
      "attack": 10,
      "defense": 8
    }
  ]
}
```

#### Add Card to Deck
Add cards to a deck.

**Endpoint:** `POST /api/decks/{deck_id}/cards`

**Headers:** `Authorization: Bearer <token>`

**Request Body:**
```json
{
  "card_id": 1,
  "quantity": 2
}
```

**Response:**
```json
{
  "success": true
}
```

#### Remove Card from Deck
Remove cards from a deck.

**Endpoint:** `DELETE /api/decks/{deck_id}/cards/{card_id}?quantity=1`

**Headers:** `Authorization: Bearer <token>`

**Query Parameters:**
- `quantity` (optional): Number of cards to remove (default: 1)

**Response:**
```json
{
  "success": true
}
```

#### Validate Deck
Validate a deck against game rules.

**Endpoint:** `GET /api/decks/{deck_id}/validate`

**Headers:** `Authorization: Bearer <token>`

**Response:**
```json
{
  "valid": true,
  "errors": [],
  "card_count": 40,
  "required_size": 40
}
```

#### Update Deck
Update deck name.

**Endpoint:** `PUT /api/decks/{deck_id}`

**Headers:** `Authorization: Bearer <token>`

**Request Body:**
```json
{
  "name": "Updated Deck Name"
}
```

**Response:**
```json
{
  "success": true
}
```

#### Delete Deck
Delete a deck.

**Endpoint:** `DELETE /api/decks/{deck_id}`

**Headers:** `Authorization: Bearer <token>`

**Response:**
```json
{
  "success": true
}
```

### Inventory

#### Get Inventory
Get user's card inventory.

**Endpoint:** `GET /api/inventory/?tcg_id=1`

**Headers:** `Authorization: Bearer <token>`

**Query Parameters:**
- `tcg_id` (optional): Filter by TCG game

**Response:**
```json
{
  "stats": {
    "total_cards": 150,
    "unique_cards": 45
  },
  "cards": [
    {
      "id": 1,
      "user_id": 1,
      "card_id": 1,
      "quantity": 3,
      "obtained_at": "2024-01-01T00:00:00+00:00",
      "source": "pack",
      "card_name": "Fire Dragon",
      "card_type": "Creature",
      "attack": 10,
      "defense": 8,
      "rarity_name": "Ultra Rare",
      "rarity_color": "#FFD700",
      "image_url": "/assets/images/fire_dragon.jpg"
    }
  ]
}
```

#### Get Card Quantity
Get quantity of a specific card in inventory.

**Endpoint:** `GET /api/inventory/card/{card_id}`

**Headers:** `Authorization: Bearer <token>`

**Response:**
```json
{
  "card_id": 1,
  "quantity": 3,
  "owned": true
}
```

### Matches

#### Find Match
Find an opponent for a match.

**Endpoint:** `POST /api/matches/find`

**Headers:** `Authorization: Bearer <token>`

**Request Body:**
```json
{
  "deck_id": 1,
  "mode": "ranked"
}
```

**Response:**
```json
{
  "id": 1,
  "player1_id": 1,
  "player2_id": 2,
  "deck1_id": 1,
  "deck2_id": 2,
  "mode": "ranked",
  "status": "active",
  "winner_id": null,
  "created_at": "2024-01-01T00:00:00+00:00",
  "ended_at": null
}
```

Or if queued:

```json
{
  "status": "queued"
}
```

#### Get Match
Get details of a specific match.

**Endpoint:** `GET /api/matches/{match_id}`

**Headers:** `Authorization: Bearer <token>`

**Response:**
```json
{
  "id": 1,
  "player1_id": 1,
  "player2_id": 2,
  "deck1_id": 1,
  "deck2_id": 2,
  "mode": "ranked",
  "status": "active",
  "winner_id": null,
  "created_at": "2024-01-01T00:00:00+00:00",
  "ended_at": null,
  "is_player": true,
  "opponent_id": 2,
  "my_deck_id": 1
}
```

#### Get Matches
Get all matches for the authenticated user.

**Endpoint:** `GET /api/matches/?status=active`

**Headers:** `Authorization: Bearer <token>`

**Query Parameters:**
- `status` (optional): Filter by status (pending, active, finished)

**Response:**
```json
[
  {
    "id": 1,
    "player1_id": 1,
    "player2_id": 2,
    "deck1_id": 1,
    "deck2_id": 2,
    "mode": "ranked",
    "status": "active",
    "winner_id": null,
    "created_at": "2024-01-01T00:00:00+00:00",
    "ended_at": null
  }
]
```

#### Get Match History
Get match history for the authenticated user.

**Endpoint:** `GET /api/matches/history?limit=20&offset=0`

**Headers:** `Authorization: Bearer <token>`

**Query Parameters:**
- `limit` (optional): Number of results (default: 20)
- `offset` (optional): Offset for pagination (default: 0)

**Response:**
```json
[
  {
    "id": 1,
    "player1_id": 1,
    "player2_id": 2,
    "deck1_id": 1,
    "deck2_id": 2,
    "mode": "ranked",
    "status": "finished",
    "winner_id": 1,
    "created_at": "2024-01-01T00:00:00+00:00",
    "ended_at": "2024-01-01T01:00:00+00:00",
    "player1_name": "Player1",
    "player2_name": "Player2",
    "deck1_name": "Deck 1",
    "deck2_name": "Deck 2"
  }
]
```

#### End Match
End a match and declare a winner.

**Endpoint:** `POST /api/matches/{match_id}/end`

**Headers:** `Authorization: Bearer <token>`

**Request Body:**
```json
{
  "winner_id": 1
}
```

**Response:**
```json
{
  "match_id": 1,
  "winner_id": 1,
  "loser_id": 2,
  "mode": "ranked"
}
```

#### Abandon Match
Abandon a match (counts as loss).

**Endpoint:** `POST /api/matches/{match_id}/abandon`

**Headers:** `Authorization: Bearer <token>`

**Response:**
```json
{
  "success": true
}
```

### Packs

#### List Packs
Get all available booster packs.

**Endpoint:** `GET /api/packs/`

**Response:**
```json
[
  {
    "id": 1,
    "tcg_id": 1,
    "name": "Starter Pack",
    "price": 0.00,
    "cards_per_pack": 5,
    "pack_type": "standard",
    "drop_table": [
      {
        "rarity_id": 1,
        "rarity_name": "Common",
        "probability": 0.70
      },
      {
        "rarity_id": 2,
        "rarity_name": "Uncommon",
        "probability": 0.20
      }
    ]
  }
]
```

#### Get Pack
Get details of a specific pack.

**Endpoint:** `GET /api/packs/{pack_id}`

**Response:**
```json
{
  "id": 1,
  "tcg_id": 1,
  "name": "Starter Pack",
  "price": 0.00,
  "cards_per_pack": 5,
  "pack_type": "standard",
  "drop_table": [ ... ]
}
```

#### Open Pack
Open a booster pack and receive cards.

**Endpoint:** `POST /api/packs/{pack_id}/open`

**Headers:** `Authorization: Bearer <token>`

**Response:**
```json
{
  "opening_id": 1,
  "pack": {
    "id": 1,
    "name": "Starter Pack",
    "cards_per_pack": 5
  },
  "cards": [
    {
      "id": 1,
      "name": "Fire Dragon",
      "rarity": {
        "id": 4,
        "name": "Ultra Rare",
        "color": "#FFD700"
      },
      "type": "Creature",
      "attack": 10,
      "defense": 8,
      "ability_text": "Deal 2 damage to opponent",
      "image_url": "/assets/images/fire_dragon.jpg"
    }
  ]
}
```

#### Get Pack Opening Result
Get result of a specific pack opening.

**Endpoint:** `GET /api/packs/openings/{opening_id}`

**Headers:** `Authorization: Bearer <token>`

**Response:**
```json
{
  "id": 1,
  "user_id": 1,
  "pack": {
    "id": 1,
    "name": "Starter Pack",
    "cards_per_pack": 5
  },
  "opened_at": "2024-01-01T00:00:00+00:00",
  "cards": [ ... ]
}
```

#### Get My Pack Openings
Get pack opening history for the authenticated user.

**Endpoint:** `GET /api/packs/my-openings?limit=20&offset=0`

**Headers:** `Authorization: Bearer <token>`

**Query Parameters:**
- `limit` (optional): Number of results (default: 20)
- `offset` (optional): Offset for pagination (default: 0)

**Response:**
```json
[
  {
    "id": 1,
    "user_id": 1,
    "pack_id": 1,
    "pack_name": "Starter Pack",
    "opened_at": "2024-01-01T00:00:00+00:00"
  }
]
```

### Daily Rewards

#### Get Daily Pack Status
Check if daily pack is available.

**Endpoint:** `GET /api/users/daily/status`

**Headers:** `Authorization: Bearer <token>`

**Response:**
```json
{
  "can_claim": true,
  "wait_time_seconds": 0,
  "wait_time_formatted": "0 seconds",
  "next_available_at": "Now",
  "pack": {
    "id": 1,
    "name": "Daily Pack",
    "price": 0.00,
    "cards_per_pack": 5
  }
}
```

#### Claim Daily Pack
Claim the daily free pack.

**Endpoint:** `POST /api/users/daily/claim`

**Headers:** `Authorization: Bearer <token>`

**Response:**
```json
{
  "success": true,
  "pack": {
    "id": 1,
    "name": "Daily Pack",
    "cards_per_pack": 5
  },
  "cards": [ ... ],
  "next_available_at": "2024-01-02T00:00:00+00:00"
}
```

### Games

#### Get Games
Get all available TCG games.

**Endpoint:** `GET /api/users/games`

**Response:**
```json
[
  {
    "id": 1,
    "name": "Sample Game",
    "deck_size": 40,
    "max_card_copies": 3,
    "ruleset_version": "1.0",
    "created_at": "2024-01-01T00:00:00+00:00"
  }
]
```

#### Get Game
Get details of a specific game.

**Endpoint:** `GET /api/users/games/{game_id}`

**Response:**
```json
{
  "id": 1,
  "name": "Sample Game",
  "deck_size": 40,
  "max_card_copies": 3,
  "ruleset_version": "1.0",
  "created_at": "2024-01-01T00:00:00+00:00"
}
```

### Leaderboard

#### Get Leaderboard
Get the global leaderboard.

**Endpoint:** `GET /api/users/leaderboard?limit=100&offset=0`

**Query Parameters:**
- `limit` (optional): Number of results (default: 100)
- `offset` (optional): Offset for pagination (default: 0)

**Response:**
```json
[
  {
    "rank": 1,
    "user_id": 1,
    "username": "Player1",
    "elo_rating": 1500,
    "rank_tier": "Gold"
  },
  {
    "rank": 2,
    "user_id": 2,
    "username": "Player2",
    "elo_rating": 1450,
    "rank_tier": "Gold"
  }
]
```

### Trades

#### Get Trades
Get all trades for the authenticated user.

**Endpoint:** `GET /api/trades/?status=pending`

**Headers:** `Authorization: Bearer <token>`

**Query Parameters:**
- `status` (optional): Filter by status (pending, accepted, rejected, cancelled)

**Response:**
```json
[
  {
    "id": 1,
    "sender_id": 1,
    "receiver_id": 2,
    "status": "pending",
    "created_at": "2024-01-01T00:00:00+00:00",
    "is_sender": true,
    "my_cards": [ ... ],
    "other_cards": [ ... ]
  }
]
```

#### Get Trade
Get details of a specific trade.

**Endpoint:** `GET /api/trades/{trade_id}`

**Headers:** `Authorization: Bearer <token>`

**Response:**
```json
{
  "id": 1,
  "sender_id": 1,
  "receiver_id": 2,
  "status": "pending",
  "created_at": "2024-01-01T00:00:00+00:00",
  "is_sender": true,
  "sender_cards": [ ... ],
  "receiver_cards": [ ... ]
}
```

#### Create Trade
Create a new trade request.

**Endpoint:** `POST /api/trades/`

**Headers:** `Authorization: Bearer <token>`

**Request Body:**
```json
{
  "receiver_id": 2
}
```

**Response:**
```json
{
  "id": 1,
  "sender_id": 1,
  "receiver_id": 2,
  "status": "pending",
  "created_at": "2024-01-01T00:00:00+00:00"
}
```

#### Add Card to Trade
Add cards to a trade.

**Endpoint:** `POST /api/trades/{trade_id}/cards`

**Headers:** `Authorization: Bearer <token>`

**Request Body:**
```json
{
  "card_id": 1,
  "quantity": 1
}
```

**Response:**
```json
{
  "success": true
}
```

#### Accept Trade
Accept a trade request (receiver only).

**Endpoint:** `POST /api/trades/{trade_id}/accept`

**Headers:** `Authorization: Bearer <token>`

**Response:**
```json
{
  "success": true
}
```

#### Reject Trade
Reject a trade request (receiver only).

**Endpoint:** `POST /api/trades/{trade_id}/reject`

**Headers:** `Authorization: Bearer <token>`

**Response:**
```json
{
  "success": true
}
```

#### Cancel Trade
Cancel a trade request (sender only).

**Endpoint:** `POST /api/trades/{trade_id}/cancel`

**Headers:** `Authorization: Bearer <token>`

**Response:**
```json
{
  "success": true
}
```

## Error Codes

Common error responses:

- `401 Unauthorized`: Invalid or missing authentication token
- `403 Forbidden`: User doesn't have permission for this action
- `404 Not Found`: Resource doesn't exist
- `400 Bad Request`: Invalid request parameters
- `500 Internal Server Error`: Server error

## WebSocket Events

### Connection

Connect to WebSocket server at `ws://your-server:8080`

#### Authenticate
```json
{
  "action": "authenticate",
  "token": "your-jwt-token"
}
```

#### Join Match
```json
{
  "action": "join_match",
  "match_id": 1
}
```

#### Game Action
```json
{
  "action": "game_action",
  "match_id": 1,
  "game_action": "play_card",
  "data": {
    "card_id": 123,
    "target_id": 456
  }
}
```

#### Leave Match
```json
{
  "action": "leave_match",
  "match_id": 1
}
```

## Rate Limiting

API endpoints may be rate limited to prevent abuse. Standard limits:

- Authentication endpoints: 10 requests per minute
- Pack opening: 20 requests per minute
- Match finding: 30 requests per minute
- Other endpoints: 100 requests per minute

## Pagination

List endpoints support pagination via `limit` and `offset` query parameters:

- `limit`: Number of results per page (default varies by endpoint)
- `offset`: Number of results to skip (default: 0)

Example:
```
GET /api/matches/history?limit=10&offset=20
```

## Filtering

Many endpoints support filtering via query parameters:

- `tcg_id`: Filter by TCG game
- `type`: Filter by card type
- `rarity_id`: Filter by rarity
- `status`: Filter by status

## Sorting

Results are typically sorted by:
- Creation date (newest first)
- Name (alphabetical)
- ELO rating (highest first for leaderboard)

## Webhooks

Currently not implemented, but planned for future features.

## Versioning

API version: v1

Include version in requests:
```
GET /api/v1/cards/
```
