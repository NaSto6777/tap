// temp2 Storefront Script
// - Navbar scroll behavior
// - Cart count badge
// - Newsletter AJAX
// - Toast notifications
// - Shared helpers for other pages (product_view, cart, checkout)

(function () {
    'use strict';

    const doc = document;

    /* --------------------------------------------------------------
       Navbar: transparent to solid on scroll
    -------------------------------------------------------------- */
    const navbar = doc.querySelector('.sf-navbar');
    const navScrollThreshold = 10;

    function handleScroll() {
        if (!navbar) return;
        if (window.scrollY > navScrollThreshold) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    }

    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();

    /* --------------------------------------------------------------
       Toast helper
    -------------------------------------------------------------- */
    const toastContainer = doc.querySelector('.sf-toast-container');

    function createToast(message, type = 'success') {
        if (!toastContainer) return;
        const el = doc.createElement('div');
        el.className = 'sf-toast';

        const icon = doc.createElement('div');
        icon.className = 'sf-toast-icon ' + (type === 'error' ? 'error' : 'success');
        icon.innerHTML = type === 'error'
            ? '<i class="fas fa-circle-exclamation"></i>'
            : '<i class="fas fa-circle-check"></i>';

        const msg = doc.createElement('div');
        msg.className = 'sf-toast-message';
        msg.textContent = message;

        const closeBtn = doc.createElement('button');
        closeBtn.className = 'sf-toast-close';
        closeBtn.type = 'button';
        closeBtn.innerHTML = '<i class="fas fa-xmark"></i>';
        closeBtn.addEventListener('click', () => el.remove());

        el.appendChild(icon);
        el.appendChild(msg);
        el.appendChild(closeBtn);
        toastContainer.appendChild(el);

        setTimeout(() => {
            el.classList.add('sf-toast-hide');
            setTimeout(() => el.remove(), 300);
        }, 4000);
    }

    // Expose toast helper globally for other pages
    window.sfShowToast = createToast;

    /* --------------------------------------------------------------
       Cart count badge
    -------------------------------------------------------------- */
    const cartCountEl = doc.getElementById('sf-cart-count');

    async function refreshCartCount() {
        if (!cartCountEl) return;
        try {
            const res = await fetch('index.php?action=get_cart_count', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (!res.ok) throw new Error('Network error');
            const data = await res.json();
            const count = Number(data.count || 0);
            cartCountEl.textContent = count;
        } catch (e) {
            // Fail silently — do not break UI
        }
    }

    window.sfRefreshCartCount = refreshCartCount;
    refreshCartCount();

    /* --------------------------------------------------------------
       Newsletter AJAX (footer)
    -------------------------------------------------------------- */
    const newsletterForm = doc.getElementById('sf-newsletter-form');
    if (newsletterForm) {
        const feedbackEl = doc.getElementById('sf-newsletter-feedback');
        const btnText = newsletterForm.querySelector('.sf-newsletter-btn-text');
        const btnSpinner = newsletterForm.querySelector('.sf-newsletter-btn-spinner');
        const btn = newsletterForm.querySelector('.sf-newsletter-btn');

        newsletterForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            if (!btn || !btnText || !btnSpinner) return;

            const formData = new FormData(newsletterForm);

            btn.disabled = true;
            btnText.classList.add('d-none');
            btnSpinner.classList.remove('d-none');
            if (feedbackEl) {
                feedbackEl.textContent = '';
                feedbackEl.classList.remove('success', 'error');
            }

            try {
                const res = await fetch('newsletter_subscribe.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                if (!res.ok) throw new Error('Network error');
                const data = await res.json();
                const ok = !!data.success;
                const msg = data.message || (ok ? 'Subscribed successfully.' : 'Subscription failed.');

                if (feedbackEl) {
                    feedbackEl.textContent = msg;
                    feedbackEl.classList.add(ok ? 'success' : 'error');
                }
                if (ok) {
                    newsletterForm.reset();
                    createToast('Subscribed to newsletter', 'success');
                } else {
                    createToast(msg, 'error');
                }
            } catch (err) {
                if (feedbackEl) {
                    feedbackEl.textContent = 'An error occurred. Please try again.';
                    feedbackEl.classList.add('error');
                }
                createToast('Could not subscribe right now.', 'error');
            } finally {
                btn.disabled = false;
                btnText.classList.remove('d-none');
                btnSpinner.classList.add('d-none');
            }
        });
    }

    /* --------------------------------------------------------------
       Search overlay (desktop + mobile)
    -------------------------------------------------------------- */
    const searchOverlay     = doc.getElementById('sf-search-overlay');
    const searchToggle      = doc.getElementById('sf-search-toggle');
    const searchClose       = doc.getElementById('sf-search-close');
    const mobileSearchToggle = doc.getElementById('sf-mobile-search-toggle');

    function openSearchOverlay() {
        if (!searchOverlay) return;
        searchOverlay.classList.add('active');
        const input = searchOverlay.querySelector('.sf-search-overlay-input');
        setTimeout(() => input && input.focus(), 50);
    }

    function closeSearchOverlay() {
        if (!searchOverlay) return;
        searchOverlay.classList.remove('active');
    }

    if (searchToggle) {
        searchToggle.addEventListener('click', openSearchOverlay);
    }
    if (mobileSearchToggle) {
        mobileSearchToggle.addEventListener('click', openSearchOverlay);
    }
    if (searchClose) {
        searchClose.addEventListener('click', closeSearchOverlay);
    }
    if (searchOverlay) {
        searchOverlay.addEventListener('click', (e) => {
            if (e.target === searchOverlay) {
                closeSearchOverlay();
            }
        });
    }
    doc.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeSearchOverlay();
        }
    });

    /* --------------------------------------------------------------
       Shared Add-to-Cart helper for product_view/shop + cart drawer
       Usage: window.sfAddToCart(formElement)
    -------------------------------------------------------------- */

    const cartDrawer         = doc.getElementById('sf-cart-drawer');
    const cartDrawerBackdrop = doc.getElementById('sf-cart-drawer-backdrop');
    const cartDrawerCloseBtn = doc.getElementById('sf-cart-drawer-close');
    const cartDrawerBody     = doc.getElementById('sf-cart-drawer-body');
    const cartDrawerSubtotal = doc.getElementById('sf-cart-drawer-subtotal');
    const mobileCartToggle   = doc.getElementById('sf-mobile-cart-toggle');
    const mobileCartCountEl  = doc.getElementById('sf-mobile-cart-count');

    function syncMobileCartCount(count) {
        if (mobileCartCountEl) {
            mobileCartCountEl.textContent = count;
        }
    }

    async function loadCartDrawer() {
        if (!cartDrawerBody || !cartDrawerSubtotal) return;
        try {
            const res = await fetch('ajax/cart_summary.php', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!res.ok) throw new Error('Network error');
            const data = await res.json();
            if (!data.success) throw new Error('Bad response');

            const items = data.items || [];
            const subtotalText = data.subtotal_formatted || data.subtotal || '0';
            cartDrawerSubtotal.textContent = subtotalText;

            if (!items.length) {
                cartDrawerBody.innerHTML = `
                    <div class="sf-cart-drawer-empty text-center py-4">
                        <p class="text-secondary mb-1">Your bag is empty.</p>
                        <a href="index.php?page=shop" class="sf-btn-outline mt-2">Start shopping</a>
                    </div>
                `;
                return;
            }

            const html = items.map(item => `
                <div class="sf-cart-drawer-item">
                    <div class="sf-cart-drawer-item-thumb">
                        <img src="${item.image}" alt="${item.name}">
                    </div>
                    <div class="sf-cart-drawer-item-info">
                        <div class="small text-white text-truncate">${item.name}</div>
                        ${item.variant ? `<div class="sf-cart-drawer-item-meta">${item.variant}</div>` : ''}
                        <div class="sf-cart-drawer-item-meta">Qty: ${item.quantity}</div>
                    </div>
                    <div class="sf-cart-drawer-item-price text-end">
                        ${item.total_formatted || item.total}
                    </div>
                </div>
            `).join('');

            cartDrawerBody.innerHTML = html;
        } catch (e) {
            cartDrawerBody.innerHTML = `
                <div class="text-center py-4">
                    <p class="text-secondary mb-1">Could not load your bag.</p>
                </div>
            `;
        }
    }

    function openCartDrawer() {
        if (!cartDrawer || !cartDrawerBackdrop) return;
        cartDrawer.classList.add('active');
        cartDrawerBackdrop.classList.add('active');
        loadCartDrawer();
    }

    function closeCartDrawer() {
        if (!cartDrawer || !cartDrawerBackdrop) return;
        cartDrawer.classList.remove('active');
        cartDrawerBackdrop.classList.remove('active');
    }

    if (cartDrawerBackdrop) {
        cartDrawerBackdrop.addEventListener('click', closeCartDrawer);
    }
    if (cartDrawerCloseBtn) {
        cartDrawerCloseBtn.addEventListener('click', closeCartDrawer);
    }
    if (mobileCartToggle) {
        mobileCartToggle.addEventListener('click', openCartDrawer);
    }

    window.sfOpenCartDrawer = openCartDrawer;

    async function addToCart(form) {
        if (!form) return;
        const submitBtn = form.querySelector('[type="submit"]');
        const originalHtml = submitBtn ? submitBtn.innerHTML : null;

        const formData = new FormData(form);
        formData.set('action', 'add');

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
        }

        try {
            const res = await fetch('index.php?page=cart', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (!res.ok) throw new Error('Network error');
            const data = await res.json();
            if (data.success) {
                createToast('Added to bag', 'success');
                refreshCartCount().then(() => {
                    const count = Number(cartCountEl ? cartCountEl.textContent : 0);
                    syncMobileCartCount(count);
                });
                openCartDrawer();
            } else {
                createToast(data.message || 'Could not add to cart.', 'error');
            }
        } catch (err) {
            createToast('Something went wrong. Please try again.', 'error');
        } finally {
            if (submitBtn && originalHtml !== null) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
            }
        }
    }

    window.sfAddToCart = addToCart;

    /* --------------------------------------------------------------
       Skeleton loader hookup
    -------------------------------------------------------------- */
    doc.addEventListener('DOMContentLoaded', () => {
        const skeletonWrappers = doc.querySelectorAll('.sf-skeleton');
        skeletonWrappers.forEach(wrapper => {
            const img = wrapper.querySelector('.sf-skeleton-img');
            if (!img) return;
            if (img.complete) {
                wrapper.classList.add('sf-skeleton-loaded');
            } else {
                img.addEventListener('load', () => {
                    wrapper.classList.add('sf-skeleton-loaded');
                });
                img.addEventListener('error', () => {
                    wrapper.classList.add('sf-skeleton-loaded');
                });
            }
        });
    });
})();

