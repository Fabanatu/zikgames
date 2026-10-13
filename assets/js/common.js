(function () {
  const APP_BASE = '/ZikGames';
  const SHARE_PLATFORMS = ['whatsapp', 'facebook', 'twitter', 'tiktok'];

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.className = 'toast-container';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `<span>${escapeHtml(message)}</span>`;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 3200);
  }

  async function apiJson(url, options = {}) {
    const response = await fetch(url, options);
    let data = {};
    try {
      data = await response.json();
    } catch (error) {
      data = {};
    }
    if (!response.ok || data.error) {
      throw new Error(data.error || 'Request failed');
    }
    return data;
  }

  function buildShareUrls(game, score, extra = {}) {
    const url = `${window.location.origin}${APP_BASE}/dashboard.php`;
    const difficultyText = extra.difficultyLabel
      ? ` on ${extra.difficultyLabel}`
      : (extra.showDifficulty === false || !extra.difficulty ? '' : ` on level ${extra.difficulty}`);
    const survivalText = extra.survivalRun ? ' in Survival Run' : '';
    const message = `I scored ${score} in ${game}${difficultyText}${survivalText} on ZikGames. Can you beat me? ${url}`;
    const encodedMessage = encodeURIComponent(message);
    const encodedUrl = encodeURIComponent(url);

      return {
        whatsapp: `https://wa.me/?text=${encodedMessage}`,
        facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}&quote=${encodedMessage}`,
        twitter: `https://twitter.com/intent/tweet?text=${encodedMessage}`,
        tiktok: 'https://www.tiktok.com/upload?lang=en',
      };
  }

  async function copyShareCaption(game, score, meta = {}) {
    try {
      const difficultyText = meta.difficultyLabel
        ? ` on ${meta.difficultyLabel}`
        : (meta.showDifficulty === false || !meta.difficulty ? '' : ` on level ${meta.difficulty}`);
      const caption = `I scored ${score} in ${game}${difficultyText}${meta.survivalRun ? ' in Survival Run' : ''} on ZikGames. Can you beat me? ${window.location.origin}${APP_BASE}/dashboard.php`;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(caption);
        showToast('TikTok caption copied. Paste it into your post.', 'success');
      } else {
        showToast('Copy your score caption manually for TikTok.', 'info');
      }
    } catch (error) {
      showToast('Could not prepare the TikTok caption.', 'error');
    }
    return true;
  }

  let currentSharePayload = null;

  function openTikTokShare() {
    if (!currentSharePayload) {
      return true;
    }

    copyShareCaption(currentSharePayload.game, currentSharePayload.score, currentSharePayload.meta || {});
    return true;
  }

  async function postScore(game, score, meta = {}) {
    return apiJson(`${APP_BASE}/api/save-score.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        game,
        score,
        difficulty: meta.difficulty ?? 1,
        multiplier: meta.multiplier ?? 1,
        gameMode: meta.gameMode ?? 'single',
        survivalRun: !!meta.survivalRun,
        duration: meta.duration ?? null,
      }),
    });
  }

  async function fetchLeaderboard(game, limit = 10) {
    return apiJson(`${APP_BASE}/api/leaderboard.php?game=${encodeURIComponent(game)}&limit=${limit}&includeUserRank=1`);
  }

  async function pingPresence(game) {
    return apiJson(`${APP_BASE}/api/ping-presence.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ game }),
    });
  }

  async function checkOnlinePeers(game) {
    return apiJson(`${APP_BASE}/api/online-status.php?game=${encodeURIComponent(game)}`);
  }

  async function updatePresenceUI(game, options = {}) {
    const statusEl = document.getElementById(options.statusId || 'friend-status');
    const buttonEl = document.getElementById(options.buttonId || 'friend-play-btn');
    const offlineMessage = options.offlineMessage || 'No friend is online right now. Invite someone and check back soon.';
    try {
      const data = await checkOnlinePeers(game);
      if (statusEl) {
        statusEl.className = `presence-chip ${data.online ? 'online' : 'offline'}`;
        statusEl.textContent = data.online
          ? `Friend online now: ${data.phone}`
          : offlineMessage;
      }
      if (buttonEl) {
        buttonEl.disabled = !data.online;
        buttonEl.classList.toggle('hidden', !data.online);
      }
      return data;
    } catch (error) {
      if (statusEl) {
        statusEl.className = 'presence-chip offline';
        statusEl.textContent = 'Unable to check friend status right now.';
      }
      return { online: false };
    }
  }

  function initPresencePing(game, intervalMs = 15000) {
    const run = async () => {
      try {
        await pingPresence(game);
      } catch (error) {
        // Ignore transient presence write failures.
      }
    };

    run();
    return window.setInterval(run, intervalMs);
  }

  function initPresenceLoop(game, options = {}) {
    const run = async () => {
      try {
        await pingPresence(game);
      } catch (error) {
        // Ignore transient presence write failures.
      }
      await updatePresenceUI(game, options);
    };

    run();
    return window.setInterval(run, options.intervalMs || 15000);
  }

  function renderLeaderboardTable(payload) {
    const rows = payload.top || [];
    if (!rows.length) {
      return '<div class="alert alert-info">No scores yet for this game. Be the first to set the pace.</div>';
    }

    const body = rows.map((entry) => `
      <tr class="${entry.is_current_user ? 'leaderboard-highlight' : ''}">
        <td>#${entry.rank}</td>
        <td>${escapeHtml(entry.phone)}${entry.is_current_user ? ' (You)' : ''}</td>
        <td>${Number(entry.best_score || 0).toLocaleString()}</td>
        <td>${entry.top_level || 1}</td>
      </tr>
    `).join('');

    const currentUser = payload.currentUser && !rows.some((row) => row.user_id === payload.currentUser.user_id)
      ? `
        <div class="alert alert-warning" style="margin-top:12px;">
          Your rank: #${payload.currentUser.rank} with ${Number(payload.currentUser.best_score || 0).toLocaleString()} pts.
        </div>
      `
      : '';

    return `
      <div class="table-wrapper leaderboard-table">
        <table>
          <thead>
            <tr>
              <th>Rank</th>
              <th>Player</th>
              <th>Best</th>
              <th>Top Level</th>
            </tr>
          </thead>
          <tbody>${body}</tbody>
        </table>
      </div>
      ${currentUser}
    `;
  }

  function ensureModal() {
    let overlay = document.getElementById('global-modal-overlay');
    if (overlay) {
      return overlay;
    }

    overlay = document.createElement('div');
    overlay.id = 'global-modal-overlay';
    overlay.className = 'modal-overlay';
    overlay.innerHTML = `
      <div class="modal">
        <div id="global-modal-content"></div>
      </div>
    `;
    overlay.addEventListener('click', (event) => {
      if (event.target === overlay) {
        overlay.classList.remove('open');
      }
    });
    document.body.appendChild(overlay);
    return overlay;
  }

  function openModal(html) {
    const overlay = ensureModal();
    document.getElementById('global-modal-content').innerHTML = html;
    overlay.classList.add('open');
  }

  function closeModal() {
    const overlay = document.getElementById('global-modal-overlay');
    if (overlay) {
      overlay.classList.remove('open');
    }
  }

  async function showLeaderboardModal(game) {
    openModal(`
      <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px;">
        <div>
          <div class="badge badge-purple">Leaderboard</div>
          <h3 style="margin-top:10px;">${escapeHtml(game)}</h3>
        </div>
        <button class="btn btn-outline btn-sm" onclick="ZikGames.closeModal()">Close</button>
      </div>
      <div id="modal-leaderboard-body">
        <div class="skeleton" style="height:220px;"></div>
      </div>
    `);

    try {
      const payload = await fetchLeaderboard(game, 10);
      document.getElementById('modal-leaderboard-body').innerHTML = renderLeaderboardTable(payload);
    } catch (error) {
      document.getElementById('modal-leaderboard-body').innerHTML = `<div class="alert alert-error">${escapeHtml(error.message)}</div>`;
    }
  }

  async function showPostGamePanel(config) {
    currentSharePayload = config;
    const shareUrls = buildShareUrls(config.game, config.score, config.meta || {});
    openModal(`
      <div class="postgame-panel">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
          <div>
            <div class="badge badge-orange">Run Complete</div>
            <h3 style="margin-top:10px;">${escapeHtml(config.game)}</h3>
            <p class="text-muted">Your score has been saved. Share it or check the leaderboard.</p>
          </div>
          <button class="btn btn-outline btn-sm" onclick="ZikGames.closeModal()">Close</button>
        </div>
        <div class="postgame-stats">
          <div class="postgame-stat">
            <span class="label">Score</span>
            <span class="value">${Number(config.score || 0).toLocaleString()}</span>
          </div>
          ${config.meta?.showDifficulty === false
            ? `
          <div class="postgame-stat">
            <span class="label">Mode</span>
            <span class="value">${escapeHtml(config.meta?.modeLabel || (config.meta?.survivalRun ? 'Survival' : 'Classic'))}</span>
          </div>
          <div class="postgame-stat">
            <span class="label">Session</span>
            <span class="value">${config.meta?.survivalRun ? 'Survival' : 'Classic'}</span>
          </div>
            `
            : `
          <div class="postgame-stat">
            <span class="label">${config.meta?.difficultyLabel ? 'Difficulty' : 'Level'}</span>
            <span class="value">${escapeHtml(config.meta?.difficultyLabel || String(config.meta?.difficulty || 1))}</span>
          </div>
          <div class="postgame-stat">
            <span class="label">Mode</span>
            <span class="value">${config.meta?.survivalRun ? 'Survival' : 'Classic'}</span>
          </div>
            `}
        </div>
        <div>
          <div style="font-size:0.82rem; color:var(--text-muted); margin-bottom:10px;">Share your score</div>
          <div class="share-actions">
            ${SHARE_PLATFORMS.map((platform) => `
              <a class="share-btn" data-platform="${platform}" href="${shareUrls[platform]}" target="_blank" rel="noopener noreferrer" ${platform === 'tiktok' ? 'onclick="return ZikGames.openTikTokShare()"' : ''}>
                ${platform === 'twitter' ? 'Twitter/X' : platform.charAt(0).toUpperCase() + platform.slice(1)}
              </a>
            `).join('')}
          </div>
        </div>
        <div id="postgame-leaderboard">
          <div class="skeleton" style="height:220px;"></div>
        </div>
      </div>
    `);

    try {
      const payload = await fetchLeaderboard(config.game, 10);
      document.getElementById('postgame-leaderboard').innerHTML = renderLeaderboardTable(payload);
    } catch (error) {
      document.getElementById('postgame-leaderboard').innerHTML = `<div class="alert alert-error">${escapeHtml(error.message)}</div>`;
    }
  }

  async function saveScoreAndShowResults(game, score, meta = {}) {
    try {
      await postScore(game, score, meta);
      await showPostGamePanel({ game, score, meta });
    } catch (error) {
      showToast(error.message || 'Unable to save score right now.', 'error');
    }
  }

  async function hydrateMiniLeaderboard(container, game) {
    try {
      const payload = await fetchLeaderboard(game, 3);
      const rows = payload.top || [];
      if (!rows.length) {
        container.innerHTML = '<div class="mini-leaderboard-row"><span>No scores yet</span></div>';
        return;
      }

      container.innerHTML = rows.map((row) => `
        <div class="mini-leaderboard-row">
          <span>#${row.rank}</span>
          <strong>${escapeHtml(row.phone)}</strong>
          <span style="margin-left:auto;">${Number(row.best_score || 0).toLocaleString()}</span>
        </div>
      `).join('');
    } catch (error) {
      container.innerHTML = '<div class="mini-leaderboard-row"><span>Leaderboard unavailable</span></div>';
    }
  }

  window.ZikGames = {
    apiJson,
    showToast,
    postScore,
    fetchLeaderboard,
    pingPresence,
    checkOnlinePeers,
    updatePresenceUI,
    initPresencePing,
    initPresenceLoop,
    buildShareUrls,
    showLeaderboardModal,
    saveScoreAndShowResults,
    hydrateMiniLeaderboard,
    closeModal,
    copyShareCaption,
    openTikTokShare,
  };
})();
