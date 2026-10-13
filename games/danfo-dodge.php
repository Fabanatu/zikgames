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
<title>Danfo Dodge – FabGames Nigeria</title>
<link rel="stylesheet" href="/ZikGames/assets/css/style.css">
<style>
  .dd-street { background: linear-gradient(180deg, #1a1a2e 0%, #0f3460 100%); }
</style>
</head>
<body>
<div class="game-wrapper">
  <div class="game-hud">
    <a href="/ZikGames/dashboard.php" style="font-size:1.4rem; padding:4px;" id="exit-dd-btn">🔙</a>
    <div style="display:flex; align-items:center; gap:20px;">
      <div style="text-align:center;">
        <div style="font-size:0.65rem; color:var(--text-muted); text-transform:uppercase;">Score</div>
        <div style="font-size:1.4rem; font-weight:900; color:var(--accent);" id="dd-score">0</div>
      </div>
      <div style="text-align:center;">
        <div style="font-size:0.65rem; color:var(--text-muted); text-transform:uppercase;">Speed</div>
        <div style="font-size:1.4rem; font-weight:900; color:var(--neon-green);" id="dd-speed">1x</div>
      </div>
      <div style="text-align:center;">
        <div style="font-size:0.65rem; color:var(--text-muted); text-transform:uppercase;">Best</div>
        <div style="font-size:1.4rem; font-weight:900; color:var(--primary-light);" id="dd-best">0</div>
      </div>
    </div>
    <div style="display:flex; gap:8px;">
      <button class="control-btn" style="background:rgba(245,158,11,0.2); color:#FCD34D;" onclick="ddToggleSound()" id="dd-sound-btn">🔊</button>
      <button class="control-btn" style="background:rgba(124,58,237,0.2);" onclick="ddPause()" id="dd-pause-btn">⏸</button>
    </div>
  </div>

  <div class="game-canvas-wrap">
    <canvas id="ddCanvas"></canvas>
    <div class="game-over-screen" id="dd-start">
      <div style="font-size:5rem; animation:float 2s ease-in-out infinite;">🚌</div>
      <h2>Danfo Dodge!</h2>
      <p style="color:var(--text-muted); margin-bottom:10px;">🇳🇬 Navigate Lagos traffic!<br>Dodge traffic, roadblocks & potholes!<br>Select a difficulty to start:</p>
      <div style="display:flex; flex-direction:column; gap:8px; width:100%; max-width:280px; margin:0 auto;">
        <button class="btn btn-accent btn-sm" onclick="ddStart(1)" style="padding:10px;">Easy</button>
        <button class="btn btn-accent btn-sm" onclick="ddStart(2)" style="padding:10px;">Intermediate</button>
        <button class="btn btn-accent btn-sm" onclick="ddStart(3)" style="padding:10px;">Hard</button>
        <button class="btn btn-accent btn-sm" onclick="ddStart(4)" style="padding:10px;">Very Hard</button>
      </div>
    </div>
    <div class="game-over-screen hidden" id="dd-gameover">
      <div style="font-size:4rem;">💥</div>
      <h2>WAHALA!</h2>
      <p style="color:var(--text-muted); margin-bottom:8px;">You got hit by Lagos traffic!</p>
      <div class="score-display" id="dd-final">0</div>
      <div id="dd-newbest" style="color:var(--neon-green);font-weight:700;display:none;">🏆 New Best Score!</div>
      <div style="display:flex; gap:12px; flex-wrap:wrap; justify-content:center; margin-top:16px;">
        <button class="btn btn-primary" onclick="ddShowLevelSelect()">🔄 Try Again</button>
        <a href="/ZikGames/dashboard.php" class="btn btn-outline" id="dd-home-btn">🏠 Home</a>
      </div>
    </div>
  </div>

  <!-- Controls -->
  <div class="game-controls" style="justify-content:space-around;">
    <button class="btn btn-primary" style="flex:1; font-size:1.5rem; padding:18px; border-radius:16px; max-width:140px;"
            onpointerdown="ddMoveLeft()" onpointerup="ddStopMove()" id="dd-left-btn">◀ LEFT</button>
    <button class="btn btn-primary" style="flex:1; font-size:1.5rem; padding:18px; border-radius:16px; max-width:140px;"
            onpointerdown="ddMoveRight()" onpointerup="ddStopMove()" id="dd-right-btn">RIGHT ▶</button>
  </div>
</div>

<script src="/ZikGames/assets/js/common.js"></script>
<script>
const ddCanvas = document.getElementById('ddCanvas');
const ddCtx    = ddCanvas.getContext('2d');
const DD_DIFFICULTIES = {
  1: { label: 'Easy', multiplier: 1 },
  2: { label: 'Intermediate', multiplier: 2 },
  3: { label: 'Hard', multiplier: 3 },
  4: { label: 'Very Hard', multiplier: 4 },
};

let ddW, ddH, ddScore=0, ddBest=0, ddRunning=false, ddPaused_=false;
let ddSpeed=3, ddFrame=0, ddMoveDir=0;
const LANE_COUNT=4;
let lanes=[], player={lane:1, x:0, y:0, w:36, h:48, targetX:0, transitioning:false};
let obstacles=[], coins=[], roadLines=[], spawnTimer=0, scoreTimer=0;

const OBSTACLES=[
  {emoji:'🚌',name:'Danfo Bus',w:52,h:52,score:-1,color:'#F59E0B'},
  {emoji:'🏍️',name:'Okada',w:40,h:40,score:-1,color:'#EF4444'},
  {emoji:'🛺',name:'Keke',w:44,h:44,score:-1,color:'#10B981'},
  {emoji:'🚧',name:'Roadblock',w:48,h:48,score:-1,color:'#EF4444'},
  {emoji:'🕳️',name:'Pothole',w:44,h:30,score:-1,color:'#78350F'},
  {emoji:'🐕',name:'Agbero Dog',w:36,h:36,score:-1,color:'#92400E'},
];

function ddResize() {
  const wrap=ddCanvas.parentElement;
  ddW=ddCanvas.width=wrap.clientWidth;
  ddH=ddCanvas.height=wrap.clientHeight;
  const laneW=ddW/LANE_COUNT;
  lanes=Array.from({length:LANE_COUNT},(_,i)=>({x:i*laneW+laneW/2,w:laneW}));
  player.y=ddH-100;
  player.x=lanes[player.lane].x-player.w/2;
  player.targetX=player.x;
  // Road lines
  roadLines=Array.from({length:8},(_,i)=>({y:i*(ddH/8)}));
}

function ddShowLevelSelect() {
  document.getElementById('dd-start').classList.remove('hidden');
  document.getElementById('dd-gameover').classList.add('hidden');
}

let selectedLevel = 1;

function ddStart(lvl = 1) {
  selectedLevel = lvl;
  ddScore=0;ddSpeed=2 + selectedLevel;ddFrame=0;ddRunning=true;ddPaused_=false;
  obstacles=[];coins=[];spawnTimer=0;scoreTimer=0;
  player.lane=1;player.transitioning=false;
  document.getElementById('dd-score').textContent='0';
  document.getElementById('dd-speed').textContent='1x';
  document.getElementById('dd-start').classList.add('hidden');
  document.getElementById('dd-gameover').classList.add('hidden');
  ddResize();
  requestAnimationFrame(ddLoop);
}

function ddLoop() {
  if(!ddRunning||ddPaused_)return;
  ddFrame++;
  ddSpeed=(2+selectedLevel)+Math.floor(ddFrame/300)*0.5;
  document.getElementById('dd-speed').textContent=(1+Math.floor(ddFrame/300)*0.17).toFixed(1)+'x';

  // Move player
  if(!player.transitioning&&ddMoveDir!==0){
    const newLane=Math.max(0,Math.min(LANE_COUNT-1,player.lane+ddMoveDir));
    if(newLane!==player.lane){
      player.lane=newLane;
      player.targetX=lanes[player.lane].x-player.w/2;
      player.transitioning=true;
    }
  }
  if(player.transitioning){
    const dx=player.targetX-player.x;
    player.x+=dx*0.25;
    if(Math.abs(dx)<2){player.x=player.targetX;player.transitioning=false;}
  }

  // Spawn obstacles
  spawnTimer--;
  if(spawnTimer<=0){
    spawnTimer=Math.max(20,(90-selectedLevel*10)-Math.floor(ddFrame/200)*5);
    const lane=Math.floor(Math.random()*LANE_COUNT);
    const obs=OBSTACLES[Math.floor(Math.random()*OBSTACLES.length)];
    obstacles.push({...obs, x:lanes[lane].x-obs.w/2, y:-obs.h, lane, speed:ddSpeed});
    // Coin chance
    if(Math.random()<0.3){
      const coinLane=Math.floor(Math.random()*LANE_COUNT);
      coins.push({x:lanes[coinLane].x-15,y:-30,w:30,h:30,speed:ddSpeed*0.8});
    }
  }

  // Score by time
  scoreTimer++;
  if(scoreTimer>=10){
    ddScore += selectedLevel;
    scoreTimer=0;
    document.getElementById('dd-score').textContent=ddScore.toLocaleString();
  }

  // Draw
  ddCtx.clearRect(0,0,ddW,ddH);

  // Road background
  const roadGrad=ddCtx.createLinearGradient(0,0,0,ddH);
  roadGrad.addColorStop(0,'#1a1a2e');
  roadGrad.addColorStop(1,'#0f3460');
  ddCtx.fillStyle=roadGrad;
  ddCtx.fillRect(0,0,ddW,ddH);

  // Lane dividers
  ddCtx.strokeStyle='rgba(255,255,255,0.15)';
  ddCtx.lineWidth=2;
  ddCtx.setLineDash([30,20]);
  for(let i=1;i<LANE_COUNT;i++){
    ddCtx.beginPath();
    ddCtx.moveTo(lanes[i].x-lanes[i].w/2,0);
    ddCtx.lineTo(lanes[i].x-lanes[i].w/2,ddH);
    ddCtx.stroke();
  }
  ddCtx.setLineDash([]);

  // Road edge stripes
  ddCtx.fillStyle='rgba(245,158,11,0.6)';
  ddCtx.fillRect(0,0,8,ddH);
  ddCtx.fillRect(ddW-8,0,8,ddH);

  // Moving road lines (dashes)
  roadLines.forEach(l=>{
    l.y=(l.y+ddSpeed*1.5)%ddH;
  });

  // Coins
  coins.forEach(c=>{
    c.y+=c.speed;
    ddCtx.font='26px serif';ddCtx.textAlign='center';ddCtx.textBaseline='middle';
    ddCtx.fillText('🪙',c.x+15,c.y+15);
    // Collect
    if(c.y+c.h>player.y&&c.y<player.y+player.h&&c.x+c.w>player.x&&c.x<player.x+player.w){
      c.y=ddH+100;ddScore += 5 * selectedLevel;document.getElementById('dd-score').textContent=ddScore.toLocaleString();
    }
  });
  coins=coins.filter(c=>c.y<ddH+60);

  // Obstacles
  obstacles.forEach(obs=>{
    obs.y+=obs.speed;
    // Shadow
    ddCtx.fillStyle='rgba(0,0,0,0.3)';
    ddCtx.beginPath();
    ddCtx.ellipse(obs.x+obs.w/2,obs.y+obs.h+5,obs.w/2*0.8,8,0,0,Math.PI*2);
    ddCtx.fill();
    ddCtx.font=(obs.h*1.1)+'px serif';
    ddCtx.textAlign='center';ddCtx.textBaseline='middle';
    ddCtx.shadowColor=obs.color;ddCtx.shadowBlur=16;
    ddCtx.fillText(obs.emoji,obs.x+obs.w/2,obs.y+obs.h/2);
    ddCtx.shadowBlur=0;
    // Collision
    const margin=8;
    if(obs.y+margin<player.y+player.h && obs.y+obs.h-margin>player.y &&
       obs.x+margin<player.x+player.w && obs.x+obs.w-margin>player.x){
      ddGameOver();return;
    }
  });
  obstacles=obstacles.filter(o=>o.y<ddH+80);

  // Draw player (high-fidelity Lagos Danfo bus)
  drawDanfoBus(ddCtx, player.x, player.y, player.w, player.h);
}

function drawDanfoBus(ctx, x, y, w, h) {
  ctx.save();
  ctx.translate(x, y);

  // Wheels (4 wheels on corners)
  ctx.fillStyle = '#111';
  // Front left wheel
  ctx.fillRect(-2, 4, 4, 10);
  // Front right wheel
  ctx.fillRect(w - 2, 4, 4, 10);
  // Back left wheel
  ctx.fillRect(-2, h - 14, 4, 10);
  // Back right wheel
  ctx.fillRect(w - 2, h - 14, 4, 10);

  // Side mirrors
  ctx.fillStyle = '#EAB308'; // Yellow
  ctx.fillRect(-5, 8, 4, 3);
  ctx.fillRect(w + 1, 8, 4, 3);

  // Bus Main Body (Yellow)
  ctx.fillStyle = '#EAB308'; // Danfo Yellow
  ctx.strokeStyle = '#111';
  ctx.lineWidth = 1.5;
  
  // Rounded rectangle for body
  ctx.beginPath();
  ctx.roundRect(0, 0, w, h, 8);
  ctx.fill();
  ctx.stroke();

  // Distinctive twin black stripes down the center (top-down view)
  ctx.fillStyle = '#111'; // Black stripes
  ctx.fillRect(w * 0.25, 0, w * 0.1, h);
  ctx.fillRect(w * 0.65, 0, w * 0.1, h);

  // Windshield (Front glass)
  ctx.fillStyle = '#A5F3FC'; // Ice blue glass
  ctx.beginPath();
  ctx.roundRect(w * 0.1, 4, w * 0.8, h * 0.18, 3);
  ctx.fill();
  ctx.stroke();

  // Windshield glare / reflection highlight
  ctx.strokeStyle = '#FFF';
  ctx.lineWidth = 1.5;
  ctx.beginPath();
  ctx.moveTo(w * 0.2, 6);
  ctx.lineTo(w * 0.4, 10);
  ctx.stroke();

  // Rear windshield (Back glass)
  ctx.fillStyle = '#A5F3FC';
  ctx.beginPath();
  ctx.roundRect(w * 0.1, h - 10, w * 0.8, 6, 2);
  ctx.fill();
  ctx.stroke();

  // Headlights (Front glow)
  ctx.fillStyle = '#FFFDE7';
  ctx.shadowColor = '#FFFDE7';
  ctx.shadowBlur = 10;
  ctx.beginPath();
  ctx.arc(w * 0.15, 0, 3, 0, Math.PI * 2);
  ctx.arc(w * 0.85, 0, 3, 0, Math.PI * 2);
  ctx.fill();

  // Taillights (Rear red glow)
  ctx.shadowBlur = 0;
  ctx.fillStyle = '#EF4444';
  ctx.fillRect(w * 0.1, h - 2, 6, 2);
  ctx.fillRect(w * 0.9 - 6, h - 2, 6, 2);

  ctx.restore();

  // Nigerian road elements
  if(ddFrame%200===0){
    ddCtx.font='14px serif';ddCtx.fillStyle='rgba(255,255,255,0.1)';
    ddCtx.fillText('LAGOS → IBADAN',ddW/2,20);
  }

  requestAnimationFrame(ddLoop);
}

function ddGameOver() {
  ddRunning=false;
  if(navigator.vibrate)navigator.vibrate([200,100,200]);
  if(ddScore>ddBest){
    ddBest=ddScore;
    document.getElementById('dd-best').textContent=ddBest;
    document.getElementById('dd-newbest').style.display='block';
  } else {
    document.getElementById('dd-newbest').style.display='none';
  }
  document.getElementById('dd-final').textContent=ddScore.toLocaleString();
  document.getElementById('dd-gameover').classList.remove('hidden');
  ZikGames.saveScoreAndShowResults('Danfo Dodge', ddScore, {
    difficulty: selectedLevel,
    difficultyLabel: DD_DIFFICULTIES[selectedLevel]?.label || 'Easy',
    multiplier: DD_DIFFICULTIES[selectedLevel]?.multiplier || selectedLevel,
    gameMode: 'single',
    survivalRun: true,
  });
}

function ddMoveLeft(){ddMoveDir=-1;}
function ddMoveRight(){ddMoveDir=1;}
function ddStopMove(){ddMoveDir=0;}
function ddPause(){
  ddPaused_=!ddPaused_;
  document.getElementById('dd-pause-btn').textContent=ddPaused_?'▶':'⏸';
  if(!ddPaused_)requestAnimationFrame(ddLoop);
}

// Keyboard
window.addEventListener('keydown',e=>{
  if(e.key==='ArrowLeft')ddMoveDir=-1;
  if(e.key==='ArrowRight')ddMoveDir=1;
});
window.addEventListener('keyup',e=>{
  if(e.key==='ArrowLeft'||e.key==='ArrowRight')ddMoveDir=0;
});

// Touch swipe
let touchStartX=0;
ddCanvas.addEventListener('touchstart',e=>{touchStartX=e.touches[0].clientX;});
ddCanvas.addEventListener('touchend',e=>{
  if(!ddRunning) return;
  const dx=e.changedTouches[0].clientX-touchStartX;
  if(dx<-30)ddMoveDir=-1;
  if(dx>30)ddMoveDir=1;
  setTimeout(()=>ddMoveDir=0, 200);
});

window.addEventListener('resize',ddResize);
ddResize();

let ddSoundOn = true;
function ddToggleSound() {
  ddSoundOn = !ddSoundOn;
  document.getElementById('dd-sound-btn').textContent = ddSoundOn ? '🔊' : '🔇';
}

// Load best score
fetch('/ZikGames/api/leaderboard.php?game=Danfo+Dodge&limit=1')
  .then(r=>r.json()).then(d=>{ if(d.top && d.top[0]) { ddBest = parseInt(d.top[0].best_score, 10) || 0; document.getElementById('dd-best').textContent = ddBest; } });
</script>
</body>
</html>


