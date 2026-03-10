/**
 * Admin Content Frame: runs inside the iframe.
 * - Sync theme from parent/localStorage
 * - Ensure form actions and GET params include content=1 so navigation stays in iframe
 * - Intercept links so they always include content=1 (avoids full shell loading in iframe)
 * - Expose showParentToast() for pages to show toasts in shell
 */
(function() {
  const STORAGE_KEY = 'admin-theme';

  function applyTheme(theme, primaryColor) {
    if (theme) {
      document.documentElement.setAttribute('data-theme', theme);
      try { localStorage.setItem(STORAGE_KEY, theme); } catch (e) {}
    }
    if (primaryColor) {
      document.documentElement.style.setProperty('--primary-color', primaryColor);
      document.documentElement.style.setProperty('--color-primary', primaryColor);
    }
  }

  // 1) Theme: listen for parent message or read localStorage
  if (window.parent !== window) {
    window.addEventListener('message', function(e) {
      if (e.data && e.data.type === 'theme') applyTheme(e.data.theme, e.data.primaryColor);
    });
    applyTheme(localStorage.getItem(STORAGE_KEY) || document.documentElement.getAttribute('data-theme') || 'light');
  }

  /** Build URL that ensures content=1 for same-origin admin index.php */
  function ensureContentUrl(url) {
    try {
      var u = new URL(url, window.location.href);
      if (u.origin !== window.location.origin) return null;
      var path = u.pathname || '';
      if (path.indexOf('index.php') === -1 && path.slice(-1) !== '/' && path.indexOf('/admin/') === -1) return null;
      u.searchParams.set('content', '1');
      return u.toString();
    } catch (e) { return null; }
  }

  // 2) Form actions and GET forms: ensure content=1 so response stays in iframe
  function ensureContentParam() {
    document.querySelectorAll('form').forEach(function(form) {
      var action = form.getAttribute('action');
      var method = (form.getAttribute('method') || 'GET').toUpperCase();
      if (method === 'GET') {
        if (!form.querySelector('input[name="content"]')) {
          var hidden = document.createElement('input');
          hidden.setAttribute('type', 'hidden');
          hidden.setAttribute('name', 'content');
          hidden.setAttribute('value', '1');
          form.appendChild(hidden);
        }
      }
      if (!action || action.indexOf('content=1') !== -1) return;
      try {
        var url = new URL(action, window.location.href);
        url.searchParams.set('content', '1');
        form.setAttribute('action', url.toString());
      } catch (err) {
        var sep = action.indexOf('?') === -1 ? '?' : '&';
        form.setAttribute('action', action + sep + 'content=1');
      }
    });
  }

  // 3) Intercept link clicks so navigation always includes content=1 (prevents full shell in iframe)
  function handleLinkClick(e) {
    var a = e.target.closest('a[href]');
    if (!a || a.target === '_blank' || a.getAttribute('href').indexOf('logout') !== -1) return;
    var href = a.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
    var newUrl = ensureContentUrl(href);
    if (newUrl && newUrl !== (a.href || '')) {
      e.preventDefault();
      window.location.href = newUrl;
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ensureContentParam);
    document.addEventListener('DOMContentLoaded', function() {
      document.body.addEventListener('click', handleLinkClick, true);
    });
  } else {
    ensureContentParam();
    document.body.addEventListener('click', handleLinkClick, true);
  }

  // Run ensureContentParam again when new content is added (e.g. dynamic forms)
  var formObserver = null;
  if (typeof MutationObserver !== 'undefined') {
    formObserver = new MutationObserver(function() { ensureContentParam(); });
    document.addEventListener('DOMContentLoaded', function() {
      formObserver.observe(document.body, { childList: true, subtree: true });
    });
  }

  // 4) Tell parent shell the current page so sidebar stays in sync when navigating inside iframe
  function getCurrentPage() {
    try {
      var params = new URLSearchParams(window.location.search);
      return params.get('page') || '';
    } catch (e) { return ''; }
  }

  function notifyParentPage() {
    if (window.parent && window.parent !== window) {
      var page = getCurrentPage();
      if (page) window.parent.postMessage({ type: 'content_page', page: page }, '*');
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', notifyParentPage);
  } else {
    notifyParentPage();
  }
  window.addEventListener('pageshow', notifyParentPage);

  // 5) Global: show toast in parent shell
  window.showParentToast = function(message, variant) {
    if (window.parent && window.parent !== window) {
      window.parent.postMessage({ type: 'toast', message: message, variant: variant || 'info' }, '*');
    }
  };
})();
