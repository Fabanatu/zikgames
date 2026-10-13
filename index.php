<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: /ZikGames/dashboard.php');
    exit;
}
if (isset($_SESSION['admin_id'])) {
    header('Location: /ZikGames/admin/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FabGames – Nigeria's #1 Mobile Game Portal</title>
<meta name="description" content="FabGames is Nigeria's hottest mobile game portal. Play Tic Tac Toe, Bubble Shooter, Fruit Slice, Space Shooter and Danfo Dodge. Fun, fast and free!">
<link rel="stylesheet" href="/ZikGames/assets/css/style.css">
<link rel="manifest" href="/ZikGames/manifest.json">
<style>
  .particles { position: absolute; inset: 0; overflow: hidden; pointer-events: none; }
  .particle {
    position: absolute;
    width: 4px; height: 4px;
    border-radius: 50%;
    animation: particleFloat linear infinite;
    opacity: 0.6;
  }
  @keyframes particleFloat {
    0%   { transform: translateY(100vh) rotate(0deg); opacity: 0; }
    10%  { opacity: 0.6; }
    90%  { opacity: 0.6; }
    100% { transform: translateY(-20px) rotate(720deg); opacity: 0; }
  }
  .scroll-reveal { opacity: 0; transform: translateY(40px); transition: opacity 0.7s ease, transform 0.7s ease; }
  .scroll-reveal.visible { opacity: 1; transform: translateY(0); }
  .naija-flag { display: inline-flex; gap: 3px; align-items: center; }
  .flag-stripe { width: 8px; height: 14px; border-radius: 2px; }
  .flag-green { background: #008751; }
  .flag-white { background: #fff; }
  .steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
  .step { text-align: center; padding: 30px 20px; }
  .step-num {
    width: 56px; height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--neon-blue));
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; font-weight: 900;
    margin: 0 auto 16px;
    box-shadow: 0 0 30px rgba(124,58,237,0.5);
  }
  .step h3 { margin-bottom: 8px; }
  .step p   { font-size: 0.9rem; color: var(--text-muted); }
  .cta-section {
    text-align: center;
    padding: 80px 24px;
    background: radial-gradient(ellipse at center, rgba(124,58,237,0.15) 0%, transparent 70%);
    border-radius: var(--radius-lg);
    margin: 40px 0;
  }
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
  <div class="navbar-brand">
    <div class="logo-icon">🎮</div>
    <span class="gradient-text">FabGames</span>
  </div>
  <a href="/ZikGames/login.php" class="btn btn-primary btn-sm" id="nav-login-btn">🚀 Play Now</a>
</nav>

<!-- Hero Section -->
<section class="hero">
  <div class="hero-bg"></div>
  <div class="particles" id="particles"></div>
  <div class="hero-glow"></div>
  <div class="hero-content animate-fade-up">
    <div class="hero-badge">
      <div class="naija-flag">
        <div class="flag-stripe flag-green"></div>
        <div class="flag-stripe flag-white"></div>
        <div class="flag-stripe flag-green"></div>
      </div>
      Nigeria's #1 Mobile Game Portal
    </div>
    <h1>Play. Win. <span class="gradient-text">Shine!</span></h1>
    <p>Five addictive games waiting for you. Compete with your friends, climb the leaderboard, and show Lagos who's the real gamer. No dulling allowed!</p>
    <div class="hero-buttons">
      <a href="/ZikGames/login.php" class="btn btn-primary btn-lg" id="hero-play-btn">🎮 Start Playing Free</a>
      <a href="#games" class="btn btn-outline btn-lg" id="hero-games-btn">👀 See the Games</a>
    </div>
  </div>
</section>

<!-- Stats Strip -->
<section style="padding: 24px 0; background: var(--bg-card); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);">
  <div class="container">
    <div style="display:grid; grid-template-columns: repeat(4,1fr); gap: 16px; text-align: center;">
      <div>
        <div style="font-size:1.8rem; font-weight:900; color:var(--primary-light);">5</div>
        <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em;">Epic Games</div>
      </div>
      <div>
        <div style="font-size:1.8rem; font-weight:900; color:var(--neon-green);">100%</div>
        <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em;">Free to Play</div>
      </div>
      <div>
        <div style="font-size:1.8rem; font-weight:900; color:var(--accent);">🔥</div>
        <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em;">Daily Prizes</div>
      </div>
      <div>
        <div style="font-size:1.8rem; font-weight:900; color:var(--neon-pink);">24/7</div>
        <div style="font-size:0.75rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em;">Always Live</div>
      </div>
    </div>
  </div>
</section>

<!-- Games Section -->
<section class="section" id="games">
  <div class="container">
    <div class="section-title scroll-reveal">
      <div class="badge badge-purple" style="margin-bottom:12px;">🎯 Five Epic Games</div>
      <h2>Pick Your <span class="gradient-text">Favourite</span></h2>
      <p>From strategic tic-tac-toe to Nigerian traffic dodging — we've got something for every gamer</p>
    </div>
    <div class="game-preview-grid scroll-reveal">
      <div class="game-preview-card" onclick="location.href='/ZikGames/login.php'" id="game-card-tic-tac-toe">
        <div class="card-glow" style="background:var(--accent3);"></div>
        <span class="game-emoji">❌</span>
        <h3>Tic Tac Toe</h3>
        <p>Beat the smart AI in standard 3x3 grid! Test your logic!</p>
        <div class="badge badge-red" style="margin-top:12px;">🧠 Casual Fun</div>
      </div>
      <div class="game-preview-card" onclick="location.href='/ZikGames/login.php'" id="game-card-bubble">
        <div class="card-glow" style="background:var(--primary);"></div>
        <span class="game-emoji">🫧</span>
        <h3>Bubble Shooter</h3>
        <p>Match coloured bubbles. Pop them all for massive points!</p>
        <div class="badge badge-purple" style="margin-top:12px;">🧠 Brain Teaser</div>
      </div>
      <div class="game-preview-card" onclick="location.href='/ZikGames/login.php'" id="game-card-fruit">
        <div class="card-glow" style="background:var(--accent2);"></div>
        <span class="game-emoji">🍉</span>
        <h3>Fruit Slice</h3>
        <p>Slice flying fruits, dodge bombs! How many can you cut?</p>
        <div class="badge badge-green" style="margin-top:12px;">✂️ Quick Reflexes</div>
      </div>
      <div class="game-preview-card" onclick="location.href='/ZikGames/login.php'" id="game-card-space-shooter">
        <div class="card-glow" style="background:var(--neon-blue);"></div>
        <span class="game-emoji">🚀</span>
        <h3>Space Shooter</h3>
        <p>Defend the galaxy against alien ships! Blast your way to glory!</p>
        <div class="badge badge-orange" style="margin-top:12px;">💪 Action Packed</div>
      </div>
      <div class="game-preview-card" onclick="location.href='/ZikGames/login.php'" id="game-card-danfo">
        <div class="card-glow" style="background:var(--accent);"></div>
        <span class="game-emoji">🚌</span>
        <h3>Danfo Dodge</h3>
        <p>Nigeria's own! Dodge danfos, okadas and potholes on Lagos roads!</p>
        <div class="badge badge-orange" style="margin-top:12px;">🇳🇬 Made for Naija</div>
      </div>
    </div>
  </div>
</section>

<!-- How It Works -->
<section class="section" style="background: linear-gradient(180deg, transparent, rgba(124,58,237,0.05), transparent);">
  <div class="container">
    <div class="section-title scroll-reveal">
      <h2>How to <span class="gradient-text">Get Started</span></h2>
      <p>Three simple steps to gaming glory</p>
    </div>
    <div class="steps scroll-reveal">
      <div class="step">
        <div class="step-num">1</div>
        <h3>Enter Phone Number</h3>
        <p>Login with your Nigerian phone number. No email needed!</p>
      </div>
      <div class="step">
        <div class="step-num">2</div>
        <h3>Verify Subscription</h3>
        <p>We check your subscription status automatically. Fast and smooth.</p>
      </div>
      <div class="step">
        <div class="step-num">3</div>
        <h3>Start Playing!</h3>
        <p>Pick any game and start earning points. Compete on the leaderboard!</p>
      </div>
    </div>
  </div>
</section>

<!-- Features -->
<section class="section">
  <div class="container">
    <div class="section-title scroll-reveal">
      <h2>Why FabGames <span class="gradient-text">Slaps</span></h2>
    </div>
    <div class="features-grid scroll-reveal">
      <div class="feature-card">
        <div class="feature-icon" style="background:rgba(124,58,237,0.15);">📱</div>
        <h3>Mobile First</h3>
        <p>Designed specifically for phones. Smooth on even entry-level Android devices.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background:rgba(16,185,129,0.15);">⚡</div>
        <h3>Lightning Fast</h3>
        <p>Loads in seconds even on slow networks. No heavy downloads needed.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background:rgba(245,158,11,0.15);">🏆</div>
        <h3>Compete & Win</h3>
        <p>Real-time leaderboards. See where you rank among all Nigerian players.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background:rgba(239,68,68,0.15);">🔔</div>
        <h3>Stay Updated</h3>
        <p>Push notifications for new games, tournaments, and your friend's scores.</p>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="section">
  <div class="container">
    <div class="cta-section scroll-reveal">
      <div style="font-size:4rem; margin-bottom:20px;">🚀</div>
      <h2>Ready to <span class="gradient-text-2">Shine?</span></h2>
      <p style="color:var(--text-muted); margin: 16px 0 32px; font-size:1.1rem;">Join thousands of Nigerians already playing. No cap!</p>
      <a href="/ZikGames/login.php" class="btn btn-accent btn-lg" id="cta-play-btn">🎮 Play Now — It's Free!</a>
    </div>
  </div>
</section>

<!-- Footer -->
<footer style="border-top:1px solid var(--border); padding:40px 24px; text-align:center;">
  <div class="navbar-brand" style="justify-content:center; margin-bottom:16px;">
    <div class="logo-icon">🎮</div>
    <span class="gradient-text" style="font-size:1.5rem; font-weight:900;">FabGames</span>
  </div>
  <p style="color:var(--text-muted); font-size:0.875rem;">© 2026 FabGames Nigeria. All rights reserved. Made with ❤️ for Naija gamers.</p>
</footer>

<script>
// Particle system
const particleColors = ['#7C3AED','#00D4FF','#39FF14','#FF006E','#F59E0B'];
const container = document.getElementById('particles');
for (let i = 0; i < 40; i++) {
  const p = document.createElement('div');
  p.className = 'particle';
  p.style.left = Math.random() * 100 + 'vw';
  p.style.width = p.style.height = (Math.random() * 6 + 2) + 'px';
  p.style.background = particleColors[Math.floor(Math.random() * particleColors.length)];
  p.style.animationDuration = (Math.random() * 15 + 8) + 's';
  p.style.animationDelay = (Math.random() * 10) + 's';
  container.appendChild(p);
}

// Scroll reveal
const observer = new IntersectionObserver((entries) => {
  entries.forEach(el => {
    if (el.isIntersecting) { el.target.classList.add('visible'); }
  });
}, { threshold: 0.1 });
document.querySelectorAll('.scroll-reveal').forEach(el => observer.observe(el));
</script>
</body>
</html>

