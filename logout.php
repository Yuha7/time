<?php
session_start();
require_once __DIR__ . '/config.php';
if (!empty($_SESSION['user_id'])) log_action($pdo, $_SESSION['user_id'], 'LOGOUT', '');
session_destroy();
header('Location: /time/login.php'); exit;
