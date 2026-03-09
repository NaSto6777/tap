/**
 * Analytics Tracker - JavaScript tracking system
 * Automatically tracks user interactions and sends data to analytics endpoint
 */

(function() {
    'use strict';
    
    // Configuration
    const config = {
        endpoint: 'config/analytics_endpoint.php',
        sessionId: null,
        trackClicks: true,
        trackForms: true,
        trackProducts: true,
        trackSearches: true,
        batchSize: 10,
        flushInterval: 30000, // 30 seconds
        debug: false
    };
    
    // Event queue for batching
    let eventQueue = [];
    let sessionId = null;
    let isInitialized = false;
    
    // Initialize session ID
    function initSession() {
        if (!sessionId) {
            sessionId = sessionStorage.getItem('analytics_session_id');
            if (!sessionId) {
                sessionId = 'sess_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                sessionStorage.setItem('analytics_session_id', sessionId);
            }
        }
        return sessionId;
    }
    
    // Log function for debugging
    function log(...args) {
        if (config.debug) {
            console.log('[Analytics]', ...args);
        }
    }
    
    // Send events to server
    function sendEvents(events) {
        if (!events || events.length === 0) return;
        
        fetch(config.endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                events: events,
                session_id: sessionId
            })
        })
        .then(response => response.json())
        .then(data => {
            log('Events sent successfully:', data);
        })
        .catch(error => {
            log('Error sending events:', error);
        });
    }
    
    // Flush event queue
    function flushEvents() {
        if (eventQueue.length > 0) {
            sendEvents([...eventQueue]);
            eventQueue = [];
        }
    }
    
    // Track an event
    function track(eventType, eventData = {}) {
        if (!isInitialized) {
            log('Analytics not initialized');
            return;
        }
        
        const event = {
            event_type: eventType,
            event_data: eventData,
            page_url: window.location.href,
            page_title: document.title,
            referrer: document.referrer,
            timestamp: new Date().toISOString(),
            user_agent: navigator.userAgent
        };
        
        eventQueue.push(event);
        log('Event tracked:', event);
        
        // Send immediately if batch size reached
        if (eventQueue.length >= config.batchSize) {
            flushEvents();
        }
    }
    
    // Track page view
    function trackPageView() {
        track('page_view', {
            page_url: window.location.href,
            page_title: document.title
        });
    }
    
    // Track product view
    function trackProductView(productId, productName = null) {
        track('product_view', {
            product_id: productId,
            product_name: productName
        });
    }
    
    // Track add to cart
    function trackAddToCart(productId, quantity = 1, productName = null) {
        track('add_to_cart', {
            product_id: productId,
            quantity: quantity,
            product_name: productName
        });
    }
    
    // Track search
    function trackSearch(searchTerm, resultsCount = 0) {
        track('search', {
            search_term: searchTerm,
            results_count: resultsCount
        });
    }
    
    // Track button click
    function trackButtonClick(buttonLabel, buttonSelector = null) {
        track('button_click', {
            button_label: buttonLabel,
            button_selector: buttonSelector
        });
    }
    
    // Track form submission
    function trackFormSubmission(formName, formData = {}) {
        track('form_submit', {
            form_name: formName,
            form_data: formData
        });
    }
    
    // Track funnel step
    function trackFunnelStep(step) {
        track('funnel_step', {
            step: step
        });
    }
    
    // Auto-detect and track clicks
    function setupClickTracking() {
        if (!config.trackClicks) return;
        
        document.addEventListener('click', function(e) {
            const element = e.target;
            
            // Check for data attributes
            const trackEvent = element.getAttribute('data-track-event');
            const trackLabel = element.getAttribute('data-track-label');
            const trackProductId = element.getAttribute('data-track-product-id');
            
            if (trackEvent) {
                let eventData = {};
                
                if (trackLabel) {
                    eventData.button_label = trackLabel;
                }
                
                if (trackProductId) {
                    eventData.product_id = trackProductId;
                }
                
                // Get additional data attributes
                const dataAttributes = element.attributes;
                for (let i = 0; i < dataAttributes.length; i++) {
                    const attr = dataAttributes[i];
                    if (attr.name.startsWith('data-track-') && attr.name !== 'data-track-event') {
                        const key = attr.name.replace('data-track-', '').replace(/-/g, '_');
                        eventData[key] = attr.value;
                    }
                }
                
                track(trackEvent, eventData);
            }
            
            // Auto-detect common elements
            if (element.tagName === 'BUTTON' || element.classList.contains('btn')) {
                const buttonText = element.textContent.trim() || element.value || 'Unknown Button';
                trackButtonClick(buttonText, element.className);
            }
            
            // Auto-detect product links - Updated for correct URL pattern
            if (element.tagName === 'A' && (element.href.includes('product_view') || element.href.includes('page=product_view'))) {
                const productId = element.href.match(/[?&]id=(\d+)/);
                if (productId) {
                    track('product_click', {
                        product_id: productId[1],
                        product_url: element.href
                    });
                }
            }
            
            // Auto-detect add to cart buttons
            if (element.tagName === 'BUTTON' && (element.textContent.toLowerCase().includes('add to cart') || element.classList.contains('add-to-cart'))) {
                const form = element.closest('form');
                if (form) {
                    const productId = form.querySelector('input[name="product_id"]')?.value;
                    const quantity = form.querySelector('input[name="quantity"]')?.value || 1;
                    if (productId) {
                        trackAddToCart(productId, quantity);
                    }
                }
            }
        });
    }
    
    // Auto-detect and track form submissions
    function setupFormTracking() {
        if (!config.trackForms) return;
        
        document.addEventListener('submit', function(e) {
            const form = e.target;
            const formName = form.name || form.id || 'Unknown Form';
            const formData = {};
            
            // Extract form data
            const formElements = form.elements;
            for (let i = 0; i < formElements.length; i++) {
                const element = formElements[i];
                if (element.name && element.type !== 'password') {
                    formData[element.name] = element.value;
                }
            }
            
            trackFormSubmission(formName, formData);
        });
    }
    
    // Track cart changes for abandonment
    function setupCartTracking() {
        let lastCartState = null;
        
        // Monitor cart changes
        function checkCartState() {
            const cartData = sessionStorage.getItem('cart') || localStorage.getItem('cart');
            if (cartData !== lastCartState) {
                lastCartState = cartData;
                
                if (cartData) {
                    try {
                        const cart = JSON.parse(cartData);
                        track('cart_update', {
                            cart_items: cart.length || Object.keys(cart).length,
                            cart_data: cart
                        });
                    } catch (e) {
                        log('Error parsing cart data:', e);
                    }
                }
            }
        }
        
        // Check cart state periodically
        setInterval(checkCartState, 5000);
        
        // Check on page load
        checkCartState();
    }
    
    // Track search functionality
    function setupSearchTracking() {
        if (!config.trackSearches) return;
        
        // Look for search forms
        const searchForms = document.querySelectorAll('form[action*="search"], form input[name*="search"], form input[type="search"]');
        
        searchForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const searchInput = form.querySelector('input[name*="search"], input[type="search"]');
                if (searchInput) {
                    const searchTerm = searchInput.value.trim();
                    if (searchTerm) {
                        // Count results (this would need to be implemented based on your search system)
                        const resultsCount = document.querySelectorAll('.search-result, .product-item').length;
                        trackSearch(searchTerm, resultsCount);
                    }
                }
            });
        });
    }
    
    // Track page performance
    function trackPagePerformance() {
        // Track page load time
        window.addEventListener('load', function() {
            // Use performance.now() for more reliable timing
            const loadTime = performance.now();
            const domReady = performance.timing.domContentLoadedEventEnd - performance.timing.navigationStart;
            
            // Only track if we have valid timing data
            if (loadTime > 0 && domReady > 0) {
                track('page_performance', {
                    load_time: Math.round(loadTime),
                    dom_ready: domReady
                });
            }
        });
        
        // Track time on page with periodic updates
        const startTime = Date.now();
        let timeOnPage = 0;
        
        // Send time updates every 30 seconds while on page
        const timeInterval = setInterval(function() {
            timeOnPage = Date.now() - startTime;
            if (timeOnPage > 5000) { // Only track if user spent more than 5 seconds
                track('time_on_page', {
                    time_on_page: timeOnPage,
                    page_url: window.location.href
                });
            }
        }, 30000);
        
        // Send final time on page when user leaves
        window.addEventListener('beforeunload', function() {
            clearInterval(timeInterval);
            timeOnPage = Date.now() - startTime;
            if (timeOnPage > 1000) { // Only track if user spent more than 1 second
                // Use sendBeacon for reliable delivery on page unload
                if (navigator.sendBeacon) {
                    const data = JSON.stringify({
                        events: [{
                            event_type: 'time_on_page',
                            event_data: {
                                time_on_page: timeOnPage,
                                page_url: window.location.href
                            },
                            page_url: window.location.href,
                            page_title: document.title,
                            timestamp: new Date().toISOString()
                        }],
                        session_id: sessionId
                    });
                    navigator.sendBeacon(config.endpoint, data);
                } else {
                    // Fallback to regular track
                    track('time_on_page', {
                        time_on_page: timeOnPage,
                        page_url: window.location.href
                    });
                }
            }
        });
        
        // Also track on page visibility change (when user switches tabs)
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                timeOnPage = Date.now() - startTime;
                if (timeOnPage > 1000) {
                    track('time_on_page', {
                        time_on_page: timeOnPage,
                        page_url: window.location.href
                    });
                }
            }
        });
    }
    
    // Initialize analytics
    function init(options = {}) {
        // Merge configuration
        Object.assign(config, options);
        
        // Initialize session
        sessionId = initSession();
        
        // Set up tracking
        setupClickTracking();
        setupFormTracking();
        setupCartTracking();
        setupSearchTracking();
        trackPagePerformance();
        
        // Track initial page view
        trackPageView();
        
        // Set up periodic flushing
        setInterval(flushEvents, config.flushInterval);
        
        // Flush on page unload
        window.addEventListener('beforeunload', flushEvents);
        
        isInitialized = true;
        log('Analytics initialized');
    }
    
    // Public API
    window.Analytics = {
        init: init,
        track: track,
        trackPageView: trackPageView,
        trackProductView: trackProductView,
        trackAddToCart: trackAddToCart,
        trackSearch: trackSearch,
        trackButtonClick: trackButtonClick,
        trackFormSubmission: trackFormSubmission,
        trackFunnelStep: trackFunnelStep,
        flush: flushEvents,
        config: config
    };
    
    // Auto-initialize if config is available
    if (window.AnalyticsConfig) {
        init(window.AnalyticsConfig);
    }
})();
