<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/subscriptions.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /ZikGames/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (isset($_GET['error']) && $_GET['error'] === 'disabled') {
        $error = 'Your account has been disabled by the administrator.';
    } elseif (isset($_GET['error']) && $_GET['error'] === 'expired') {
        $error = 'Your subscription has expired.';
    } elseif (isset($_GET['error']) && $_GET['error'] === 'inactive') {
        $error = 'You need an active subscription before you can log in.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $phone = preg_replace('/\s+/', '', $phone);

    if (!preg_match('/^0[789][01]\d{8}$/', $phone)) {
        $error = 'Please enter a valid Nigerian phone number (e.g. 08012345678).';
    } else {
        $db = getDB();
        ensureSubscriptionSchema($db);
        $subscription = getPhoneSubscriptionStatus($phone);

        if (!empty($subscription['active'])) {
            $stmt = $db->prepare('SELECT id, status FROM users WHERE phone = ? LIMIT 1');
            $stmt->execute([$phone]);
            $user = $stmt->fetch();

            if ($user) {
                if (($user['status'] ?? 'active') === 'disabled') {
                    $error = 'Your account has been disabled by the administrator.';
                } else {
                    $db->prepare('UPDATE users SET last_login = NOW(), updated_at = NOW() WHERE id = ?')->execute([$user['id']]);
                    $userId = (int) $user['id'];
                }
            } else {
                $db->prepare(
                    'INSERT INTO users (
                        phone, status, last_login, total_games_played, created_at, updated_at,
                        subscription_active, subscription_start_at, subscription_end_at,
                        subscription_updated_at
                    ) VALUES (?, ?, NOW(), 0, NOW(), NOW(), 1, ?, ?, NOW())'
                )->execute([$phone, 'active', $subscription['start_at'], $subscription['end_at']]);
                $userId = (int) $db->lastInsertId();
            }

            if (empty($error)) {
                $db->prepare('INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())')
                    ->execute([$userId, 'login', 'User logged in']);

                $_SESSION['user_id'] = $userId;
                $_SESSION['phone'] = $phone;
                header('Location: /ZikGames/dashboard.php');
                exit;
            }
        } else {
            $error = $subscription['message'] ?? 'You need an active subscription before playing.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - ZikGames Nigeria</title>
<meta name="description" content="Login to ZikGames with your Nigerian phone number and start playing instantly.">
<link rel="stylesheet" href="/ZikGames/assets/css/style.css">
<style>
  body { display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px; }
  .login-wrap { width:100%; max-width:440px; }
  .login-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 40px 32px;
    position: relative;
    overflow: hidden;
  }
  .login-card::before {
    content:'';
    position:absolute;
    top:-60px; left:50%;
    transform:translateX(-50%);
    width:200px; height:200px;
    background:radial-gradient(circle, rgba(29,78,216,0.18) 0%, transparent 70%);
    border-radius:50%;
    pointer-events:none;
  }
  .phone-input-wrap { position:relative; }
  .phone-prefix {
    position:absolute;
    left:16px; top:50%;
    transform:translateY(-50%);
    font-weight:700;
    color:var(--primary);
    font-size:1rem;
    pointer-events:none;
  }
  .phone-field { padding-left:70px !important; font-size:1.2rem !important; letter-spacing:0.08em; }
  .keypad { display:grid; grid-template-columns: repeat(3,1fr); gap:10px; margin-top:16px; }
  .key {
    padding:16px;
    background:var(--bg-card2);
    border:1px solid var(--border);
    border-radius:18px;
    font-size:1.2rem;
    font-weight:700;
    color:var(--text);
    cursor:pointer;
    transition:var(--transition);
    text-align:center;
    user-select:none;
  }
  .key:hover { background:var(--primary); border-color:var(--primary); color:#fff; }
  .key:active { transform:scale(0.96); }
  .key-clear { background:rgba(220,38,38,0.08); border-color:rgba(220,38,38,0.2); color:#b91c1c; }
  .key-del   { background:rgba(29,78,216,0.08); border-color:rgba(29,78,216,0.2); color:#1d4ed8; }
  .back-link { display:flex; align-items:center; gap:8px; font-size:0.875rem; color:var(--text-muted); margin-bottom:24px; }
  .back-link:hover { color:var(--text); }
  .loading-spinner { width:24px; height:24px; border:3px solid rgba(255,255,255,0.3); border-top-color:#fff; border-radius:50%; animation:spin 0.8s linear infinite; }
  @keyframes spin { to { transform:rotate(360deg); } }
  .subscribe-hint {
    background:rgba(29,78,216,0.06);
    border:1px solid rgba(29,78,216,0.12);
    border-radius:var(--radius-sm);
    padding:16px;
    text-align:center;
    margin-top:20px;
  }
  .subscribe-hint p { font-size:0.875rem; color:var(--text-muted); line-height:1.6; }
</style>
</head>
<body>
<div class="login-wrap">
  <a href="/ZikGames/index.php" class="back-link" id="back-home">
    <span>&larr;</span> Back to Home
  </a>
  <div class="login-card">
    <div style="text-align:center; margin-bottom:32px;">
      <div style="font-size:3.5rem; margin-bottom:12px;">ZG</div>
      <h1 style="font-size:1.8rem; margin-bottom:8px;">Welcome to <span class="gradient-text">ZikGames</span></h1>
      <p style="color:var(--text-muted); font-size:0.9rem;">Enter your phone number to verify your subscription and start playing.</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error" id="login-error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="" id="login-form" novalidate>
      <div class="form-group">
        <label for="phone-input">Phone Number</label>
        <div class="phone-input-wrap">
          <span class="phone-prefix">NG</span>
          <input type="tel"
                 id="phone-input"
                 name="phone"
                 class="form-control phone-field"
                 placeholder="08012345678"
                 maxlength="11"
                 readonly
                 value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                 autocomplete="tel"
                 required>
        </div>
      </div>

      <div class="keypad" id="keypad">
        <?php foreach (['1','2','3','4','5','6','7','8','9','CLR','0','DEL'] as $k): ?>
        <div class="key <?= $k === 'CLR' ? 'key-clear' : ($k === 'DEL' ? 'key-del' : '') ?>" data-key="<?= $k ?>">
          <?= $k ?>
        </div>
        <?php endforeach; ?>
      </div>

      <button type="submit" class="btn btn-primary btn-block mt-16" id="login-submit-btn" style="margin-top:20px;">
        <span id="btn-text">Verify and Play</span>
        <span id="btn-spinner" class="loading-spinner hidden"></span>
      </button>
    </form>

    <div class="subscribe-hint">
      <p><strong>Not subscribed yet?</strong><br>Use your shortcode or keyword flow first. If your subscription has ended, renew it before logging in again.</p>
    </div>
  </div>

  <p style="text-align:center; color:var(--text-dim); font-size:0.8rem; margin-top:20px;">
    Your number is only used for access and leaderboard identity.
  </p>
</div>

<script>
const phoneInput = document.getElementById('phone-input');
const form = document.getElementById('login-form');
const submitBtn = document.getElementById('login-submit-btn');
const btnText = document.getElementById('btn-text');
const btnSpinner = document.getElementById('btn-spinner');

document.querySelectorAll('.key').forEach((key) => {
  key.addEventListener('click', () => {
    const k = key.dataset.key;
    if (k === 'CLR') {
      phoneInput.value = '';
    } else if (k === 'DEL') {
      phoneInput.value = phoneInput.value.slice(0, -1);
    } else if (phoneInput.value.length < 11) {
      phoneInput.value += k;
    }
    if (navigator.vibrate) navigator.vibrate(15);
  });
});

form.addEventListener('submit', (e) => {
  const phone = phoneInput.value.trim();
  if (!/^0[789][01]\d{8}$/.test(phone)) {
    e.preventDefault();
    alert('Please enter a valid Nigerian phone number (e.g. 08012345678)');
    return;
  }
  btnText.classList.add('hidden');
  btnSpinner.classList.remove('hidden');
  submitBtn.disabled = true;
});

phoneInput.removeAttribute('readonly');
phoneInput.addEventListener('input', (e) => {
  e.target.value = e.target.value.replace(/\D/g, '').slice(0, 11);
});
</script>
</body>
</html>

