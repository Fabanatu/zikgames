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
<title>Fruit Slice – FabGames</title>
<link rel="stylesheet" href="/ZikGames/assets/css/style.css">
</head>
<body>
<div class="game-wrapper">
  <div class="game-hud">
    <a href="/ZikGames/dashboard.php" style="font-size:1.4rem; padding:4px;" id="exit-fruit-btn">🔙</a>
    <div style="display:flex; align-items:center; gap:16px;">
      <div style="text-align:center;">
        <div style="font-size:0.65rem; color:var(--text-muted); text-transform:uppercase;">Score</div>
        <div style="font-size:1.4rem; font-weight:900; color:var(--accent);" id="fs-score">0</div>
      </div>
      <div style="text-align:center;">
        <div style="font-size:0.65rem; color:var(--text-muted); text-transform:uppercase;">Best</div>
        <div style="font-size:1.4rem; font-weight:900; color:var(--primary-light);" id="fs-best">0</div>
      </div>
      <div style="text-align:center;">
        <div style="font-size:0.65rem; color:var(--text-muted); text-transform:uppercase;">Lives</div>
        <div style="font-size:1.4rem; font-weight:900; color:#EF4444;" id="fs-lives">❤️❤️❤️</div>
      </div>
      <div style="text-align:center;">
        <div style="font-size:0.65rem; color:var(--text-muted); text-transform:uppercase;">Combo</div>
        <div style="font-size:1.4rem; font-weight:900; color:var(--neon-green);" id="fs-combo">x1</div>
      </div>
    </div>
    <div style="display:flex; gap:8px;">
      <button class="control-btn" style="background:rgba(245,158,11,0.2); color:#FCD34D;" onclick="fsToggleSound()" id="fs-sound-btn">🔊</button>
      <button class="control-btn" style="background:rgba(124,58,237,0.2); color:var(--primary-light);" onclick="fsPause()" id="fs-pause-btn">⏸</button>
    </div>
  </div>

  <div class="game-canvas-wrap">
    <canvas id="fsCanvas"></canvas>
    <div class="game-over-screen" id="fs-start">
      <div style="font-size:5rem;">🍉</div>
      <h2>Fruit Slice</h2>
      <p style="color:var(--text-muted); margin-bottom:10px;">Swipe to slice fruits! Avoid bombs! 💣<br>Select a difficulty to start:</p>
      <div style="display:flex; flex-direction:column; gap:8px; width:100%; max-width:280px; margin:0 auto;">
        <button class="btn btn-primary btn-sm" onclick="fsStart(1)" style="background:#10B981; border:none; padding:10px;">Easy</button>
        <button class="btn btn-primary btn-sm" onclick="fsStart(2)" style="background:#F59E0B; border:none; padding:10px;">Intermediate</button>
        <button class="btn btn-primary btn-sm" onclick="fsStart(3)" style="background:#FF6B00; border:none; padding:10px;">Hard</button>
        <button class="btn btn-primary btn-sm" onclick="fsStart(4)" style="background:#EF4444; border:none; padding:10px;">Very Hard</button>
      </div>
    </div>
    <div class="game-over-screen hidden" id="fs-gameover">
      <div style="font-size:4rem;">💥</div>
      <h2>Game Over!</h2>
      <div class="score-display" id="fs-final">0</div>
      <div style="display:flex; gap:12px;">
        <button class="btn btn-primary" onclick="fsShowLevelSelect()">🔄 Play Again</button>
        <a href="/ZikGames/dashboard.php" class="btn btn-outline" id="fs-home-btn">🏠 Home</a>
      </div>
    </div>
  </div>
  <div class="game-controls">
    <div style="font-size:0.85rem; color:var(--text-muted); text-align:center;">✂️ Swipe your finger across fruits to slice them!</div>
  </div>
</div>

<script src="/ZikGames/assets/js/common.js"></script>
<script>
const fsCanvas = document.getElementById('fsCanvas');
const fsCtx    = fsCanvas.getContext('2d');
const FS_DIFFICULTIES = {
  1: { label: 'Easy', multiplier: 1 },
  2: { label: 'Intermediate', multiplier: 2 },
  3: { label: 'Hard', multiplier: 3 },
  4: { label: 'Very Hard', multiplier: 4 },
};

const FRUITS = ['🍉','🍊','🍋','🍎','🍇','🥭','🍓','🍍','🫐','🍑'];
let fsW, fsH, fsScore=0, fsLives=3, fsCombo=1, fsPaused=false, fsRunning=false;
let fruits=[], bombs=[], slices=[], trailPoints=[], spawnTimer=0, gameSpeed=1;
let slashing=false, lastSlashX, lastSlashY, slashTrail=[];

function fsResize() {
  const wrap = fsCanvas.parentElement;
  fsW = fsCanvas.width  = wrap.clientWidth;
  fsH = fsCanvas.height = wrap.clientHeight;
}

class FruitObj {
  constructor() {
    this.x  = fsW * 0.15 + Math.random() * fsW * 0.7;
    this.y  = fsH + 60;
    this.vy = -(Math.random() * 12 + 10) * gameSpeed;
    this.vx = (Math.random() - 0.5) * 4;
    this.vy0 = this.vy;
    this.g  = 0.25;
    this.emoji = FRUITS[Math.floor(Math.random() * FRUITS.length)];
    this.r  = 28 + Math.random() * 10;
    this.rotation = 0;
    this.rotSpeed = (Math.random() - 0.5) * 0.15;
    this.sliced = false;
    this.sliceTimer = 0;
    this.pieces = [];
    this.color = `hsl(${Math.random()*360},80%,55%)`;
  }
  update() {
    this.vy += this.g;
    this.x  += this.vx;
    this.y  += this.vy;
    this.rotation += this.rotSpeed;
    if (this.sliced) { this.sliceTimer++; }
  }
  draw() {
    if (this.sliced) {
      // Draw two halves flying apart
      this.pieces.forEach((p, i) => {
        p.x += p.vx;
        p.y += p.vy;
        p.vy += 0.4;
        p.alpha = Math.max(0, 1 - this.sliceTimer / 30);
        fsCtx.save();
        fsCtx.globalAlpha = p.alpha;
        fsCtx.translate(p.x, p.y);
        fsCtx.font = (this.r * 1.4) + 'px serif';
        fsCtx.textAlign = 'center';
        fsCtx.textBaseline = 'middle';
        fsCtx.fillText(this.emoji, 0, 0);
        fsCtx.restore();
      });
      return;
    }
    fsCtx.save();
    fsCtx.translate(this.x, this.y);
    fsCtx.rotate(this.rotation);
    // Glow
    fsCtx.shadowColor = this.color;
    fsCtx.shadowBlur  = 16;
    fsCtx.font = (this.r * 2) + 'px serif';
    fsCtx.textAlign = 'center';
    fsCtx.textBaseline = 'middle';
    fsCtx.fillText(this.emoji, 0, 0);
    fsCtx.restore();
  }
  slice() {
    this.sliced = true;
    this.pieces = [
      {x:this.x-10, y:this.y, vx:-3, vy:-4, alpha:1},
      {x:this.x+10, y:this.y, vx:3,  vy:-4, alpha:1}
    ];
  }
}

class BombObj {
  constructor() {
    this.x  = fsW * 0.1 + Math.random() * fsW * 0.8;
    this.y  = fsH + 60;
    this.vy = -(Math.random() * 10 + 8) * gameSpeed;
    this.vx = (Math.random() - 0.5) * 3;
    this.g  = 0.25;
    this.r  = 26;
    this.hit = false;
    this.pulse = 0;
  }
  update() {
    this.vy += this.g;
    this.x  += this.vx;
    this.y  += this.vy;
    this.pulse += 0.1;
  }
  draw() {
    const s = 1 + Math.sin(this.pulse) * 0.08;
    fsCtx.save();
    fsCtx.translate(this.x, this.y);
    fsCtx.scale(s, s);
    fsCtx.shadowColor = '#FF006E';
    fsCtx.shadowBlur = 20;
    fsCtx.font = (this.r * 2) + 'px serif';
    fsCtx.textAlign = 'center';
    fsCtx.textBaseline = 'middle';
    fsCtx.fillText('💣', 0, 0);
    fsCtx.restore();
  }
}

class SliceParticle {
  constructor(x, y, color) {
    this.x=x; this.y=y;
    this.vx=(Math.random()-0.5)*12;
    this.vy=(Math.random()-0.5)*12 - 4;
    this.life=1;
    this.r=Math.random()*6+2;
    this.color=color;
  }
  update() {
    this.x+=this.vx; this.y+=this.vy;
    this.vy+=0.3; this.life-=0.04;
  }
  draw() {
    fsCtx.save();
    fsCtx.globalAlpha=this.life;
    fsCtx.beginPath();
    fsCtx.arc(this.x,this.y,this.r,0,Math.PI*2);
    fsCtx.fillStyle=this.color;
    fsCtx.shadowColor=this.color;
    fsCtx.shadowBlur=8;
    fsCtx.fill();
    fsCtx.restore();
  }
}

function fsShowLevelSelect() {
  document.getElementById('fs-start').classList.remove('hidden');
  document.getElementById('fs-gameover').classList.add('hidden');
}

let selectedLevel = 1;

function fsStart(lvl = 1) {
  selectedLevel = lvl;
  fsScore=0; fsLives=3; fsCombo=1; fruits=[]; bombs=[]; slices=[]; slashTrail=[];
  spawnTimer=0; gameSpeed=1; fsRunning=true; fsPaused=false;
  document.getElementById('fs-score').textContent='0';
  document.getElementById('fs-lives').textContent='❤️❤️❤️';
  document.getElementById('fs-combo').textContent='x1';
  document.getElementById('fs-start').classList.add('hidden');
  document.getElementById('fs-gameover').classList.add('hidden');
  requestAnimationFrame(fsLoop);
}

let fsMissed=0, fsFrame=0;
function fsLoop() {
  if (!fsRunning || fsPaused) return;
  fsFrame++;
  spawnTimer--;
  if (spawnTimer <= 0) {
    spawnTimer = Math.max(30, (80 - selectedLevel * 8) - fsScore/50);
    gameSpeed = (1 + selectedLevel * 0.2) + fsScore/500;
    fruits.push(new FruitObj());
    if (Math.random() < (0.1 + selectedLevel * 0.05)) bombs.push(new BombObj());
  }

  fsCtx.clearRect(0, 0, fsW, fsH);
  // BG
  const grad=fsCtx.createLinearGradient(0,0,0,fsH);
  grad.addColorStop(0,'#0D0B1E'); grad.addColorStop(1,'#0D1A0D');
  fsCtx.fillStyle=grad; fsCtx.fillRect(0,0,fsW,fsH);

  // Slash trail
  if (slashTrail.length > 1) {
    fsCtx.beginPath();
    fsCtx.moveTo(slashTrail[0].x, slashTrail[0].y);
    for (let i=1; i<slashTrail.length; i++) {
      fsCtx.lineTo(slashTrail[i].x, slashTrail[i].y);
    }
    fsCtx.strokeStyle='rgba(255,255,255,0.6)';
    fsCtx.lineWidth=3;
    fsCtx.lineCap='round';
    fsCtx.lineJoin='round';
    fsCtx.shadowColor='#00D4FF';
    fsCtx.shadowBlur=12;
    fsCtx.stroke();
    slashTrail = slashTrail.slice(-8);
  }

  // Fruits
  fruits.forEach(f => f.update());
  fruits.forEach(f => f.draw());
  slices.forEach(p => { p.update(); p.draw(); });
  slices = slices.filter(p => p.life > 0);

  // Bombs
  bombs.forEach(b => { b.update(); b.draw(); });

  // Check missed fruits (fallen off screen)
  const missedCount = fruits.filter(f => !f.sliced && f.y > fsH + 80).length;
  fruits = fruits.filter(f => f.sliced ? f.sliceTimer < 35 : f.y < fsH + 80);
  bombs  = bombs.filter(b => !b.hit && b.y < fsH + 80);
  if (missedCount > 0) {
    fsLives -= missedCount;
    updateLives();
    if (fsLives <= 0) { fsGameOver(); return; }
  }

  requestAnimationFrame(fsLoop);
}

function checkSliceHit(x, y, px, py) {
  fruits.forEach(f => {
    if (f.sliced) return;
    if (Math.hypot(f.x-x, f.y-y) < f.r + 8 || Math.hypot(f.x-px, f.y-py) < f.r + 8) {
      f.slice();
      const pts = 10 * fsCombo * selectedLevel;
      fsScore += pts;
      fsCombo = Math.min(fsCombo + 1, 8);
      document.getElementById('fs-score').textContent=fsScore.toLocaleString();
      document.getElementById('fs-combo').textContent='x'+fsCombo;
      for (let i=0; i<12; i++) slices.push(new SliceParticle(f.x,f.y,f.color));
      if (navigator.vibrate) navigator.vibrate(30);
      // Show points popup
      showPoints(f.x, f.y, '+'+pts);
    }
  });
  bombs.forEach(b => {
    if (b.hit) return;
    if (Math.hypot(b.x-x, b.y-y) < b.r + 8) {
      b.hit = true;
      fsLives -= 2;
      fsCombo = 1;
      document.getElementById('fs-combo').textContent='x1';
      updateLives();
      if (navigator.vibrate) navigator.vibrate([100,50,100]);
      if (fsLives <= 0) { fsGameOver(); }
    }
  });
}

const popups=[];
function showPoints(x, y, text) {
  popups.push({x, y, text, life:1, vy:-2});
}

function updateLives() {
  const hearts='❤️'.repeat(Math.max(0,fsLives));
  document.getElementById('fs-lives').textContent=hearts||'💔';
}

function fsPause() {
  fsPaused=!fsPaused;
  document.getElementById('fs-pause-btn').textContent=fsPaused?'▶':'⏸';
  if (!fsPaused) requestAnimationFrame(fsLoop);
}

function fsGameOver() {
  fsRunning=false;
  if(navigator.vibrate)navigator.vibrate([200,100,200]);
  if(fsScore>fsBestScore){
    fsBestScore=fsScore;
    document.getElementById('fs-best').textContent=fsBestScore;
  }
  document.getElementById('fs-final').textContent=fsScore.toLocaleString();
  document.getElementById('fs-gameover').classList.remove('hidden');
  ZikGames.saveScoreAndShowResults('Fruit Slice', fsScore, {
    difficulty: selectedLevel,
    difficultyLabel: FS_DIFFICULTIES[selectedLevel]?.label || 'Easy',
    multiplier: FS_DIFFICULTIES[selectedLevel]?.multiplier || selectedLevel,
    gameMode: 'single',
    survivalRun: true,
  });
}

// Pointer events for slicing
fsCanvas.addEventListener('pointerdown', e=>{
  slashing=true;
  const rect=fsCanvas.getBoundingClientRect();
  lastSlashX=e.clientX-rect.left;
  lastSlashY=e.clientY-rect.top;
  slashTrail=[];
});
fsCanvas.addEventListener('pointermove', e=>{
  if (!slashing) return;
  const rect=fsCanvas.getBoundingClientRect();
  const cx=e.clientX-rect.left, cy=e.clientY-rect.top;
  slashTrail.push({x:cx,y:cy});
  checkSliceHit(cx,cy,lastSlashX,lastSlashY);
  lastSlashX=cx; lastSlashY=cy;
});
fsCanvas.addEventListener('pointerup', ()=>{ slashing=false; slashTrail=[]; fsCombo=Math.max(1,fsCombo-1); });

window.addEventListener('resize', fsResize);
fsResize();

let fsBestScore = 0;
let fsSoundOn = true;

function fsToggleSound() {
  fsSoundOn = !fsSoundOn;
  document.getElementById('fs-sound-btn').textContent = fsSoundOn ? '🔊' : '🔇';
}

// Load best score
fetch('/ZikGames/api/leaderboard.php?game=Fruit+Slice&limit=1')
  .then(r=>r.json()).then(d=>{ if(d.top && d.top[0]) { fsBestScore = parseInt(d.top[0].best_score, 10) || 0; document.getElementById('fs-best').textContent = fsBestScore; } });
</script>
</body>
</html>


