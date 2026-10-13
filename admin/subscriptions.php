<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
requireAdminLogin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/subscriptions.php';

$db = getDB();
ensureSubscriptionSchema($db);
$csrfToken = generateCsrfToken();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_keyword_rate') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('CSRF error');
    }

    $keywordName = trim(strval($_POST['keyword_name'] ?? ''));
    $planName = trim(strval($_POST['plan_name'] ?? ''));
    $shortcode = trim(strval($_POST['shortcode'] ?? ''));
    $amount = round(floatval($_POST['amount'] ?? 0), 2);

    if ($keywordName === '') {
        $error = 'Keyword name is required.';
    } elseif ($amount < 0) {
        $error = 'Amount cannot be negative.';
    } else {
        setKeywordAmount($keywordName, $amount, $shortcode, $planName);
        $message = 'Plan pricing saved successfully.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_subscription') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('CSRF error');
    }

    $phone = preg_replace('/\s+/', '', trim(strval($_POST['phone'] ?? '')));
    $planName = trim(strval($_POST['plan_name'] ?? ''));
    $shortcode = trim(strval($_POST['shortcode'] ?? ''));
    $keyword = trim(strval($_POST['keyword'] ?? ''));
    $active = intval($_POST['subscription_active'] ?? 0) === 1 ? 1 : 0;
    $startAt = normalizeSubscriptionDate($_POST['subscription_start_at'] ?? '');
    $endAt = normalizeSubscriptionDate($_POST['subscription_end_at'] ?? '');

    if (!preg_match('/^0[789][01]\d{8}$/', $phone)) {
        $error = 'Enter a valid Nigerian phone number.';
    } elseif ($startAt === null || $endAt === null) {
        $error = 'Start date and end date are required.';
    } elseif (strtotime($endAt) <= strtotime($startAt)) {
        $error = 'End date must be after start date.';
    } else {
        upsertUserSubscription($phone, $active, $startAt, $endAt, $shortcode, $keyword, $planName);
        $message = 'Subscription updated successfully.';
    }
}

$search = trim(strval($_GET['q'] ?? ''));
$whereSql = '';
$params = [];

if ($search !== '') {
    $whereSql = 'WHERE phone LIKE ?';
    $params[] = '%' . $search . '%';
}

$stmt = $db->prepare("
    SELECT id, phone, status, subscription_active, subscription_plan, subscription_start_at, subscription_end_at,
           subscription_shortcode, subscription_keyword, subscription_updated_at
    FROM users
    $whereSql
    ORDER BY updated_at DESC, created_at DESC
    LIMIT 100
");
$stmt->execute($params);
$users = $stmt->fetchAll();
$keywordRates = getKeywordRates();

function navLink(string $href, string $icon, string $label, string $match): string {
    $active = strpos($_SERVER['PHP_SELF'], $match) !== false ? 'active' : '';
    return "<a href=\"$href\" class=\"$active\"><span>$icon</span> $label</a>";
}

function subscriptionBadge(array $user): array {
    $status = computeSubscriptionState($user);
    if ($status['active']) {
        return ['class' => 'badge-green', 'label' => 'Active'];
    }
    if ($status['expired']) {
        return ['class' => 'badge-red', 'label' => 'Expired'];
    }
    if ($status['pending']) {
        return ['class' => 'badge-orange', 'label' => 'Pending'];
    }
    return ['class' => 'badge-purple', 'label' => 'Inactive'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Subscriptions - ZikGames Admin</title>
<link rel="stylesheet" href="/ZikGames/assets/css/style.css">
</head>
<body class="admin-page">
<button class="mobile-toggle" id="sidebar-toggle" type="button" aria-label="Toggle menu" aria-expanded="false">Menu</button>
<div class="admin-sidebar-overlay" id="admin-sidebar-overlay"></div>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="sidebar-brand"><div style="font-size:1.4rem;font-weight:900;" class="gradient-text">ZikGames</div><div style="font-size:0.75rem;color:var(--text-muted);">Admin Panel</div></div>
    <nav class="sidebar-nav">
      <?= navLink('/ZikGames/admin/dashboard.php', '??', 'Dashboard', 'dashboard') ?>
      <?= navLink('/ZikGames/admin/users.php', '??', 'Users', 'users') ?>
      <?= navLink('/ZikGames/admin/subscriptions.php', '??', 'Subscriptions', 'subscriptions') ?>
      <?= navLink('/ZikGames/admin/reports.php', '??', 'Reports', 'reports') ?>
      <?= navLink('/ZikGames/admin/notifications.php', '??', 'Notifications', 'notifications') ?>
      <div class="divider" style="margin:12px 24px;"></div>
      <?= navLink('/ZikGames/index.php', '??', 'View Site', '') ?>
      <?= navLink('/ZikGames/admin/logout.php', '??', 'Logout', 'logout') ?>
    </nav>
  </aside>

  <main class="admin-content">
    <div class="admin-topbar">
      <div>
        <h1>Subscriptions</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">Manage access windows, plans, and pricing for your subscription flow.</p>
      </div>
    </div>

    <?php if ($message !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php elseif ($error !== ''): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:24px;">
      <h3 style="margin-bottom:12px;">Subscription Endpoint</h3>
      <p style="color:var(--text-muted);font-size:0.88rem;margin-bottom:14px;">POST updates here after a user sends your plan keyword and shortcode.</p>
      <div style="display:grid;gap:10px;">
        <div><strong>URL:</strong> <code>/ZikGames/api/subscription.php</code></div>
        <div><strong>Fields:</strong> <code>phone</code>, <code>plan</code>, <code>shortcode</code>, <code>keyword</code>, <code>active</code>, <code>start_date</code>, <code>end_date</code></div>
        <div><strong>Pricing:</strong> the report uses the saved price for the matching plan, shortcode, and keyword.</div>
      </div>
    </div>

    <div class="card" style="margin-bottom:24px;">
      <h3 style="margin-bottom:16px;">Plan Pricing</h3>
      <form method="POST" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:16px;align-items:end;margin-bottom:18px;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="save_keyword_rate">
        <div class="form-group" style="margin-bottom:0;">
          <label>Plan</label>
          <input type="text" name="plan_name" class="form-control" placeholder="Daily Plan">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label>Keyword</label>
          <input type="text" name="keyword_name" class="form-control" placeholder="PLAY" required>
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label>Shortcode</label>
          <input type="text" name="shortcode" class="form-control" placeholder="34567">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label>Amount</label>
          <input type="number" name="amount" class="form-control" min="0" step="0.01" placeholder="100.00" required>
        </div>
        <div>
          <button class="btn btn-primary" type="submit">Save Price</button>
        </div>
      </form>

      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Plan</th>
              <th>Keyword</th>
              <th>Shortcode</th>
              <th>Amount</th>
              <th>Updated</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($keywordRates)): ?>
            <tr><td colspan="5" style="text-align:center;padding:28px;color:var(--text-muted);">No plan pricing saved yet.</td></tr>
            <?php else: ?>
            <?php foreach ($keywordRates as $rate): ?>
            <tr>
              <td><strong><?= htmlspecialchars($rate['plan_name'] !== '' ? $rate['plan_name'] : 'Any') ?></strong></td>
              <td><strong><?= htmlspecialchars($rate['keyword_name']) ?></strong></td>
              <td><?= htmlspecialchars($rate['shortcode'] !== '' ? $rate['shortcode'] : 'Any') ?></td>
              <td>N<?= number_format((float) $rate['amount'], 2) ?></td>
              <td style="font-size:0.8rem;color:var(--text-muted);"><?= htmlspecialchars(date('M j, H:i', strtotime($rate['updated_at']))) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
      <input type="text" name="q" class="form-control" placeholder="Search by phone..." value="<?= htmlspecialchars($search) ?>" style="max-width:360px;">
      <button class="btn btn-primary" type="submit">Search</button>
      <?php if ($search !== ''): ?><a href="/ZikGames/admin/subscriptions.php" class="btn btn-outline">Clear</a><?php endif; ?>
    </form>

    <div class="card" style="margin-bottom:20px;">
      <h3 style="margin-bottom:16px;">Quick Add or Update</h3>
      <form method="POST" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;align-items:end;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="save_subscription">
        <div class="form-group" style="margin-bottom:0;">
          <label>Plan</label>
          <input type="text" name="plan_name" class="form-control" placeholder="Daily Plan">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label>Phone</label>
          <input type="text" name="phone" class="form-control" placeholder="08012345678" required>
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label>Active</label>
          <select name="subscription_active" class="form-control">
            <option value="1">1 - Active</option>
            <option value="0">0 - Inactive</option>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label>Start Date</label>
          <input type="datetime-local" name="subscription_start_at" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label>End Date</label>
          <input type="datetime-local" name="subscription_end_at" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime('+30 days')) ?>" required>
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label>Shortcode</label>
          <input type="text" name="shortcode" class="form-control" placeholder="34567">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label>Keyword</label>
          <input type="text" name="keyword" class="form-control" placeholder="PLAY">
        </div>
        <div>
          <button class="btn btn-primary" type="submit">Save Subscription</button>
        </div>
      </form>
    </div>

    <div class="table-wrapper card" style="padding:0;">
      <table>
        <thead>
          <tr>
            <th>Phone</th>
            <th>Subscription</th>
            <th>Plan</th>
            <th>Window</th>
            <th>Shortcode</th>
            <th>Keyword</th>
            <th>Updated</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($users)): ?>
          <tr><td colspan="7" style="text-align:center;padding:36px;color:var(--text-muted);">No matching users found.</td></tr>
          <?php else: ?>
          <?php foreach ($users as $user): ?>
          <?php $badge = subscriptionBadge($user); ?>
          <tr>
            <td><strong><?= htmlspecialchars($user['phone']) ?></strong></td>
            <td><span class="badge <?= $badge['class'] ?>"><?= htmlspecialchars($badge['label']) ?></span></td>
            <td><?= htmlspecialchars($user['subscription_plan'] ?? '-') ?></td>
            <td style="font-size:0.82rem;">
              <div><?= $user['subscription_start_at'] ? htmlspecialchars(date('M j, Y H:i', strtotime($user['subscription_start_at']))) : '-' ?></div>
              <div style="color:var(--text-muted);"><?= $user['subscription_end_at'] ? htmlspecialchars(date('M j, Y H:i', strtotime($user['subscription_end_at']))) : '-' ?></div>
            </td>
            <td><?= htmlspecialchars($user['subscription_shortcode'] ?? '-') ?></td>
            <td><?= htmlspecialchars($user['subscription_keyword'] ?? '-') ?></td>
            <td style="font-size:0.8rem;color:var(--text-muted);"><?= $user['subscription_updated_at'] ? htmlspecialchars(date('M j, H:i', strtotime($user['subscription_updated_at']))) : 'Never' ?></td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>
<script src="/ZikGames/assets/js/admin.js"></script>
</body>
</html>
