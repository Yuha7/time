<?php
session_start();
require_once __DIR__ . '/config.php';

if (!empty($_SESSION['user_id'])) { header('Location: /time/dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([trim($_POST['email'])]);
    $user = $stmt->fetch();
    if ($user && password_verify($_POST['password'], $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['role']    = $user['role'];
        log_action($pdo, $user['id'], 'LOGIN', 'User logged in');
        header('Location: /time/dashboard.php'); exit;
    }
    $error = 'Invalid email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login TIME MANAGEMENT SYSTEM</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>body{background:#1a237e;min-height:100vh;display:flex;align-items:center;justify-content:center}</style>
</head>
<body>
<div class="card shadow" style="width:400px">
  <div class="card-body p-4">
    <h4 class="text-center mb-1 fw-bold text-primary">ETMS</h4>
    <p class="text-center text-muted mb-4">Examination Timetable Management</p>
    <?php if($error): ?><div class="alert alert-danger py-2"><?= $error ?></div><?php endif; ?>
    <form method="POST">
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button class="btn btn-primary w-100">Login</button>
    </form>
  
  </div>
</div>
</body>
</html>
