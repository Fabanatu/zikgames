(function () {
  const MANIFEST_HREF = '/ZikGames/manifest.json';
  const SERVICE_WORKER_HREF = '/ZikGames/service-worker.js';
  const ICON_192 = '/ZikGames/assets/images/icon-192.png';
  const ICON_72 = '/ZikGames/assets/images/icon-72.png';
  const INSTALL_DISMISS_KEY = 'zikgames_install_prompt_dismissed_at';
  const SEEN_NOTIFICATIONS_KEY = 'zikgames_seen_notifications';
  const seenNotificationIds = new Set(JSON.parse(localStorage.getItem(SEEN_NOTIFICATIONS_KEY) || '[]'));
  let deferredInstallPrompt = null;

  function ensureManifestLinks() {
    if (!document.querySelector('link[rel="manifest"]')) {
      const link = document.createElement('link');
      link.rel = 'manifest';
      link.href = MANIFEST_HREF;
      document.head.appendChild(link);
    }

    if (!document.querySelector('link[rel="apple-touch-icon"]')) {
      const apple = document.createElement('link');
      apple.rel = 'apple-touch-icon';
      apple.href = ICON_192;
      document.head.appendChild(apple);
    }

    if (!document.querySelector('meta[name="theme-color"]')) {
      const meta = document.createElement('meta');
      meta.name = 'theme-color';
      meta.content = '#1d4ed8';
      document.head.appendChild(meta);
    }
  }

  function ensureInstallPopup() {
    if (document.getElementById('install-popup')) {
      return;
    }

    const style = document.createElement('style');
    style.textContent = `
      .install-popup {
        position: fixed;
        inset: 0;
        z-index: 1200;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(15, 23, 42, 0.32);
        backdrop-filter: blur(8px);
      }
      .install-card {
        width: min(100%, 360px);
        background: rgba(255,255,255,0.98);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow);
        padding: 22px 20px;
        text-align: center;
      }
      .install-card p {
        color: var(--text-muted);
        font-size: 0.9rem;
        margin: 10px 0 16px;
        line-height: 1.5;
      }
      .install-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
      }
    `;
    document.head.appendChild(style);

    const popup = document.createElement('div');
    popup.id = 'install-popup';
    popup.className = 'install-popup hidden';
    popup.innerHTML = `
      <div class="install-card">
        <div style="font-size:2.6rem;">📲</div>
        <h3 style="margin-top:8px;">Add ZikGames to Home Screen</h3>
        <p id="install-copy">Install ZikGames for faster access, a full-screen experience, and alerts from the admin team.</p>
        <div class="install-actions">
          <button class="btn btn-primary" type="button" id="install-now-btn">Add to Home Screen</button>
          <button class="btn btn-outline" type="button" id="install-later-btn">Maybe Later</button>
        </div>
      </div>
    `;
    document.body.appendChild(popup);

    document.getElementById('install-now-btn').addEventListener('click', handleInstallNow);
    document.getElementById('install-later-btn').addEventListener('click', dismissInstallPrompt);
  }

  function shouldShowInstallPrompt() {
    const dismissedAt = Number(localStorage.getItem(INSTALL_DISMISS_KEY) || 0);
    const oneDay = 24 * 60 * 60 * 1000;
    const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
    return !standalone && (Date.now() - dismissedAt > oneDay);
  }

  function showInstallPrompt(copyText) {
    ensureInstallPopup();
    if (copyText) {
      document.getElementById('install-copy').textContent = copyText;
    }
    document.getElementById('install-popup').classList.remove('hidden');
  }

  function dismissInstallPrompt() {
    const popup = document.getElementById('install-popup');
    if (popup) {
      popup.classList.add('hidden');
    }
    localStorage.setItem(INSTALL_DISMISS_KEY, Date.now().toString());
  }

  async function handleInstallNow() {
    if (deferredInstallPrompt) {
      deferredInstallPrompt.prompt();
      await deferredInstallPrompt.userChoice;
      deferredInstallPrompt = null;
    }
    dismissInstallPrompt();
  }

  async function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
      return;
    }

    try {
      await navigator.serviceWorker.register(SERVICE_WORKER_HREF);
    } catch (error) {
      console.warn('Service worker registration failed', error);
    }
  }

  async function fetchAdminNotifications() {
    if (!window.ZikGames?.apiJson) {
      return;
    }

    try {
      const payload = await window.ZikGames.apiJson('/ZikGames/api/user-notifications.php?limit=5');
      const notifications = payload.notifications || [];
      for (const notification of notifications) {
        const id = Number(notification.id);
        if (seenNotificationIds.has(id)) {
          continue;
        }

        seenNotificationIds.add(id);
        localStorage.setItem(SEEN_NOTIFICATIONS_KEY, JSON.stringify(Array.from(seenNotificationIds).slice(-40)));

        if (window.ZikGames?.showToast) {
          window.ZikGames.showToast(notification.title + ': ' + notification.message, 'info');
        }

        if ('Notification' in window && Notification.permission === 'granted' && 'serviceWorker' in navigator) {
          const reg = await navigator.serviceWorker.ready;
          reg.showNotification(notification.title, {
            body: notification.message,
            icon: ICON_192,
            badge: ICON_72,
            data: { url: '/ZikGames/dashboard.php' }
          });
        }
      }
    } catch (error) {
      console.warn('Notification fetch failed', error);
    }
  }

  function initInstallPromptFlow() {
    window.addEventListener('beforeinstallprompt', (event) => {
      event.preventDefault();
      deferredInstallPrompt = event;
      if (shouldShowInstallPrompt()) {
        showInstallPrompt();
      }
    });

    window.addEventListener('appinstalled', () => {
      deferredInstallPrompt = null;
      dismissInstallPrompt();
    });

    if (shouldShowInstallPrompt() && /iphone|ipad|ipod/i.test(navigator.userAgent)) {
      showInstallPrompt('On iPhone or iPad, tap Share and choose Add to Home Screen to install ZikGames.');
    }
  }

  async function init() {
    ensureManifestLinks();
    ensureInstallPopup();
    initInstallPromptFlow();
    await registerServiceWorker();

    if ('Notification' in window && Notification.permission === 'granted') {
      const banner = document.getElementById('notif-banner');
      if (banner) {
        banner.style.display = 'none';
      }
    }

    await fetchAdminNotifications();
    window.setInterval(fetchAdminNotifications, 60000);
  }

  window.dismissInstallPrompt = dismissInstallPrompt;
  window.addEventListener('load', init);
})();
