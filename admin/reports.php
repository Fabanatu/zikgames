<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
requireAdminLogin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/subscriptions.php';

$db = getDB();
ensureSubscriptionSchema($db);

$fromDate = trim(strval($_GET['from'] ?? date('Y-m-d', strtotime('-29 days'))));
$toDate = trim(strval($_GET['to'] ?? date('Y-m-d')));

$fromDateObj = DateTime::createFromFormat('Y-m-d', $fromDate) ?: new DateTime('-29 days');
$toDateObj = DateTime::createFromFormat('Y-m-d', $toDate) ?: new DateTime();

if ($fromDateObj > $toDateObj) {
    [$fromDateObj, $toDateObj] = [$toDateObj, $fromDateObj];
}

$fromDate = $fromDateObj->format('Y-m-d');
$toDate = $toDateObj->format('Y-m-d');
$fromDateTime = $fromDate . ' 00:00:00';
$toDateTime = $toDate . ' 23:59:59';

$summaryStmt = $db->prepare("
    SELECT
        COUNT(*) AS games_played,
        COUNT(DISTINCT user_id) AS players,
        COALESCE(SUM(score), 0) AS total_score,
        COALESCE(MAX(score), 0) AS best_score
    FROM game_scores
    WHERE created_at BETWEEN ? AND ?
");
$summaryStmt->execute([$fromDateTime, $toDateTime]);
$gameSummary = $summaryStmt->fetch() ?: ['games_played' => 0, 'players' => 0, 'total_score' => 0, 'best_score' => 0];

$subscriptionStmt = $db->prepare("
    SELECT
        COUNT(*) AS successful_subscriptions,
        COUNT(DISTINCT phone) AS subscribed_users,
        COALESCE(SUM(amount), 0) AS total_revenue
    FROM subscription_transactions
    WHERE active = 1
      AND created_at BETWEEN ? AND ?
");
$subscriptionStmt->execute([$fromDateTime, $toDateTime]);
$subscriptionSummary = $subscriptionStmt->fetch() ?: ['successful_subscriptions' => 0, 'subscribed_users' => 0, 'total_revenue' => 0];

$gameStatsStmt = $db->prepare("
    SELECT
        game_name,
        COUNT(*) AS plays,
        MAX(score) AS best,
        AVG(score) AS avg_score,
        COUNT(DISTINCT user_id) AS unique_players
    FROM game_scores
    WHERE created_at BETWEEN ? AND ?
    GROUP BY game_name
    ORDER BY plays DESC, game_name ASC
");
$gameStatsStmt->execute([$fromDateTime, $toDateTime]);
$gameStats = $gameStatsStmt->fetchAll();

$topPlayersStmt = $db->prepare("
    SELECT
        u.phone,
        SUM(gs.score) AS total_score,
        COUNT(*) AS games,
        MAX(gs.score) AS best
    FROM game_scores gs
    JOIN users u ON gs.user_id = u.id
    WHERE gs.created_at BETWEEN ? AND ?
    GROUP BY gs.user_id, u.phone
    ORDER BY total_score DESC
    LIMIT 10
");
$topPlayersStmt->execute([$fromDateTime, $toDateTime]);
$topPlayers = $topPlayersStmt->fetchAll();

$keywordRevenueStmt = $db->prepare("
    SELECT
        plan_name,
        keyword_name,
        shortcode,
        COUNT(*) AS successful_subscriptions,
        COUNT(DISTINCT phone) AS unique_subscribers,
        COALESCE(SUM(amount), 0) AS revenue
    FROM subscription_transactions
    WHERE active = 1
      AND created_at BETWEEN ? AND ?
    GROUP BY plan_name, keyword_name, shortcode
    ORDER BY revenue DESC, plan_name ASC, keyword_name ASC
");
$keywordRevenueStmt->execute([$fromDateTime, $toDateTime]);
$keywordRevenue = $keywordRevenueStmt->fetchAll();

$timelineStmt = $db->prepare("
    SELECT report_day, SUM(games_played) AS games_played, SUM(successful_subscriptions) AS successful_subscriptions
    FROM (
        SELECT DATE(created_at) AS report_day, COUNT(*) AS games_played, 0 AS successful_subscriptions
        FROM game_scores
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)

        UNION ALL

        SELECT DATE(created_at) AS report_day, 0 AS games_played, COUNT(*) AS successful_subscriptions
        FROM subscription_transactions
        WHERE active = 1
          AND created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
    ) t
    GROUP BY report_day
    ORDER BY report_day ASC
");
$timelineStmt->execute([$fromDateTime, $toDateTime, $fromDateTime, $toDateTime]);
$timelineRows = $timelineStmt->fetchAll();

$timelineMap = [];
foreach ($timelineRows as $row) {
    $timelineMap[$row['report_day']] = $row;
}

$labels = [];
$gamesSeries = [];
$subscriptionSeries = [];
$cursor = clone $fromDateObj;
while ($cursor <= $toDateObj) {
    $day = $cursor->format('Y-m-d');
    $labels[] = "'" . $cursor->format('M j') . "'";
    $gamesSeries[] = intval($timelineMap[$day]['games_played'] ?? 0);
    $subscriptionSeries[] = intval($timelineMap[$day]['successful_subscriptions'] ?? 0);
    $cursor->modify('+1 day');
}

if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="zikgames-report-' . $fromDate . '-to-' . $toDate . '.csv"');
    $out = fopen('php://output', 'w');

    fputcsv($out, ['Report Range', $fromDate, $toDate]);
    fputcsv($out, []);
    fputcsv($out, ['Summary']);
    fputcsv($out, ['Games Played', $gameSummary['games_played']]);
    fputcsv($out, ['Players', $gameSummary['players']]);
    fputcsv($out, ['Total Score', $gameSummary['total_score']]);
    fputcsv($out, ['Best Score', $gameSummary['best_score']]);
    fputcsv($out, ['Successful Subscriptions', $subscriptionSummary['successful_subscriptions']]);
    fputcsv($out, ['Subscribed Users', $subscriptionSummary['subscribed_users']]);
    fputcsv($out, ['Revenue', $subscriptionSummary['total_revenue']]);
    fputcsv($out, []);

    fputcsv($out, ['Game Breakdown']);
    fputcsv($out, ['Game', 'Plays', 'Players', 'Best Score', 'Average Score']);
    foreach ($gameStats as $row) {
        fputcsv($out, [$row['game_name'], $row['plays'], $row['unique_players'], $row['best'], round((float) $row['avg_score'], 2)]);
    }
    fputcsv($out, []);

    fputcsv($out, ['Keyword Revenue']);
    fputcsv($out, ['Plan', 'Keyword', 'Shortcode', 'Successful Subscriptions', 'Unique Subscribers', 'Revenue']);
    foreach ($keywordRevenue as $row) {
        fputcsv($out, [$row['plan_name'], $row['keyword_name'], $row['shortcode'], $row['successful_subscriptions'], $row['unique_subscribers'], $row['revenue']]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports - ZikGames Admin</title>
<link rel="stylesheet" href="/ZikGames/assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
      <a href="/ZikGames/admin/reports.php" class="active"><span>📈</span> Reports</a>
      <a href="/ZikGames/admin/notifications.php"><span>🔔</span> Notifications</a>
      <div class="divider" style="margin:12px 24px;"></div>
      <a href="/ZikGames/index.php"><span>🌐</span> View Site</a>
      <a href="/ZikGames/admin/logout.php"><span>🚪</span> Logout</a>
    </nav>
  </aside>

  <main class="admin-content">
    <div class="admin-topbar">
      <div>
        <h1>Reports</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">View game, subscription, and revenue reports for any date range.</p>
      </div>
      <a href="?from=<?= urlencode($fromDate) ?>&to=<?= urlencode($toDate) ?>&export=1" class="btn btn-accent btn-sm">Export CSV</a>
    </div>

    <div class="card" style="margin-bottom:24px;">
      <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;align-items:end;">
        <div class="form-group" style="margin-bottom:0;">
          <label for="report-from">From Date</label>
          <input class="form-control" id="report-from" type="date" name="from" value="<?= htmlspecialchars($fromDate) ?>">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label for="report-to">To Date</label>
          <input class="form-control" id="report-to" type="date" name="to" value="<?= htmlspecialchars($toDate) ?>">
        </div>
        <div>
          <button class="btn btn-primary" type="submit">Apply Range</button>
        </div>
      </form>
    </div>

    <div class="admin-stats">
      <div class="admin-stat-card purple">
        <div class="admin-stat-icon">🎮</div>
        <div class="admin-stat-value" style="color:var(--primary);"><?= number_format((int) $gameSummary['games_played']) ?></div>
        <div class="admin-stat-label">Games Played</div>
      </div>
      <div class="admin-stat-card green">
        <div class="admin-stat-icon">👥</div>
        <div class="admin-stat-value" style="color:var(--accent);"><?= number_format((int) $gameSummary['players']) ?></div>
        <div class="admin-stat-label">Players</div>
      </div>
      <div class="admin-stat-card orange">
        <div class="admin-stat-icon">✅</div>
        <div class="admin-stat-value" style="color:var(--neon-blue);"><?= number_format((int) $subscriptionSummary['successful_subscriptions']) ?></div>
        <div class="admin-stat-label">Successful Subscriptions</div>
      </div>
      <div class="admin-stat-card red">
        <div class="admin-stat-icon">₦</div>
        <div class="admin-stat-value" style="color:var(--primary-dark);">₦<?= number_format((float) $subscriptionSummary['total_revenue'], 2) ?></div>
        <div class="admin-stat-label">Revenue</div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1.2fr 0.8fr;gap:24px;margin-bottom:24px;">
      <div class="card">
        <h3 style="margin-bottom:18px;">Daily Activity in Range</h3>
        <div class="chart-container">
          <canvas id="rangeReportChart"></canvas>
        </div>
      </div>
      <div class="card">
        <h3 style="margin-bottom:18px;">Range Summary</h3>
        <div style="display:grid;gap:12px;">
          <div style="display:flex;justify-content:space-between;gap:12px;">
            <span style="color:var(--text-muted);">Total score in range</span>
            <strong><?= number_format((int) $gameSummary['total_score']) ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between;gap:12px;">
            <span style="color:var(--text-muted);">Best score in range</span>
            <strong><?= number_format((int) $gameSummary['best_score']) ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between;gap:12px;">
            <span style="color:var(--text-muted);">Subscribed users</span>
            <strong><?= number_format((int) $subscriptionSummary['subscribed_users']) ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between;gap:12px;">
            <span style="color:var(--text-muted);">Date window</span>
            <strong><?= htmlspecialchars(date('M j, Y', strtotime($fromDate))) ?> - <?= htmlspecialchars(date('M j, Y', strtotime($toDate))) ?></strong>
          </div>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">
      <div class="card">
        <h3 style="margin-bottom:16px;">Games Played by Title</h3>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr><th>Game</th><th>Plays</th><th>Players</th><th>Best</th><th>Avg</th></tr>
            </thead>
            <tbody>
              <?php if (empty($gameStats)): ?>
              <tr><td colspan="5" style="text-align:center;color:var(--text-muted);">No game activity in this range.</td></tr>
              <?php else: ?>
              <?php foreach ($gameStats as $row): ?>
              <tr>
                <td><strong><?= htmlspecialchars($row['game_name']) ?></strong></td>
                <td><?= number_format((int) $row['plays']) ?></td>
                <td><?= number_format((int) $row['unique_players']) ?></td>
                <td><?= number_format((int) $row['best']) ?></td>
                <td><?= number_format((float) $row['avg_score'], 0) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <h3 style="margin-bottom:16px;">Revenue by Plan</h3>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr><th>Plan</th><th>Keyword</th><th>Shortcode</th><th>Subs</th><th>Revenue</th></tr>
            </thead>
            <tbody>
              <?php if (empty($keywordRevenue)): ?>
              <tr><td colspan="5" style="text-align:center;color:var(--text-muted);">No successful subscriptions in this range.</td></tr>
              <?php else: ?>
              <?php foreach ($keywordRevenue as $row): ?>
              <tr>
                <td><strong><?= htmlspecialchars($row['plan_name'] !== '' ? $row['plan_name'] : 'Any') ?></strong></td>
                <td><strong><?= htmlspecialchars($row['keyword_name'] !== '' ? $row['keyword_name'] : 'N/A') ?></strong></td>
                <td><?= htmlspecialchars($row['shortcode'] !== '' ? $row['shortcode'] : 'Any') ?></td>
                <td><?= number_format((int) $row['successful_subscriptions']) ?></td>
                <td style="font-weight:700;color:var(--accent);">₦<?= number_format((float) $row['revenue'], 2) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="card">
      <h3 style="margin-bottom:16px;">Top Players in Range</h3>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr><th>Rank</th><th>Phone</th><th>Total Score</th><th>Games</th><th>Best Score</th></tr>
          </thead>
          <tbody>
            <?php if (empty($topPlayers)): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--text-muted);">No player results in this date range.</td></tr>
            <?php else: ?>
            <?php foreach ($topPlayers as $i => $player): ?>
            <tr>
              <td><div class="rank-badge rank-<?= $i < 3 ? ($i + 1) : 'other' ?>"><?= $i + 1 ?></div></td>
              <td><?= htmlspecialchars(substr($player['phone'], 0, 4)) ?>****<?= htmlspecialchars(substr($player['phone'], -3)) ?></td>
              <td style="font-weight:700;color:var(--accent);"><?= number_format((int) $player['total_score']) ?></td>
              <td><?= number_format((int) $player['games']) ?></td>
              <td><?= number_format((int) $player['best']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<script>
const reportCtx = document.getElementById('rangeReportChart').getContext('2d');
new Chart(reportCtx, {
  type: 'line',
  data: {
    labels: [<?= implode(',', $labels) ?>],
    datasets: [
      {
        label: 'Games Played',
        data: [<?= implode(',', $gamesSeries) ?>],
        borderColor: '#c1121f',
        backgroundColor: 'rgba(193, 18, 31, 0.12)',
        fill: true,
        tension: 0.35,
        borderWidth: 2
      },
      {
        label: 'Successful Subscriptions',
        data: [<?= implode(',', $subscriptionSeries) ?>],
        borderColor: '#2563eb',
        backgroundColor: 'rgba(37, 99, 235, 0.08)',
        fill: true,
        tension: 0.35,
        borderWidth: 2
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        labels: {
          color: '#5c6b85'
        }
      }
    },
    scales: {
      x: {
        ticks: { color: '#5c6b85', maxTicksLimit: 8 },
        grid: { color: 'rgba(29, 78, 216, 0.07)' }
      },
      y: {
        ticks: { color: '#5c6b85' },
        grid: { color: 'rgba(29, 78, 216, 0.07)' },
        beginAtZero: true
      }
    }
  }
});
</script>
<script src="/ZikGames/assets/js/admin.js"></script>
</body>
</html>
