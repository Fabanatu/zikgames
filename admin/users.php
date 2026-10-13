<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
requireAdminLogin();
require_once __DIR__ . '/../config/database.php';

$db = getDB();
$csrfToken = generateCsrfToken();

// Handle toggle user status
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['toggle_user'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { die('CSRF error'); }
    $uid = intval($_POST['user_id']);
    $db->prepare("UPDATE users SET status=IF(status='active','disabled','active'),updated_at=NOW() WHERE id=?")->execute([$uid]);
    header('Location: /ZikGames/admin/users.php?msg=updated'); exit;
}

// Search
$search = sanitizeInput($_GET['q'] ?? '');
$page   = max(1, intval($_GET['page'] ?? 1));
$perPage= 20;
$offset = ($page-1)*$perPage;

if ($search) {
    $stmt=$db->prepare("SELECT * FROM users WHERE phone LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute(["%$search%",$perPage,$offset]);
    $countStmt=$db->prepare("SELECT COUNT(*) FROM users WHERE phone LIKE ?");
    $countStmt->execute(["%$search%"]);
} else {
    $stmt=$db->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute([$perPage,$offset]);
    $countStmt=$db->query("SELECT COUNT(*) FROM users");
}
$users=$stmt->fetchAll();
$total=$countStmt->fetchColumn();
$pages=ceil($total/$perPage);

// Export CSV
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="fabgames-users-'.date('Ymd').'.csv"');
    $out=fopen('php://output','w');
    fputcsv($out,['ID','Phone','Status','Total Games','Last Login','Created']);
    $all=$db->query("SELECT id,phone,status,total_games_played,last_login,created_at FROM users ORDER BY created_at DESC")->fetchAll();
    foreach($all as $u) fputcsv($out,[$u['id'],$u['phone'],$u['status'],$u['total_games_played'],$u['last_login'],$u['created_at']]);
    fclose($out); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Users – FabGames Admin</title>
<link rel="stylesheet" href="/ZikGames/assets/css/style.css">
</head>
<body class="admin-page">
<button class="mobile-toggle" id="sidebar-toggle" type="button" aria-label="Toggle menu" aria-expanded="false">Menu</button>
<div class="admin-sidebar-overlay" id="admin-sidebar-overlay"></div>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="sidebar-brand"><div style="font-size:1.4rem;font-weight:900;" class="gradient-text">🎮 FabGames</div><div style="font-size:0.75rem;color:var(--text-muted);">Admin Panel</div></div>
    <nav class="sidebar-nav">
      <a href="/ZikGames/admin/dashboard.php"><span>📊</span> Dashboard</a>
      <a href="/ZikGames/admin/users.php" class="active"><span>👥</span> Users</a>
      <a href="/ZikGames/admin/subscriptions.php"><span>💳</span> Subscriptions</a>
      <a href="/ZikGames/admin/reports.php"><span>📈</span> Reports</a>
      <a href="/ZikGames/admin/notifications.php"><span>🔔</span> Notifications</a>
      <div class="divider" style="margin:12px 24px;"></div>
      <a href="/ZikGames/index.php"><span>🌐</span> View Site</a>
      <a href="/ZikGames/admin/logout.php"><span>🚪</span> Logout</a>
    </nav>
  </aside>

  <main class="admin-content">
    <div class="admin-topbar">
      <div>
        <h1>👥 Users</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;"><?= number_format($total) ?> total registered users</p>
      </div>
      <a href="?export=1" class="btn btn-accent btn-sm" id="export-csv-btn">📥 Export CSV</a>
    </div>

    <?php if(isset($_GET['msg'])): ?>
    <div class="alert alert-success">✅ User status updated successfully.</div>
    <?php endif; ?>

    <!-- Search -->
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
      <input type="text" name="q" class="form-control" placeholder="Search by phone..." value="<?= htmlspecialchars($search) ?>" style="max-width:360px;" id="user-search-input">
      <button class="btn btn-primary" type="submit" id="user-search-btn">🔍 Search</button>
      <?php if($search): ?><a href="/ZikGames/admin/users.php" class="btn btn-outline">✕ Clear</a><?php endif; ?>
    </form>

    <div class="table-wrapper card" style="padding:0;">
      <table>
        <thead>
          <tr>
            <th>Phone</th>
            <th>Status</th>
            <th>Games Played</th>
            <th>Last Login</th>
            <th>Joined</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($users)): ?>
          <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted);">No users found</td></tr>
          <?php else: ?>
          <?php foreach($users as $u): ?>
          <tr>
            <td><strong><?= htmlspecialchars($u['phone']) ?></strong></td>
            <td>
              <span class="badge <?= $u['status']==='active'?'badge-green':'badge-red' ?>">
                <?= $u['status']==='active'?'● Active':'○ Disabled' ?>
              </span>
            </td>
            <td><?= number_format($u['total_games_played']) ?></td>
            <td style="font-size:0.8rem;color:var(--text-muted);"><?= $u['last_login']?date('M j, H:i',strtotime($u['last_login'])):'Never' ?></td>
            <td style="font-size:0.8rem;color:var(--text-muted);"><?= date('M j, Y',strtotime($u['created_at'])) ?></td>
            <td>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <input type="hidden" name="toggle_user" value="1">
                <button type="submit" class="btn btn-sm <?= $u['status']==='active'?'btn-danger':'btn-accent' ?>">
                  <?= $u['status']==='active'?'🚫 Disable':'✅ Enable' ?>
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if($pages>1): ?>
    <div style="display:flex;gap:8px;margin-top:20px;flex-wrap:wrap;">
      <?php for($p=1;$p<=$pages;$p++): ?>
      <a href="?q=<?=urlencode($search)?>&page=<?=$p?>" 
         class="btn <?=$p===$page?'btn-primary':'btn-outline'?> btn-sm">
        <?=$p?>
      </a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </main>
</div>
<script src="/ZikGames/assets/js/admin.js"></script>
</body>
</html>

