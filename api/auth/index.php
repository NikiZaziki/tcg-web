<?php

require_once __DIR__ . "/../../vendor/autoload.php";

use TCG\Platform\Config\Config;
use TCG\Platform\Database\Database;
use TCG\Platform\Auth\AuthService;
use TCG\Platform\Middleware\AuthMiddleware;

Config::load();
Database::init(Config::get("database"));
AuthService::init();

header("Content-Type: application/json");

$method = $_SERVER["REQUEST_METHOD"];
$path = $_SERVER["PATH_INFO"] ?? "/";

switch ($method) {
    case "POST":
        if ($path === "/register") {
            handleRegister();
        } elseif ($path === "/login") {
            handleLogin();
        } else {
            sendError(404, "Not found");
        }
        break;

    case "GET":
        if ($path === "/me") {
            handleMe();
        } else {
            sendError(404, "Not found");
        }
        break;

    default:
        sendError(405, "Method not allowed");
}

function handleRegister(): void {
    $input = getInput();

    if (empty($input["username"]) || empty($input["email"]) || empty($input["password"])) {
        sendError(400, "Missing required fields");
    }

    try {
        $result = AuthService::register($input["username"], $input["email"], $input["password"]);
        sendJson($result, 201);
    } catch (\InvalidArgumentException $e) {
        sendError(400, $e->getMessage());
    }
}

function handleLogin(): void {
    $input = getInput();

    if (empty($input["email"]) || empty($input["password"])) {
        sendError(400, "Missing email or password");
    }

    try {
        $result = AuthService::login($input["email"], $input["password"]);
        sendJson($result);
    } catch (\InvalidArgumentException $e) {
        sendError(401, $e->getMessage());
    }
}

function handleMe(): void {
    $user = AuthMiddleware::requireAuth();
    $userModel = \TCG\Platform\Models\User::findById((int) $user["user_id"]);

    if (!$userModel) {
        sendError(404, "User not found");
    }

    sendJson($userModel->toArray());
}

function getInput(): array {
    $input = file_get_contents("php://input");
    return json_decode($input, true) ?? [];
}

function sendJson(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
}

function sendError(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(["error" => $message]);
}
