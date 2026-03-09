/**
 * Admin Shell: iframe navigation, theme sync to iframe, toast from iframe
 * Runs in the parent window (layout.php) only.
 */
(function() {
  const frame = document.getElementById('admin-frame');
  if (!frame) return;

  const TOAST_CONTAINER_ID = 'shell-toast-container';
  const STORAGE_KEY = 'admin-theme';

  // ----- Iframe navigation: intercept links, load in iframe -----
  function getContentUrl(page) {
    return 'index.php?content=1&page=' + encodeURIComponent(page);
  }

  function setActiveNav(page) {
    document.querySelectorAll('.sidebar .nav-link[data-page], .mobile-bottom-nav .nav-link[data-page]').forEach(function(a) {
      a.classList.toggle('active', a.getAttribute('data-page') === page);
      a.setAttribute('aria-current', a.getAttribute('data-page') === page ? 'page' : 'false');
    });
  }

  function handleNavClick(e) {
    const a = e.target.closest('a[data-page]');
    if (!a || a.getAttribute('href') === '#' || a.getAttribute('href').indexOf('logout') !== -1) return;
    e.preventDefault();
    const page = a.getAttribute('data-page');
    if (page) {
      frame.src = getContentUrl(page);
      setActiveNav(page);
      if (window.history && window.history.replaceState) {
        window.history.replaceState({ page: page }, '', 'index.php?page=' + encodeURIComponent(page));
      }
    }
  }

  document.querySelector('.sidebar') && document.querySelector('.sidebar').addEventListener('click', handleNavClick);
  document.getElementById('mobileBottomNav') && document.getElementById('mobileBottomNav').addEventListener('click', handleNavClick);

  // ----- Theme: broadcast to iframe when shell theme changes -----
  function getTheme() {
    return document.documentElement.getAttribute('data-theme') || localStorage.getItem(STORAGE_KEY) || 'light';
  }

  function getPrimaryColor() {
    try {
      const cs = getComputedStyle(document.documentElement);
      return (cs.getPropertyValue('--primary-color') || cs.getPropertyValue('--color-primary') || '').trim();
    } catch (e) {
      return '';
    }
  }

  function sendThemeToFrame() {
    try {
      if (frame.contentWindow) {
        frame.contentWindow.postMessage(
          { type: 'theme', theme: getTheme(), primaryColor: getPrimaryColor() },
          '*'
        );
      }
    } catch (err) {}
  }

  // After Utils.setTheme runs, notify iframe (patch after admin.js load)
  const origSetTheme = window.Utils && window.Utils.setTheme;
  if (origSetTheme) {
    window.Utils.setTheme = function(theme) {
      // Preserve original `this` (the Utils object) so
      // methods like `updateThemeSwitcher` still exist.
      origSetTheme.call(this, theme);
      sendThemeToFrame();
    };
  }

  frame.addEventListener('load', function() {
    sendThemeToFrame();
  });

  // ----- Toast: listen for messages from iframe -----
  function showShellToast(message, variant) {
    const container = document.getElementById(TOAST_CONTAINER_ID);
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = 'toast ' + (variant || 'info');
    toast.setAttribute('role', 'alert');
    const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
    const icon = icons[variant] || icons.info;
    toast.innerHTML = '<i class="fas ' + icon + '"></i><span>' + (message || '') + '</span><button class="toast-close" aria-label="Close"><i class="fas fa-times"></i></button>';
    const closeBtn = toast.querySelector('.toast-close');
    if (closeBtn) closeBtn.addEventListener('click', function() { toast.remove(); });
    container.appendChild(toast);
    setTimeout(function() { toast.remove(); }, 5000);
  }

  window.addEventListener('message', function(e) {
    if (!e.data || e.data.type !== 'toast') return;
    showShellToast(e.data.message, e.data.variant || 'info');
  });

  // Sidebar collapse: sync with main-content margin (admin.js SidebarController may already do this; ensure shell class is applied)
  const mainContent = document.querySelector('.main-content-shell');
  const sidebar = document.getElementById('sidebar');
  if (sidebar && mainContent) {
    const observer = new MutationObserver(function() {
      mainContent.classList.toggle('sidebar-collapsed', sidebar.classList.contains('collapsed'));
    });
    observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
  }
})();
