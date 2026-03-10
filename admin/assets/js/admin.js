/**
 * Ultra-Modern Admin Panel JavaScript
 * Advanced interactions, animations, and UX enhancements
 */

// Global Admin State
const AdminState = {
  sidebarCollapsed: false,
  currentPage: 'dashboard',
  notifications: [],
  theme: localStorage.getItem('admin-theme') || 'light',
  isLoading: false,
  searchQuery: '',
  activeModals: new Set(),
  keyboardShortcuts: new Map()
};

// Utility Functions
const Utils = {
  // Debounce function for performance
  debounce(func, wait, immediate = false) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        timeout = null;
        if (!immediate) func(...args);
      };
      const callNow = immediate && !timeout;
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
      if (callNow) func(...args);
    };
  },

  // Throttle function for scroll events
  throttle(func, limit) {
    let inThrottle;
    return function(...args) {
      if (!inThrottle) {
        func.apply(this, args);
        inThrottle = true;
        setTimeout(() => inThrottle = false, limit);
      }
    };
  },

  // Generate unique IDs
  generateId(prefix = 'id') {
    return `${prefix}-${Math.random().toString(36).substr(2, 9)}`;
  },

  // Theme Management
  setTheme(theme) {
    AdminState.theme = theme;
    localStorage.setItem('admin-theme', theme);
    document.documentElement.setAttribute('data-theme', theme);
    // Keep brand primary color consistent across themes
    try {
      const root = document.documentElement;
      const computed = getComputedStyle(root);
      const brand =
        computed.getPropertyValue('--primary-color') ||
        computed.getPropertyValue('--color-primary-db') ||
        computed.getPropertyValue('--color-primary');
      const brandTrimmed = brand && brand.trim();
      if (brandTrimmed) {
        root.style.setProperty('--primary-color', brandTrimmed);
        root.style.setProperty('--color-primary', brandTrimmed);
      }
    } catch (e) {}
    this.updateThemeSwitcher(theme);
    
    // Save theme to server session via AJAX
    fetch('theme_handler.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ theme: theme })
    })
    .then(response => response.json())
    .then(data => {
      if (!data.success) {
        console.warn('Failed to save theme to session:', data.message);
      }
    })
    .catch(error => {
      console.error('Error saving theme to session:', error);
    });
  },

  getTheme() {
    return AdminState.theme;
  },

  toggleTheme() {
    const newTheme = AdminState.theme === 'light' ? 'dark' : 'light';
    this.setTheme(newTheme);
    this.showToast('Theme Changed', `Switched to ${newTheme} mode`, 'success');
  },

  updateThemeSwitcher(theme) {
    const switcher = document.querySelector('.theme-switcher-btn');
    if (switcher) {
      const icon = switcher.querySelector('i');
      if (icon) {
        icon.className = theme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
      }
    }
  },

  initializeTheme() {
    // Check if theme is already set from server session (via HTML data-theme attribute)
    const serverTheme = document.documentElement.getAttribute('data-theme');
    const localTheme = localStorage.getItem('admin-theme');
    
    // Prioritize server session theme, fallback to localStorage, then default to light
    const theme = serverTheme || localTheme || 'light';
    
    // Update AdminState and localStorage to match server theme
    AdminState.theme = theme;
    if (localTheme !== theme) {
      localStorage.setItem('admin-theme', theme);
    }
    
    // Apply theme (this will also sync with server)
    this.setTheme(theme);
    
    // Listen for system theme changes
    if (window.matchMedia) {
      const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
      mediaQuery.addEventListener('change', (e) => {
        if (!localStorage.getItem('admin-theme') && !document.documentElement.getAttribute('data-theme')) {
          this.setTheme(e.matches ? 'dark' : 'light');
        }
      });
    }
  },

  // Format numbers with commas
  formatNumber(num) {
    return new Intl.NumberFormat().format(num);
  },

  // Format currency
  formatCurrency(amount, currency = 'USD') {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currency
    }).format(amount);
  },

  // Format date
  formatDate(date, options = {}) {
    const defaultOptions = {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    };
    return new Intl.DateTimeFormat('en-US', { ...defaultOptions, ...options }).format(new Date(date));
  },

  // Animate element
  animate(element, keyframes, options = {}) {
    const defaultOptions = {
      duration: 300,
      easing: 'ease-in-out',
      fill: 'forwards'
    };
    return element.animate(keyframes, { ...defaultOptions, ...options });
  },

  // Check if element is in viewport
  isInViewport(element) {
    const rect = element.getBoundingClientRect();
    return (
      rect.top >= 0 &&
      rect.left >= 0 &&
      rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
      rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
  },

  // Copy to clipboard
  async copyToClipboard(text) {
    try {
      await navigator.clipboard.writeText(text);
      this.showToast('Copied to clipboard', 'success');
    } catch (err) {
      console.error('Failed to copy: ', err);
      this.showToast('Failed to copy', 'error');
    }
  },

  // Local storage helpers
  storage: {
    set(key, value) {
      try {
        localStorage.setItem(key, JSON.stringify(value));
      } catch (e) {
        console.error('Storage error:', e);
      }
    },
    get(key, defaultValue = null) {
      try {
        const item = localStorage.getItem(key);
        if (item === null) return defaultValue;
        
        // Try to parse as JSON, fallback to raw string if it fails
        try {
          return JSON.parse(item);
        } catch (parseError) {
          // If JSON parsing fails, return the raw string
          return item;
        }
      } catch (e) {
        console.error('Storage error:', e);
        return defaultValue;
      }
    },
    remove(key) {
      try {
        localStorage.removeItem(key);
      } catch (e) {
        console.error('Storage error:', e);
      }
    }
  }
};

// Animation Controller
const AnimationController = {
  // Fade in animation
  fadeIn(element, duration = 300) {
    element.style.opacity = '0';
    element.style.display = 'block';
    
    const animation = element.animate([
      { opacity: 0 },
      { opacity: 1 }
    ], {
      duration: duration,
      easing: 'ease-in-out',
      fill: 'forwards'
    });
    
    return animation;
  },

  // Fade out animation
  fadeOut(element, duration = 300) {
    const animation = element.animate([
      { opacity: 1 },
      { opacity: 0 }
    ], {
      duration: duration,
      easing: 'ease-in-out',
      fill: 'forwards'
    });
    
    animation.addEventListener('finish', () => {
      element.style.display = 'none';
    });
    
    return animation;
  },

  // Slide in from right
  slideInRight(element, duration = 300) {
    element.style.transform = 'translateX(100%)';
    element.style.display = 'block';
    
    const animation = element.animate([
      { transform: 'translateX(100%)' },
      { transform: 'translateX(0)' }
    ], {
      duration: duration,
      easing: 'ease-out',
      fill: 'forwards'
    });
    
    return animation;
  },

  // Slide out to right
  slideOutRight(element, duration = 300) {
    const animation = element.animate([
      { transform: 'translateX(0)' },
      { transform: 'translateX(100%)' }
    ], {
      duration: duration,
      easing: 'ease-in',
      fill: 'forwards'
    });
    
    animation.addEventListener('finish', () => {
      element.style.display = 'none';
    });
    
    return animation;
  },

  // Bounce animation
  bounce(element, duration = 600) {
    return element.animate([
      { transform: 'scale(1)' },
      { transform: 'scale(1.1)' },
      { transform: 'scale(1)' }
    ], {
      duration: duration,
      easing: 'ease-in-out'
    });
  },

  // Shake animation
  shake(element, duration = 500) {
    return element.animate([
      { transform: 'translateX(0)' },
      { transform: 'translateX(-10px)' },
      { transform: 'translateX(10px)' },
      { transform: 'translateX(-10px)' },
      { transform: 'translateX(10px)' },
      { transform: 'translateX(0)' }
    ], {
      duration: duration,
      easing: 'ease-in-out'
    });
  },

  // Pulse animation
  pulse(element, duration = 1000) {
    return element.animate([
      { transform: 'scale(1)', opacity: 1 },
      { transform: 'scale(1.05)', opacity: 0.8 },
      { transform: 'scale(1)', opacity: 1 }
    ], {
      duration: duration,
      easing: 'ease-in-out',
      iterations: Infinity
    });
  }
};

// Toast Notification System
const ToastSystem = {
  container: null,

  init() {
    this.container = document.createElement('div');
    this.container.className = 'toast-container';
    this.container.setAttribute('aria-live', 'polite');
    this.container.setAttribute('aria-atomic', 'true');
    document.body.appendChild(this.container);
  },

  show(message, type = 'info', duration = 5000) {
    if (!this.container) this.init();

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');

    const iconMap = {
      success: 'fas fa-check-circle',
      error: 'fas fa-exclamation-circle',
      warning: 'fas fa-exclamation-triangle',
      info: 'fas fa-info-circle'
    };

    toast.innerHTML = `
      <i class="${iconMap[type] || iconMap.info}"></i>
      <span>${message}</span>
      <button class="toast-close" aria-label="Close notification">
        <i class="fas fa-times"></i>
      </button>
    `;

    // Add close functionality
    const closeBtn = toast.querySelector('.toast-close');
    closeBtn.addEventListener('click', () => this.remove(toast));

    // Auto remove
    if (duration > 0) {
      setTimeout(() => this.remove(toast), duration);
    }

    this.container.appendChild(toast);
    AnimationController.fadeIn(toast);

    return toast;
  },

  remove(toast) {
    AnimationController.fadeOut(toast, 200).addEventListener('finish', () => {
      if (toast.parentNode) {
        toast.parentNode.removeChild(toast);
      }
    });
  },

  success(message, duration = 5000) {
    return this.show(message, 'success', duration);
  },

  error(message, duration = 7000) {
    return this.show(message, 'error', duration);
  },

  warning(message, duration = 6000) {
    return this.show(message, 'warning', duration);
  },

  info(message, duration = 5000) {
    return this.show(message, 'info', duration);
  }
};

// Loading States Manager
const LoadingManager = {
  // Show button loading state
  showButtonLoading(button, text = 'Loading...') {
    const originalText = button.innerHTML;
    const originalDisabled = button.disabled;
    
    button.disabled = true;
    button.innerHTML = `<i class="fas fa-spinner fa-spin me-2"></i>${text}`;
    button.classList.add('loading');
    
    button.dataset.originalText = originalText;
    button.dataset.originalDisabled = originalDisabled;
  },

  // Hide button loading state
  hideButtonLoading(button) {
    if (button.dataset.originalText) {
      button.innerHTML = button.dataset.originalText;
      button.disabled = button.dataset.originalDisabled === 'true';
      button.classList.remove('loading');
      
      delete button.dataset.originalText;
      delete button.dataset.originalDisabled;
    }
  },

  // Show page loading overlay
  showPageLoading(message = 'Loading...') {
    const overlay = document.createElement('div');
    overlay.id = 'page-loading-overlay';
    overlay.className = 'page-loading-overlay';
    overlay.innerHTML = `
      <div class="loading-spinner">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-3">${message}</p>
      </div>
    `;
    document.body.appendChild(overlay);
    document.body.classList.add('loading');
  },

  // Hide page loading overlay
  hidePageLoading() {
    const overlay = document.getElementById('page-loading-overlay');
    if (overlay) {
      AnimationController.fadeOut(overlay, 200).addEventListener('finish', () => {
        overlay.remove();
      });
    }
    document.body.classList.remove('loading');
  },

  // Show skeleton loader
  showSkeleton(container, rows = 5) {
    const skeleton = document.createElement('div');
    skeleton.className = 'skeleton-loader';
    
    for (let i = 0; i < rows; i++) {
      const row = document.createElement('div');
      row.className = 'skeleton-line';
      skeleton.appendChild(row);
    }
    
    container.innerHTML = '';
    container.appendChild(skeleton);
  },

  // Hide skeleton loader
  hideSkeleton(container) {
    const skeleton = container.querySelector('.skeleton-loader');
    if (skeleton) {
      skeleton.remove();
    }
  }
};

// Search and Filter System
const SearchSystem = {
  // Enhanced search with debouncing
  init() {
    const searchInputs = document.querySelectorAll('.search-input, .filter-input');
    searchInputs.forEach(input => {
      const debouncedSearch = Utils.debounce((query) => {
        this.performSearch(input, query);
      }, 300);
      
      input.addEventListener('input', (e) => {
        debouncedSearch(e.target.value);
      });
    });
  },

  // Perform search with highlighting
  performSearch(input, query) {
    const container = input.closest('.card, .table-container, .content');
    if (!container) return;
    
    const searchableElements = container.querySelectorAll('tr, .card, .list-item');
    const searchTerm = query.toLowerCase().trim();
    
    if (searchTerm === '') {
      searchableElements.forEach(element => {
        element.style.display = '';
        this.removeHighlights(element);
      });
      return;
    }
    
    searchableElements.forEach(element => {
      const text = element.textContent.toLowerCase();
      if (text.includes(searchTerm)) {
        element.style.display = '';
        this.highlightText(element, searchTerm);
      } else {
        element.style.display = 'none';
      }
    });
  },

  // Highlight search terms
  highlightText(element, searchTerm) {
    const walker = document.createTreeWalker(
      element,
      NodeFilter.SHOW_TEXT,
      null,
      false
    );
    
    const textNodes = [];
    let node;
    while (node = walker.nextNode()) {
      textNodes.push(node);
    }
    
    textNodes.forEach(textNode => {
      const text = textNode.textContent;
      const regex = new RegExp(`(${searchTerm})`, 'gi');
      if (regex.test(text)) {
        const highlightedText = text.replace(regex, '<mark class="search-highlight">$1</mark>');
        const wrapper = document.createElement('span');
        wrapper.innerHTML = highlightedText;
        textNode.parentNode.replaceChild(wrapper, textNode);
      }
    });
  },

  // Remove highlights
  removeHighlights(element) {
    const highlights = element.querySelectorAll('.search-highlight');
    highlights.forEach(highlight => {
      const parent = highlight.parentNode;
      parent.replaceChild(document.createTextNode(highlight.textContent), highlight);
      parent.normalize();
    });
  }
};

// Keyboard Shortcuts System
const KeyboardShortcuts = {
  shortcuts: new Map(),

  init() {
    document.addEventListener('keydown', this.handleKeyDown.bind(this));
    
    // Register default shortcuts
    this.register('ctrl+s', (e) => {
      e.preventDefault();
      const saveButton = document.querySelector('button[type="submit"], .btn-save');
      if (saveButton) {
        saveButton.click();
      }
    });
    
    this.register('ctrl+k', (e) => {
      e.preventDefault();
      const searchInput = document.querySelector('.search-input, .filter-input');
      if (searchInput) {
        searchInput.focus();
      }
    });
    
    this.register('escape', (e) => {
      this.closeModals();
      this.closeDropdowns();
    });
    
    this.register('ctrl+/', (e) => {
      e.preventDefault();
      this.showShortcutsHelp();
    });
  },

  register(key, callback) {
    this.shortcuts.set(key, callback);
  },

  handleKeyDown(e) {
    const key = this.getKeyString(e);
    const callback = this.shortcuts.get(key);
    
    if (callback) {
      callback(e);
    }
  },

  getKeyString(e) {
    const keys = [];
    if (e.ctrlKey) keys.push('ctrl');
    if (e.altKey) keys.push('alt');
    if (e.shiftKey) keys.push('shift');
    if (e.metaKey) keys.push('meta');
    keys.push(e.key.toLowerCase());
    
    return keys.join('+');
  },

  closeModals() {
    const modals = document.querySelectorAll('.modal.show');
    modals.forEach(modal => {
      const bsModal = bootstrap.Modal.getInstance(modal);
      if (bsModal) bsModal.hide();
    });
  },

  closeDropdowns() {
    const dropdowns = document.querySelectorAll('.dropdown-menu.show');
    dropdowns.forEach(dropdown => {
      dropdown.classList.remove('show');
    });
  },

  showShortcutsHelp() {
    ToastSystem.info('Keyboard shortcuts: Ctrl+S (Save), Ctrl+K (Search), Esc (Close)');
  }
};

// Sidebar Controller
const SidebarController = {
  init() {
    this.sidebar = document.getElementById('sidebar');
    this.overlay = document.getElementById('sidebarOverlay');
    this.toggle = document.getElementById('sidebarToggle');
    this.mainContent = document.querySelector('.main-content');
    
    if (this.toggle) {
      this.toggle.addEventListener('click', this.toggleSidebar.bind(this));
    }
    
    if (this.overlay) {
      this.overlay.addEventListener('click', this.closeSidebar.bind(this));
    }
    
    // Load saved state
    const savedState = Utils.storage.get('sidebarCollapsed', false);
    if (savedState) {
      this.collapseSidebar();
    }
    
    // Handle window resize
    window.addEventListener('resize', Utils.throttle(this.handleResize.bind(this), 250));
    
    // Enhance navigation with modern features
    this.enhanceNavigation();
  },

  toggleSidebar() {
    if (!this.sidebar) return;
    if (window.innerWidth < 768) {
      this.sidebar.classList.toggle('show');
      if (this.overlay) this.overlay.classList.toggle('show');
    } else {
      if (AdminState.sidebarCollapsed) {
        this.expandSidebar();
      } else {
        this.collapseSidebar();
      }
    }
  },

  collapseSidebar() {
    if (!this.sidebar) return;
    AdminState.sidebarCollapsed = true;
    this.sidebar.classList.add('collapsed');
    if (this.mainContent) this.mainContent.classList.add('sidebar-collapsed');
    Utils.storage.set('sidebarCollapsed', true);
  },

  expandSidebar() {
    if (!this.sidebar) return;
    AdminState.sidebarCollapsed = false;
    this.sidebar.classList.remove('collapsed');
    if (this.mainContent) this.mainContent.classList.remove('sidebar-collapsed');
    Utils.storage.set('sidebarCollapsed', false);
  },

  closeSidebar() {
    if (!this.sidebar) return;
    if (window.innerWidth < 768) {
      this.sidebar.classList.remove('show');
      if (this.overlay) this.overlay.classList.remove('show');
    }
  },

  handleResize() {
    if (!this.sidebar) return;
    if (window.innerWidth >= 768) {
      this.sidebar.classList.remove('show');
      if (this.overlay) this.overlay.classList.remove('show');
    }
  },

  // Enhanced navigation features
  enhanceNavigation() {
    this.addNavigationTooltips();
    this.addRippleEffects();
    this.addKeyboardNavigation();
    this.addSmoothScrolling();
  },

  addNavigationTooltips() {
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
      const text = link.querySelector('span')?.textContent;
      if (text) {
        link.setAttribute('title', text);
      }
    });
  },

  addRippleEffects() {
    document.querySelectorAll('.nav-link').forEach(link => {
      link.addEventListener('mousedown', (e) => {
        this.createRippleEffect(e, link);
      });
    });
  },

  createRippleEffect(e, element) {
    const ripple = document.createElement('span');
    const rect = element.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = e.clientX - rect.left - size / 2;
    const y = e.clientY - rect.top - size / 2;
    
    ripple.style.cssText = `
      position: absolute;
      width: ${size}px;
      height: ${size}px;
      left: ${x}px;
      top: ${y}px;
      background: rgba(99, 102, 241, 0.3);
      border-radius: 50%;
      transform: scale(0);
      animation: ripple 0.6s linear;
      pointer-events: none;
      z-index: 1;
    `;
    
    element.style.position = 'relative';
    element.style.overflow = 'hidden';
    element.appendChild(ripple);
    
    setTimeout(() => {
      ripple.remove();
    }, 600);
  },

  addKeyboardNavigation() {
    document.addEventListener('keydown', (e) => {
      if (e.altKey) {
        const navItems = Array.from(document.querySelectorAll('.nav-link'));
        const currentIndex = navItems.findIndex(item => item.classList.contains('active'));
        
        switch(e.key) {
          case 'ArrowDown':
            e.preventDefault();
            const nextIndex = (currentIndex + 1) % navItems.length;
            navItems[nextIndex].focus();
            break;
          case 'ArrowUp':
            e.preventDefault();
            const prevIndex = currentIndex <= 0 ? navItems.length - 1 : currentIndex - 1;
            navItems[prevIndex].focus();
            break;
        }
      }
    });
  },

  addSmoothScrolling() {
    const navLinks = document.querySelectorAll('.nav-link[href^="#"]');
    navLinks.forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const targetId = link.getAttribute('href').substring(1);
        const targetElement = document.getElementById(targetId);
        if (targetElement) {
          targetElement.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }
      });
    });
  },

  // Add badge to navigation item
  addBadge(selector, count, type = 'primary') {
    const navLink = document.querySelector(selector);
    if (navLink) {
      // Remove existing badge
      const existingBadge = navLink.querySelector('.nav-badge');
      if (existingBadge) {
        existingBadge.remove();
      }
      
      // Add new badge
      if (count > 0) {
        const badge = document.createElement('span');
        badge.className = `nav-badge ${type}`;
        badge.textContent = count > 99 ? '99+' : count;
        navLink.appendChild(badge);
      }
    }
  },

  // Remove badge from navigation item
  removeBadge(selector) {
    const navLink = document.querySelector(selector);
    if (navLink) {
      const badge = navLink.querySelector('.nav-badge');
      if (badge) {
        badge.remove();
      }
    }
  }
};

// Mobile Bottom Navigation Controller
const MobileNavController = {
  init() {
    this.nav = document.getElementById('mobileBottomNav');
    this.moreMenu = document.getElementById('moreMenu');
    this.moreToggle = document.getElementById('moreMenuToggle');
    
    if (this.moreToggle && this.moreMenu) {
      this.moreToggle.addEventListener('click', this.toggleMoreMenu.bind(this));
    }
    
    // Close more menu when clicking outside
    document.addEventListener('click', (e) => {
      if (this.moreMenu && !this.moreToggle.contains(e.target) && !this.moreMenu.contains(e.target)) {
        this.moreMenu.classList.remove('show');
      }
    });
    
    // Set active nav item
    this.setActiveNavItem();
  },

  toggleMoreMenu(e) {
    e.preventDefault();
    e.stopPropagation();
    this.moreMenu.classList.toggle('show');
  },

  setActiveNavItem() {
    const currentPage = window.location.search.split('page=')[1] || 'dashboard';
    const navLinks = document.querySelectorAll('.mobile-bottom-nav .nav-link');
    
    navLinks.forEach(link => {
      const href = link.getAttribute('href');
      if (href && href.includes(`page=${currentPage}`)) {
        link.classList.add('active');
      } else {
        link.classList.remove('active');
      }
    });
  }
};

// Tooltip System
const TooltipSystem = {
  init() {
    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl, {
        trigger: 'hover focus',
        placement: 'top',
        delay: { show: 500, hide: 100 }
      });
    });
    
    // Auto-add tooltips to action buttons
    this.autoAddTooltips();
    
    // Watch for dynamically added elements
    this.observeChanges();
  },

  autoAddTooltips() {
    const actionButtons = document.querySelectorAll('.btn-sm, .btn-xs, .action-btn');
    actionButtons.forEach(button => {
      if (!button.hasAttribute('data-bs-toggle')) {
        const title = button.getAttribute('title') || button.getAttribute('aria-label') || 'Action';
        button.setAttribute('data-bs-toggle', 'tooltip');
        button.setAttribute('title', title);
        new bootstrap.Tooltip(button);
      }
    });
  },

  observeChanges() {
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (mutation.type === 'childList') {
          const newButtons = mutation.target.querySelectorAll('.btn-sm, .btn-xs, .action-btn');
          newButtons.forEach(button => {
            if (!button.hasAttribute('data-bs-toggle')) {
              const title = button.getAttribute('title') || button.getAttribute('aria-label') || 'Action';
              button.setAttribute('data-bs-toggle', 'tooltip');
              button.setAttribute('title', title);
              new bootstrap.Tooltip(button);
            }
          });
        }
      });
    });
    
    observer.observe(document.body, { childList: true, subtree: true });
  }
};

// Form Enhancement System
const FormEnhancement = {
  init() {
    this.enhanceFloatingLabels();
    this.enhanceFileUploads();
    this.enhanceFormValidation();
    this.enhanceAutoSave();
  },

  enhanceFloatingLabels() {
    const floatingInputs = document.querySelectorAll('.form-floating input, .form-floating textarea');
    
    floatingInputs.forEach(input => {
      // Check if input has value on load
      if (input.value) {
        input.classList.add('has-value');
      }
      
      // Add event listeners
      input.addEventListener('focus', () => {
        input.classList.add('focused');
      });
      
      input.addEventListener('blur', () => {
        input.classList.remove('focused');
        if (input.value) {
          input.classList.add('has-value');
        } else {
          input.classList.remove('has-value');
        }
      });
      
      input.addEventListener('input', () => {
        if (input.value) {
          input.classList.add('has-value');
        } else {
          input.classList.remove('has-value');
        }
      });
    });
  },

  enhanceFileUploads() {
    const fileUploads = document.querySelectorAll('.file-upload');
    
    fileUploads.forEach(upload => {
      const input = upload.querySelector('input[type="file"]');
      if (!input) return;
      
      // Drag and drop functionality
      upload.addEventListener('dragover', (e) => {
        e.preventDefault();
        upload.classList.add('dragover');
      });
      
      upload.addEventListener('dragleave', () => {
        upload.classList.remove('dragover');
      });
      
      upload.addEventListener('drop', (e) => {
        e.preventDefault();
        upload.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
          input.files = files;
          this.handleFileUpload(input, files[0]);
        }
      });
      
      // Click to upload
      upload.addEventListener('click', () => {
        input.click();
      });
      
      // File selection
      input.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
          this.handleFileUpload(input, e.target.files[0]);
        }
      });
    });
  },

  handleFileUpload(input, file) {
    const upload = input.closest('.file-upload');
    const preview = upload.querySelector('.file-preview');
    
    if (preview) {
      preview.innerHTML = `
        <div class="file-info">
          <i class="fas fa-file"></i>
          <span>${file.name}</span>
          <small>${this.formatFileSize(file.size)}</small>
        </div>
      `;
    }
    
    // Show upload progress
    this.showUploadProgress(upload);
  },

  formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  },

  showUploadProgress(upload) {
    const progress = document.createElement('div');
    progress.className = 'upload-progress';
    progress.innerHTML = '<div class="progress-bar"></div>';
    upload.appendChild(progress);
    
    // Simulate upload progress
    let width = 0;
    const interval = setInterval(() => {
      width += Math.random() * 20;
      if (width >= 100) {
        width = 100;
        clearInterval(interval);
        setTimeout(() => {
          progress.remove();
        }, 1000);
      }
      progress.querySelector('.progress-bar').style.width = width + '%';
    }, 200);
  },

  enhanceFormValidation() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
      form.addEventListener('submit', (e) => {
        if (!form.checkValidity()) {
          e.preventDefault();
          e.stopPropagation();
          
          // Show validation errors
          this.showValidationErrors(form);
        }
        
        form.classList.add('was-validated');
      });
    });
  },

  showValidationErrors(form) {
    const invalidInputs = form.querySelectorAll(':invalid');
    
    invalidInputs.forEach(input => {
      input.classList.add('is-invalid');
      
      // Add error message if not exists
      if (!input.nextElementSibling || !input.nextElementSibling.classList.contains('invalid-feedback')) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback';
        errorDiv.textContent = input.validationMessage;
        input.parentNode.insertBefore(errorDiv, input.nextSibling);
      }
    });
    
    // Focus first invalid input
    if (invalidInputs.length > 0) {
      invalidInputs[0].focus();
    }
  },

  enhanceAutoSave() {
    const autoSaveForms = document.querySelectorAll('form[data-auto-save]');
    
    autoSaveForms.forEach(form => {
      const inputs = form.querySelectorAll('input, textarea, select');
      const saveInterval = parseInt(form.dataset.autoSave) || 30000; // 30 seconds default
      
      let saveTimeout;
      
      inputs.forEach(input => {
        input.addEventListener('input', () => {
          clearTimeout(saveTimeout);
          saveTimeout = setTimeout(() => {
            this.autoSave(form);
          }, saveInterval);
        });
      });
    });
  },

  autoSave(form) {
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    // Save to localStorage
    const formId = form.id || 'auto-save-form';
    Utils.storage.set(`auto-save-${formId}`, data);
    
    // Show auto-save indicator
    ToastSystem.info('Form auto-saved', 2000);
  }
};

// Data Table Enhancements
const TableEnhancement = {
  init() {
    this.enhanceSorting();
    this.enhanceFiltering();
    this.enhancePagination();
    this.enhanceRowSelection();
  },

  enhanceSorting() {
    const sortableHeaders = document.querySelectorAll('th[data-sortable]');
    
    sortableHeaders.forEach(header => {
      header.style.cursor = 'pointer';
      header.addEventListener('click', () => {
        this.sortTable(header);
      });
    });
  },

  sortTable(header) {
    const table = header.closest('table');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const columnIndex = Array.from(header.parentNode.children).indexOf(header);
    const isAscending = header.classList.contains('sort-asc');
    
    // Remove existing sort classes
    header.parentNode.querySelectorAll('th').forEach(th => {
      th.classList.remove('sort-asc', 'sort-desc');
    });
    
    // Add sort class
    header.classList.add(isAscending ? 'sort-desc' : 'sort-asc');
    
    // Sort rows
    rows.sort((a, b) => {
      const aValue = a.children[columnIndex].textContent.trim();
      const bValue = b.children[columnIndex].textContent.trim();
      
      if (isAscending) {
        return bValue.localeCompare(aValue);
      } else {
        return aValue.localeCompare(bValue);
      }
    });
    
    // Reorder rows
    rows.forEach(row => tbody.appendChild(row));
  },

  enhanceFiltering() {
    const filterInputs = document.querySelectorAll('.table-filter');
    
    filterInputs.forEach(input => {
      input.addEventListener('input', Utils.debounce(() => {
        this.filterTable(input);
      }, 300));
    });
  },

  filterTable(input) {
    const table = input.closest('.table-container').querySelector('table');
    const tbody = table.querySelector('tbody');
    const rows = tbody.querySelectorAll('tr');
    const filterValue = input.value.toLowerCase();
    
    rows.forEach(row => {
      const text = row.textContent.toLowerCase();
      if (text.includes(filterValue)) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    });
  },

  enhancePagination() {
    const paginationLinks = document.querySelectorAll('.pagination a');
    
    paginationLinks.forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        this.loadPage(link.href);
      });
    });
  },

  loadPage(url) {
    LoadingManager.showPageLoading();
    
    fetch(url)
      .then(response => response.text())
      .then(html => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newContent = doc.querySelector('.content');
        
        if (newContent) {
          document.querySelector('.content').innerHTML = newContent.innerHTML;
          this.init(); // Re-initialize enhancements
        }
      })
      .catch(error => {
        console.error('Error loading page:', error);
        ToastSystem.error('Error loading page');
      })
      .finally(() => {
        LoadingManager.hidePageLoading();
      });
  },

  enhanceRowSelection() {
    const selectAllCheckbox = document.querySelector('th input[type="checkbox"]');
    const rowCheckboxes = document.querySelectorAll('tbody input[type="checkbox"]');
    
    if (selectAllCheckbox) {
      selectAllCheckbox.addEventListener('change', () => {
        rowCheckboxes.forEach(checkbox => {
          checkbox.checked = selectAllCheckbox.checked;
        });
        this.updateBulkActions();
      });
    }
    
    rowCheckboxes.forEach(checkbox => {
      checkbox.addEventListener('change', () => {
        this.updateBulkActions();
      });
    });
  },

  updateBulkActions() {
    const selectedRows = document.querySelectorAll('tbody input[type="checkbox"]:checked');
    const bulkActions = document.querySelector('.bulk-actions');
    
    if (bulkActions) {
      if (selectedRows.length > 0) {
        bulkActions.style.display = 'block';
        bulkActions.querySelector('.selected-count').textContent = selectedRows.length;
      } else {
        bulkActions.style.display = 'none';
      }
    }
  }
};

// Main Initialization
document.addEventListener('DOMContentLoaded', function() {
  // Initialize theme first
  Utils.initializeTheme();
  
  // Initialize all systems (only when relevant DOM exists – e.g. skip sidebar in content iframe)
  if (document.getElementById('sidebar')) SidebarController.init();
  MobileNavController.init();
  TooltipSystem.init();
  SearchSystem.init();
  KeyboardShortcuts.init();
  FormEnhancement.init();
  TableEnhancement.init();
  ToastSystem.init();
  
  // Theme switcher event listener
  const themeSwitcher = document.getElementById('themeSwitcher');
  if (themeSwitcher) {
    themeSwitcher.addEventListener('click', () => {
      Utils.toggleTheme();
    });
  }
  
  // Initialize existing functionality
  initSidebar();
  initMobileBottomNav();
  initTooltips();
  initToastSystem();
  initLoadingStates();
  initSkeletonLoaders();
  initEnhancedSearch();
  initKeyboardShortcuts();
  
  // Add smooth scrolling
  document.documentElement.style.scrollBehavior = 'smooth';
  
  // Add page transition effects
  addPageTransitions();
  
  // Theme is already initialized above via Utils.initializeTheme()
  
  // Initialize performance monitoring
  initPerformanceMonitoring();
  
  if (window.self === window.top) {
    console.log('🚀 Ultra-Modern Admin Panel initialized successfully!');
  }
});

// Legacy function compatibility
function initSidebar() {
  // Legacy sidebar initialization
}

function initMobileBottomNav() {
  // Legacy mobile nav initialization
}

function initTooltips() {
  // Legacy tooltips initialization
}

function initToastSystem() {
  // Legacy toast system initialization
}

function initLoadingStates() {
  // Legacy loading states initialization
}

function initSkeletonLoaders() {
  // Legacy skeleton loaders initialization
}

function initEnhancedSearch() {
  // Legacy search initialization
}

function initKeyboardShortcuts() {
  // Legacy keyboard shortcuts initialization
}

// Page Transition Effects (skip sidebar/mobile nav – those load in iframe via admin-shell.js)
function addPageTransitions() {
  const links = document.querySelectorAll('a[href*="page="]');
  links.forEach(link => {
    if (link.closest('.sidebar') || link.closest('.mobile-bottom-nav')) return;
    link.addEventListener('click', (e) => {
      e.preventDefault();
      const href = link.getAttribute('href');
      LoadingManager.showPageLoading();
      setTimeout(() => {
        window.location.href = href;
      }, 300);
    });
  });
}

// Theme System
function initThemeSystem() {
  // Delegate to the new unified theme initializer to avoid conflicting state
  if (!document.documentElement.getAttribute('data-theme')) {
    Utils.initializeTheme();
  } else {
    Utils.updateThemeSwitcher(Utils.getTheme());
  }
}

// Performance Monitoring
function initPerformanceMonitoring() {
  window.addEventListener('load', () => {
    if (window.self !== window.top) return;
    const perfData = performance.getEntriesByType('navigation')[0];
    if (perfData && typeof perfData.loadEventEnd === 'number' && perfData.loadEventEnd > 0) {
      const loadTime = perfData.loadEventEnd - (perfData.fetchStart || perfData.startTime);
      if (loadTime >= 0) console.log('Page load time:', Math.round(loadTime), 'ms');
    }
  });
  
  // Monitor long tasks
  if ('PerformanceObserver' in window) {
    const observer = new PerformanceObserver((list) => {
      for (const entry of list.getEntries()) {
        if (entry.duration > 50) {
          console.warn('Long task detected:', entry.duration, 'ms');
        }
      }
    });
    
    observer.observe({ entryTypes: ['longtask'] });
  }
}

// Export for global access
window.AdminState = AdminState;
window.Utils = Utils;
window.ToastSystem = ToastSystem;
window.LoadingManager = LoadingManager;
window.AnimationController = AnimationController;