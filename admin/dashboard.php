<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
requireAdminLogin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app_settings.php';

$db = getDB();
$settingsSaved = false;
$settingsError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_ttt_limits') {
    $drawLimit = max(1, min(50, intval($_POST['ttt_draw_limit'] ?? 10)));
    $lossLimit = max(1, min(20, intval($_POST['ttt_loss_limit'] ?? 5)));

    try {
        setAppSetting('ttt_draw_limit', (string) $drawLimit);
        setAppSetting('ttt_loss_limit', (string) $lossLimit);
        $settingsSaved = true;
    } catch (Exception $e) {
        $settingsError = 'Unable to save Tic Tac Toe settings right now.';
    }
}

$tttDrawLimit = intval(getAppSetting('ttt_draw_limit', '10'));
$tttLossLimit = intval(getAppSetting('ttt_loss_limit', '5'));

// Stats
$totalUsers    = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$activeToday   = $db->query("SELECT COUNT(*) FROM users WHERE DATE(last_login)=CURDATE()")->fetchColumn();
$newToday      = $db->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$totalGames    = $db->query("SELECT COUNT(*) FROM game_scores")->fetchColumn();
$todayGames    = $db->query("SELECT COUNT(*) FROM game_scores WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$highestScore  = $db->query("SELECT MAX(score) FROM game_scores")->fetchColumn() ?? 0;

// Most played game
$mostPlayed = $db->query("SELECT game_name, COUNT(*) as cnt FROM game_scores GROUP BY game_name ORDER BY cnt DESC LIMIT 1")->fetch();

// Daily chart data (last 7 days)
$chartData = $db->query("
    SELECT DATE(created_at) as day, COUNT(*) as plays
    FROM game_scores
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day
")->fetchAll();
$chartLabels = []; $chartValues = [];
for($i=6;$i>=0;$i--){
    $d=date('Y-m-d',strtotime("-$i days"));
    $chartLabels[]="'".date('D',strtotime("-$i days"))."'";
    $found=array_filter($chartData,fn($r)=>$r['day']===$d);
    $chartValues[]=count($found)?intval(array_values($found)[0]['plays'] ?? 0):0;
}

// Recent activity
$recentActivity = $db->query("
    SELECT al.action, al.details, al.created_at, u.phone
    FROM activity_logs al
    JOIN users u ON al.user_id=u.id
    ORDER BY al.created_at DESC LIMIT 8
")->fetchAll();

function sidebarLink($href, $icon, $label, $active='') {
    $cls = strpos($_SERVER['PHP_SELF'],$active)!==false?'active':'';
    return "<a href='$href' class='$cls'><span>$icon</span> $label</a>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard – FabGames</title>
<link rel="stylesheet" href="/ZikGames/assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
  body { overflow-x:hidden; }
  body.admin-nav-open { overflow: hidden; }
  .mobile-toggle {
    display:none; position:fixed; top:16px; left:16px; z-index:200;
    background:var(--bg-card); border:1px solid var(--border);
    border-radius:var(--radius-sm); padding:10px; cursor:pointer; font-size:1.2rem;
  }
  @media(max-width:768px){ .mobile-toggle{display:block;} }
  .activity-item { display:flex; gap:12px; padding:12px 0; border-bottom:1px solid var(--border); }
  .activity-item:last-child { border-bottom:none; }
  .activity-icon { width:36px;height:36px;border-radius:50%;background:rgba(124,58,237,0.15);display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0; }
  .admin-sidebar-footer { padding:20px 24px; border-top:1px solid var(--border); margin-top:auto; }
  .admin-sidebar-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.32);
    backdrop-filter: blur(3px);
    z-index: 90;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.25s ease;
  }
  .admin-sidebar-overlay.open { opacity: 1; pointer-events: auto; }
  @media(max-width:768px){
    .admin-topbar { padding-left: 52px; }
    .admin-sidebar-footer { position: static !important; width: auto !important; }
  }
  @media(max-width:560px){
    .admin-topbar { flex-direction: column; align-items: flex-start; gap: 8px; padding-left: 52px; }
  }
</style>
</head>
<body>

<button class="mobile-toggle" id="sidebar-toggle" type="button" aria-label="Toggle menu" aria-expanded="false">Menu</button>
<div class="admin-sidebar-overlay" id="admin-sidebar-overlay"></div>

<div class="admin-layout">
  <!-- Sidebar -->
  <aside class="admin-sidebar">
    <div class="sidebar-brand">
      <div style="font-size:1.4rem;font-weight:900;" class="gradient-text">🎮 FabGames</div>
      <div style="font-size:0.75rem;color:var(--text-muted);margin-top:4px;">Admin Panel</div>
    </div>
    <nav class="sidebar-nav">
      <?= sidebarLink('/ZikGames/admin/dashboard.php','📊','Dashboard','dashboard') ?>
      <?= sidebarLink('/ZikGames/admin/users.php','👥','Users','users') ?>
      <?= sidebarLink('/ZikGames/admin/subscriptions.php','💳','Subscriptions','subscriptions') ?>
      <?= sidebarLink('/ZikGames/admin/reports.php','📈','Reports','reports') ?>
      <?= sidebarLink('/ZikGames/admin/notifications.php','🔔','Notifications','notifications') ?>
      <div class="divider" style="margin:12px 24px;"></div>
      <?= sidebarLink('/ZikGames/index.php','🌐','View Site','') ?>
      <?= sidebarLink('/ZikGames/admin/logout.php','🚪','Logout','') ?>
    </nav>
    <div class="admin-sidebar-footer">
      <div style="font-size:0.8rem;color:var(--text-muted);">Logged in as</div>
      <div style="font-weight:700;font-size:0.9rem;"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></div>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="admin-content">
    <div class="admin-topbar">
      <div>
        <h1>Dashboard</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">Welcome back, <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?>! Here's what's happening.</p>
      </div>
      <div style="font-size:0.875rem;color:var(--text-muted);"><?= date('D, M j Y') ?></div>
    </div>

    <!-- Stat Cards -->
    <div class="admin-stats">
      <div class="admin-stat-card purple">
        <div class="admin-stat-icon">👥</div>
        <div class="admin-stat-value" style="color:var(--primary-light);"><?= number_format($totalUsers) ?></div>
        <div class="admin-stat-label">Total Users</div>
      </div>
      <div class="admin-stat-card green">
        <div class="admin-stat-icon">✅</div>
        <div class="admin-stat-value" style="color:#34D399;"><?= number_format($activeToday) ?></div>
        <div class="admin-stat-label">Active Today</div>
      </div>
      <div class="admin-stat-card orange">
        <div class="admin-stat-icon">🎮</div>
        <div class="admin-stat-value" style="color:#FCD34D;"><?= number_format($totalGames) ?></div>
        <div class="admin-stat-label">Total Games Played</div>
      </div>
      <div class="admin-stat-card red">
        <div class="admin-stat-icon">🏆</div>
        <div class="admin-stat-value" style="color:#FCA5A5;"><?= number_format($highestScore) ?></div>
        <div class="admin-stat-label">Highest Score</div>
      </div>
    </div>

    <!-- Quick Stats Row -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:32px;">
      <div class="card" style="display:flex;align-items:center;gap:14px;">
        <div style="font-size:2rem;">🆕</div>
        <div><div style="font-size:1.3rem;font-weight:900;color:var(--neon-green);"><?= $newToday ?></div><div style="font-size:0.75rem;color:var(--text-muted);">New Users Today</div></div>
      </div>
      <div class="card" style="display:flex;align-items:center;gap:14px;">
        <div style="font-size:2rem;">🎯</div>
        <div><div style="font-size:1.3rem;font-weight:900;color:var(--neon-blue);"><?= $todayGames ?></div><div style="font-size:0.75rem;color:var(--text-muted);">Games Today</div></div>
      </div>
      <div class="card" style="display:flex;align-items:center;gap:14px;">
        <div style="font-size:2rem;">🔥</div>
        <div><div style="font-size:1rem;font-weight:900;color:var(--accent);"><?= htmlspecialchars($mostPlayed['game_name'] ?? 'N/A') ?></div><div style="font-size:0.75rem;color:var(--text-muted);">Most Played Game</div></div>
      </div>
    </div>

    <!-- Charts + Activity -->
    <?php if ($settingsSaved): ?>
    <div class="alert alert-success">Tic Tac Toe limits updated successfully.</div>
    <?php elseif ($settingsError !== ''): ?>
    <div class="alert alert-error"><?= htmlspecialchars($settingsError) ?></div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:24px;">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
        <div>
          <h3>Tic Tac Toe Limits</h3>
          <p style="color:var(--text-muted);font-size:0.85rem;margin-top:6px;">Change how many draws or losses end a ranked run.</p>
        </div>
        <a href="/ZikGames/admin/subscriptions.php" class="btn btn-outline btn-sm">Open Subscriptions</a>
      </div>
      <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;align-items:end;">
        <input type="hidden" name="action" value="save_ttt_limits">
        <div class="form-group" style="margin-bottom:0;">
          <label for="ttt_draw_limit">Draw Limit</label>
          <input class="form-control" type="number" min="1" max="50" id="ttt_draw_limit" name="ttt_draw_limit" value="<?= $tttDrawLimit ?>">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label for="ttt_loss_limit">Loss Limit</label>
          <input class="form-control" type="number" min="1" max="20" id="ttt_loss_limit" name="ttt_loss_limit" value="<?= $tttLossLimit ?>">
        </div>
        <div>
          <button class="btn btn-primary" type="submit">Save Limits</button>
        </div>
      </form>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:32px;">
      <div class="card">
        <h3 style="margin-bottom:20px;">📈 Daily Game Activity</h3>
        <div class="chart-container">
          <canvas id="activityChart"></canvas>
        </div>
      </div>
      <div class="card">
        <h3 style="margin-bottom:16px;">⚡ Recent Activity</h3>
        <?php if(empty($recentActivity)): ?>
        <div style="text-align:center;padding:30px;color:var(--text-muted);">No recent activity</div>
        <?php else: ?>
        <?php foreach($recentActivity as $log): ?>
        <div class="activity-item">
          <div class="activity-icon"><?= $log['action']==='login'?'🔑':($log['action']==='game_played'?'🎮':'📝') ?></div>
          <div style="flex:1;">
            <div style="font-size:0.85rem;font-weight:600;"><?= htmlspecialchars(substr($log['phone'],0,4))?>****<?=htmlspecialchars(substr($log['phone'],-3))?></div>
            <div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($log['details']) ?></div>
          </div>
          <div style="font-size:0.7rem;color:var(--text-dim);"><?= date('H:i',strtotime($log['created_at'])) ?></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Game Play Distribution -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
      <div class="card">
        <h3 style="margin-bottom:20px;">🎮 Game Distribution</h3>
        <div style="height:220px;">
          <canvas id="gameDistChart"></canvas>
        </div>
      </div>
      <div class="card">
        <h3 style="margin-bottom:16px;">🏆 Top Scores</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Phone</th><th>Game</th><th>Score</th></tr></thead>
            <tbody>
            <?php
              $topScores=$db->query("SELECT u.phone,gs.game_name,gs.score FROM game_scores gs JOIN users u ON gs.user_id=u.id ORDER BY gs.score DESC LIMIT 6")->fetchAll();
              foreach($topScores as $r):
            ?>
            <tr>
              <td><?=htmlspecialchars(substr($r['phone'],0,4))?>****</td>
              <td><?=htmlspecialchars($r['game_name'])?></td>
              <td><strong style="color:var(--accent);"><?=number_format($r['score'])?></strong></td>
            </tr>
            <?php endforeach; if(empty($topScores)): ?>
            <tr><td colspan="3" style="text-align:center;color:var(--text-muted);">No scores yet</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
const sidebar = document.querySelector('.admin-sidebar');
const sidebarToggle = document.getElementById('sidebar-toggle');
const sidebarOverlay = document.getElementById('admin-sidebar-overlay');

function closeAdminSidebar() {
  if (!sidebar || !sidebarOverlay || !sidebarToggle) return;
  sidebar.classList.remove('open');
  sidebarOverlay.classList.remove('open');
  document.body.classList.remove('admin-nav-open');
  sidebarToggle.setAttribute('aria-expanded', 'false');
}

function toggleAdminSidebar() {
  if (!sidebar || !sidebarOverlay || !sidebarToggle) return;
  const willOpen = !sidebar.classList.contains('open');
  sidebar.classList.toggle('open', willOpen);
  sidebarOverlay.classList.toggle('open', willOpen);
  document.body.classList.toggle('admin-nav-open', willOpen);
  sidebarToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
}

if (sidebarToggle) {
  sidebarToggle.addEventListener('click', toggleAdminSidebar);
}
if (sidebarOverlay) {
  sidebarOverlay.addEventListener('click', closeAdminSidebar);
}
window.addEventListener('resize', () => {
  if (window.innerWidth > 768) {
    closeAdminSidebar();
  }
});
document.querySelectorAll('.sidebar-nav a').forEach((link) => {
  link.addEventListener('click', () => {
    if (window.innerWidth <= 768) {
      closeAdminSidebar();
    }
  });
});

// Activity Chart
const actCtx=document.getElementById('activityChart').getContext('2d');
new Chart(actCtx,{
  type:'bar',
  data:{
    labels:[<?= implode(',',$chartLabels) ?>],
    datasets:[{
      label:'Games Played',
      data:[<?= implode(',',$chartValues) ?>],
      backgroundColor:'rgba(124,58,237,0.5)',
      borderColor:'#7C3AED',
      borderWidth:2,
      borderRadius:8,
    }]
  },
  options:{
    responsive:true,maintainAspectRatio:false,
    plugins:{legend:{display:false}},
    scales:{
      x:{grid:{color:'rgba(255,255,255,0.05)'},ticks:{color:'#9B8FC4'}},
      y:{grid:{color:'rgba(255,255,255,0.05)'},ticks:{color:'#9B8FC4'},beginAtZero:true}
    }
  }
});

// Game Distribution Chart
<?php
$gameDist=$db->query("SELECT game_name,COUNT(*) as cnt FROM game_scores GROUP BY game_name")->fetchAll();
$gdLabels=json_encode(array_column($gameDist,'game_name'));
$gdValues=json_encode(array_column($gameDist,'cnt'));
?>
const gdCtx=document.getElementById('gameDistChart').getContext('2d');
new Chart(gdCtx,{
  type:'doughnut',
  data:{
    labels:<?=$gdLabels?>,
    datasets:[{data:<?=$gdValues?>,backgroundColor:['#EF4444','#7C3AED','#10B981','#00D4FF','#F59E0B'],borderWidth:0}]
  },
  options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{labels:{color:'#F0EEFF',font:{size:11}}}}}
});
</script>
</body>
</html>

