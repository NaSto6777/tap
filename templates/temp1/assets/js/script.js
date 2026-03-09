// Template 1 JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize cart count
    if (typeof updateCartCount === 'function') {
        updateCartCount();
    }

    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Add to cart functionality
    document.addEventListener('click', function(e) {
        if (e.target.matches('.add-to-cart') || e.target.closest('.add-to-cart')) {
            e.preventDefault();
            addToCart(e.target);
        }
    });
    
    // Quantity input validation
    document.addEventListener('input', function(e) {
        if (e.target.matches('input[name="quantity"]')) {
            validateQuantity(e.target);
        }
    });
    
    // Search functionality
    const searchForm = document.querySelector('form[method="GET"] input[name="search"]');
    if (searchForm) {
        searchForm.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.target.closest('form').submit();
            }
        });
    }
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Lazy loading for images
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
    
    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
    
    // Auto-hide alerts (skip important success/error messages)
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert:not(.alert-permanent):not(.alert-success):not(.alert-danger)');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Add animation classes on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
            }
        });
    }, observerOptions);
    
    document.querySelectorAll('.card, .feature-item').forEach(el => {
        observer.observe(el);
    });
});

// Cart functions
function updateCartCount() {
    const cartCount = document.getElementById('cart-count');
    if (!cartCount) return;

    fetch('index.php?action=get_cart_count', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => response.json())
        .then(data => {
            cartCount.textContent = (data && typeof data.count !== 'undefined') ? data.count : 0;
        })
        .catch(error => {
            console.error('Error updating cart count:', error);
        });
}

function addToCart(button) {
    const form = button.closest('form');
    if (!form) return;
    
    const formData = new FormData(form);
    const productId = formData.get('product_id');
    const quantity = formData.get('quantity') || 1;
    
    // Show loading state
    const originalText = button.innerHTML;
    button.innerHTML = '<span class="loading"></span> Adding...';
    button.disabled = true;
    
    fetch('index.php?page=cart', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data && data.success) {
                showAlert('Product added to cart!', 'success');
                if (typeof updateCartCount === 'function') {
                    updateCartCount();
                }
            } else {
                const message = (data && data.message) ? data.message : 'Error adding product to cart';
                showAlert(message, 'danger');
            }

            button.innerHTML = originalText;
            button.disabled = false;
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error adding product to cart', 'danger');

            button.innerHTML = originalText;
            button.disabled = false;
        });
}

function validateQuantity(input) {
    const min = parseInt(input.getAttribute('min')) || 1;
    const max = parseInt(input.getAttribute('max')) || 99;
    let value = parseInt(input.value);
    
    if (isNaN(value) || value < min) {
        input.value = min;
    } else if (value > max) {
        input.value = max;
    }
}

function showAlert(message, type = 'info') {
    const alertContainer = document.getElementById('alert-container') || createAlertContainer();
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    alertContainer.appendChild(alertDiv);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

function createAlertContainer() {
    const container = document.createElement('div');
    container.id = 'alert-container';
    container.style.position = 'fixed';
    container.style.top = '20px';
    container.style.right = '20px';
    container.style.zIndex = '9999';
    container.style.width = '300px';
    document.body.appendChild(container);
    return container;
}

// Utility functions
function formatCurrency(amount, currency = 'USD') {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency
    }).format(amount);
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Search suggestions (if needed)
function initSearchSuggestions() {
    const searchInput = document.querySelector('input[name="search"]');
    if (!searchInput) return;
    
    const debouncedSearch = debounce(function(query) {
        if (query.length < 2) return;
        
        // Implement search suggestions here
        console.log('Searching for:', query);
    }, 300);
    
    searchInput.addEventListener('input', function(e) {
        debouncedSearch(e.target.value);
    });
}

// Product image zoom (if needed)
function initImageZoom() {
    const productImages = document.querySelectorAll('.product-image');
    productImages.forEach(img => {
        img.addEventListener('click', function() {
            // Implement image zoom modal here
            console.log('Zoom image:', this.src);
        });
    });
}

// Wishlist functionality (if needed)
function toggleWishlist(productId) {
    // Implement wishlist toggle here
    console.log('Toggle wishlist for product:', productId);
}

// Compare products (if needed)
function addToCompare(productId) {
    // Implement product comparison here
    console.log('Add to compare:', productId);
}

// Initialize additional features
document.addEventListener('DOMContentLoaded', function() {
    initSearchSuggestions();
    initImageZoom();
});
