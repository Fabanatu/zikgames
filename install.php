<?php
if (php_sapi_name() === 'cli') {
    $_GET['step'] = 'run';
}

$error = '';
$success = '';
$newAdminPassword = null;
$step = $_GET['step'] ?? '1';

if ($step === 'run') {
    // Read config
    $db_host = '127.0.0.1';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'fabgames';

    try {
        // Try connecting to server without db first
        $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Execute SQL file
        $sqlFile = __DIR__ . '/database.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("database.sql not found at " . $sqlFile);
        }

        $sql = file_get_contents($sqlFile);
        
        // Remove comments
        $sql = preg_replace('/--.*$/m', '', $sql);
        
        // Split by semicolon (roughly, making sure we don't break queries)
        $queries = explode(';', $sql);

        foreach ($queries as $query) {
            $query = trim($query);
            if ($query) {
                $pdo->exec($query);
            }
        }

        // Insert sample score entries for top leaderboard preview to look alive
        $pdo->exec("USE `$db_name`");

        $adminCount = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
        if ($adminCount === 0) {
            $newAdminPassword = bin2hex(random_bytes(12));
            $adminStmt = $pdo->prepare(
                'INSERT INTO admins (name, email, password, created_at) VALUES (?, ?, ?, NOW())'
            );
            $adminStmt->execute(['Admin', 'admin@fabgames.ng', password_hash($newAdminPassword, PASSWORD_DEFAULT)]);
        }
        
        // Check if we have sample users
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        if ($stmt->fetchColumn() == 0) {
            // Create some funny Nigerian-themed mock users and high scores
            $samples = [
                ['08031112222', 'Zombie Shooter', 450],
                ['08031112222', 'Danfo Dodge', 120],
                ['07099998888', 'Fruit Slice', 850],
                ['08122223333', 'Stickman Shooter', 620],
                ['09055554444', 'Bubble Shooter', 310],
                ['08031112222', 'Bubble Shooter', 150]
            ];

            foreach ($samples as $s) {
                // Check if user exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
                $stmt->execute([$s[0]]);
                $uid = $stmt->fetchColumn();

                if (!$uid) {
                    $pdo->prepare("INSERT INTO users (phone, status, last_login, created_at, updated_at) VALUES (?, 'active', NOW(), NOW(), NOW())")
                        ->execute([$s[0]]);
                    $uid = $pdo->lastInsertId();
                }

                $pdo->prepare("INSERT INTO game_scores (user_id, game_name, score, created_at) VALUES (?, ?, ?, NOW())")
                    ->execute([$uid, $s[1], $s[2]]);
            }
            
            // Recalculate total_games_played
            $pdo->exec("UPDATE users u SET total_games_played = (SELECT COUNT(*) FROM game_scores WHERE user_id = u.id)");
        }

        $success = 'Database installed and populated with sample data successfully!';
    } catch (Exception $e) {
        $error = 'Installation failed: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Database Setup – FabGames</title>
<link rel="stylesheet" href="/ZikGames/assets/css/style.css">
<style>
  body { display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px; }
  .install-card { width:100%; max-width:500px; }
  .step-indicator { display:flex; justify-content:space-between; margin-bottom:24px; font-size:0.8rem; color:var(--text-muted); }
  .step-indicator .active { color:var(--primary-light); font-weight:700; }
</style>
</head>
<body>
<div class="install-card">
  <div class="card">
    <div style="text-align:center; margin-bottom:24px;">
      <div style="font-size:3.5rem; animation:float 3s ease-in-out infinite;">⚙️</div>
      <h1 style="font-size:1.8rem; margin-top:8px;">FabGames Setup</h1>
      <p style="color:var(--text-muted); font-size:0.875rem;">Initialize your local MySQL database</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error">
      <div><strong>Installation Error:</strong><br><?= htmlspecialchars($error) ?></div>
    </div>
    <div style="text-align:center; margin-top:20px;">
      <a href="?step=run" class="btn btn-primary" id="retry-btn">🔄 Retry Connection</a>
    </div>
    <?php elseif ($success): ?>
    <div class="alert alert-success">
      <div>🎉 <?= $success ?></div>
    </div>
    <div style="background:rgba(124,58,237,0.1); border:1px solid var(--border); border-radius:8px; padding:16px; margin:20px 0; font-size:0.85rem;">
      <h4 style="margin-bottom:8px; color:var(--primary-light);">🔑 Admin Access</h4>
      <p style="margin-bottom:4px;"><strong>Admin Portal:</strong> <a href="/ZikGames/admin/login.php" style="color:var(--accent); text-decoration:underline;">/ZikGames/admin/login.php</a></p>
      <?php if ($newAdminPassword !== null): ?>
      <p style="margin-bottom:4px;">Email: <code>admin@fabgames.ng</code></p>
      <p style="margin-bottom:4px;">Temporary password: <code><?= htmlspecialchars($newAdminPassword) ?></code></p>
      <p>Save this password now. It is only shown during initial setup.</p>
      <?php else: ?>
      <p>Existing admin accounts were preserved. Use your current admin credentials.</p>
      <?php endif; ?>
    </div>
    <div style="display:flex; gap:12px; justify-content:center;">
      <a href="/ZikGames/index.php" class="btn btn-outline" id="go-portal-btn">🌐 View Portal</a>
      <a href="/ZikGames/admin/login.php" class="btn btn-primary" id="go-admin-btn">🛡️ Admin Login</a>
    </div>
    <?php else: ?>
    <div style="font-size:0.9rem; line-height:1.6; color:var(--text-muted); margin-bottom:24px;">
      <p style="margin-bottom:12px;">This script will configure the local database <strong>fabgames</strong> in MySQL using standard XAMPP/local credentials:</p>
      <ul style="margin-left:20px; margin-bottom:16px;">
        <li>Host: <code>localhost</code></li>
        <li>User: <code>root</code></li>
        <li>Password: <em>(none)</em></li>
        <li>Database: <code>fabgames</code> (created automatically)</li>
      </ul>
      <p style="color:#FCD34D;">Make sure your MySQL server (XAMPP/WAMP) is running before proceeding.</p>
    </div>
    <div style="text-align:center;">
      <a href="?step=run" class="btn btn-accent btn-block btn-lg" id="run-setup-btn">🚀 Initialize Database Now</a>
    </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>

