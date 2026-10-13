(function () {
  function closeAdminSidebar() {
    const sidebar = document.querySelector('.admin-sidebar');
    const overlay = document.getElementById('admin-sidebar-overlay');
    const toggle = document.getElementById('sidebar-toggle');

    if (!sidebar || !overlay || !toggle) {
      return;
    }

    sidebar.classList.remove('open');
    overlay.classList.remove('open');
    document.body.classList.remove('admin-nav-open');
    toggle.setAttribute('aria-expanded', 'false');
  }

  function toggleAdminSidebar() {
    const sidebar = document.querySelector('.admin-sidebar');
    const overlay = document.getElementById('admin-sidebar-overlay');
    const toggle = document.getElementById('sidebar-toggle');

    if (!sidebar || !overlay || !toggle) {
      return;
    }

    const willOpen = !sidebar.classList.contains('open');
    sidebar.classList.toggle('open', willOpen);
    overlay.classList.toggle('open', willOpen);
    document.body.classList.toggle('admin-nav-open', willOpen);
    toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
  }

  function initAdminNav() {
    const toggle = document.getElementById('sidebar-toggle');
    const overlay = document.getElementById('admin-sidebar-overlay');

    if (!toggle || !overlay) {
      return;
    }

    toggle.addEventListener('click', toggleAdminSidebar);
    overlay.addEventListener('click', closeAdminSidebar);

    document.querySelectorAll('.sidebar-nav a').forEach((link) => {
      link.addEventListener('click', function () {
        if (window.innerWidth <= 768) {
          closeAdminSidebar();
        }
      });
    });

    window.addEventListener('resize', function () {
      if (window.innerWidth > 768) {
        closeAdminSidebar();
      }
    });
  }

  window.addEventListener('load', initAdminNav);
})();
