# TCG Platform - Multi-Game Trading Card Game Web Platform

A comprehensive, production-ready web platform for multiple trading card games (TCGs) with digital card collection, booster pack economy, and online competitive gameplay.

**Repository:** https://github.com/NikiZaziki/tcg-web

## Features

- **Multi-Game Support**: Play multiple TCGs (Pokémon, Yu-Gi-Oh, Magic, etc.) on a single platform
- **Digital Card Collection**: Collect and manage cards from different games
- **Booster Pack Economy**: Buy and open booster packs with server-side RNG
- **Deck Building**: Create and validate decks according to game rules
- **Online Matches**: Play against other players in real-time
- **Ranked Mode**: Competitive matches with ELO rating and card risk
- **Daily Rewards**: Free booster pack every 24 hours
- **Trading System**: Trade cards with other players
- **Leaderboard**: Track rankings across all players

## Tech Stack

### Backend
- **PHP 8+**: Core application logic
- **MySQL**: Database storage
- **Redis**: Caching and matchmaking queues
- **Ratchet**: WebSocket server for real-time matches
- **Firebase JWT**: Authentication tokens
- **Monolog**: Logging
- **vlucas/phpdotenv**: Configuration management

### Frontend
- **HTML5**: Markup
- **CSS3**: Custom styling (no frameworks)
- **JavaScript (Vanilla)**: Client-side logic
- **WebSocket**: Real-time match communication

## Project Structure

```
TCG/
├── api/                    # REST API endpoints
│   ├── auth/              # Authentication
│   ├── cards/             # Card management
│   ├── decks/             # Deck building
│   ├── inventory/         # User inventory
│   ├── matches/           # Match management
│   ├── packs/             # Booster packs
│   ├── trades/            # Trading system
│   └── users/             # User management
├── config/                # Configuration files
├── database/              # Database migrations
│   └── migrations/        # SQL migration files
├── public/                # Frontend assets
│   ├── assets/
│   │   ├── css/          # Stylesheets
│   │   └── js/           # JavaScript files
│   ├── index.html        # Dashboard
│   ├── login.html        # Login page
│   └── register.html     # Registration page
├── services/              # Business logic services
├── src/                   # PHP source code
│   ├── Auth/             # Authentication
│   ├── Config/           # Configuration
│   ├── Database/         # Database connection
│   ├── Middleware/       # Request middleware
│   └── Models/           # Data models
├── websocket/             # WebSocket server
├── .env                   # Environment configuration
├── composer.json          # PHP dependencies
└── README.md             # This file
```

## Installation

### Prerequisites

- PHP 8.2 or higher
- MySQL 8.0 or higher
- Redis 6.0 or higher
- Composer
- Web server (Apache/Nginx)

### Setup

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd TCG
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   # Edit .env with your database and Redis credentials
   ```

4. **Run database migrations**
   ```bash
   mysql -u root -p tcg_platform < database/migrations/001_create_users.sql
   mysql -u root -p tcg_platform < database/migrations/002_create_tcg_games.sql
   mysql -u root -p tcg_platform < database/migrations/003_create_card_rarity.sql
   mysql -u root -p tcg_platform < database/migrations/004_create_cards.sql
   mysql -u root -p tcg_platform < database/migrations/005_create_booster_packs.sql
   mysql -u root -p tcg_platform < database/migrations/006_create_pack_drop_tables.sql
   mysql -u root -p tcg_platform < database/migrations/007_create_user_inventory.sql
   mysql -u root -p tcg_platform < database/migrations/008_create_decks.sql
   mysql -u root -p tcg_platform < database/migrations/009_create_deck_cards.sql
   mysql -u root -p tcg_platform < database/migrations/010_create_matches.sql
   mysql -u root -p tcg_platform < database/migrations/011_create_ranked_transfers.sql
   mysql -u root -p tcg_platform < database/migrations/012_create_pack_openings.sql
   mysql -u root -p tcg_platform < database/migrations/013_create_pack_opening_cards.sql
   mysql -u root -p tcg_platform < database/migrations/014_create_orders.sql
   mysql -u root -p tcg_platform < database/migrations/015_create_order_items.sql
   mysql -u root -p tcg_platform < database/migrations/016_create_trades.sql
   mysql -u root -p tcg_platform < database/migrations/017_create_trade_cards.sql
   ```

5. **Configure web server**
   - Point your web server to the `public/` directory
   - Configure URL rewriting for clean URLs

6. **Start WebSocket server**
   ```bash
   php websocket/match_server.php
   ```

## API Documentation

### Authentication

#### Register
```http
POST /api/auth/register
Content-Type: application/json

{
  "username": "player1",
  "email": "player1@example.com",
  "password": "securepassword123"
}
```

#### Login
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "player1@example.com",
  "password": "securepassword123"
}
```

#### Get Current User
```http
GET /api/auth/me
Authorization: Bearer <token>
```

### Cards

#### List Cards
```http
GET /api/cards/?tcg_id=1&type=Creature&rarity_id=2
```

#### Search Cards
```http
GET /api/cards/search?q=dragon&tcg_id=1
```

### Decks

#### List Decks
```http
GET /api/decks/?tcg_id=1
Authorization: Bearer <token>
```

#### Create Deck
```http
POST /api/decks/
Authorization: Bearer <token>
Content-Type: application/json

{
  "name": "My Deck",
  "tcg_id": 1
}
```

#### Add Card to Deck
```http
POST /api/decks/{deck_id}/cards
Authorization: Bearer <token>
Content-Type: application/json

{
  "card_id": 123,
  "quantity": 2
}
```

#### Validate Deck
```http
GET /api/decks/{deck_id}/validate
Authorization: Bearer <token>
```

### Matches

#### Find Match
```http
POST /api/matches/find
Authorization: Bearer <token>
Content-Type: application/json

{
  "deck_id": 1,
  "mode": "ranked"
}
```

#### End Match
```http
POST /api/matches/{match_id}/end
Authorization: Bearer <token>
Content-Type: application/json

{
  "winner_id": 123
}
```

### Packs

#### List Packs
```http
GET /api/packs/
```

#### Open Pack
```http
POST /api/packs/{pack_id}/open
Authorization: Bearer <token>
```

### Daily Rewards

#### Get Status
```http
GET /api/users/daily/status
Authorization: Bearer <token>
```

#### Claim Pack
```http
POST /api/users/daily/claim
Authorization: Bearer <token>
```

### Trading

#### Create Trade
```http
POST /api/trades/
Authorization: Bearer <token>
Content-Type: application/json

{
  "receiver_id": 456
}
```

#### Add Card to Trade
```http
POST /api/trades/{trade_id}/cards
Authorization: Bearer <token>
Content-Type: application/json

{
  "card_id": 123,
  "quantity": 1
}
```

#### Accept Trade
```http
POST /api/trades/{trade_id}/accept
Authorization: Bearer <token>
```

## Security Features

### Server-Side Validation
- All card rewards and transfers validated server-side
- Deck validation enforced by game rules
- Match results validated server-side
- Inventory changes atomic with database transactions

### Anti-Cheating Measures
- RNG runs only on server
- Client cannot manipulate pack results
- Ranked card transfers enforced server-side
- Authentication via JWT tokens

### Data Integrity
- Database transactions for critical operations
- Foreign key constraints
- Unique constraints on user-card relationships

## Architecture Decisions

### Modular Design
The platform is split into logical modules:
- **Auth System**: User authentication and authorization
- **User/Inventory System**: Card collection management
- **Pack RNG System**: Server-side pack generation
- **Shop System**: Booster pack purchasing
- **Deck System**: Deck building and validation
- **Matchmaking System**: Player matching
- **Match Server**: Real-time game communication
- **Trading System**: Card trading between players
- **Reward System**: Daily rewards and achievements

### Scalability Considerations
- Redis for matchmaking queues and session management
- Database indexes for frequently queried data
- WebSocket server for real-time communication
- Modular architecture allows for microservices migration

### Database Schema
The database is designed with:
- Proper normalization
- Foreign key relationships
- Indexes for performance
- Audit trails for pack openings and transfers

## Development

### Running Tests
```bash
vendor/bin/phpunit
```

### Code Style
Follow PSR-12 coding standards.

### Adding New Features
1. Create model in `src/Models/`
2. Create service in `services/`
3. Create API endpoint in `api/`
4. Add frontend JavaScript in `public/assets/js/`
5. Update documentation

## License

MIT License

## Contributing

Contributions are welcome! Please follow these guidelines:
- Write clean, documented code
- Follow existing code style
- Add tests for new features
- Update documentation

## Support

For issues and questions, please open an issue on the repository.
