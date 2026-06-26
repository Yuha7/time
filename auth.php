<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function auth_required($role = null) {
    if (empty($_SESSION['user_id'])) {
        header('Location: /time/login.php'); exit;
    }
    if ($role && $_SESSION['role'] !== $role && $_SESSION['role'] !== 'admin') {
        header('Location: /time/dashboard.php'); exit;
    }
}

function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

if (!function_exists('current_user_id')) {
    function current_user_id(){
        return $_SESSION['user_id'] ?? null;
    }
}
