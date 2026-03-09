/**
 * Admin Content Frame: runs inside the iframe.
 * - Sync theme from parent/localStorage
 * - Ensure form actions include content=1 so POST stays in iframe
 * - Expose showParentToast() for pages to show toasts in shell
 */
(function() {
  const STORAGE_KEY = 'admin-theme';

  function applyTheme(theme) {
    if (!theme) return;
    document.documentElement.setAttribute('data-theme', theme);
    try { localStorage.setItem(STORAGE_KEY, theme); } catch (e) {}
  }

  // 1) Theme: listen for parent message or read localStorage
  if (window.parent !== window) {
    window.addEventListener('message', function(e) {
      if (e.data && e.data.type === 'theme') applyTheme(e.data.theme);
    });
    applyTheme(localStorage.getItem(STORAGE_KEY) || document.documentElement.getAttribute('data-theme') || 'light');
  }

  // 2) Form actions: add content=1 so POST response stays in iframe
  function ensureContentParam() {
    document.querySelectorAll('form[action]').forEach(function(form) {
      var action = form.getAttribute('action');
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

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ensureContentParam);
  } else {
    ensureContentParam();
  }

  // 3) Global: show toast in parent shell
  window.showParentToast = function(message, variant) {
    if (window.parent && window.parent !== window) {
      window.parent.postMessage({ type: 'toast', message: message, variant: variant || 'info' }, '*');
    }
  };
})();
