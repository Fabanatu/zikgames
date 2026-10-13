<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (isAdminLoggedIn()) {
    header('Location: /ZikGames/admin/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please fill in all fields.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            header('Location: /ZikGames/admin/dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login – FabGames</title>
<link rel="stylesheet" href="/ZikGames/assets/css/style.css">
<style>
  body { display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px; }
  .admin-login-card { width:100%; max-width:420px; }
</style>
</head>
<body>
<div class="admin-login-card">
  <div class="card">
    <div style="text-align:center; margin-bottom:28px;">
      <div style="font-size:2.5rem; margin-bottom:10px;">🛡️</div>
      <h2>Admin Panel</h2>
      <p class="text-muted" style="font-size:0.875rem; margin-top:6px;">FabGames Control Center</p>
    </div>
    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="" id="admin-login-form">
      <div class="form-group">
        <label for="admin-email">Email</label>
        <input type="email" id="admin-email" name="email" class="form-control" placeholder="admin@fabgames.ng" required autocomplete="username">
      </div>
      <div class="form-group">
        <label for="admin-password">Password</label>
        <input type="password" id="admin-password" name="password" class="form-control" placeholder="••••••••" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary btn-block" id="admin-login-btn">🔐 Login</button>
    </form>
    <p style="text-align:center; margin-top:20px; font-size:0.8rem; color:var(--text-dim);">
      <a href="/ZikGames/index.php" style="color:var(--text-dim);">← Back to FabGames</a>
    </p>
  </div>
</div>
</body>
</html>


