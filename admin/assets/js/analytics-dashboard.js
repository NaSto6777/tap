document.addEventListener('DOMContentLoaded', function () {
  const charts = {
    funnel: null,
    sales: null
  };

  const getCssVar = (name, fallback) => {
    const value = getComputedStyle(document.documentElement).getPropertyValue(name);
    return value ? value.trim() : fallback;
  };

  const hexToRgba = (hex, alpha) => {
    if (!hex) return `rgba(99,102,241, ${alpha})`;
    let clean = hex.replace('#', '');
    if (clean.length === 3) {
      clean = clean.split('').map(ch => ch + ch).join('');
    }
    const num = parseInt(clean, 16);
    const r = (num >> 16) & 255;
    const g = (num >> 8) & 255;
    const b = num & 255;
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
  };

  const withAlpha = (color, alpha) => {
    if (!color) return `rgba(148, 163, 184, ${alpha})`;
    color = color.trim();
    if (color.startsWith('#')) {
      return hexToRgba(color, alpha);
    }
    if (color.startsWith('rgba')) {
      const parts = color
        .replace('rgba', '')
        .replace('(', '')
        .replace(')', '')
        .split(',')
        .map(p => p.trim());
      if (parts.length >= 3) {
        return `rgba(${parts[0]}, ${parts[1]}, ${parts[2]}, ${alpha})`;
      }
    }
    if (color.startsWith('rgb')) {
      const parts = color
        .replace('rgb', '')
        .replace('(', '')
        .replace(')', '')
        .split(',')
        .map(p => p.trim());
      if (parts.length >= 3) {
        return `rgba(${parts[0]}, ${parts[1]}, ${parts[2]}, ${alpha})`;
      }
    }
    return color;
  };

  const formatNumber = (value) => new Intl.NumberFormat().format(value ?? 0);
  
  // Cache currency settings to avoid repeated DOM queries
  let cachedCurrencySymbol = null;
  let cachedCurrencyPosition = null;
  
  const getCurrencySettings = (forceRefresh = false) => {
    // Return cached values if available and not forcing refresh
    if (!forceRefresh && cachedCurrencySymbol !== null && cachedCurrencyPosition !== null) {
      return { symbol: cachedCurrencySymbol, position: cachedCurrencyPosition };
    }
    
    let symbol = '$';
    let position = 'left';
    let foundInDataAttr = false;
    let foundInWindow = false;
    
    // Try data attributes first (set directly in HTML, always available)
    const dashboardPage = document.querySelector('.analytics-dashboard-page');
    if (dashboardPage) {
      const dataSymbol = dashboardPage.getAttribute('data-currency-symbol');
      const dataPosition = dashboardPage.getAttribute('data-currency-position');
      if (dataSymbol !== null && dataSymbol !== '') {
        symbol = dataSymbol;
        foundInDataAttr = true;
      }
      if (dataPosition !== null && dataPosition !== '') {
        position = dataPosition;
        foundInDataAttr = true;
      }
    }
    
    // Fallback to window variables if data attributes not available
    if (typeof window !== 'undefined') {
      if (window.CURRENCY_SYMBOL !== undefined && window.CURRENCY_SYMBOL !== null && window.CURRENCY_SYMBOL !== '') {
        symbol = String(window.CURRENCY_SYMBOL);
        foundInWindow = true;
      }
      if (window.CURRENCY_POSITION !== undefined && window.CURRENCY_POSITION !== null && window.CURRENCY_POSITION !== '') {
        position = String(window.CURRENCY_POSITION);
        foundInWindow = true;
      }
    }
    
    // Cache the values
    cachedCurrencySymbol = symbol;
    cachedCurrencyPosition = position;
    
    if (!foundInDataAttr && !foundInWindow) {
      console.warn('Currency settings: Using defaults ($, left). Data attributes:', !!dashboardPage, 'Window vars:', !!window.CURRENCY_SYMBOL);
    } else {
      console.log('Currency settings loaded:', { symbol, position, fromDataAttr: foundInDataAttr, fromWindow: foundInWindow });
    }
    
    return { symbol, position };
  };
  
  // Initialize currency settings on page load
  const initCurrencySettings = () => {
    getCurrencySettings();
  };
  
  const formatCurrency = (value) => {
    const { symbol, position } = getCurrencySettings();
    
    const numValue = Number(value) || 0;
    const formatted = new Intl.NumberFormat('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(numValue);
    
    const result = position === 'left' ? `${symbol}${formatted}` : `${formatted} ${symbol}`;
    
    // Debug log for first few calls to verify currency is being used
    if (!formatCurrency._logged) {
      console.log('formatCurrency called:', { value, numValue, symbol, position, result });
      formatCurrency._logged = true;
      setTimeout(() => { formatCurrency._logged = false; }, 1000);
    }
    
    return result;
  };

  const destroyCharts = () => {
    if (charts.funnel) {
      charts.funnel.destroy();
      charts.funnel = null;
    }
    if (charts.sales) {
      charts.sales.destroy();
      charts.sales = null;
    }
  };

  const renderConversionFunnel = () => {
    if (!(window.ConversionFunnelData && document.getElementById('conversionFunnelChart'))) {
      return;
    }

    const ctx = document.getElementById('conversionFunnelChart').getContext('2d');
    const data = window.ConversionFunnelData.raw;
    const labels = window.ConversionFunnelData.labels;
    const total = data.reduce((a, b) => a + b, 0);

    const cardBg = getCssVar('--bg-card', '#ffffff');
    const textPrimary = getCssVar('--text-primary', '#0f172a');
    const textSecondary = getCssVar('--text-secondary', '#64748b');
    const borderPrimary = getCssVar('--border-primary', '#e2e8f0');

    const colors = [
      getCssVar('--color-primary', '#6366f1'),
      '#f97316',
      '#facc15',
      '#14b8a6',
      '#22c55e'
    ];

    charts.funnel = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{
          data,
          backgroundColor: colors,
          borderColor: cardBg,
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        cutout: '68%',
        plugins: {
          legend: { display: false },
          tooltip: {
            padding: 12,
            displayColors: false,
            backgroundColor: cardBg,
            borderColor: borderPrimary,
            borderWidth: 1,
            titleColor: textPrimary,
            bodyColor: textPrimary,
            callbacks: {
              label: function (context) {
                const label = context.label || '';
                const value = context.raw;
                const percent = total > 0 ? Math.round(1000 * value / total) / 10 : 0;
                return `${label}: ${formatNumber(value)} (${percent}%)`;
              }
            }
          }
        }
      }
    });

    ctx.canvas.style.width = 'auto';
    ctx.canvas.style.height = 'auto';

    const legendContainer = document.getElementById('conversionFunnelLegend');
    if (legendContainer) {
      let legendMarkup = '<div class="legend-list">';
      data.forEach(function (value, i) {
        const percent = total > 0 ? Math.round(1000 * value / total) / 10 : 0;
        legendMarkup += `
          <div class="legend-item">
            <span class="legend-swatch" style="background:${colors[i]};"></span>
            <div class="legend-body">
              <span class="legend-label">${labels[i]}</span>
              <span class="legend-subtext">${formatNumber(value)} • ${percent}% ${(window.translations && window.translations.of_sessions) || 'of sessions'}</span>
            </div>
          </div>
        `;
      });
      legendMarkup += '</div>';
      legendContainer.innerHTML = legendMarkup;
    }
  };

  const renderSalesTrends = () => {
    if (!(window.SalesTrendsData && document.getElementById('salesTrendsChart'))) {
      return;
    }

    const ctx = document.getElementById('salesTrendsChart').getContext('2d');
    const d = window.SalesTrendsData;

    const parent = ctx.canvas.parentNode;
    if (parent) {
      parent.style.position = 'relative';
      parent.style.height = '320px';
    }

    const primary = getCssVar('--color-primary', '#6366f1');
    const success = getCssVar('--color-success', '#10b981');
    const textPrimary = getCssVar('--text-primary', '#0f172a');
    const textSecondary = getCssVar('--text-secondary', '#64748b');
    const borderPrimary = getCssVar('--border-primary', '#e2e8f0');
    const cardBg = getCssVar('--bg-card', '#ffffff');

    charts.sales = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: d.labels,
        datasets: [
          {
            label: (window.translations && window.translations.orders) || 'Orders',
            data: d.orders,
            yAxisID: 'orders',
            type: 'bar',
            backgroundColor: withAlpha(primary, 0.28),
            borderColor: primary,
            borderWidth: 1.5
          },
          {
            label: (window.translations && window.translations.revenue) || 'Revenue',
            data: d.revenue,
            yAxisID: 'revenue',
            type: 'line',
            tension: 0.35,
            pointRadius: 3,
            fill: true,
            borderColor: success,
            backgroundColor: withAlpha(success, 0.16)
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'nearest', axis: 'x', intersect: false },
        plugins: {
          legend: {
            display: true,
            labels: {
              usePointStyle: true,
              pointStyle: 'circle',
              color: textPrimary
            }
          },
          tooltip: {
            backgroundColor: cardBg,
            borderColor: borderPrimary,
            borderWidth: 1,
            titleColor: textPrimary,
            bodyColor: textPrimary,
            padding: 12,
            callbacks: {
              label: function (context) {
                const label = context.dataset.label || '';
                const value = context.raw;
                const revenueLabel = (window.translations && window.translations.revenue) || 'Revenue';
                if (label === revenueLabel || label === 'Revenue') {
                  return `${label}: ${formatCurrency(value)}`;
                }
                return `${label}: ${formatNumber(value)}`;
              }
            }
          }
        },
        scales: {
          orders: {
            type: 'linear',
            position: 'left',
            grid: { drawOnChartArea: true, color: withAlpha(borderPrimary, 0.35) },
            ticks: { color: textSecondary },
            title: { display: true, text: (window.translations && window.translations.orders) || 'Orders', color: primary }
          },
          revenue: {
            type: 'linear',
            position: 'right',
            grid: { drawOnChartArea: false, color: withAlpha(borderPrimary, 0.15) },
            ticks: {
              color: textSecondary,
              callback: (value) => formatCurrency(value)
            },
            title: { display: true, text: (window.translations && window.translations.revenue) || 'Revenue', color: success }
          }
        }
      }
    });
  };

  const buildCharts = () => {
    destroyCharts();
    renderConversionFunnel();
    renderSalesTrends();
  };

  const analyticsApiUrl = window.ANALYTICS_API_URL || '../analytics_api.php';

  const postAnalyticsAction = async (action, payload) => {
    const body = new URLSearchParams({ action, ...payload });
    const response = await fetch(analyticsApiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.success) {
      throw new Error((data && data.message) || 'Unable to complete request');
    }
    return data;
  };

  const initAbandonedCarts = () => {
    const toggleBtn = document.getElementById('toggleAnonymousCarts');
    if (toggleBtn) {
      if (!toggleBtn.dataset.originalLabel) {
        toggleBtn.dataset.originalLabel = toggleBtn.textContent.trim();
      }
      toggleBtn.addEventListener('click', () => {
        const rows = document.querySelectorAll('.cart-row-anonymous');
        const isHidden = toggleBtn.getAttribute('data-state') !== 'shown';
        rows.forEach(row => {
          row.classList.toggle('is-visible', isHidden);
        });
        const translations = window.translations || {};
        toggleBtn.setAttribute('data-state', isHidden ? 'shown' : 'hidden');
        toggleBtn.textContent = isHidden ? (translations.hide_anonymous_carts || 'Hide anonymous carts') : toggleBtn.dataset.originalLabel;
      });
    }

    document.querySelectorAll('.js-edit-cart').forEach(btn => {
      btn.addEventListener('click', async () => {
        const translations = window.translations || {};
        const cartId = btn.dataset.cartId;
        const currentName = btn.dataset.cartName || '';
        const currentEmail = btn.dataset.cartEmail || '';
        const currentPhone = btn.dataset.cartPhone || '';

        let name = prompt((translations.customer_name || 'Customer name') + ':', currentName);
        if (name === null) return;
        name = name.trim();

        let email = prompt((translations.email || 'Email') + ' (' + (translations.required || 'required') + '):', currentEmail);
        if (email === null) return;
        email = email.trim();
        if (!email) {
          alert(translations.email_required || 'Email is required to save contact details.');
          return;
        }

        let phone = prompt((translations.phone || 'Phone') + ':', currentPhone);
        if (phone === null) return;
        phone = phone.trim();

        try {
          await postAnalyticsAction('update_customer_info', {
            cart_id: cartId,
            name,
            email,
            phone
          });
          alert(translations.customer_info_updated || 'Customer information updated.');
          window.location.reload();
        } catch (error) {
          alert(error.message);
        }
      });
    });

    document.querySelectorAll('.js-convert-cart').forEach(btn => {
      btn.addEventListener('click', async () => {
        const translations = window.translations || {};
        const cartId = btn.dataset.cartId;
        if (!confirm(translations.convert_cart_confirm || 'Convert this cart into a pending order?')) {
          return;
        }
        try {
          const result = await postAnalyticsAction('convert_to_order', { cart_id: cartId });
          const orderId = result.order_id ? ` ${translations.order_number || 'Order'} #${result.order_id}.` : '';
          alert((translations.cart_converted || 'Cart converted to order.') + orderId);
          window.location.reload();
        } catch (error) {
          alert(error.message);
        }
      });
    });
  };

  const fetchRecentOrders = async () => {
    try {
      const storeId = window.STORE_ID != null ? window.STORE_ID : '';
      const response = await fetch(`${analyticsApiUrl}?action=get_recent_orders&store_id=${encodeURIComponent(storeId)}`);
      const data = await response.json();
      if (!response.ok || !data.success) {
        throw new Error(data.message || 'Failed to load recent orders');
      }
      renderRecentOrders(data.orders || []);
    } catch (error) {
      renderRecentOrders([], error.message);
    }
  };

  const renderRecentOrders = (orders, errorMessage) => {
    const container = document.getElementById('recentOrdersContainer');
    if (!container) return;

    if (errorMessage) {
      container.innerHTML = `<div class="empty-state">${errorMessage}</div>`;
      return;
    }

    if (!orders.length) {
      container.innerHTML = `<div class="empty-state">${(window.translations && window.translations.no_orders_found) || 'No orders found for this period.'}</div>`;
      getCurrencySettings(true);
      const statsContainer = document.getElementById('recentOrdersStats');
      if (statsContainer) {
        const statValues = statsContainer.querySelectorAll('.stat-value');
        const zeroFormatted = formatCurrency(0);
        if (statValues.length >= 3) {
          statValues[0].textContent = zeroFormatted;
          statValues[1].textContent = zeroFormatted;
          statValues[2].textContent = zeroFormatted;
        }
      }
      return;
    }

    // Calculate stats
    let paidTotal = 0;
    let pendingTotal = 0;
    let grandTotal = 0;

    const rows = orders.map(order => {
      const total = Number(order.total) || 0;
      const status = (order.status || 'pending').toLowerCase();
      const badge = status === 'paid' ? 'success' : status === 'pending' ? 'warning' : status === 'refunded' ? 'secondary' : 'info';
      
      // Accumulate totals
      grandTotal += total;
      if (status === 'paid') {
        paidTotal += total;
      } else if (status === 'pending') {
        pendingTotal += total;
      }
      
      const translations = window.translations || {};
      const statusTranslations = {
        'paid': translations.paid || 'Paid',
        'pending': translations.pending || 'Pending',
        'refunded': translations.refunded || 'Refunded',
        'failed': translations.failed || 'Failed'
      };
      const statusText = statusTranslations[status] || status.charAt(0).toUpperCase() + status.slice(1);
      
      return `
        <tr data-order-id="${order.id}">
          <td>${order.order_number || order.id}</td>
          <td>${order.customer_name || order.customer_email || (translations.guest || 'Guest')}</td>
          <td>${order.date || ''}</td>
          <td>${formatCurrency(total)}</td>
          <td><span class="badge bg-${badge}">${statusText}</span></td>
          <td class="orders-actions">
            <button class="btn btn-sm btn-outline-primary js-view-order" data-order-id="${order.id}">${translations.view || 'View'}</button>
            <button class="btn btn-sm btn-light js-mark-paid" data-order-id="${order.id}">${translations.mark_paid || 'Mark Paid'}</button>
          </td>
        </tr>
      `;
    }).join('');
    
    // Update stats display - ensure currency is initialized first
    getCurrencySettings(true); // Force refresh to ensure we have latest values
    const statsContainer = document.getElementById('recentOrdersStats');
    if (statsContainer) {
      const statValues = statsContainer.querySelectorAll('.stat-value');
      if (statValues.length >= 3) {
        const paidFormatted = formatCurrency(paidTotal);
        const pendingFormatted = formatCurrency(pendingTotal);
        const totalFormatted = formatCurrency(grandTotal);
        
        statValues[0].textContent = paidFormatted;
        statValues[1].textContent = pendingFormatted;
        statValues[2].textContent = totalFormatted;
        
        console.log('Stats updated:', { paid: paidFormatted, pending: pendingFormatted, total: totalFormatted });
      } else {
        console.warn('Recent Orders Stats: Expected 3 stat values, found', statValues.length);
      }
    } else {
      console.warn('Recent Orders Stats: Container not found');
    }

    const translations = window.translations || {};
    container.innerHTML = `
      <table class="analytics-table">
        <thead>
          <tr>
            <th>${translations.order_number || 'Order #'}</th>
            <th>${translations.customer || 'Customer'}</th>
            <th>${translations.date || 'Date'}</th>
            <th>${translations.total || 'Total'}</th>
            <th>${translations.status || 'Status'}</th>
            <th>${translations.actions || 'Actions'}</th>
          </tr>
        </thead>
        <tbody>
          ${rows}
        </tbody>
      </table>
    `;

    container.querySelectorAll('.js-view-order').forEach(btn => {
      btn.addEventListener('click', () => {
        const orderId = btn.dataset.orderId;
        window.location.href = `?page=orders&view=${orderId}`;
      });
    });

    container.querySelectorAll('.js-mark-paid').forEach(btn => {
      btn.addEventListener('click', async () => {
        const translations = window.translations || {};
        const orderId = btn.dataset.orderId;
        if (!confirm(translations.mark_order_paid_confirm || 'Mark this order as paid?')) return;
        try {
          await postAnalyticsAction('mark_order_paid', { order_id: orderId });
          alert(translations.order_marked_paid || 'Order marked as paid.');
          fetchRecentOrders();
        } catch (error) {
          alert(error.message);
        }
      });
    });
  };

  const initRecentOrders = () => {
    fetchRecentOrders();
  };

  // Initialize currency settings first
  initCurrencySettings();
  
  buildCharts();
  initAbandonedCarts();
  initRecentOrders();

  const themeObserver = new MutationObserver((mutations) => {
    for (const mutation of mutations) {
      if (mutation.attributeName === 'data-theme') {
        buildCharts();
        break;
      }
    }
  });

  themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

  document.querySelectorAll('.data-card button[title="Edit"]').forEach(btn => {
    btn.addEventListener('click', function () {
      alert('Edit customer info coming soon!');
    });
  });
  document.querySelectorAll('.data-card button[title="Convert to Order"]').forEach(btn => {
    btn.addEventListener('click', function () {
      alert('Convert to order coming soon!');
    });
  });
});
