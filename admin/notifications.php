<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
requireAdminLogin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/notifications.php';
$db = getDB();
$csrfToken = generateCsrfToken();

$msg = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!verifyCsrfToken($_POST['csrf_token']??'')) { die('CSRF error'); }
    $title      = sanitizeInput($_POST['title']??'');
    $message    = sanitizeInput($_POST['message']??'');
    $targetType = sanitizeInput($_POST['target_type']??'all');
    $targetVal  = sanitizeInput($_POST['target_value']??'');

    if ($title && $message) {
        $result = queueAdminNotification($title, $message, $targetType, $targetVal);
        if (!empty($result['success'])) {
            $msg = 'Notification queued! Sent to '.intval($result['sent'] ?? 0).' subscribers.';
        } else {
            $msg = $result['error'] ?? 'Unable to queue notification right now.';
        }
    } else {
        $msg = 'Please fill in title and message.';
    }
}

// Notification history
$history=$db->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 20")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications - ZikGames Admin</title>
<link rel="stylesheet" href="/ZikGames/assets/css/style.css">
</head>
<body class="admin-page">
<button class="mobile-toggle" id="sidebar-toggle" type="button" aria-label="Toggle menu" aria-expanded="false">Menu</button>
<div class="admin-sidebar-overlay" id="admin-sidebar-overlay"></div>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="sidebar-brand"><div style="font-size:1.4rem;font-weight:900;" class="gradient-text">ZikGames</div></div>
    <nav class="sidebar-nav">
      <a href="/ZikGames/admin/dashboard.php"><span>📊</span> Dashboard</a>
      <a href="/ZikGames/admin/users.php"><span>👥</span> Users</a>
      <a href="/ZikGames/admin/subscriptions.php"><span>💳</span> Subscriptions</a>
      <a href="/ZikGames/admin/reports.php"><span>📈</span> Reports</a>
      <a href="/ZikGames/admin/notifications.php" class="active"><span>🔔</span> Notifications</a>
      <div class="divider" style="margin:12px 24px;"></div>
      <a href="/ZikGames/index.php"><span>🌐</span> View Site</a>
      <a href="/ZikGames/admin/logout.php"><span>🚪</span> Logout</a>
    </nav>
  </aside>

  <main class="admin-content">
    <div class="admin-topbar">
      <div><h1>🔔 Push Notifications</h1><p style="color:var(--text-muted);font-size:0.875rem;">Send alerts to your players</p></div>
    </div>

    <?php if($msg): ?>
    <div class="alert <?= str_contains($msg, 'queued') ? 'alert-success' : 'alert-warning' ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;">
      <!-- Send Form -->
      <div class="card">
        <h3 style="margin-bottom:20px;">📤 Send New Notification</h3>
        <form method="POST" id="send-notif-form">
          <input type="hidden" name="csrf_token" value="<?=$csrfToken?>">
          <div class="form-group">
            <label>Title</label>
            <input type="text" name="title" class="form-control" placeholder="e.g. New High Score Alert!" maxlength="100" required id="notif-title">
          </div>
          <div class="form-group">
            <label>Message</label>
            <textarea name="message" class="form-control" rows="4" placeholder="Your notification message..." maxlength="500" required id="notif-message" style="resize:vertical;"></textarea>
          </div>
          <div class="form-group">
            <label>Send To</label>
            <select name="target_type" class="form-control" onchange="toggleTargetInput(this.value)" id="notif-target-type">
              <option value="all">🌐 All Users</option>
              <option value="phone">📱 Specific Phone Number</option>
              <option value="game">🎮 Players of a Specific Game</option>
            </select>
          </div>
          <div class="form-group hidden" id="target-value-group">
            <label id="target-label">Value</label>
            <input type="text" name="target_value" class="form-control" id="notif-target-value" placeholder="">
          </div>
          <button type="submit" class="btn btn-primary btn-block" id="send-notif-btn">🚀 Send Notification</button>
        </form>
      </div>

      <!-- History -->
      <div class="card">
        <h3 style="margin-bottom:16px;">📋 Notification History</h3>
        <?php if(empty($history)): ?>
        <div style="text-align:center;padding:40px;color:var(--text-muted);">No notifications sent yet</div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:12px;max-height:500px;overflow-y:auto;">
          <?php foreach($history as $n): ?>
          <div style="background:var(--bg-card2);border-radius:var(--radius-sm);padding:14px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
              <strong style="font-size:0.9rem;"><?=htmlspecialchars($n['title'])?></strong>
              <span class="badge badge-green"><?=$n['sent_count']?> sent</span>
            </div>
            <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:8px;"><?=htmlspecialchars($n['message'])?></p>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
              <span class="badge badge-purple"><?=$n['target_type']?></span>
              <?php if($n['target_value']): ?><span class="badge badge-orange"><?=htmlspecialchars($n['target_value'])?></span><?php endif; ?>
              <span style="font-size:0.7rem;color:var(--text-dim);margin-left:auto;"><?=date('M j H:i',strtotime($n['created_at']))?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<script>
function toggleTargetInput(type) {
  const group=document.getElementById('target-value-group');
  const label=document.getElementById('target-label');
  const input=document.getElementById('notif-target-value');
  if(type==='all'){group.classList.add('hidden');}
  else {
    group.classList.remove('hidden');
    if(type==='phone'){label.textContent='Phone Number';input.placeholder='e.g. 08012345678';}
    else{label.textContent='Game Name';input.placeholder='e.g. Danfo Dodge';}
  }
}
</script>
<script src="/ZikGames/assets/js/admin.js"></script>
</body>
</html>


