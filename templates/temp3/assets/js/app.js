// TEMP3 — Boutique frontend
// - AJAX add-to-cart with toast notifications
// - Cart count badge refresh

(function () {
  'use strict';

  const doc = document;

  /* Toast system ---------------------------------------------------------- */
  const TOAST_CONTAINER_ID = 'bt-toast-container';

  function ensureToastContainer() {
    let container = doc.getElementById(TOAST_CONTAINER_ID);
    if (!container) {
      container = doc.createElement('div');
      container.id = TOAST_CONTAINER_ID;
      container.className = 'fixed inset-x-0 top-3 flex justify-center z-50 pointer-events-none';
      doc.body.appendChild(container);
    }
    return container;
  }

  function showToast(message, type) {
    const container = ensureToastContainer();
    const toast = doc.createElement('div');
    const isError = type === 'error';

    toast.className =
      'pointer-events-auto inline-flex items-center gap-3 rounded-full px-4 py-2 text-xs font-medium shadow-lg border ' +
      (isError
        ? 'bg-red-50 text-red-700 border-red-200'
        : 'bg-black text-white border-black/10');

    toast.innerHTML = `
      <span>${message}</span>
      <button type="button" class="opacity-70 hover:opacity-100 text-[10px]">Close</button>
    `;

    container.appendChild(toast);

    const closeBtn = toast.querySelector('button');
    closeBtn.addEventListener('click', () => {
      toast.classList.add('opacity-0', 'translate-y-1');
      setTimeout(() => toast.remove(), 180);
    });

    setTimeout(() => {
      if (!toast.isConnected) return;
      toast.classList.add('opacity-0', 'translate-y-1');
      setTimeout(() => toast.remove(), 180);
    }, 3500);
  }

  window.btShowToast = showToast;

  /* Cart count badge — update all elements with class .bt-cart-count (header + bottom nav) */
  async function refreshCartCount() {
    try {
      const res = await fetch('index.php?action=get_cart_count', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (!res.ok) throw new Error('Network error');
      const data = await res.json();
      const count = String(Number(data.count || 0));
      doc.querySelectorAll('.bt-cart-count').forEach(function (el) {
        el.textContent = count;
      });
    } catch (e) {
      // fail silently; count just stays as is
    }
  }

  window.btRefreshCartCount = refreshCartCount;

  /* Add-to-cart helper ---------------------------------------------------- */
  async function addToCart(form) {
    if (!form) return;
    const submitBtn = form.querySelector('[type="submit"]');
    const originalText = submitBtn ? submitBtn.innerHTML : null;

    const formData = new FormData(form);
    if (!formData.get('action')) {
      formData.set('action', 'add');
    }

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML =
        '<span class="inline-block h-3 w-3 rounded-full border border-white border-t-transparent animate-spin"></span>';
    }

    try {
      const res = await fetch('index.php?page=cart', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (!res.ok) throw new Error('Network error');
      const data = await res.json();
      if (data.success) {
        showToast('Added to cart', 'success');
        await refreshCartCount();
      } else {
        showToast(data.message || 'Could not add to cart.', 'error');
      }
    } catch (err) {
      showToast('Something went wrong. Please try again.', 'error');
    } finally {
      if (submitBtn && originalText !== null) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
      }
    }
  }

  window.btAddToCart = addToCart;

  /* Newsletter ------------------------------------------------------------ */
  async function submitNewsletter(form) {
    if (!form) return;
    const input = form.querySelector('input[name="email"]');
    const btn = form.querySelector('button[type="submit"]');
    const original = btn ? btn.innerHTML : null;

    const fd = new FormData(form);

    if (btn) {
      btn.disabled = true;
      btn.innerHTML =
        '<span class="inline-block h-3 w-3 rounded-full border border-white border-t-transparent animate-spin"></span>';
    }

    try {
      const res = await fetch('templates/temp3/newsletter_subscribe.php', {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (!res.ok) throw new Error('Network error');
      const data = await res.json();
      if (data.success) {
        showToast(data.message || 'Subscribed!', 'success');
        if (input) input.value = '';
      } else {
        showToast(data.message || 'Subscription failed.', 'error');
      }
    } catch (e) {
      showToast('Could not subscribe right now.', 'error');
    } finally {
      if (btn && original !== null) {
        btn.disabled = false;
        btn.innerHTML = original;
      }
    }
  }

  /* Wire forms with data-bt-cart on DOM ready ---------------------------- */
  doc.addEventListener('DOMContentLoaded', () => {
    refreshCartCount();
    const forms = doc.querySelectorAll('form[data-bt-cart]');
    forms.forEach((form) => {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        addToCart(form);
      });
    });

    const newsletterForm = doc.getElementById('bt-newsletter-form');
    if (newsletterForm) {
      newsletterForm.addEventListener('submit', function (e) {
        e.preventDefault();
        submitNewsletter(newsletterForm);
      });
    }
  });
})();

