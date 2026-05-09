# TCG Platform - Architecture Documentation

## System Overview

The TCG Platform is a multi-game trading card game web platform built with PHP 8+, MySQL, Redis, and WebSocket support. The system is designed to be modular, scalable, and secure.

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                         Client (Browser)                          │
└────────────────────┬────────────────────────────────────────┘
                     │ HTTP/WebSocket
                     ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Web Server (Apache/Nginx)                   │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────────┐
│                    PHP Application Layer                      │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                    Front Controller (index.php)          │  │
│  └────────────────────┬───────────────────────────────────┘  │
│                       │                                            │
│  ┌────────────────────▼──────────────────────────────────┐  │
│  │              Middleware Layer                            │  │
│  │  ┌────────────────────────────────────────────────┐   │  │
│  │  │ AuthMiddleware (JWT validation)              │   │  │
│  │  └────────────────────────────────────────────────┘   │  │
│  └────────────────────┬───────────────────────────────────┘  │
│                       │                                            │
│  ┌────────────────────▼──────────────────────────────────┐  │
│  │              API Endpoints                             │  │
│  │  ┌────────────────────────────────────────────────┐   │  │
│  │  │ /api/auth/ - Authentication                   │   │  │
│  │  │ /api/cards/ - Card management               │   │  │
│  │  │ /api/decks/ - Deck building                 │   │ │
│  │  │ /api/matches/ - Match management            │   │  │
│  │  │ /api/packs/ - Booster packs                  │   │ │
│  │  │ /api/inventory/ - User inventory              │   │  │
│  │  │ /api/trades/ - Trading system                │   │ │
│  │  │ /api/users/ - User management                │   │  │
│  │  └────────────────────────────────────────────────┘   │  │
│  └────────────────────┬───────────────────────────────────┘  │
│                       │                                            │
│  ┌────────────────────▼──────────────────────────────────┐  │
│  │              Service Layer                             │  │
│  │  ┌────────────────────────────────────────────────┐   │  │
│  │  │ PackOpeningService - Pack generation         │   │ │  │
│  │  │ MatchmakingService - Player matching         │   │  │
│  │  │ DailyRewardService - Daily rewards            │   │  │
│  │  └────────────────────────────────────────────────┘   │ │ │
│  └────────────────────┬───────────────────────────────────┘  │
│                       │                                            │
│  ┌────────────────────▼──────────────────────────────────┐  │
│  │              Model Layer                               │  │
│  │  ┌────────────────────────────────────────────────┐   │  │
│  │  │ User - User accounts and authentication     │   │ │ │
│  │  │ Card - Card definitions and data           │   │ │ │
│  │  │ TcgGame - TCG game configurations        │   │ │ │
│  │  │ BoosterPack - Pack definitions            │   │ │ │
│  │  │ UserInventory - User card collections      │   │ │ │
│  │  │ Deck - User decks                         │   │ │ │
│  │  │ Match - Match records and state          │   │ │ │
│  │  │ RankedTransfer - Ranked card transfers     │   │ │ │
│  │  │ Trade - Trade requests and state          │   │ │ │
│  │  └────────────────────────────────────────────────┘   │ │
│  └────────────────────┬───────────────────────────────────┘  │
│                       │                                            │
│  ┌────────────────────▼──────────────────────────────────┐  │
│  │              Database Layer                            │  │
│  │  ┌────────────────────────────────────────────────┐   │ │ │
│  │  │ Database - PDO connection management       │   │ │ │
│  │  └────────────────────────────────────────────────┘   │ │
│  └────────────────────┬───────────────────────────────────┘  │
└────────────────────┼────────────────────────────────────────┘
                     │
        ┌────────────┴────────────┐
        │                             │
        ▼                             ▼
┌───────────────┐          ┌───────────────┐
│   MySQL        │          │    Redis      │
│   Database     │          │    Cache      │
└───────────────┘          └───────────────┘
```

## Core Components

### 1. Authentication System

**Purpose**: User authentication and authorization

**Components**:
- `AuthService`: Handles registration, login, token generation
- `AuthMiddleware`: Validates JWT tokens on protected endpoints
- JWT tokens stored in HTTP cookies

**Flow**:
1. User registers → Account created in database
2. User logs in → Credentials validated → JWT token generated
3. Token stored in cookie → Used for subsequent requests
4. Middleware validates token on each protected request

**Security**:
- Passwords hashed with `password_hash()` (bcrypt)
- JWT tokens expire after 24 hours
- Tokens validated on every request

### 2. Pack Opening System

**Purpose**: Server-side RNG for booster pack generation

**Components**:
- `PackOpeningService`: Handles pack opening logic
- `BoosterPack` model: Pack definitions
- `Card` model: Card definitions
- Drop tables: Rarity probability configuration

**Flow**:
1. User requests pack opening
2. Service validates user has pack access
3. Service generates cards using drop table probabilities
4. Cards saved to `pack_openings` and `pack_opening_cards`
5. Cards added to user inventory atomically

**Security**:
- RNG runs only on server
- Pack results persisted to database
- Inventory updated in database transaction
- Client cannot manipulate results

### 3. Deck Building System

**Purpose**: Create and validate decks according to game rules

**Components**:
- `Deck` model: Deck definitions and card management
- `TcgGame` model: Game rules and constraints
- Validation logic: Ensures decks meet game requirements

**Flow**:
1. User creates deck with name and game selection
2. User adds/removes cards from inventory
3. System validates deck against game rules
4. Deck saved when valid

**Validation Rules**:
- Deck size must match game requirement
- No card exceeds max copies limit
- All cards must be owned by user

### 4. Matchmaking System

**Purpose**: Find opponents for matches

**Components**:
- `MatchmakingService`: Handles player matching logic
- `Match` model: Match state and results
- ELO rating system: Player skill assessment

**Flow**:
1. User requests match with deck and mode
2. Service finds opponent within ELO range
3. Match created with both players and decks
4. Match status set to "active"

**ELO System**:
- Initial rating: 1000
- K-factor: 32
- Expected score calculated using standard ELO formula
- Winner gains ELO, loser loses ELO

### 5. Ranked Transfer System

**Purpose**: Handle card transfers in ranked matches

**Components**:
- `RankedTransfer` model: Transfer records
- Transfer logic: Atomic card movement

**Flow**:
1. Ranked match ends with winner
2. Service calculates ELO changes
3. Loser's deck randomly selected for card transfer
4. Card transferred from loser to winner
5. Transfer recorded in database

**Security**:
- Transfer enforced server-side
- Random selection prevents manipulation
- Atomic transaction ensures consistency

### 6. Daily Reward System

**Purpose**: Provide daily free packs to users

**Components**:
- `DailyRewardService`: Handles daily pack logic
- Cooldown tracking: 24-hour cooldown

**Flow**:
1. User requests daily pack
2. Service checks cooldown timer
3. If available, pack opened and added to inventory
4. Cooldown timer reset

**Security**:
- Cooldown enforced server-side
- Cannot claim multiple packs per day
- Timestamps stored in database

### 7. Trading System

**Purpose**: Allow players to trade cards

**Components**:
- `Trade` model: Trade requests and state
- Trade validation: Ensures both parties have cards

**Flow**:
1. User creates trade request
2. Other user adds cards to trade
3. Receiver accepts trade
4. Cards transferred atomically

**States**:
- `pending`: Trade created, waiting for response
- `accepted`: Trade completed, cards transferred
- `rejected`: Trade declined
- `cancelled`: Trade cancelled by sender

## Database Schema

### Key Tables

**Users**: User accounts and authentication
- Stores credentials, ELO rating, rank tier
- Tracks last login and daily pack timestamp

**TCG Games**: Game configurations
- Defines deck size, max card copies, ruleset version
- Supports multiple games simultaneously

**Cards**: Card definitions
- Links to TCG game and rarity
- Stores stats, abilities, image URLs

**Booster Packs**: Pack definitions
- Links to TCG game
- Defines price, cards per pack, pack type

**Pack Drop Tables**: Rarity probabilities
- Links pack to rarity
- Defines probability for each rarity in pack

**User Inventory**: User card collections
- Links user to card with quantity
- Tracks source and acquisition date

**Decks**: User decks
- Links user to TCG game
- Stores deck name and metadata

**Deck Cards**: Deck composition
- Links deck to card with quantity
- Enforces uniqueness constraints

**Matches**: Match records
- Links two players and their decks
- Stores mode, status, winner, timestamps

**Ranked Transfers**: Card transfer records
- Links match to winner, loser, and transferred card
- Provides audit trail

**Trades**: Trade requests
- Links sender and receiver
- Tracks status and timestamps

**Trade Cards**: Trade composition
- Links trade to user and card with quantity
- Defines what each party offers

## Security Architecture

### Authentication
- JWT-based authentication
- Tokens stored in HTTP-only cookies
- 24-hour token expiration
- Password hashing with bcrypt

### Authorization
- Role-based access control
- Resource ownership validation
- User can only access their own data

### Data Validation
- Server-side validation for all inputs
- Type checking and sanitization
- SQL injection prevention via prepared statements

### Transaction Safety
- Database transactions for critical operations
- Atomic updates for inventory changes
- Rollback on failure

### Anti-Cheating
- RNG server-side only
- Deck validation server-side
- Match results validated server-side
- Inventory changes atomic

## Scalability Considerations

### Current Architecture
- Monolithic PHP application
- Single MySQL database
- Redis for caching and queues
- WebSocket server for real-time features

### Future Scalability Options

#### Microservices Migration
- Split into separate services:
  - Auth Service
  - Card Service
  - Match Service
  - Trade Service
  - Notification Service

#### Database Scaling
- Read replicas for read-heavy operations
- Database sharding by user ID
- Connection pooling

#### Caching Strategy
- Redis for session storage
- Redis for matchmaking queues
- Application-level caching for frequently accessed data

#### WebSocket Scaling
- Multiple WebSocket servers behind load balancer
- Redis pub/sub for cross-server communication
- Sticky sessions for match continuity

## Performance Optimization

### Database Optimization
- Indexed frequently queried columns
- Foreign key constraints for referential integrity
- Query optimization for complex joins

### Caching Strategy
- User sessions cached in Redis
- Matchmaking queues in Redis
- Frequently accessed data cached

### Frontend Optimization
- Lazy loading for large card collections
- Pagination for list views
- Debouncing for search inputs

## Error Handling

### Error Types
- Authentication errors (401)
- Authorization errors (403)
- Not found errors (404)
- Validation errors (400)
- Server errors (500)

### Error Response Format
```json
{
  "error": "Error message",
  "code": "ERROR_CODE",
  "details": {}
}
```

### Logging
- Application errors logged to Monolog
- Database errors logged to MySQL error log
- WebSocket errors logged to separate log file

## Monitoring

### Key Metrics
- Active users
- Matches played
- Packs opened
- API response times
- Database query times
- WebSocket connections

### Health Checks
- Database connectivity
- Redis connectivity
- WebSocket server status
- Disk space usage

## Deployment Architecture

### Development Environment
- Local PHP server
- Local MySQL instance
- Local Redis instance
- No SSL required

### Production Environment
- Load-balanced web servers
- Master-slave MySQL replication
- Redis Cluster
- SSL/TLS termination
- Process manager for WebSocket server

### CI/CD Pipeline
- Automated testing
- Code quality checks
- Automated deployment
- Rollback capability

## Technology Choices

### PHP 8+
- Modern language features
- Strong typing support
- Performance improvements
- Wide hosting support

### MySQL
- Relational database
- ACID compliance
- Wide ecosystem
- Mature tooling

### Redis
- In-memory data store
- Fast read/write operations
- Pub/sub messaging
- Session storage

### Ratchet
- PHP WebSocket library
- Real-time communication
- Event-driven architecture
- Easy integration

### JWT
- Stateless authentication
- Cross-origin support
- Built-in expiration
- Industry standard

## Future Enhancements

### Planned Features
- In-game purchases
- Tournament system
- Guild/clan system
- Achievement system
- Card crafting
- Spectator mode
- Replay system

### Technical Improvements
- API versioning
- GraphQL API
- Message queue for async operations
- Microservices architecture
- Container orchestration (Docker/Kubernetes)
- CDN for static assets
- Database read replicas
- Advanced caching strategies
