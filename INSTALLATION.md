# TCG Platform - Installation Guide

## Prerequisites

- **PHP 8.2 or higher**
- **MySQL 8.0 or higher**
- **Redis 6.0 or higher**
- **Composer** (PHP package manager)
- **Web server** (Apache with mod_rewrite or Nginx)
- **Git** (for cloning the repository)

## Installation Steps

### 1. Clone the Repository

```bash
git clone <repository-url>
cd TCG
```

### 2. Install PHP Dependencies

```bash
composer install
```

### 3. Configure Environment

Copy the example environment file and configure it:

```bash
cp .env.example .env
```

Edit `.env` with your database and Redis credentials:

```env
DB_HOST=localhost
DB_NAME=tcg_platform
DB_USER=root
DB_PASS=your_password
JWT_SECRET=your-secret-key-change-in-production
REDIS_HOST=localhost
REDIS_PORT=6379
WS_HOST=0.0.0.0
WS_PORT=8080
APP_ENV=production
APP_DEBUG=false
```

### 4. Create Database

Create a MySQL database named `tcg_platform`:

```bash
mysql -u root -p -e "CREATE DATABASE tcg_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 5. Run Database Migrations

Run all migration files in order:

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

### 6. Configure Web Server

#### Apache Configuration

Create a virtual host configuration:

```apache
<VirtualHost *:80>
    ServerName tcg-platform.local
    DocumentRoot /path/to/TCG/public
    
    <Directory /path/to/TCG/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/tcg-platform-error.log
    CustomLog ${APACHE_LOG_DIR}/tcg-platform-access.log combined
</VirtualHost>
```

Enable mod_rewrite:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

#### Nginx Configuration

```nginx
server {
    listen 80;
    server_name tcg-platform.local;
    root /path/to/TCG/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

### 7. Start Redis Server

```bash
redis-server
```

### 8. Start WebSocket Server (Optional)

For real-time match functionality:

```bash
php websocket/match_server.php
```

### 9. Set File Permissions

Ensure the web server can write to necessary directories:

```bash
chmod -R 755 public/
chmod -R 777 public/assets/images/  # If you have image uploads
```

### 10. Access the Application

Open your browser and navigate to:

```
http://localhost/
```

or

```
http://your-server-ip/
```

## Initial Setup

### Create First User

1. Navigate to `/register.php`
2. Fill in the registration form
3. Submit to create your account

### Create Sample Data

You'll need to create sample data for testing:

1. **Create TCG Games** (via MySQL or API)
2. **Create Cards** (via MySQL or API)
3. **Create Booster Packs** (via MySQL or API)
4. **Configure Pack Drop Tables** (via MySQL or API)

### Sample SQL for Initial Data

```sql
-- Create a sample TCG game
INSERT INTO tcg_games (name, deck_size, max_card_copies, ruleset_version) 
VALUES ('Sample Game', 40, 3, '1.0');

-- Create sample cards
INSERT INTO cards (tcg_id, name, rarity_id, type, attack, defense, ability_text, image_url)
VALUES 
(1, 'Fire Dragon', 4, 'Creature', 10, 8, 'Deal 2 damage to opponent', '/assets/images/fire_dragon.jpg'),
(1, 'Water Shield', 3, 'Spell', 0, 15, 'Block 5 damage', '/assets/images/water_shield.jpg'),
(1, 'Earth Golem', 2, 'Creature', 8, 12, 'Cannot be targeted by spells', '/assets/images/earth_golem.jpg');

-- Create a booster pack
INSERT INTO booster_packs (tcg_id, name, price, cards_per_pack, pack_type)
VALUES (1, 'Starter Pack', 0.00, 5, 'standard');

-- Configure drop table
INSERT INTO pack_drop_tables (pack_id, rarity_id, probability)
VALUES 
(1, 1, 0.70),  -- Common 70%
(1, 2, 0.20),  -- Uncommon 20%
(1, 3, 0.08),  -- Rare 8%
(1, 4, 0.02);  -- Ultra Rare 2%
```

## Troubleshooting

### Database Connection Issues

If you get database connection errors:

1. Check MySQL is running: `sudo systemctl status mysql`
2. Verify credentials in `.env`
3. Ensure database exists: `mysql -u root -p -e "SHOW DATABASES;"`

### Permission Issues

If you get permission errors:

1. Check file permissions: `ls -la`
2. Fix permissions: `chmod -R 755 public/`
3. Check web server user: `ps aux | grep apache` or `ps aux | grep nginx`

### WebSocket Issues

If WebSocket server won't start:

1. Check port 8080 is available: `netstat -tlnp | grep 8080`
2. Check Ratchet is installed: `composer show cboden/ratchet`
3. Check PHP error logs

### URL Rewriting Issues

If URLs don't work correctly:

1. Ensure mod_rewrite is enabled: `sudo a2enmod rewrite`
2. Check .htaccess file exists in `public/`
3. Check Apache error logs

## Development

### Running in Development Mode

Set `APP_ENV=development` and `APP_DEBUG=true` in `.env` for detailed error messages.

### Running Tests

```bash
vendor/bin/phpunit
```

### Code Style

Follow PSR-12 coding standards:

```bash
vendor/bin/phpcs --standard=PSR12 src/
```

## Security Notes

1. **Change JWT Secret**: Always change the default JWT_SECRET in production
2. **Use HTTPS**: Enable SSL/TLS for production deployments
3. **Database Security**: Use strong passwords and restrict database access
4. **File Permissions**: Ensure sensitive files are not accessible via web
5. **Regular Updates**: Keep dependencies updated with `composer update`

## Production Deployment

For production deployment:

1. Set `APP_ENV=production` and `APP_DEBUG=false`
2. Enable HTTPS with valid SSL certificate
3. Configure proper error logging
4. Set up monitoring and alerting
5. Enable database backups
6. Configure Redis persistence
7. Use process manager for WebSocket server (e.g., Supervisor)

## Support

For issues and questions:
- Check the [README.md](README.md) for API documentation
- Review error logs in `/var/log/apache2/error.log` or similar
- Check PHP error logs: `/var/log/php/error.log`
