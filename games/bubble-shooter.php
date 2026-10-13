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
<title>Bubble Shooter – FabGames</title>
<link rel="stylesheet" href="/ZikGames/assets/css/style.css">
</head>
<body>
<div class="game-wrapper">
  <div class="game-hud">
    <a href="/ZikGames/dashboard.php" style="font-size:1.4rem; padding:4px;" id="exit-bubble-btn">🔙</a>
    <div style="display:flex; align-items:center; gap:20px;">
      <div style="text-align:center;">
        <div style="font-size:0.65rem; color:var(--text-muted); text-transform:uppercase;">Score</div>
        <div style="font-size:1.4rem; font-weight:900; color:var(--accent);" id="bs-score">0</div>
      </div>
      <div style="text-align:center;">
        <div style="font-size:0.65rem; color:var(--text-muted); text-transform:uppercase;">Difficulty</div>
        <div style="font-size:1.1rem; font-weight:900; color:var(--neon-green);" id="bs-level">Easy</div>
      </div>
    </div>
    <div style="display:flex; gap:8px;">
      <button class="control-btn" style="background:rgba(245,158,11,0.2); color:#FCD34D;" onclick="bsToggleSound()" id="bs-sound-btn">🔊</button>
      <button class="control-btn" style="background:rgba(124,58,237,0.2); color:var(--primary-light);" onclick="bsPause()" id="bs-pause-btn">⏸</button>
    </div>
  </div>

  <div class="game-canvas-wrap">
    <canvas id="bsCanvas"></canvas>
    <div class="game-over-screen" id="bs-start">
      <div style="font-size:5rem;">🫧</div>
      <h2>Bubble Shooter</h2>
      <p style="color:var(--text-muted); margin-bottom:10px;">Aim and shoot bubbles! Match 3+ to pop them.<br>Select a difficulty to start:</p>
      <div style="display:flex; flex-direction:column; gap:8px; width:100%; max-width:280px; margin:0 auto;">
        <button class="btn btn-primary btn-sm" onclick="bsStart(1)" style="background:#10B981; border:none; padding:10px;">Easy</button>
        <button class="btn btn-primary btn-sm" onclick="bsStart(2)" style="background:#F59E0B; border:none; padding:10px;">Intermediate</button>
        <button class="btn btn-primary btn-sm" onclick="bsStart(3)" style="background:#FF6B00; border:none; padding:10px;">Hard</button>
        <button class="btn btn-primary btn-sm" onclick="bsStart(4)" style="background:#EF4444; border:none; padding:10px;">Very Hard</button>
      </div>
    </div>
    <div class="game-over-screen hidden" id="bs-gameover">
      <div style="font-size:4rem;">🫧</div>
      <h2>Game Over!</h2>
      <div class="score-display" id="bs-final-score">0</div>
      <div style="display:flex; gap:12px;">
        <button class="btn btn-primary" onclick="bsShowLevelSelect()">🔄 Play Again</button>
        <a href="/ZikGames/dashboard.php" class="btn btn-outline" id="bs-home-btn">🏠 Home</a>
      </div>
    </div>
  </div>

  <!-- Aim Indicator -->
  <div class="game-controls" style="flex-direction:column; align-items:center; padding:8px 16px; gap:8px;">
    <div style="font-size:0.8rem; color:var(--text-muted);">← Swipe/drag to aim → Release to shoot</div>
    <div style="display:flex; gap:12px; align-items:center;">
      <div id="bs-current-bubble" style="width:40px; height:40px; border-radius:50%; border:2px solid var(--border); display:flex; align-items:center; justify-content:center; font-size:1.2rem;">🔵</div>
      <div style="font-size:0.75rem; color:var(--text-muted);">Current</div>
      <div style="width:30px; height:30px; border-radius:50%; border:2px solid var(--border); display:flex; align-items:center; justify-content:center; font-size:1rem;" id="bs-next-bubble">🟣</div>
      <div style="font-size:0.75rem; color:var(--text-muted);">Next</div>
      <button class="btn btn-accent btn-sm" onclick="bsShoot()" id="bs-shoot-btn">🔫 SHOOT</button>
    </div>
  </div>
</div>

<script src="/ZikGames/assets/js/common.js"></script>
<script>
const bsCanvas = document.getElementById('bsCanvas');
const bsCtx    = bsCanvas.getContext('2d');
const BS_DIFFICULTIES = {
  1: { label: 'Easy', multiplier: 1 },
  2: { label: 'Intermediate', multiplier: 2 },
  3: { label: 'Hard', multiplier: 3 },
  4: { label: 'Very Hard', multiplier: 4 },
};

const COLS = 8, ROWS_VISIBLE = 10;
const COLORS = ['#EF4444','#7C3AED','#10B981','#F59E0B','#00D4FF','#FF006E'];
const EMOJIS = { '#EF4444':'🔴','#7C3AED':'🟣','#10B981':'🟢','#F59E0B':'🟡','#00D4FF':'🔵','#FF006E':'🩷' };

let bsW, bsH, cellR, bsScore=0, bsLevel=1, bsPaused=false, bsRunning=false;
let grid=[], shooter={x:0, y:0, angle:-Math.PI/2}, currentColor, nextColor;
let shootBall=null, aimX=0, aimY=0, aiming=false;

function bsResize() {
  const wrap = bsCanvas.parentElement;
  bsW = bsCanvas.width  = wrap.clientWidth;
  bsH = bsCanvas.height = wrap.clientHeight;
  cellR = Math.min(bsW / (COLS * 2 + 1), bsH / (ROWS_VISIBLE * 2 + 4));
  shooter.x = bsW / 2;
  shooter.y = bsH - cellR * 2;
}

function randomColor() {
  const colorCount = Math.min(COLORS.length, 2 + bsLevel);
  return COLORS[Math.floor(Math.random() * colorCount)];
}

function initGrid() {
  grid = [];
  const filledRows = 5 + bsLevel;
  for (let r = 0; r < filledRows; r++) {
    grid[r] = [];
    for (let c = 0; c < COLS; c++) {
      grid[r][c] = Math.random() < 0.85 ? randomColor() : null;
    }
  }
  for (let r = filledRows; r < ROWS_VISIBLE + 5; r++) {
    grid[r] = new Array(COLS).fill(null);
  }
}

function cellPos(r, c) {
  const offset = r % 2 === 0 ? 0 : cellR;
  return {
    x: cellR + c * cellR * 2 + offset + (bsW - (COLS * cellR * 2)) / 2,
    y: cellR * 1.5 + r * cellR * 1.8
  };
}

function bsDraw() {
  bsCtx.clearRect(0, 0, bsW, bsH);
  // Background
  const grad = bsCtx.createLinearGradient(0, 0, 0, bsH);
  grad.addColorStop(0, '#0D0B1E');
  grad.addColorStop(1, '#16132B');
  bsCtx.fillStyle = grad;
  bsCtx.fillRect(0, 0, bsW, bsH);

  // Draw grid bubbles
  for (let r = 0; r < grid.length; r++) {
    for (let c = 0; c < COLS; c++) {
      if (!grid[r][c]) continue;
      const pos = cellPos(r, c);
      drawBubble(bsCtx, pos.x, pos.y, cellR - 1, grid[r][c]);
    }
  }

  // Danger line
  const dangerY = bsH - cellR * 6;
  bsCtx.strokeStyle = 'rgba(239,68,68,0.3)';
  bsCtx.setLineDash([8, 6]);
  bsCtx.lineWidth = 2;
  bsCtx.beginPath(); bsCtx.moveTo(0, dangerY); bsCtx.lineTo(bsW, dangerY); bsCtx.stroke();
  bsCtx.setLineDash([]);

  // Aim line
  if (aiming) {
    const dx = aimX - shooter.x;
    const dy = aimY - shooter.y;
    const angle = Math.atan2(dy, dx);
    bsCtx.strokeStyle = 'rgba(255,255,255,0.3)';
    bsCtx.lineWidth = 1;
    bsCtx.setLineDash([8, 8]);
    bsCtx.beginPath();
    bsCtx.moveTo(shooter.x, shooter.y);
    // Bounce prediction
    let px = shooter.x, py = shooter.y;
    let pvx = Math.cos(angle) * 30, pvy = Math.sin(angle) * 30;
    for (let i = 0; i < 20; i++) {
      px += pvx; py += pvy;
      if (px < cellR || px > bsW - cellR) pvx = -pvx;
      if (py < 0) break;
      bsCtx.lineTo(px, py);
    }
    bsCtx.stroke();
    bsCtx.setLineDash([]);
  }

  // Flying ball
  if (shootBall) {
    drawBubble(bsCtx, shootBall.x, shootBall.y, cellR - 1, shootBall.color);
  }

  // Shooter base
  drawBubble(bsCtx, shooter.x, shooter.y, cellR, currentColor || COLORS[0]);
  bsCtx.fillStyle = 'rgba(255,255,255,0.6)';
  bsCtx.font = 'bold ' + (cellR * 1.2) + 'px serif';
  bsCtx.textAlign = 'center';
  bsCtx.textBaseline = 'middle';
  bsCtx.fillText('🎯', shooter.x, shooter.y);
}

function drawBubble(ctx, x, y, r, color) {
  // Glow
  ctx.save();
  ctx.shadowColor = color;
  ctx.shadowBlur  = 12;
  ctx.beginPath();
  ctx.arc(x, y, r, 0, Math.PI * 2);
  ctx.fillStyle = color;
  ctx.fill();
  ctx.restore();
  // Shine
  ctx.beginPath();
  ctx.arc(x - r * 0.3, y - r * 0.3, r * 0.3, 0, Math.PI * 2);
  ctx.fillStyle = 'rgba(255,255,255,0.35)';
  ctx.fill();
  // Emoji
  ctx.font = (r * 1.4) + 'px serif';
  ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
  ctx.fillText(EMOJIS[color] || '⚪', x, y);
}

function bsShowLevelSelect() {
  document.getElementById('bs-start').classList.remove('hidden');
  document.getElementById('bs-gameover').classList.add('hidden');
}

function bsStart(selectedLevel = 1) {
  bsScore = 0; bsLevel = selectedLevel; bsRunning = true; bsPaused = false; shootBall = null;
  document.getElementById('bs-score').textContent = '0';
  document.getElementById('bs-level').textContent = BS_DIFFICULTIES[bsLevel]?.label || 'Easy';
  document.getElementById('bs-start').classList.add('hidden');
  document.getElementById('bs-gameover').classList.add('hidden');
  currentColor = randomColor(); nextColor = randomColor();
  updateColorUI();
  initGrid();
  requestAnimationFrame(bsLoop);
}

function bsLoop() {
  if (!bsRunning || bsPaused) return;
  if (shootBall) {
    shootBall.x += shootBall.vx;
    shootBall.y += shootBall.vy;
    // Wall bounce
    if (shootBall.x < cellR || shootBall.x > bsW - cellR) shootBall.vx = -shootBall.vx;
    // Check collision with grid
    let stuck = false;
    for (let r = 0; r < grid.length; r++) {
      for (let c = 0; c < COLS; c++) {
        if (!grid[r][c]) continue;
        const pos = cellPos(r, c);
        if (Math.hypot(shootBall.x - pos.x, shootBall.y - pos.y) < cellR * 1.9) {
          stuck = true;
          placeAt(shootBall.x, shootBall.y, shootBall.color);
          shootBall = null;
          break;
        }
      }
      if (stuck) break;
    }
    if (shootBall && shootBall.y < cellR) {
      placeAt(shootBall.x, shootBall.y, shootBall.color);
      shootBall = null;
    }
    if (shootBall && shootBall.y > bsH) {
      shootBall = null;
    }
  }
  bsDraw();
  requestAnimationFrame(bsLoop);
}

function placeAt(px, py, color) {
  // Find nearest empty cell
  let best = null, bestDist = Infinity;
  for (let r = 0; r < grid.length; r++) {
    for (let c = 0; c < COLS; c++) {
      if (grid[r][c]) continue;
      const pos = cellPos(r, c);
      const d = Math.hypot(px - pos.x, py - pos.y);
      if (d < bestDist) { bestDist = d; best = {r, c}; }
    }
  }
  if (best) {
    grid[best.r][best.c] = color;
    checkMatches(best.r, best.c, color);
    
    // Check if any bubble crossed danger line
    const dangerY = bsH - cellR * 6;
    for (let r = 0; r < grid.length; r++) {
      for (let c = 0; c < COLS; c++) {
        if (grid[r][c]) {
          const pos = cellPos(r, c);
          if (pos.y > dangerY) {
            bsGameOver();
            return;
          }
        }
      }
    }
  }
  currentColor = nextColor;
  nextColor = randomColor();
  updateColorUI();
}

function getNeighbors(r, c) {
  const even = r % 2 === 0;
  return [
    [r-1, even?c-1:c], [r-1, even?c:c+1],
    [r,   c-1],        [r,   c+1],
    [r+1, even?c-1:c], [r+1, even?c:c+1]
  ].filter(([nr,nc]) => nr>=0 && nc>=0 && nr<grid.length && nc<COLS);
}

function checkMatches(r, c, color) {
  const visited = new Set();
  const cluster = [];
  const stack = [[r,c]];
  while (stack.length) {
    const [cr,cc] = stack.pop();
    const key = cr+','+cc;
    if (visited.has(key)) continue;
    visited.add(key);
    if (grid[cr] && grid[cr][cc] === color) {
      cluster.push([cr,cc]);
      getNeighbors(cr,cc).forEach(n => stack.push(n));
    }
  }
  if (cluster.length >= 3) {
    cluster.forEach(([br,bc]) => { grid[br][bc] = null; });
    bsScore += cluster.length * 10 * bsLevel;
    document.getElementById('bs-score').textContent = bsScore.toLocaleString();
    if (navigator.vibrate) navigator.vibrate([50,30,50]);
    checkFloating();
    checkWin();
  }
}

function checkFloating() {
  // Remove disconnected bubbles
  const connected = new Set();
  const stack = [];
  for (let c = 0; c < COLS; c++) { if (grid[0] && grid[0][c]) stack.push([0,c]); }
  while (stack.length) {
    const [r,c] = stack.pop();
    const key = r+','+c;
    if (connected.has(key) || !grid[r] || !grid[r][c]) continue;
    connected.add(key);
    getNeighbors(r,c).forEach(n => stack.push(n));
  }
  let floatingCount = 0;
  for (let r=1; r<grid.length; r++) {
    for (let c=0; c<COLS; c++) {
      if (grid[r][c] && !connected.has(r+','+c)) {
        grid[r][c] = null;
        floatingCount++;
      }
    }
  }
  if (floatingCount > 0) bsScore += floatingCount * 20;
}

function checkWin() {
  const hasAny = grid.some(row => row.some(c => c !== null));
  if (!hasAny) {
    bsScore += 50 * (BS_DIFFICULTIES[bsLevel]?.multiplier || bsLevel);
    document.getElementById('bs-score').textContent = bsScore.toLocaleString();
    document.getElementById('bs-level').textContent = BS_DIFFICULTIES[bsLevel]?.label || 'Easy';
    initGrid();
  }
}

function bsShoot() {
  if (!bsRunning || bsPaused || shootBall) return;
  const angle = Math.atan2(aimY - shooter.y, aimX - shooter.x);
  const speed = 12 + bsLevel;
  shootBall = {
    x: shooter.x, y: shooter.y,
    vx: Math.cos(angle) * speed,
    vy: Math.sin(angle) * speed,
    color: currentColor
  };
  if (navigator.vibrate) navigator.vibrate(20);
}

function bsPause() {
  bsPaused = !bsPaused;
  document.getElementById('bs-pause-btn').textContent = bsPaused ? '▶' : '⏸';
  if (!bsPaused) requestAnimationFrame(bsLoop);
}

function updateColorUI() {
  document.getElementById('bs-current-bubble').textContent = EMOJIS[currentColor] || '⚪';
  document.getElementById('bs-next-bubble').textContent = EMOJIS[nextColor] || '⚪';
}

// Aim controls
bsCanvas.addEventListener('pointermove', (e) => {
  const rect = bsCanvas.getBoundingClientRect();
  aimX = e.clientX - rect.left;
  aimY = e.clientY - rect.top;
  aiming = true;
});
bsCanvas.addEventListener('pointerdown', (e) => {
  const rect = bsCanvas.getBoundingClientRect();
  aimX = e.clientX - rect.left;
  aimY = e.clientY - rect.top;
  aiming = true;
});
bsCanvas.addEventListener('pointerup', (e) => {
  if (bsRunning) bsShoot();
  aiming = false;
});

window.addEventListener('resize', bsResize);
bsResize();

let bsBestScore = 0;
let bsSoundOn = true;

function bsToggleSound() {
  bsSoundOn = !bsSoundOn;
  document.getElementById('bs-sound-btn').textContent = bsSoundOn ? '🔊' : '🔇';
}

function bsGameOver() {
  bsRunning = false;
  if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
  document.getElementById('bs-final-score').textContent = bsScore.toLocaleString();
  document.getElementById('bs-gameover').classList.remove('hidden');

  ZikGames.saveScoreAndShowResults('Bubble Shooter', bsScore, {
    difficulty: bsLevel,
    difficultyLabel: BS_DIFFICULTIES[bsLevel]?.label || 'Easy',
    multiplier: BS_DIFFICULTIES[bsLevel]?.multiplier || bsLevel,
    gameMode: 'single',
    survivalRun: true,
  });
}

// Load best score
fetch('/ZikGames/api/leaderboard.php?game=Bubble+Shooter&limit=1')
  .then(r => r.json())
  .then(d => { if (d.top && d.top[0]) bsBestScore = parseInt(d.top[0].best_score, 10) || 0; });
</script>
</body>
</html>


