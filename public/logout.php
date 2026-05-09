<?php
require_once __DIR__ . "/../vendor/autoload.php";

use TCG\Platform\Config\Config;
use TCG\Platform\Database\Database;

Config::load();
Database::init(Config::get("database"));

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Clear token cookie
    setcookie("tcg_token", "", time() - 3600, "/");
    
    // Redirect to login
    header("Location: /login.html");
    exit;
}

// If GET request, show logout confirmation
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - TCG Platform</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="auth-box">
            <h1>TCG Platform</h1>
            <h2>Logout</h2>
            <p>Are you sure you want to logout?</p>
            <form method="POST">
                <button type="submit" class="btn btn-primary">Yes, Logout</button>
                <a href="/index.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</body>
</html>
