<?php
session_start();
require_once __DIR__ . '/config/auth.php';
requireUserLogin();
require_once __DIR__ . '/config/database.php';

$db    = getDB();
$userId = $_SESSION['user_id'];
$phone  = $_SESSION['phone'];

// Fetch user stats
$user = $db->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$userId]);
$user = $user->fetch();

// Top scores per game
$topScores = $db->prepare("
    SELECT game_name, MAX(score) as high_score
    FROM game_scores WHERE user_id = ?
    GROUP BY game_name
");
$topScores->execute([$userId]);
$scores = [];
foreach ($topScores->fetchAll() as $r) {
    $scores[$r['game_name']] = $r['high_score'];
}

// Overall high score
$overallHigh = max(array_values($scores) ?: [0]);

// Last played
$lastGame = $db->prepare("SELECT game_name, score, created_at FROM game_scores WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$lastGame->execute([$userId]);
$lastGame = $lastGame->fetch();

// Leaderboard (top 10 overall)
$leaderboard = $db->query("
    SELECT u.phone, MAX(gs.score) as best_score
    FROM game_scores gs
    JOIN users u ON gs.user_id = u.id
    GROUP BY gs.user_id
    ORDER BY best_score DESC
    LIMIT 10
")->fetchAll();

// My rank
$myRank = 1;
foreach ($leaderboard as $i => $row) {
    if ($row['phone'] === $phone) { $myRank = $i + 1; break; }
}

$games = [
    ['id'=>'tic-tac-toe',      'name'=>'Tic Tac Toe',      'emoji'=>'❌', 'color'=>'#EF4444', 'desc'=>'Beat the smart AI in standard 3x3 grid!'],
    ['id'=>'bubble-shooter',   'name'=>'Bubble Shooter',   'emoji'=>'🫧', 'color'=>'#7C3AED', 'desc'=>'Pop matching bubbles for big points!'],
    ['id'=>'fruit-slice',      'name'=>'Fruit Slice',      'emoji'=>'🍉', 'color'=>'#10B981', 'desc'=>'Slice fruits, avoid the bombs!'],
    ['id'=>'space-shooter',    'name'=>'Space Shooter',    'emoji'=>'🚀', 'color'=>'#00D4FF', 'desc'=>'Defend the galaxy against alien ships!'],
    ['id'=>'danfo-dodge',      'name'=>'Danfo Dodge',      'emoji'=>'🚌', 'color'=>'#F59E0B', 'desc'=>'Survive Lagos traffic!'],
];

$rankStmt = $db->prepare("\n    SELECT ranked.user_rank\n    FROM (\n        SELECT scores.user_id, @row_num := @row_num + 1 AS user_rank\n        FROM (\n            SELECT user_id, MAX(score) AS best_score\n            FROM game_scores\n            WHERE game_name = ?\n            GROUP BY user_id\n            ORDER BY best_score DESC\n        ) scores\n        CROSS JOIN (SELECT @row_num := 0) r\n    ) ranked\n    WHERE ranked.user_id = ?\n    LIMIT 1\n");

$gameRanks = [];
foreach ($games as $gameMeta) {
    $rankStmt->execute([$gameMeta['name'], $userId]);
    $rank = $rankStmt->fetchColumn();
    $gameRanks[$gameMeta['name']] = $rank ? intval($rank) : null;
}

function maskPhone($phone) {
    return substr($phone, 0, 4) . '****' . substr($phone, -3);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard – FabGames Nigeria</title>
<link rel="stylesheet" href="/ZikGames/assets/css/style.css">
<style>
  .welcome-card {
    background: linear-gradient(135deg, var(--primary-dark), #1E1A35);
    border: 1px solid var(--primary);
    border-radius: var(--radius-lg);
    padding: 28px;
    position: relative;
    overflow: hidden;
    margin-bottom: 24px;
  }
  .welcome-card::before {
    content: '🎮';
    position:absolute;
    right:-10px; top:-10px;
    font-size: 7rem;
    opacity: 0.08;
    line-height:1;
  }
  .welcome-card .rank-chip {
    display:inline-flex; align-items:center; gap:8px;
    background:rgba(245,158,11,0.15);
    border:1px solid rgba(245,158,11,0.3);
    border-radius:100px;
    padding:4px 14px;
    font-size:0.8rem;
    font-weight:700;
    color:#FCD34D;
    margin-bottom:12px;
  }
  .game-grid { display:grid; grid-template-columns: repeat(2,1fr); gap:14px; }
  @media(min-width:540px){ .game-grid { grid-template-columns: repeat(3,1fr); } }
  .game-tile {
    border-radius:var(--radius);
    padding:20px 14px;
    text-align:center;
    cursor:pointer;
    transition:var(--transition);
    position:relative;
    overflow:hidden;
    border:2px solid transparent;
    background:var(--bg-card);
  }
  .game-tile:hover { transform:translateY(-5px); border-color:var(--primary); }
  .game-tile:active { transform:scale(0.96); }
  .game-tile .tile-emoji { font-size:2.8rem; display:block; margin-bottom:10px; transition:transform 0.3s; }
  .game-tile:hover .tile-emoji { transform:scale(1.2) rotate(-8deg); }
  .game-tile .tile-name { font-size:0.85rem; font-weight:800; margin-bottom:4px; }
  .game-tile .tile-desc { font-size:0.72rem; color:var(--text-muted); line-height:1.4; }
  .game-tile .tile-score { font-size:0.8rem; font-weight:700; margin-top:8px; }
  .tile-actions {
    display:flex;
    flex-direction:column;
    gap:8px;
    margin-top:12px;
  }
  .tile-actions .btn {
    width:100%;
    justify-content:center;
    padding:10px 12px;
    font-size:0.76rem;
  }
  .tile-new-badge {
    position:absolute; top:8px; right:8px;
    background:var(--neon-pink);
    color:#fff;
    font-size:0.6rem; font-weight:800;
    padding:2px 8px;
    border-radius:100px;
  }
  .notif-banner {
    background: linear-gradient(135deg, rgba(0,212,255,0.1), rgba(124,58,237,0.1));
    border:1px solid rgba(0,212,255,0.2);
    border-radius:var(--radius-sm);
    padding:14px 18px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    margin-bottom:20px;
    cursor:pointer;
    transition:var(--transition);
  }
  .notif-banner:hover { border-color:var(--neon-blue); }
  .lb-game-filter { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
  .lb-filter-btn {
    padding:6px 16px;
    border-radius:100px;
    font-size:0.8rem;
    font-weight:700;
    border:1px solid var(--border);
    background:var(--bg-card2);
    color:var(--text-muted);
    cursor:pointer;
    transition:var(--transition);
  }
  .lb-filter-btn.active { background:var(--primary); border-color:var(--primary); color:#fff; }
  .install-popup {
    position: fixed;
    inset: 0;
    z-index: 1200;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15, 23, 42, 0.32);
    backdrop-filter: blur(8px);
  }
  .install-card {
    width: min(100%, 360px);
    background: rgba(255,255,255,0.98);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    padding: 22px 20px;
    text-align: center;
  }
  .install-card p {
    color: var(--text-muted);
    font-size: 0.9rem;
    margin: 10px 0 16px;
    line-height: 1.5;
  }
  .install-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }
</style>
</head>
<body>

<!-- Top Navbar -->
<nav class="navbar">
  <div class="navbar-brand">
    <div class="logo-icon">🎮</div>
    <span class="gradient-text">FabGames</span>
  </div>
  <div style="display:flex; align-items:center; gap:12px;">
    <div class="badge badge-green">● Active</div>
    <a href="/ZikGames/logout.php" class="btn btn-outline btn-sm" id="logout-btn">Logout</a>
  </div>
</nav>

<div class="page-wrapper">
  <div class="container" style="max-width:680px;">

    <!-- Welcome Card -->
    <div class="welcome-card">
      <div class="rank-chip">🏅 Rank #<?= $myRank ?> on Leaderboard</div>
      <h2 style="margin-bottom:6px;">Hey, <?= maskPhone($phone) ?>! 👋</h2>
      <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:20px;">
        <?= $lastGame ? "Last played: <strong style='color:var(--text)'>" . htmlspecialchars($lastGame['game_name']) . "</strong>" : "Welcome! Pick a game and start playing 🎮" ?>
      </p>
      <div class="stats-row">
        <div class="stat-box">
          <div class="stat-value"><?= number_format($user['total_games_played'] ?? 0) ?></div>
          <div class="stat-label">Games Played</div>
        </div>
        <div class="stat-box">
          <div class="stat-value"><?= number_format($overallHigh) ?></div>
          <div class="stat-label">Best Score</div>
        </div>
        <div class="stat-box">
          <div class="stat-value">#<?= $myRank ?></div>
          <div class="stat-label">Your Rank</div>
        </div>
      </div>
    </div>

    <!-- Notification Banner -->
    <div class="notif-banner" id="notif-banner" onclick="requestNotifPermission()">
      <div style="display:flex; align-items:center; gap:12px;">
        <span style="font-size:1.5rem;">🔔</span>
        <div>
          <div style="font-size:0.9rem; font-weight:700;">Enable Notifications</div>
          <div style="font-size:0.78rem; color:var(--text-muted);">Get alerts for new games & your rivals' scores</div>
        </div>
      </div>
      <span style="color:var(--neon-blue); font-weight:700; font-size:0.85rem; white-space:nowrap;">Allow →</span>
    </div>

    <!-- Games Section -->
    <div style="margin-bottom:24px;">
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
        <h3>🎯 Choose a Game</h3>
      </div>
      <div class="game-grid">
        <?php foreach($games as $g): ?>
        <div class="game-tile" onclick="location.href='/ZikGames/games/<?= $g['id'] ?>.php'" id="tile-<?= $g['id'] ?>">
          <span class="tile-emoji"><?= $g['emoji'] ?></span>
          <div class="tile-name"><?= $g['name'] ?></div>
          <div class="tile-desc"><?= $g['desc'] ?></div>
          <?php if(!empty($scores[$g['name']])): ?>
          <div class="tile-score" style="color:var(--accent);">🏆 Best: <?= number_format($scores[$g['name']]) ?></div>
          <?php else: ?>
          <div class="tile-score" style="color:var(--text-dim);">No score yet</div>
          <?php endif; ?>
          <div class="mini-leaderboard">
            <div class="mini-leaderboard-row">
              <span>Rank</span>
              <strong style="margin-left:auto;"><?= isset($gameRanks[$g['name']]) ? '#' . $gameRanks[$g['name']] : 'Unranked' ?></strong>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="divider"></div>

    <!-- Leaderboard Preview -->
    <div>
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
        <h3>🏆 Leaderboard</h3>
        <div class="badge badge-orange">Top 10</div>
      </div>

      <div class="lb-game-filter" id="lb-filter">
        <button class="lb-filter-btn active" data-game="all" onclick="filterLB(this, 'all')">All Games</button>
        <?php foreach($games as $g): ?>
        <button class="lb-filter-btn" data-game="<?= htmlspecialchars($g['name']) ?>" onclick="filterLB(this, '<?= htmlspecialchars($g['name']) ?>')">
          <?= $g['emoji'] ?> <?= $g['name'] ?>
        </button>
        <?php endforeach; ?>
      </div>

      <div id="leaderboard-list">
        <?php if (empty($leaderboard)): ?>
        <div style="text-align:center; padding:40px; color:var(--text-muted);">
          <div style="font-size:3rem; margin-bottom:12px;">🏆</div>
          <p>No scores yet. Be the first to set a record!</p>
        </div>
        <?php else: ?>
        <?php foreach($leaderboard as $i => $entry): ?>
        <div class="leaderboard-item" style="<?= $entry['phone'] === $phone ? 'background:rgba(124,58,237,0.15); border:1px solid rgba(124,58,237,0.3);' : '' ?>">
          <div class="rank-badge rank-<?= $i < 3 ? ($i+1) : 'other' ?>"><?= $i+1 ?></div>
          <div style="flex:1;">
            <div style="font-weight:700; font-size:0.9rem;"><?= maskPhone($entry['phone']) ?> <?= $entry['phone'] === $phone ? '(You)' : '' ?></div>
            <div style="font-size:0.75rem; color:var(--text-muted);">Personal best</div>
          </div>
          <div style="font-size:1rem; font-weight:900; color:var(--accent);"><?= number_format($entry['best_score']) ?></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<!-- Bottom Navigation -->
<nav class="bottom-nav">
  <a href="/ZikGames/dashboard.php" class="active" id="bnav-home">
    <span class="nav-icon">🏠</span>Home
  </a>
  <a href="#" onclick="scrollToGames()" id="bnav-games">
    <span class="nav-icon">🎮</span>Games
  </a>
  <a href="#" onclick="showLeaderboard()" id="bnav-leaderboard">
    <span class="nav-icon">🏆</span>Rank
  </a>
  <a href="/ZikGames/logout.php" id="bnav-logout">
    <span class="nav-icon">🚪</span>Logout
  </a>
</nav>

<div class="toast-container" id="toast-container"></div>
<script src="/ZikGames/assets/js/common.js"></script>
<script src="/ZikGames/assets/js/dashboard-enhancements.js"></script>
<script>
async function requestNotifPermission() {
  const banner = document.getElementById('notif-banner');
  if (!('Notification' in window)) {
    ZikGames.showToast('Notifications not supported on this device', 'error');
    return;
  }
  if (window.Notification && Notification.permission === 'granted') {
    ZikGames.showToast('Notifications already enabled!', 'info');
    return;
  }
  const perm = await Notification.requestPermission();
  if (perm === 'granted') {
    if (banner) {
      banner.style.display = 'none';
    }
    ZikGames.showToast('Notifications enabled! You are all set.', 'success');
    registerPushSub();
  } else {
    ZikGames.showToast('Notification permission denied', 'error');
  }
}

async function registerPushSub() {
  if (!('serviceWorker' in navigator)) return;
  try {
    await navigator.serviceWorker.ready;
  } catch (e) {
    console.warn('Push sub failed:', e);
  }
}

function filterLB(btn, game) {
  document.querySelectorAll('.lb-filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const list = document.getElementById('leaderboard-list');
  list.innerHTML = '<div class="skeleton" style="height:60px;margin-bottom:8px;"></div>'.repeat(5);
  ZikGames.fetchLeaderboard(game)
    .then(payload => {
      const data = payload.top || [];
      if (!data.length) {
        list.innerHTML = '<div style="text-align:center;padding:30px;color:var(--text-muted);">No scores for this game yet!</div>';
        return;
      }
      list.innerHTML = data.map((entry, i) => `
        <div class="leaderboard-item ${entry.is_current_user ? 'leaderboard-highlight' : ''}">
          <div class="rank-badge rank-${i < 3 ? (i + 1) : 'other'}">${entry.rank}</div>
          <div style="flex:1;">
            <div style="font-weight:700;font-size:0.9rem;">${entry.phone}${entry.is_current_user ? ' (You)' : ''}</div>
            <div style="font-size:0.75rem;color:var(--text-muted);">Top level ${entry.top_level} � ${entry.game_name || 'Multiple Games'}</div>
          </div>
          <div style="font-size:1rem;font-weight:900;color:var(--accent);">${parseInt(entry.best_score, 10).toLocaleString()}</div>
        </div>
      `).join('');
    })
    .catch(() => {
      list.innerHTML = '<div style="text-align:center;padding:30px;color:var(--text-muted);">Leaderboard unavailable right now.</div>';
    });
}

function scrollToGames() { document.querySelector('.game-grid').scrollIntoView({behavior:'smooth'}); }
function showLeaderboard() { document.getElementById('leaderboard-list').scrollIntoView({behavior:'smooth'}); }
</script>
</body>
</html>

