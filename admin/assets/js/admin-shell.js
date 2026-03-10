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

  /** Map iframe page to sidebar nav key (e.g. order_view_details -> orders) */
  function navPageFromContentPage(page) {
    if (!page) return page;
    var map = { order_view_details: 'orders', category_view_details: 'categories', product_quick_view: 'products' };
    return map[page] || page;
  }

  function setActiveNav(page) {
    var navPage = navPageFromContentPage(page);
    document.querySelectorAll('.sidebar .nav-link[data-page], .mobile-bottom-nav .nav-link[data-page]').forEach(function(a) {
      var isActive = a.getAttribute('data-page') === navPage;
      a.classList.toggle('active', isActive);
      a.setAttribute('aria-current', isActive ? 'page' : 'false');
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
    if (!e.data) return;
    if (e.data.type === 'content_page' && e.data.page) {
      setActiveNav(e.data.page);
      if (window.history && window.history.replaceState) {
        window.history.replaceState({ page: e.data.page }, '', 'index.php?page=' + encodeURIComponent(e.data.page));
      }
      return;
    }
    if (e.data.type === 'toast') {
      showShellToast(e.data.message, e.data.variant || 'info');
      return;
    }
    if (e.data.type === 'open_product_modal') {
      const productId = e.data.productId != null ? e.data.productId : null;
      const overlay = document.getElementById('shell-modal-overlay');
      const modalFrame = document.getElementById('shell-modal-frame');
      if (!overlay || !modalFrame) return;
      let url = 'index.php?content=1&page=products&modal=1';
      if (productId) url += '&product_id=' + encodeURIComponent(productId);
      modalFrame.src = url;
      overlay.classList.add('active');
      overlay.setAttribute('aria-hidden', 'false');
      return;
    }
    if (e.data.type === 'open_category_modal') {
      const categoryId = e.data.categoryId != null ? e.data.categoryId : null;
      const overlay = document.getElementById('shell-modal-overlay');
      const modalFrame = document.getElementById('shell-modal-frame');
      if (!overlay || !modalFrame) return;
      let url = 'index.php?content=1&page=categories&modal=1';
      if (categoryId) url += '&category_id=' + encodeURIComponent(categoryId);
      modalFrame.src = url;
      overlay.classList.add('active');
      overlay.setAttribute('aria-hidden', 'false');
      return;
    }
    if (e.data.type === 'open_plugin_modal') {
      const pluginKey = e.data.pluginKey || '';
      const overlay = document.getElementById('shell-modal-overlay');
      const modalFrame = document.getElementById('shell-modal-frame');
      if (!overlay || !modalFrame) return;
      let url = 'index.php?content=1&page=plugins&modal=1';
      if (pluginKey) url += '&plugin_key=' + encodeURIComponent(pluginKey);
      modalFrame.src = url;
      overlay.classList.add('active');
      overlay.setAttribute('aria-hidden', 'false');
      return;
    }
    if (e.data.type === 'open_order_status_modal') {
      const orderId = e.data.orderId != null ? e.data.orderId : 0;
      const status = e.data.status || 'pending';
      const payment = e.data.payment || 'pending';
      const overlay = document.getElementById('shell-modal-overlay');
      const modalFrame = document.getElementById('shell-modal-frame');
      if (!overlay || !modalFrame) return;
      let url = 'index.php?content=1&page=orders&modal=status&order_id=' + encodeURIComponent(orderId) + '&status=' + encodeURIComponent(status) + '&payment=' + encodeURIComponent(payment);
      modalFrame.src = url;
      overlay.classList.add('active');
      overlay.setAttribute('aria-hidden', 'false');
      return;
    }
    if (e.data.type === 'close_product_modal') {
      const overlay = document.getElementById('shell-modal-overlay');
      const modalFrame = document.getElementById('shell-modal-frame');
      if (overlay) {
        overlay.classList.remove('active');
        overlay.setAttribute('aria-hidden', 'true');
      }
      if (modalFrame) modalFrame.src = 'about:blank';
      if (e.data.refresh && frame) frame.src = frame.src;
      if (e.data.message) showShellToast(e.data.message, e.data.variant || 'success');
      return;
    }
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
