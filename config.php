<?php

if (session_status() === PHP_SESSION_NONE){
    session_start();
}


define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'etms');
define('APP_NAME', 'TIME MANAGEMENT SYSTEM');

$pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function log_action($pdo, $user_id, $action, $details = '') {
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $action, $details]);
}

//new implementation

