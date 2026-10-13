<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/app_settings.php';
requireUserLogin();

$tttDrawLimit = max(1, min(50, intval(getAppSetting('ttt_draw_limit', '10'))));
$tttLossLimit = max(1, min(20, intval(getAppSetting('ttt_loss_limit', '5'))));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<title>Tic Tac Toe - ZikGames</title>
<link rel="stylesheet" href="/ZikGames/assets/css/style.css">
<style>
  .game-wrapper.ttt-layout {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    overflow-y: auto;
    overflow-x: hidden;
  }

  .ttt-game-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    width: 100%;
    min-height: 100%;
    padding: 18px 16px 120px;
  }

  .ttt-board-wrapper {
    width: 100%;
    max-width: 380px;
    padding: 18px 16px;
    border-radius: var(--radius-lg);
    background: rgba(255, 255, 255, 0.78);
    border: 1px solid rgba(193, 18, 31, 0.14);
    backdrop-filter: blur(18px);
    box-shadow: 0 24px 60px rgba(124, 9, 19, 0.14);
  }

  .ttt-board {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
  }

  .ttt-cell {
    aspect-ratio: 1;
    border-radius: 18px;
    border: 1px solid rgba(193, 18, 31, 0.16);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.95), rgba(255, 244, 244, 0.92));
    color: var(--text);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    font-weight: 800;
    cursor: pointer;
    transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    user-select: none;
  }

  .ttt-cell:hover {
    transform: translateY(-2px);
    border-color: rgba(193, 18, 31, 0.38);
    box-shadow: 0 12px 25px rgba(193, 18, 31, 0.14);
  }

  .ttt-cell.x {
    color: #c1121f;
  }

  .ttt-cell.o {
    color: #1d4ed8;
  }

  .ttt-cell.win {
    background: linear-gradient(180deg, rgba(198, 40, 40, 0.1), rgba(193, 18, 31, 0.2));
    border-color: rgba(193, 18, 31, 0.55);
    box-shadow: 0 0 0 2px rgba(193, 18, 31, 0.12);
  }

  .ttt-status-bar {
    margin-top: 16px;
    text-align: center;
    font-weight: 700;
    color: var(--text);
  }

  .ttt-meta-row {
    margin-top: 12px;
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
  }

  .ttt-pill {
    padding: 8px 12px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(193, 18, 31, 0.12);
    color: var(--text-muted);
    font-size: 0.84rem;
    font-weight: 700;
  }

  .ttt-pill strong {
    color: var(--text);
  }

  .ttt-pill.difficulty-pill {
    border-color: rgba(29, 78, 216, 0.18);
    background: rgba(239, 246, 255, 0.96);
  }

  .ttt-help {
    width: min(100%, 380px);
    margin-top: 14px;
    padding: 12px 14px;
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.88);
    border: 1px solid rgba(193, 18, 31, 0.12);
    color: var(--text-muted);
    text-align: center;
    line-height: 1.45;
    font-size: 0.88rem;
  }

  .ttt-help strong {
    color: var(--text);
  }

  .ttt-alert {
    width: min(100%, 460px);
    margin: 0 auto 14px;
    padding: 14px 16px;
    border-radius: 18px;
    border: 1px solid rgba(193, 18, 31, 0.14);
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 20px 45px rgba(124, 9, 19, 0.1);
  }

  .ttt-alert h3 {
    margin-bottom: 6px;
    font-size: 1rem;
  }

  .ttt-alert p {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.92rem;
  }

  .ttt-alert-actions {
    display: flex;
    gap: 10px;
    margin-top: 12px;
    justify-content: center;
    flex-wrap: wrap;
  }

  .ttt-hud {
    justify-content: center;
  }

  .ttt-hud-stats {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 18px;
    flex-wrap: wrap;
    width: 100%;
  }

  .ttt-stat {
    text-align: center;
    min-width: 56px;
  }

  .ttt-start-card {
    width: min(100%, 380px);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
  }

  .ttt-start-copy {
    color: var(--text-muted);
    text-align: center;
    line-height: 1.45;
    font-size: 0.94rem;
    margin: 0;
  }

  .ttt-start-actions {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 8px;
  }

  .ttt-start-actions .btn {
    width: 100%;
    min-height: 52px;
    border-radius: 18px;
    font-size: 1rem;
    font-weight: 800;
    box-shadow: none;
  }

  .ttt-start-actions .btn-outline {
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid rgba(193, 18, 31, 0.18);
    color: #6b2330;
  }

  .ttt-start-actions .btn-outline:hover {
    background: rgba(255, 246, 246, 1);
    border-color: rgba(193, 18, 31, 0.32);
  }

  .ttt-start-actions .btn-primary {
    background: linear-gradient(180deg, #c1121f 0%, #8d1020 100%);
    border: none;
    box-shadow: 0 14px 26px rgba(141, 16, 32, 0.2);
  }

  .ttt-start-actions .btn-accent {
    background: linear-gradient(180deg, #3b82f6 0%, #1d4ed8 100%);
    border: none;
    box-shadow: 0 12px 22px rgba(29, 78, 216, 0.18);
  }

  .ttt-footer {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 10px 14px calc(10px + env(safe-area-inset-bottom, 0px));
    background: rgba(255, 255, 255, 0.96);
    border-top: 1px solid rgba(193, 18, 31, 0.1);
    backdrop-filter: blur(18px);
    box-shadow: 0 -10px 28px rgba(124, 9, 19, 0.06);
  }

  .game-canvas-wrap {
    overflow-y: auto;
    overflow-x: hidden;
    align-items: flex-start;
    padding-bottom: 96px;
  }

  .ttt-footer-group {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
  }

  .ttt-footer-group.end-group {
    flex: 0.9;
  }

  .ttt-footer .btn,
  .ttt-footer .control-btn {
    min-height: 46px;
    border-radius: 16px;
    font-weight: 700;
    font-size: 0.96rem;
    box-shadow: none;
    padding: 0 16px;
  }

  .ttt-footer .btn,
  .ttt-footer .control-btn,
  .ttt-footer a.btn {
    flex: 1;
    justify-content: center;
    text-align: center;
  }

  .ttt-footer .btn-outline,
  .ttt-footer .control-btn {
    background: #fff;
    border: 1px solid rgba(29, 78, 216, 0.18);
    color: #173a74;
  }

  .ttt-footer .btn-accent {
    background: linear-gradient(180deg, #2563eb 0%, #1d4ed8 100%);
    border: none;
    box-shadow: 0 10px 20px rgba(37, 99, 235, 0.16);
  }

  .ttt-confirm-overlay {
    position: fixed;
    inset: 0;
    z-index: 20;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(87, 16, 24, 0.28);
    backdrop-filter: blur(10px);
    opacity: 1;
  }

  .ttt-confirm-card {
    width: min(100%, 360px);
    padding: 20px;
    border-radius: 24px;
    background: rgba(255, 255, 255, 0.98);
    border: 1px solid rgba(193, 18, 31, 0.14);
    box-shadow: 0 24px 50px rgba(124, 9, 19, 0.18);
    text-align: center;
    animation: tttPopIn 0.18s ease-out;
  }

  @keyframes tttPopIn {
    from {
      opacity: 0;
      transform: translateY(10px) scale(0.97);
    }
    to {
      opacity: 1;
      transform: translateY(0) scale(1);
    }
  }

  .ttt-confirm-card h3 {
    margin-bottom: 10px;
  }

  .ttt-confirm-card p {
    color: var(--text-muted);
    line-height: 1.5;
    margin-bottom: 16px;
  }

  .ttt-confirm-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .ttt-confirm-card .btn {
    width: 100%;
    min-height: 50px;
    border-radius: 16px;
    font-weight: 800;
  }

  @media (max-width: 600px) {
    .game-hud.ttt-hud {
      padding: 14px 12px;
    }

    .ttt-hud-stats {
      gap: 12px;
    }

    .ttt-stat {
      min-width: 50px;
    }

    .ttt-board-wrapper {
      max-width: 100%;
      padding: 14px 12px;
    }

    .ttt-cell {
      font-size: 2.3rem;
      border-radius: 16px;
    }

    .ttt-game-container {
      padding: 14px 10px 116px;
    }

    .game-canvas-wrap {
      padding-bottom: 88px;
    }

    .ttt-help {
      font-size: 0.82rem;
    }

    .ttt-footer {
      padding: 10px 10px calc(10px + env(safe-area-inset-bottom, 0px));
    }

    .ttt-footer-group {
      gap: 8px;
    }

    .ttt-footer .btn,
    .ttt-footer .control-btn {
      flex: 1;
      justify-content: center;
      min-height: 44px;
      font-size: 0.9rem;
      padding: 0 10px;
    }

    .ttt-confirm-card {
      padding: 18px 16px;
    }
  }
</style>
</head>
<body>
<div class="game-wrapper ttt-layout">
  <div class="game-hud ttt-hud">
    <div class="ttt-hud-stats">
      <div class="ttt-stat">
        <div style="font-size:0.65rem; color:var(--text-muted); text-transform:uppercase;">Score</div>
        <div style="font-size:1.4rem; font-weight:900; color:var(--primary);" id="ttt-score">0</div>
      </div>
      <div class="ttt-stat">
        <div style="font-size:0.65rem; color:var(--text-muted); text-transform:uppercase;">Losses</div>
        <div style="font-size:1.1rem; font-weight:900;" id="ttt-losses">0 / 5</div>
      </div>
      <div class="ttt-stat">
        <div style="font-size:0.65rem; color:var(--text-muted); text-transform:uppercase;">Draws</div>
        <div style="font-size:1.1rem; font-weight:900;" id="ttt-draws">0 / 10</div>
      </div>
      <div class="ttt-stat" style="min-width:110px;">
        <div style="font-size:0.65rem; color:var(--text-muted); text-transform:uppercase;">Mode</div>
        <div style="font-size:1rem; font-weight:900; color:var(--primary);" id="ttt-mode">Ranked Run</div>
      </div>
    </div>
  </div>

  <div class="game-canvas-wrap">
    <div class="ttt-alert hidden" id="ttt-invite-banner">
      <h3 id="ttt-invite-title">Friend invite</h3>
      <p id="ttt-invite-text"></p>
      <div class="ttt-alert-actions" id="ttt-invite-actions"></div>
    </div>

    <div class="ttt-game-container hidden" id="ttt-board-container">
      <div class="ttt-board-wrapper">
        <div class="ttt-board">
          <div class="ttt-cell" data-index="0" onclick="cellClicked(0)"></div>
          <div class="ttt-cell" data-index="1" onclick="cellClicked(1)"></div>
          <div class="ttt-cell" data-index="2" onclick="cellClicked(2)"></div>
          <div class="ttt-cell" data-index="3" onclick="cellClicked(3)"></div>
          <div class="ttt-cell" data-index="4" onclick="cellClicked(4)"></div>
          <div class="ttt-cell" data-index="5" onclick="cellClicked(5)"></div>
          <div class="ttt-cell" data-index="6" onclick="cellClicked(6)"></div>
          <div class="ttt-cell" data-index="7" onclick="cellClicked(7)"></div>
          <div class="ttt-cell" data-index="8" onclick="cellClicked(8)"></div>
        </div>
        <div class="ttt-status-bar" id="ttt-status">Your turn.</div>
        <div class="ttt-meta-row">
          <div class="ttt-pill">Symbol: <strong id="ttt-symbol">X</strong></div>
          <div class="ttt-pill">Opponent: <strong id="ttt-opponent">AI</strong></div>
          <div class="ttt-pill difficulty-pill">Difficulty: <strong id="ttt-difficulty">Intermediate</strong></div>
        </div>
      </div>
      <div class="ttt-help">
        Get <strong>3 of your marks in a row</strong> to win: across, down, or diagonal.
        Sample winning line: <strong>X X X</strong> across any row before your opponent blocks you.
      </div>
    </div>

    <div class="game-over-screen" id="ttt-start">
      <div class="ttt-start-card">
        <div style="font-size:4rem; color:var(--primary);">X O</div>
        <h2>Tic Tac Toe</h2>
        <p class="ttt-start-copy">
          Make <strong>3 in a row</strong> to win. Your game ends at <strong><?= $tttDrawLimit ?> draws</strong> or <strong><?= $tttLossLimit ?> losses</strong>.
        </p>
        <div class="ttt-help" style="margin-top:0;">
          Sample win: <strong>X X X</strong> across, down, or diagonal.
        </div>
        <div class="ttt-start-actions">
          <button class="btn btn-outline btn-sm" onclick="tttStartFriendFlow()" id="ttt-friend-btn">Play with a friend</button>
          <div class="presence-chip offline hidden" id="friend-status"></div>
          <button class="btn btn-primary btn-lg" onclick="handleStartGameClick()">Start Game</button>
        </div>
      </div>
    </div>

    <div class="game-over-screen hidden" id="ttt-outcome">
      <div style="font-size:4.5rem;" id="outcome-emoji">Round</div>
      <h2 id="outcome-title">Round Result</h2>
      <p id="outcome-desc" style="color:var(--text-muted);"></p>
      <button class="btn btn-primary btn-lg" onclick="nextRound()" id="ttt-next-btn">Next Round</button>
    </div>

    <div class="game-over-screen hidden" id="ttt-gameover">
      <div style="font-size:4.5rem;">Game Over</div>
      <h2 id="ttt-gameover-title">Ranked Game Complete</h2>
      <p style="color:var(--text-muted);" id="ttt-gameover-desc"></p>
      <div style="margin:12px 0;">
        <div style="font-size:0.9rem; color:var(--text-muted); text-transform:uppercase;">Final Score</div>
        <div class="score-display" id="ttt-final-score">0</div>
      </div>
      <div style="display:flex; gap:12px; flex-wrap:wrap; justify-content:center;">
        <button class="btn btn-primary" onclick="showStartScreen()">Play Again</button>
        <button class="btn btn-accent" onclick="ZikGames.showLeaderboardModal('Tic Tac Toe')">Leaderboard</button>
        <a href="/ZikGames/dashboard.php" class="btn btn-outline">Home</a>
      </div>
    </div>
  </div>

  <div class="ttt-footer">
    <div class="ttt-footer-group">
      <a href="/ZikGames/dashboard.php" class="btn btn-outline" id="exit-ttt-btn">Back</a>
      <button class="control-btn" onclick="tttToggleSound()" id="ttt-sound-btn">Sound On</button>
    </div>
    <div class="ttt-footer-group end-group">
      <button class="btn btn-outline hidden" type="button" id="ttt-end-session-btn" onclick="endCurrentSession()">End Session</button>
    </div>
    <div class="ttt-footer-group">
      <button class="btn btn-accent" type="button" onclick="ZikGames.showLeaderboardModal('Tic Tac Toe')">Leaderboard</button>
    </div>
  </div>

  <div class="ttt-confirm-overlay hidden" id="ttt-save-confirm">
    <div class="ttt-confirm-card">
      <h3>Saved game found</h3>
      <p>You already have a saved ranked game. You can resume it, or start fresh and replace that saved progress.</p>
      <div class="ttt-confirm-actions">
        <button class="btn btn-accent" type="button" onclick="resumeSavedRun()">Play Saved Game</button>
        <button class="btn btn-primary" type="button" onclick="confirmStartNewGame()">Start New Game</button>
        <button class="btn btn-outline" type="button" onclick="hideSavedGamePrompt()">Cancel</button>
      </div>
    </div>
  </div>
</div>

<script src="/ZikGames/assets/js/common.js"></script>
<script>
const MAX_DRAWS = <?php echo $tttDrawLimit; ?>;
const MAX_LOSSES = <?php echo $tttLossLimit; ?>;
const SINGLE_WIN_POINTS = 50;
const SINGLE_DRAW_POINTS = 10;
const MULTIPLAYER_WIN_POINTS = 80;
const MULTIPLAYER_DRAW_POINTS = 15;
const COMPETITIVE_MODE = 'Ranked Game';
const AI_DIFFICULTIES = [
  { key: 'easy', label: 'Easy', level: 2, randomChance: 0.6, weight: 10 },
  { key: 'intermediate', label: 'Intermediate', level: 3, randomChance: 0.35, weight: 20 },
  { key: 'hard', label: 'Hard', level: 4, randomChance: 0.15, weight: 30 },
  { key: 'very-hard', label: 'Very Hard', level: 5, randomChance: 0, weight: 40 },
];

let score = 0;
let losses = 0;
let draws = 0;
let soundOn = true;
let active = false;
let board = Array(9).fill(null);
let tttStartTime = 0;
let tttSaved = false;
let currentMode = 'single';
let multiplayerSessionId = null;
let multiplayerSymbol = 'X';
let multiplayerFinished = false;
let statusPollTimer = null;
let pendingInviteId = null;
let currentDifficulty = AI_DIFFICULTIES[0];
let highestDifficultyLevelSeen = AI_DIFFICULTIES[0].level;
const TTT_SAVE_KEY = 'zikgames_ttt_ranked_run';

function tttToggleSound() {
  soundOn = !soundOn;
  document.getElementById('ttt-sound-btn').textContent = soundOn ? 'Sound On' : 'Sound Off';
}

function updateHud() {
  document.getElementById('ttt-score').textContent = Number(score).toLocaleString();
  document.getElementById('ttt-losses').textContent = `${losses} / ${MAX_LOSSES}`;
  document.getElementById('ttt-draws').textContent = `${draws} / ${MAX_DRAWS}`;
  document.getElementById('ttt-mode').textContent = currentMode === 'multiplayer' ? 'Friend Match' : COMPETITIVE_MODE;
  document.getElementById('ttt-symbol').textContent = multiplayerSymbol;
  document.getElementById('ttt-opponent').textContent = currentMode === 'multiplayer' ? 'Friend' : 'AI';
  document.getElementById('ttt-difficulty').textContent = currentMode === 'multiplayer' ? 'Friend Match' : currentDifficulty.label;
  updateSessionActionState();
}

function updateSessionActionState() {
  const endBtn = document.getElementById('ttt-end-session-btn');
  if (!endBtn) {
    return;
  }

  const isInStartScreen = !document.getElementById('ttt-start').classList.contains('hidden');
  const isInGameOverScreen = !document.getElementById('ttt-gameover').classList.contains('hidden');
  const shouldShow = currentMode === 'single' && !isInStartScreen && !isInGameOverScreen && !tttSaved;
  endBtn.classList.toggle('hidden', !shouldShow);
  endBtn.disabled = !shouldShow;
}

function hideInviteBanner() {
  const banner = document.getElementById('ttt-invite-banner');
  banner.classList.add('hidden');
  document.getElementById('ttt-invite-title').textContent = 'Friend invite';
  document.getElementById('ttt-invite-text').textContent = '';
  document.getElementById('ttt-invite-actions').innerHTML = '';
  pendingInviteId = null;
}

function saveSingleSession() {
  if (currentMode !== 'single' || tttSaved) {
    return;
  }

  const payload = {
    score,
    losses,
    draws,
    soundOn,
    active,
    board: board.map((cell) => cell === null ? null : cell),
    tttStartTime,
    currentDifficultyKey: currentDifficulty.key,
    highestDifficultyLevelSeen,
    savedAt: Date.now(),
  };

  try {
    // Always keep only one resumable ranked game per player on this device.
    localStorage.setItem(TTT_SAVE_KEY, JSON.stringify(payload));
    updateResumeButton();
  } catch (error) {
    // Ignore storage failures quietly.
  }
}

function loadSavedSingleSession() {
  try {
    const raw = localStorage.getItem(TTT_SAVE_KEY);
    if (!raw) {
      return null;
    }

    const parsed = JSON.parse(raw);
    if (!parsed || typeof parsed !== 'object') {
      return null;
    }

    if (!Array.isArray(parsed.board) || parsed.board.length !== 9) {
      return null;
    }

    return parsed;
  } catch (error) {
    return null;
  }
}

function clearSavedSingleSession() {
  try {
    localStorage.removeItem(TTT_SAVE_KEY);
  } catch (error) {
    // Ignore storage failures quietly.
  }
  updateResumeButton();
}

function updateResumeButton() {
  const existing = document.getElementById('ttt-resume-btn');
  const saved = loadSavedSingleSession();

  if (!saved) {
    if (existing) {
      existing.remove();
    }
    return;
  }

  if (existing) {
    existing.classList.remove('hidden');
    return;
  }

  const startBtn = document.querySelector('#ttt-start .btn.btn-primary.btn-lg');
  if (!startBtn) {
    return;
  }

  const resumeBtn = document.createElement('button');
  resumeBtn.id = 'ttt-resume-btn';
  resumeBtn.className = 'btn btn-accent btn-lg';
  resumeBtn.textContent = 'Resume Saved Game';
  resumeBtn.onclick = resumeSavedRun;
  startBtn.insertAdjacentElement('beforebegin', resumeBtn);
  resumeBtn.style.marginBottom = '12px';
}

function showSavedGamePrompt() {
  document.getElementById('ttt-save-confirm').classList.remove('hidden');
}

function hideSavedGamePrompt() {
  document.getElementById('ttt-save-confirm').classList.add('hidden');
}

function handleStartGameClick() {
  if (loadSavedSingleSession()) {
    showSavedGamePrompt();
    return;
  }

  tttStartSingle();
}

function confirmStartNewGame() {
  hideSavedGamePrompt();
  tttStartSingle();
}

function showInviteBanner(title, text, actionsHtml = '') {
  document.getElementById('ttt-invite-title').textContent = title;
  document.getElementById('ttt-invite-text').textContent = text;
  document.getElementById('ttt-invite-actions').innerHTML = actionsHtml;
  document.getElementById('ttt-invite-banner').classList.remove('hidden');
}

function showStartScreen() {
  currentMode = 'single';
  multiplayerSessionId = null;
  multiplayerFinished = false;
  hideInviteBanner();
  document.getElementById('ttt-start').classList.remove('hidden');
  document.getElementById('ttt-board-container').classList.add('hidden');
  document.getElementById('ttt-outcome').classList.add('hidden');
  document.getElementById('ttt-gameover').classList.add('hidden');
  updateHud();
  updateResumeButton();
}

function resetBoard() {
  board = Array(9).fill(null);
  active = true;

  document.querySelectorAll('.ttt-cell').forEach((cell) => {
    cell.textContent = '';
    cell.className = 'ttt-cell';
  });
}

function pickWeightedDifficulty(pool) {
  const totalWeight = pool.reduce((sum, entry) => sum + entry.weight, 0);
  let roll = Math.random() * totalWeight;

  for (const entry of pool) {
    roll -= entry.weight;
    if (roll <= 0) {
      return entry;
    }
  }

  return pool[pool.length - 1];
}

function pickNextDifficulty(forceAny = false) {
  const pool = forceAny ? AI_DIFFICULTIES : AI_DIFFICULTIES.filter((entry) => entry.key !== currentDifficulty.key);
  currentDifficulty = pickWeightedDifficulty(pool.length ? pool : AI_DIFFICULTIES);
  highestDifficultyLevelSeen = Math.max(highestDifficultyLevelSeen, currentDifficulty.level);
}

function renderBoardFromArray(boardArray, winningLine = null) {
  board = [...boardArray];
  document.querySelectorAll('.ttt-cell').forEach((cell, index) => {
    const value = board[index];
    cell.textContent = value && value !== '-' ? value : '';
    cell.className = 'ttt-cell';
    if (value === 'X' || value === 'O') {
      cell.classList.add(value.toLowerCase());
    }
  });

  if (Array.isArray(winningLine)) {
    highlightWin(winningLine);
  }
}

function tttStartSingle() {
  hideSavedGamePrompt();
  clearSavedSingleSession();
  currentMode = 'single';
  score = 0;
  losses = 0;
  draws = 0;
  tttSaved = false;
  multiplayerSessionId = null;
  multiplayerFinished = false;
  multiplayerSymbol = 'X';
  currentDifficulty = AI_DIFFICULTIES[0];
  highestDifficultyLevelSeen = currentDifficulty.level;
  tttStartTime = Date.now();
  hideInviteBanner();
  updateHud();

  document.getElementById('ttt-start').classList.add('hidden');
  document.getElementById('ttt-gameover').classList.add('hidden');
  document.getElementById('ttt-outcome').classList.add('hidden');
  document.getElementById('ttt-board-container').classList.remove('hidden');

  startSingleRound(true);
  saveSingleSession();
}

function resumeSavedRun() {
  hideSavedGamePrompt();
  const saved = loadSavedSingleSession();
  if (!saved) {
    updateResumeButton();
    ZikGames.showToast('No saved game was found.', 'info');
    return;
  }

  currentMode = 'single';
  score = Number(saved.score || 0);
  losses = Number(saved.losses || 0);
  draws = Number(saved.draws || 0);
  soundOn = saved.soundOn !== false;
  active = saved.active !== false;
  board = saved.board.map((cell) => cell === 'X' || cell === 'O' ? cell : null);
  tttStartTime = Number(saved.tttStartTime || Date.now());
  tttSaved = false;
  multiplayerSessionId = null;
  multiplayerFinished = false;
  multiplayerSymbol = 'X';
  currentDifficulty = AI_DIFFICULTIES.find((entry) => entry.key === saved.currentDifficultyKey) || AI_DIFFICULTIES[0];
  highestDifficultyLevelSeen = Number(saved.highestDifficultyLevelSeen || currentDifficulty.level);

  document.getElementById('ttt-sound-btn').textContent = soundOn ? 'Sound On' : 'Sound Off';
  document.getElementById('ttt-start').classList.add('hidden');
  document.getElementById('ttt-gameover').classList.add('hidden');
  document.getElementById('ttt-outcome').classList.add('hidden');
  document.getElementById('ttt-board-container').classList.remove('hidden');
  hideInviteBanner();
  updateHud();
  renderBoardFromArray(board);

  if (checkWin(board, 'X') || checkWin(board, 'O') || checkDraw(board)) {
    startSingleRound();
    saveSingleSession();
    return;
  }

  const xCount = board.filter((cell) => cell === 'X').length;
  const oCount = board.filter((cell) => cell === 'O').length;
  const isPlayerTurn = xCount === oCount;
  active = isPlayerTurn;

  if (isPlayerTurn) {
    document.getElementById('ttt-status').textContent = 'Your turn.';
  } else {
    document.getElementById('ttt-status').textContent = 'AI is thinking...';
    active = false;
    window.setTimeout(() => {
      if (currentMode !== 'single') {
        return;
      }
      const aiMove = getAIMove();
      makeMove(aiMove, 'O');
      if (!checkSingleGameState()) {
        active = true;
        document.getElementById('ttt-status').textContent = 'Your turn.';
        saveSingleSession();
      }
    }, 350);
  }
}

function startSingleRound(forceAnyDifficulty = false) {
  pickNextDifficulty(forceAnyDifficulty);
  resetBoard();
  document.getElementById('ttt-status').textContent = `${currentDifficulty.label} round. Your turn.`;
  updateHud();
  saveSingleSession();
}

function endCurrentSession() {
  if (currentMode !== 'single' || tttSaved) {
    return;
  }

  if (score <= 0 && losses === 0 && draws === 0 && board.every((cell) => cell === null)) {
    ZikGames.showToast('Start a round first before ending the session.', 'info');
    return;
  }

  handleSingleGameOver('manual');
}

function nextRound() {
  document.getElementById('ttt-outcome').classList.add('hidden');
  if (currentMode === 'single') {
    startSingleRound();
  }
}

function cellClicked(index) {
  if (!active || board[index] !== null) {
    return;
  }

  if (currentMode === 'multiplayer') {
    submitMultiplayerMove(index);
    return;
  }

  makeMove(index, 'X');
  if (checkSingleGameState()) {
    return;
  }

  active = false;
  document.getElementById('ttt-status').textContent = 'AI is thinking...';

  setTimeout(() => {
    const aiMove = getAIMove();
    makeMove(aiMove, 'O');

    if (!checkSingleGameState()) {
      active = true;
      document.getElementById('ttt-status').textContent = 'Your turn.';
    }
  }, 450);
}

function makeMove(index, symbol) {
  board[index] = symbol;
  const cell = document.querySelector(`.ttt-cell[data-index="${index}"]`);
  cell.textContent = symbol;
  cell.classList.add(symbol.toLowerCase());
  if (currentMode === 'single') {
    saveSingleSession();
  }
}

function checkWin(boardState, symbol) {
  const cells = Array.isArray(boardState) ? boardState : String(boardState).split('');
  const lines = [
    [0, 1, 2],
    [3, 4, 5],
    [6, 7, 8],
    [0, 3, 6],
    [1, 4, 7],
    [2, 5, 8],
    [0, 4, 8],
    [2, 4, 6],
  ];

  for (const line of lines) {
    if (cells[line[0]] === symbol && cells[line[1]] === symbol && cells[line[2]] === symbol) {
      return line;
    }
  }

  return null;
}

function checkDraw(boardState) {
  return boardState.every((cell) => cell !== null);
}

function highlightWin(line) {
  line.forEach((index) => {
    document.querySelector(`.ttt-cell[data-index="${index}"]`).classList.add('win');
  });
}

function checkSingleGameState() {
  const playerWinLine = checkWin(board, 'X');
  if (playerWinLine) {
    active = false;
    highlightWin(playerWinLine);
    handleSingleRoundEnd('win');
    return true;
  }

  const aiWinLine = checkWin(board, 'O');
  if (aiWinLine) {
    active = false;
    highlightWin(aiWinLine);
    handleSingleRoundEnd('lose');
    return true;
  }

  if (checkDraw(board)) {
    active = false;
    handleSingleRoundEnd('draw');
    return true;
  }

  return false;
}

function handleSingleRoundEnd(result) {
  setTimeout(() => {
    let title = '';
    let emoji = '';
    let desc = '';

    if (result === 'win') {
      score += SINGLE_WIN_POINTS;
      title = 'You Won';
      emoji = 'Win';
      desc = `Strong round. +${SINGLE_WIN_POINTS} points added to your game.`;
    } else if (result === 'draw') {
      score += SINGLE_DRAW_POINTS;
      draws += 1;
      title = 'Draw';
      emoji = 'Draw';
      desc = `Draw ${draws} of ${MAX_DRAWS}. +${SINGLE_DRAW_POINTS} points and the run continues.`;
    } else {
      losses += 1;
      title = 'You Lost';
      emoji = 'Loss';
      desc = `Loss ${losses} of ${MAX_LOSSES}. Stay sharp and keep stacking your best score.`;
    }

    updateHud();

    if (draws >= MAX_DRAWS || losses >= MAX_LOSSES) {
      handleSingleGameOver(result);
      return;
    }

    document.getElementById('outcome-emoji').textContent = emoji;
    document.getElementById('outcome-title').textContent = title;
    document.getElementById('outcome-desc').textContent = desc;
    document.getElementById('ttt-outcome').classList.remove('hidden');
    saveSingleSession();
  }, 500);
}

function handleSingleGameOver(lastResult) {
  clearSavedSingleSession();
  document.getElementById('ttt-board-container').classList.add('hidden');
  document.getElementById('ttt-outcome').classList.add('hidden');
  document.getElementById('ttt-final-score').textContent = Number(score).toLocaleString();
  document.getElementById('ttt-gameover-title').textContent = lastResult === 'manual' ? 'Session Ended' : 'Ranked Game Complete';
  document.getElementById('ttt-gameover-desc').textContent =
    lastResult === 'manual'
      ? 'You ended this game manually. Your current score will now be saved and can still become your new best.'
      : draws >= MAX_DRAWS
        ? `Your session ended after ${MAX_DRAWS} draws. Wins kept adding up, and your best session score is what stays on the leaderboard.`
        : `Your session ended after ${MAX_LOSSES} losses. Wins and draws from this run have already been counted into your final score.`;
  document.getElementById('ttt-gameover').classList.remove('hidden');

  if (!tttSaved) {
    tttSaved = true;
    ZikGames.saveScoreAndShowResults('Tic Tac Toe', score, {
      difficulty: highestDifficultyLevelSeen,
      difficultyLabel: 'Mixed Ranked',
      multiplier: 1,
      gameMode: 'single',
      survivalRun: true,
      duration: Math.round((Date.now() - tttStartTime) / 1000),
      showDifficulty: true,
      modeLabel: COMPETITIVE_MODE,
    });
  }
}

function getAIMove() {
  const emptyCells = board.map((cell, index) => cell === null ? index : null).filter((value) => value !== null);

  if (currentDifficulty.randomChance > 0 && Math.random() < currentDifficulty.randomChance) {
    return emptyCells[Math.floor(Math.random() * emptyCells.length)];
  }

  if (currentDifficulty.key === 'very-hard') {
    return getPerfectMove();
  }

  return getSmartMove();
}

function getPerfectMove() {
  const emptyCells = board.map((cell, index) => cell === null ? index : null).filter((value) => value !== null);
  let bestScore = -Infinity;
  let bestMove = emptyCells[0];

  for (const index of emptyCells) {
    const nextBoard = [...board];
    nextBoard[index] = 'O';
    const scoreValue = minimax(nextBoard, false);
    if (scoreValue > bestScore) {
      bestScore = scoreValue;
      bestMove = index;
    }
  }

  return bestMove;
}

function minimax(boardState, isMaximizing) {
  if (checkWin(boardState, 'O')) return 10;
  if (checkWin(boardState, 'X')) return -10;
  if (boardState.every((cell) => cell !== null)) return 0;

  const emptyCells = boardState.map((cell, index) => cell === null ? index : null).filter((value) => value !== null);

  if (isMaximizing) {
    let bestScore = -Infinity;
    for (const index of emptyCells) {
      const nextBoard = [...boardState];
      nextBoard[index] = 'O';
      bestScore = Math.max(bestScore, minimax(nextBoard, false));
    }
    return bestScore;
  }

  let bestScore = Infinity;
  for (const index of emptyCells) {
    const nextBoard = [...boardState];
    nextBoard[index] = 'X';
    bestScore = Math.min(bestScore, minimax(nextBoard, true));
  }
  return bestScore;
}

function getSmartMove() {
  const emptyCells = board.map((cell, index) => cell === null ? index : null).filter((value) => value !== null);

  for (const index of emptyCells) {
    const nextBoard = [...board];
    nextBoard[index] = 'O';
    if (checkWin(nextBoard, 'O')) {
      return index;
    }
  }

  for (const index of emptyCells) {
    const nextBoard = [...board];
    nextBoard[index] = 'X';
    if (checkWin(nextBoard, 'X')) {
      return index;
    }
  }

  if (emptyCells.includes(4)) {
    return 4;
  }

  const corners = [0, 2, 6, 8];
  const emptyCorners = corners.filter((corner) => emptyCells.includes(corner));

  if (emptyCorners.length > 0) {
    if (board[0] === 'X' && emptyCells.includes(8)) return 8;
    if (board[8] === 'X' && emptyCells.includes(0)) return 0;
    if (board[2] === 'X' && emptyCells.includes(6)) return 6;
    if (board[6] === 'X' && emptyCells.includes(2)) return 2;
    return emptyCorners[Math.floor(Math.random() * emptyCorners.length)];
  }

  return emptyCells[Math.floor(Math.random() * emptyCells.length)];
}

async function tttStartFriendFlow() {
  const friendBtn = document.getElementById('ttt-friend-btn');
  const statusEl = document.getElementById('friend-status');
  const originalText = friendBtn.textContent;
  friendBtn.disabled = true;
  friendBtn.textContent = 'Checking...';
  statusEl.classList.remove('hidden');
  statusEl.classList.remove('online', 'offline');
  statusEl.classList.add('offline');
  statusEl.textContent = 'Checking for a friend...';

  try {
    const result = await ZikGames.checkOnlinePeers('Tic Tac Toe');
    if (!result.online) {
      statusEl.className = 'presence-chip offline';
      statusEl.textContent = 'No friend online yet. Ask them to open Tic Tac Toe and tap Play with a friend.';
      return;
    }

    statusEl.className = 'presence-chip online';
    statusEl.textContent = `Friend online now: ${result.phone}`;
    await tttPlayFriend();
  } catch (error) {
    statusEl.className = 'presence-chip offline';
    statusEl.textContent = 'Unable to check friend status right now.';
  } finally {
    friendBtn.disabled = false;
    friendBtn.textContent = originalText;
  }
}

async function tttPlayFriend() {
  try {
    const response = await ZikGames.apiJson('/ZikGames/api/ttt-create-invite.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({}),
    });

    if (response.session && response.session.status === 'active') {
      startMultiplayerSession(response.session);
      return;
    }

    multiplayerSessionId = response.session ? response.session.id : response.sessionId;
    currentMode = 'multiplayer';
    multiplayerFinished = false;
    showInviteBanner(
      'Invite sent',
      'Your friend has been notified inside Tic Tac Toe. Waiting for them to accept.',
      '<button class="btn btn-outline btn-sm" onclick="showStartScreen()">Cancel View</button>'
    );
    document.getElementById('ttt-start').classList.add('hidden');
    document.getElementById('ttt-board-container').classList.remove('hidden');
    resetBoard();
    document.getElementById('ttt-status').textContent = 'Waiting for your friend to join...';
    updateHud();
  } catch (error) {
    ZikGames.showToast(error.message || 'Unable to send invite right now.', 'error');
  }
}

async function respondToInvite(action) {
  if (!pendingInviteId) {
    return;
  }

  try {
    const response = await ZikGames.apiJson('/ZikGames/api/ttt-respond-invite.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ sessionId: pendingInviteId, action }),
    });

    hideInviteBanner();

    if (action === 'accept' && response.session) {
      startMultiplayerSession(response.session);
    } else {
      ZikGames.showToast('Invite declined.', 'info');
    }
  } catch (error) {
    ZikGames.showToast(error.message || 'Could not respond to invite.', 'error');
  }
}

function startMultiplayerSession(session) {
  currentMode = 'multiplayer';
  multiplayerSessionId = session.id;
  multiplayerSymbol = session.yourSymbol;
  multiplayerFinished = false;
  tttSaved = false;
  tttStartTime = Date.now();

  document.getElementById('ttt-start').classList.add('hidden');
  document.getElementById('ttt-outcome').classList.add('hidden');
  document.getElementById('ttt-gameover').classList.add('hidden');
  document.getElementById('ttt-board-container').classList.remove('hidden');
  hideInviteBanner();
  syncMultiplayerSession(session);
  updateHud();
}

function syncMultiplayerSession(session, winningLine = null) {
  multiplayerSessionId = session.id;
  multiplayerSymbol = session.yourSymbol;
  renderBoardFromArray(session.board.map((cell) => cell === '-' ? null : cell), winningLine);
  updateHud();

  if (session.status === 'pending') {
    active = false;
    document.getElementById('ttt-status').textContent = 'Waiting for your friend to accept the invite...';
    return;
  }

  if (session.status === 'completed') {
    active = false;
    handleMultiplayerCompletion(session, winningLine);
    return;
  }

  active = session.currentTurnUserId === <?php echo intval($_SESSION['user_id']); ?>;
  if (active) {
    document.getElementById('ttt-status').textContent = `Your turn (${multiplayerSymbol}).`;
  } else {
    document.getElementById('ttt-status').textContent = 'Your friend is thinking...';
  }
}

async function submitMultiplayerMove(index) {
  if (!multiplayerSessionId) {
    return;
  }

  active = false;
  try {
    const response = await ZikGames.apiJson('/ZikGames/api/ttt-move.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        sessionId: multiplayerSessionId,
        index,
      }),
    });

    syncMultiplayerSession(response.session, response.winningLine || null);
  } catch (error) {
    active = true;
    ZikGames.showToast(error.message || 'Move failed.', 'error');
  }
}

function handleMultiplayerCompletion(session, winningLine = null) {
  if (multiplayerFinished) {
    return;
  }

  multiplayerFinished = true;
  if (Array.isArray(winningLine)) {
    highlightWin(winningLine);
  } else if (session.result === 'host_win' || session.result === 'guest_win') {
    const symbol = session.winnerUserId === <?php echo intval($_SESSION['user_id']); ?> ? multiplayerSymbol : (multiplayerSymbol === 'X' ? 'O' : 'X');
    const line = checkWin(session.board.map((cell) => cell === '-' ? null : cell), symbol);
    if (line) {
      highlightWin(line);
    }
  }

  let earned = 0;
  let title = 'Friend Match Complete';
  let desc = 'Match ended.';

  if (session.result === 'draw') {
    earned = MULTIPLAYER_DRAW_POINTS;
    title = 'Draw Match';
    desc = `Draw against a friend. ${MULTIPLAYER_DRAW_POINTS} points added for a competitive finish.`;
  } else if (session.winnerUserId === <?php echo intval($_SESSION['user_id']); ?>) {
    earned = MULTIPLAYER_WIN_POINTS;
    title = 'You Beat Your Friend';
    desc = `Friend-match win secured. ${MULTIPLAYER_WIN_POINTS} points added to this result.`;
  } else {
    desc = 'Your friend won this one. No bonus points this match.';
  }

  score = earned;
  updateHud();
  document.getElementById('ttt-board-container').classList.add('hidden');
  document.getElementById('ttt-gameover-title').textContent = title;
  document.getElementById('ttt-gameover-desc').textContent = desc;
  document.getElementById('ttt-final-score').textContent = Number(score).toLocaleString();
  document.getElementById('ttt-gameover').classList.remove('hidden');

  if (!tttSaved) {
    tttSaved = true;
    ZikGames.saveScoreAndShowResults('Tic Tac Toe', score, {
      difficulty: 1,
      difficultyLabel: 'Friend Match',
      multiplier: 1,
      gameMode: 'multiplayer',
      survivalRun: false,
      duration: Math.round((Date.now() - tttStartTime) / 1000),
      showDifficulty: true,
      modeLabel: 'Friend Match',
    });
  }
}

async function pollTicTacToeStatus() {
  try {
    const response = await ZikGames.apiJson('/ZikGames/api/ttt-status.php');
    const session = response.session;

    if (response.pendingInvite && !multiplayerSessionId) {
      pendingInviteId = response.pendingInvite.id;
      showInviteBanner(
        'Friend invite received',
        `${response.pendingInvite.opponentPhone} wants to play Tic Tac Toe with you right now.`,
        '<button class="btn btn-primary btn-sm" onclick="respondToInvite(\'accept\')">Accept</button><button class="btn btn-outline btn-sm" onclick="respondToInvite(\'decline\')">Decline</button>'
      );
    }

    if (session && session.status === 'active') {
      startMultiplayerSession(session);
      return;
    }

    if (session && session.status === 'pending') {
      if (currentMode !== 'multiplayer') {
        currentMode = 'multiplayer';
        multiplayerSessionId = session.id;
        multiplayerSymbol = session.yourSymbol;
        document.getElementById('ttt-start').classList.add('hidden');
        document.getElementById('ttt-gameover').classList.add('hidden');
        document.getElementById('ttt-outcome').classList.add('hidden');
        document.getElementById('ttt-board-container').classList.remove('hidden');
      }

      syncMultiplayerSession(session);
      return;
    }

    if (multiplayerSessionId && session && session.id === multiplayerSessionId) {
      if (session.status === 'completed') {
        syncMultiplayerSession(session);
        return;
      }
    }

    if (!session && currentMode === 'multiplayer' && !multiplayerFinished) {
      showInviteBanner(
        'Match no longer available',
        'The invite expired or your friend left before the match started.',
        '<button class="btn btn-primary btn-sm" onclick="showStartScreen()">Back to start</button>'
      );
      currentMode = 'single';
      multiplayerSessionId = null;
      multiplayerFinished = true;
    }
  } catch (error) {
    // Keep polling quietly.
  }
}

ZikGames.initPresencePing('Tic Tac Toe');
statusPollTimer = window.setInterval(pollTicTacToeStatus, 3500);
pollTicTacToeStatus();
updateHud();
updateResumeButton();
window.addEventListener('beforeunload', () => {
  if (currentMode === 'single' && !tttSaved && (score > 0 || losses > 0 || draws > 0 || board.some((cell) => cell !== null))) {
    saveSingleSession();
  }
});
</script>
</body>
</html>
