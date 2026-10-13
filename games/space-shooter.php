<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
requireUserLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<title>Space Shooter – FabGames</title>
<link rel="stylesheet" href="/ZikGames/assets/css/style.css">
<style>
  .heart-icon {
    font-size: 1.4rem;
    color: var(--accent3);
    text-shadow: 0 0 8px rgba(239, 68, 68, 0.6);
    transition: all 0.3s ease;
  }
  .heart-icon.lost {
    opacity: 0.2;
    transform: scale(0.8);
  }
  .level-btn {
    width: 100%;
    padding: 16px;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    background: rgba(255, 255, 255, 0.03);
    color: var(--text);
    font-size: 1.1rem;
    font-weight: 700;
    text-align: left;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: var(--transition);
  }
  .level-btn:hover {
    background: var(--primary);
    border-color: var(--primary-light);
    transform: translateX(4px);
  }
</style>
</head>
<body>
<div class="game-wrapper">
  <div class="game-hud">
    <a href="/ZikGames/dashboard.php" style="font-size:1.4rem; padding:4px;" id="exit-ss-btn">🔙</a>
    <div style="display:flex; align-items:center; gap:20px;">
      <div style="text-align:center;">
        <div style="font-size:0.65rem; color:var(--text-muted); text-transform:uppercase;">Score</div>
        <div style="font-size:1.4rem; font-weight:900; color:var(--accent);" id="ss-score">0</div>
      </div>
      <div style="text-align:center;">
        <div style="font-size:0.65rem; color:var(--text-muted); text-transform:uppercase;">Lives</div>
        <div style="display:flex; gap:4px;" id="ss-lives">
          <span class="heart-icon" id="heart-1">❤️</span>
          <span class="heart-icon" id="heart-2">❤️</span>
          <span class="heart-icon" id="heart-3">❤️</span>
        </div>
      </div>
      <div style="text-align:center;">
        <div style="font-size:0.65rem; color:var(--text-muted); text-transform:uppercase;">Difficulty</div>
        <div style="font-size:1.4rem; font-weight:900; color:var(--neon-green);" id="ss-level">-</div>
      </div>
    </div>
    <div style="display:flex; gap:8px;">
      <button class="control-btn" style="background:rgba(245,158,11,0.2); color:#FCD34D;" onclick="ssToggleSound()" id="ss-sound-btn">🔊</button>
      <button class="control-btn" style="background:rgba(124,58,237,0.2); color:var(--primary-light);" onclick="ssTogglePause()" id="ss-pause-btn">⏸</button>
    </div>
  </div>

  <div class="game-canvas-wrap">
    <canvas id="ssCanvas"></canvas>
    
    <!-- Level selection overlay -->
    <div class="game-over-screen" id="ss-start">
      <div style="font-size:5rem;">🚀</div>
      <h2>Space Shooter</h2>
      <p style="color:var(--text-muted); margin-bottom:10px;">Drag or use screen buttons to move.<br>Auto-fire is enabled. Destroy the alien armada!</p>
      <div class="presence-chip offline" id="friend-status" style="margin-bottom:14px;">Checking friend status...</div>
      <button class="btn btn-outline btn-sm hidden" id="friend-play-btn" onclick="ssPlayFriend()" style="margin-bottom:14px;">Play with a friend</button>
      
      <div style="display:flex; flex-direction:column; gap:12px; width:100%; max-width:320px;">
        <button class="level-btn" onclick="ssStart(1)">
          <span>Easy</span>
          <span style="font-size:0.8rem; opacity:0.7;">1x Multiplier</span>
        </button>
        <button class="level-btn" onclick="ssStart(2)">
          <span>Intermediate</span>
          <span style="font-size:0.8rem; opacity:0.7;">2x Multiplier</span>
        </button>
        <button class="level-btn" onclick="ssStart(3)">
          <span>Hard</span>
          <span style="font-size:0.8rem; opacity:0.7;">3x Multiplier</span>
        </button>
        <button class="level-btn" onclick="ssStart(4)">
          <span>Very Hard</span>
          <span style="font-size:0.8rem; opacity:0.7;">4x Multiplier</span>
        </button>
        <button class="level-btn" onclick="ssStart(5)">
          <span>👑 Level 5 (God Mode)</span>
          <span style="font-size:0.8rem; opacity:0.7;">4x Multiplier</span>
        </button>
      </div>
    </div>

    <!-- Game Over overlay -->
    <div class="game-over-screen hidden" id="ss-gameover">
      <div style="font-size:5rem;">💀</div>
      <h2>Mission Failed</h2>
      <p style="color:var(--text-muted);">Your ship was destroyed!</p>
      <div style="margin:12px 0;">
        <div style="font-size:0.9rem; color:var(--text-muted); text-transform:uppercase;">Final Score</div>
        <div class="score-display" id="ss-final-score">0</div>
      </div>
      <div style="display:flex; gap:12px;">
        <button class="btn btn-primary" onclick="ssShowLevelSelect()">🔄 Try Again</button>
        <a href="/ZikGames/dashboard.php" class="btn btn-outline">🏠 Home</a>
      </div>
    </div>
  </div>

  <!-- Mobile screen navigation buttons -->
  <div class="game-controls" style="justify-content:space-between; padding:10px 24px;">
    <div style="display:flex; gap:16px; width:100%; justify-content:center;">
      <button class="btn btn-outline" style="padding:12px 30px; font-size:1.5rem;" id="btn-left">◀</button>
      <button class="btn btn-outline" style="padding:12px 30px; font-size:1.5rem;" id="btn-right">▶</button>
    </div>
  </div>
</div>

<script src="/ZikGames/assets/js/common.js"></script>
<script>
const canvas = document.getElementById('ssCanvas');
const ctx = canvas.getContext('2d');
const SS_DIFFICULTIES = {
  1: { label: 'Easy', multiplier: 1 },
  2: { label: 'Intermediate', multiplier: 2 },
  3: { label: 'Hard', multiplier: 3 },
  4: { label: 'Very Hard', multiplier: 4 },
};

let W, H;
let score = 0;
let lives = 3;
let level = 1;
let soundOn = true;
let running = false;
let paused = false;
let ssStartTime = 0;
let ssSaved = false;

// Stars backdrop
let stars = [];

// Game entities
let player = {
  x: 0,
  y: 0,
  r: 16,
  speed: 6,
  targetX: 0
};

let lasers = [];
let alienLasers = [];
let aliens = [];
let particles = [];

// Spawning and timers
let lastSpawnTime = 0;
let spawnInterval = 1500;
let lastFireTime = 0;
let fireInterval = 300;
let lastAlienFireTime = 0;
let alienFireInterval = 1200;

function ssToggleSound() {
  soundOn = !soundOn;
  document.getElementById('ss-sound-btn').textContent = soundOn ? '🔊' : '🔇';
}

function ssTogglePause() {
  if (!running) return;
  paused = !paused;
  document.getElementById('ss-pause-btn').textContent = paused ? '▶' : '⏸';
  if (!paused) {
    lastTime = performance.now();
    requestAnimationFrame(gameLoop);
  }
}

function ssResize() {
  const wrap = canvas.parentElement;
  W = canvas.width = wrap.clientWidth;
  H = canvas.height = wrap.clientHeight;
  if (!running) {
    player.x = W / 2;
    player.y = H - 60;
    player.targetX = player.x;
  }
}

function initStars() {
  stars = [];
  for (let i = 0; i < 60; i++) {
    stars.push({
      x: Math.random() * W,
      y: Math.random() * H,
      size: Math.random() * 2 + 0.5,
      speed: Math.random() * 1.5 + 0.5
    });
  }
}

function ssShowLevelSelect() {
  document.getElementById('ss-start').classList.remove('hidden');
  document.getElementById('ss-gameover').classList.add('hidden');
  running = false;
  paused = false;
}

function ssStart(selectedLevel) {
  score = 0;
  lives = 3;
  level = selectedLevel;
  ssSaved = false;
  ssStartTime = Date.now();

  // Level configuration
  spawnInterval = Math.max(600, 1800 - level * 200);
  alienFireInterval = Math.max(500, 1500 - level * 150);

  document.getElementById('ss-score').textContent = '0';
  document.getElementById('ss-level').textContent = SS_DIFFICULTIES[level]?.label || 'Easy';
  updateLivesUI();

  document.getElementById('ss-start').classList.add('hidden');
  document.getElementById('ss-gameover').classList.add('hidden');

  aliens = [];
  lasers = [];
  alienLasers = [];
  particles = [];

  player.x = W / 2;
  player.y = H - 60;
  player.targetX = player.x;

  initStars();
  
  running = true;
  paused = false;
  lastTime = performance.now();
  requestAnimationFrame(gameLoop);
}

function updateLivesUI() {
  for (let i = 1; i <= 3; i++) {
    const heart = document.getElementById(`heart-${i}`);
    if (i <= lives) {
      heart.classList.remove('lost');
    } else {
      heart.classList.add('lost');
    }
  }
}

// Particle System
function spawnExplosion(x, y, color) {
  for (let i = 0; i < 15; i++) {
    particles.push({
      x: x,
      y: y,
      vx: (Math.random() - 0.5) * 6,
      vy: (Math.random() - 0.5) * 6,
      r: Math.random() * 3 + 1,
      alpha: 1,
      decay: Math.random() * 0.03 + 0.015,
      color: color
    });
  }
}

function updateParticles() {
  for (let i = particles.length - 1; i >= 0; i--) {
    let p = particles[i];
    p.x += p.vx;
    p.y += p.vy;
    p.alpha -= p.decay;
    if (p.alpha <= 0) {
      particles.splice(i, 1);
    }
  }
}

// Starfield Background
function updateStars() {
  stars.forEach(s => {
    s.y += s.speed;
    if (s.y > H) {
      s.y = 0;
      s.x = Math.random() * W;
    }
  });
}

function drawStars() {
  ctx.fillStyle = '#000';
  ctx.fillRect(0, 0, W, H);
  
  stars.forEach(s => {
    ctx.fillStyle = `rgba(255, 255, 255, ${s.size > 1.5 ? 0.8 : 0.4})`;
    ctx.beginPath();
    ctx.arc(s.x, s.y, s.size, 0, Math.PI * 2);
    ctx.fill();
  });
}

// Controls
let keys = { left: false, right: false };
document.getElementById('btn-left').addEventListener('pointerdown', () => keys.left = true);
document.getElementById('btn-left').addEventListener('pointerup', () => keys.left = false);
document.getElementById('btn-left').addEventListener('pointerleave', () => keys.left = false);

document.getElementById('btn-right').addEventListener('pointerdown', () => keys.right = true);
document.getElementById('btn-right').addEventListener('pointerup', () => keys.right = false);
document.getElementById('btn-right').addEventListener('pointerleave', () => keys.right = false);

// Touch drag controls
canvas.addEventListener('pointermove', (e) => {
  if (!running || paused) return;
  const rect = canvas.getBoundingClientRect();
  const touchX = e.clientX - rect.left;
  player.targetX = Math.max(20, Math.min(W - 20, touchX));
});

canvas.addEventListener('pointerdown', (e) => {
  if (!running || paused) return;
  const rect = canvas.getBoundingClientRect();
  const touchX = e.clientX - rect.left;
  player.targetX = Math.max(20, Math.min(W - 20, touchX));
});

// Update game
let lastTime = 0;
function gameLoop(now) {
  if (!running || paused) return;
  
  const dt = now - lastTime;
  lastTime = now;

  // Starfield
  updateStars();
  
  // Smooth move player to target
  if (keys.left) {
    player.x = Math.max(20, player.x - player.speed);
    player.targetX = player.x;
  } else if (keys.right) {
    player.x = Math.min(W - 20, player.x + player.speed);
    player.targetX = player.x;
  } else {
    player.x += (player.targetX - player.x) * 0.2;
  }

  // Player Auto-Firing
  if (now - lastFireTime > fireInterval) {
    lasers.push({
      x: player.x,
      y: player.y - 15,
      vy: -10
    });
    lastFireTime = now;
  }

  // Alien Spawning
  if (now - lastSpawnTime > spawnInterval) {
    // Determine random type and speed
    const isElite = Math.random() < 0.2 * level;
    aliens.push({
      x: Math.random() * (W - 40) + 20,
      y: -20,
      r: isElite ? 18 : 12,
      vx: (Math.random() - 0.5) * (level + 1),
      vy: Math.random() * 1.5 + level * 0.4 + 1,
      hp: isElite ? 3 : 1,
      isElite: isElite,
      color: isElite ? '#FF006E' : '#39FF14'
    });
    lastSpawnTime = now;
  }

  // Alien Firing
  if (now - lastAlienFireTime > alienFireInterval && aliens.length > 0) {
    // Pick random alien
    const shooterAlien = aliens[Math.floor(Math.random() * aliens.length)];
    alienLasers.push({
      x: shooterAlien.x,
      y: shooterAlien.y + 10,
      vy: 4 + level * 0.5
    });
    lastAlienFireTime = now;
  }

  // Update Lasers
  for (let i = lasers.length - 1; i >= 0; i--) {
    lasers[i].y += lasers[i].vy;
    if (lasers[i].y < 0) {
      lasers.splice(i, 1);
    }
  }

  // Update Alien Lasers
  for (let i = alienLasers.length - 1; i >= 0; i--) {
    alienLasers[i].y += alienLasers[i].vy;
    if (alienLasers[i].y > H) {
      alienLasers.splice(i, 1);
      continue;
    }
    // Check hit player
    const dist = Math.hypot(alienLasers[i].x - player.x, alienLasers[i].y - player.y);
    if (dist < player.r) {
      alienLasers.splice(i, 1);
      playerHit();
    }
  }

  // Update Aliens
  for (let i = aliens.length - 1; i >= 0; i--) {
    let a = aliens[i];
    a.x += a.vx;
    a.y += a.vy;

    // Boundaries bounce
    if (a.x < 15 || a.x > W - 15) a.vx = -a.vx;

    // Out of screen
    if (a.y > H + 20) {
      aliens.splice(i, 1);
      playerHit(); // penalty for letting alien cross the line
      continue;
    }

    // Check hit player ship directly
    const distPlayer = Math.hypot(a.x - player.x, a.y - player.y);
    if (distPlayer < player.r + a.r) {
      spawnExplosion(a.x, a.y, a.color);
      aliens.splice(i, 1);
      playerHit();
      continue;
    }

    // Check laser collision
    for (let j = lasers.length - 1; j >= 0; j--) {
      let l = lasers[j];
      const distLaser = Math.hypot(a.x - l.x, a.y - l.y);
      if (distLaser < a.r + 4) {
        lasers.splice(j, 1);
        a.hp--;
        spawnExplosion(l.x, l.y, '#00D4FF');
        if (a.hp <= 0) {
          spawnExplosion(a.x, a.y, a.color);
          score += (a.isElite ? 25 : 10) * level;
          document.getElementById('ss-score').textContent = score.toLocaleString();
          aliens.splice(i, 1);
        }
        break;
      }
    }
  }

  // Update Particles
  updateParticles();

  // Draw frame
  drawStars();
  drawPlayer();
  drawLasers();
  drawAliens();
  drawAlienLasers();
  drawParticles();

  requestAnimationFrame(gameLoop);
}

function playerHit() {
  lives--;
  updateLivesUI();
  spawnExplosion(player.x, player.y, '#EF4444');
  if (navigator.vibrate) navigator.vibrate([100, 50, 100]);

  if (lives <= 0) {
    running = false;
    document.getElementById('ss-final-score').textContent = score.toLocaleString();
    document.getElementById('ss-gameover').classList.remove('hidden');

    if (!ssSaved) {
      ssSaved = true;
      ZikGames.saveScoreAndShowResults('Space Shooter', score, {
        difficulty: level,
        difficultyLabel: SS_DIFFICULTIES[level]?.label || 'Easy',
        multiplier: SS_DIFFICULTIES[level]?.multiplier || level,
        gameMode: 'single',
        survivalRun: true,
        duration: Math.round((Date.now() - ssStartTime) / 1000),
      });
    }
  }
}

// Drawing Functions
function drawPlayer() {
  ctx.save();
  ctx.translate(player.x, player.y);
  
  // Neon glow
  ctx.shadowColor = '#00D4FF';
  ctx.shadowBlur = 15;
  
  // Outer hull
  ctx.strokeStyle = '#00D4FF';
  ctx.lineWidth = 3;
  ctx.fillStyle = 'rgba(0, 212, 255, 0.2)';
  ctx.beginPath();
  ctx.moveTo(0, -18);
  ctx.lineTo(14, 12);
  ctx.lineTo(6, 6);
  ctx.lineTo(-6, 6);
  ctx.lineTo(-14, 12);
  ctx.closePath();
  ctx.stroke();
  ctx.fill();

  // Engine fire
  ctx.shadowColor = '#FF6B00';
  ctx.fillStyle = Math.random() < 0.5 ? '#FF6B00' : '#FFD000';
  ctx.beginPath();
  ctx.moveTo(-4, 7);
  ctx.lineTo(4, 7);
  ctx.lineTo(0, 15 + Math.random() * 8);
  ctx.closePath();
  ctx.fill();
  
  ctx.restore();
}

function drawLasers() {
  lasers.forEach(l => {
    ctx.save();
    ctx.shadowColor = '#00D4FF';
    ctx.shadowBlur = 10;
    ctx.strokeStyle = '#00D4FF';
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.moveTo(l.x, l.y);
    ctx.lineTo(l.x, l.y - 12);
    ctx.stroke();
    ctx.restore();
  });
}

function drawAlienLasers() {
  alienLasers.forEach(l => {
    ctx.save();
    ctx.shadowColor = '#FF006E';
    ctx.shadowBlur = 10;
    ctx.strokeStyle = '#FF006E';
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.moveTo(l.x, l.y);
    ctx.lineTo(l.x, l.y + 10);
    ctx.stroke();
    ctx.restore();
  });
}

function drawAliens() {
  aliens.forEach(a => {
    ctx.save();
    ctx.translate(a.x, a.y);
    ctx.shadowColor = a.color;
    ctx.shadowBlur = 12;
    ctx.strokeStyle = a.color;
    ctx.fillStyle = 'rgba(0,0,0,0.4)';
    ctx.lineWidth = 2.5;

    // Draw Alien Ship Shape
    ctx.beginPath();
    if (a.isElite) {
      // Hexagonal bossy alien
      ctx.moveTo(0, -a.r);
      ctx.lineTo(a.r, -a.r/2);
      ctx.lineTo(a.r * 0.8, a.r/2);
      ctx.lineTo(0, a.r);
      ctx.lineTo(-a.r * 0.8, a.r/2);
      ctx.lineTo(-a.r, -a.r/2);
      ctx.closePath();
    } else {
      // UFO / crab alien
      ctx.arc(0, 0, a.r, 0, Math.PI, true);
      ctx.lineTo(-a.r, a.r/2);
      ctx.lineTo(a.r, a.r/2);
      ctx.closePath();
    }
    ctx.stroke();
    ctx.fill();

    // Eyes
    ctx.fillStyle = '#FFF';
    ctx.beginPath();
    ctx.arc(-a.r/3, -a.r/5, 2.5, 0, Math.PI*2);
    ctx.arc(a.r/3, -a.r/5, 2.5, 0, Math.PI*2);
    ctx.fill();
    
    ctx.restore();
  });
}

function drawParticles() {
  particles.forEach(p => {
    ctx.save();
    ctx.globalAlpha = p.alpha;
    ctx.fillStyle = p.color;
    ctx.beginPath();
    ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
  });
}

function ssPlayFriend() {
  ZikGames.showToast('Friend mode is available for matchmaking checks. Open Space Shooter on both devices and compare your survival run.', 'info');
}

window.addEventListener('resize', ssResize);
ssResize();
ssShowLevelSelect();
ZikGames.initPresenceLoop('Space Shooter', {
  statusId: 'friend-status',
  buttonId: 'friend-play-btn',
  offlineMessage: 'No friend online for Space Shooter yet',
});
</script>
</body>
</html>



