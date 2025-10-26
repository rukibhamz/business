/**
 * Business Management System - Admin Panel JavaScript
 * Phase 1: Core Foundation
 */

// Global variables
let BMS = window.BMS || {};

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeAdmin();
});

/**
 * Initialize admin panel
 */
function initializeAdmin() {
    // Initialize core functionality
    initializeSidebar();
    initializeNotifications();
    initializeUserMenu();
    initializeTooltips();
    initializeAlerts();
    initializeForms();
    
    // Initialize page-specific functionality
    if (typeof initializePage === 'function') {
        initializePage();
    }
    
    console.log('Admin panel initialized');
}

/**
 * Initialize sidebar functionality
 */
function initializeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    const toggleBtn = document.querySelector('.sidebar-toggle');
    const mobileToggleBtn = document.querySelector('.mobile-menu-toggle');
    
    // Desktop sidebar toggle
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            toggleSidebar();
        });
    }
    
    // Mobile sidebar toggle
    if (mobileToggleBtn) {
        mobileToggleBtn.addEventListener('click', function() {
            toggleSidebar();
        });
    }
    
    // Close mobile sidebar when clicking outside
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 768) {
            const isClickInsideSidebar = sidebar && sidebar.contains(event.target);
            const isClickOnToggle = (toggleBtn && toggleBtn.contains(event.target)) || 
                                  (mobileToggleBtn && mobileToggleBtn.contains(event.target));
            
            if (!isClickInsideSidebar && !isClickOnToggle && sidebar && sidebar.classList.contains('mobile-open')) {
                sidebar.classList.remove('mobile-open');
            }
        }
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('mobile-open');
        }
    });
    
    // Restore sidebar state from localStorage
    const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (sidebarCollapsed) {
        sidebar.classList.add('collapsed');
        mainContent.classList.add('sidebar-collapsed');
    }
}

/**
 * Toggle sidebar
 */
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (window.innerWidth <= 768) {
        // Mobile: toggle visibility
        sidebar.classList.toggle('mobile-open');
    } else {
        // Desktop: toggle collapsed state
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('sidebar-collapsed');
        
        // Save state
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    }
}

/**
 * Initialize dropdown menus
 */
function initializeDropdowns() {
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const dropdown = this.nextElementSibling;
            const icon = this.querySelector('.dropdown-icon');
            
            // Close other dropdowns
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                if (menu !== dropdown) {
                    menu.classList.remove('show');
                    const otherIcon = menu.parentElement.querySelector('.dropdown-icon');
                    if (otherIcon) {
                        otherIcon.classList.remove('rotated');
                    }
                }
            });
            
            // Toggle current dropdown
            if (dropdown) {
                dropdown.classList.toggle('show');
                if (icon) {
                    icon.classList.toggle('rotated');
                }
            }
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.nav-dropdown')) {
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
                const icon = menu.parentElement.querySelector('.dropdown-icon');
                if (icon) {
                    icon.classList.remove('rotated');
                }
            });
        }
    });
}

/**
 * Initialize notifications
 */
function initializeNotifications() {
    const notificationBtn = document.querySelector('.notification-btn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    
    if (notificationBtn && notificationDropdown) {
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('show');
        });
        
        // Load notifications
        loadNotifications();
        
        // Auto-refresh notifications
        setInterval(loadNotifications, 30000); // 30 seconds
    }
}

/**
 * Load notifications
 */
function loadNotifications() {
    // This would make an AJAX call to load notifications
    console.log('Loading notifications...');
    
    // For now, we'll just update the notification count
    updateNotificationCount();
}

/**
 * Update notification count
 */
function updateNotificationCount() {
    const badge = document.querySelector('.notification-badge');
    if (badge) {
        // This would get the actual count from the server
        const count = Math.floor(Math.random() * 5); // Mock count
        badge.textContent = count;
        badge.style.display = count > 0 ? 'block' : 'none';
    }
}

/**
 * Mark all notifications as read
 */
function markAllNotificationsRead() {
    // This would make an AJAX call to mark all notifications as read
    console.log('Marking all notifications as read...');
    
    // Update UI
    const notificationItems = document.querySelectorAll('.notification-item');
    notificationItems.forEach(item => {
        item.style.opacity = '0.5';
    });
    
    // Hide notification badge
    const badge = document.querySelector('.notification-badge');
    if (badge) {
        badge.style.display = 'none';
    }
    
    showAlert('All notifications marked as read', 'success');
}

/**
 * Initialize user menu
 */
function initializeUserMenu() {
    const userBtn = document.querySelector('.user-btn');
    const userDropdown = document.getElementById('userDropdown');
    
    if (userBtn && userDropdown) {
        userBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('show');
        });
    }
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
        const userMenu = event.target.closest('.user-menu');
        const notificationMenu = event.target.closest('.notifications');
        
        if (!userMenu && userDropdown) {
            userDropdown.classList.remove('show');
        }
        
        if (!notificationMenu && notificationDropdown) {
            notificationDropdown.classList.remove('show');
        }
    });
}

/**
 * Initialize tooltips
 */
function initializeTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

/**
 * Show tooltip
 */
function showTooltip(event) {
    const element = event.target;
    const text = element.getAttribute('data-tooltip');
    
    if (!text) return;
    
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = text;
    tooltip.id = 'tooltip-' + Date.now();
    
    document.body.appendChild(tooltip);
    
    const rect = element.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
}

/**
 * Hide tooltip
 */
function hideTooltip(event) {
    const tooltips = document.querySelectorAll('.tooltip');
    tooltips.forEach(tooltip => tooltip.remove());
}

/**
 * Initialize alerts
 */
function initializeAlerts() {
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert:not(.alert-dismissible)');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (alert.parentElement) {
                alert.remove();
            }
        }, 5000);
    });
    
    // Handle alert close buttons
    const closeButtons = document.querySelectorAll('.alert-close');
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            this.parentElement.remove();
        });
    });
}

/**
 * Initialize forms
 */
function initializeForms() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // Add loading state to submit button
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="icon-spinner"></i> Processing...';
                
                // Re-enable button after 3 seconds (in case of errors)
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }, 3000);
            }
        });
    });
    
    // Real-time form validation
    const inputs = document.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.addEventListener('blur', validateField);
        input.addEventListener('input', clearFieldError);
    });
}

/**
 * Validate form field
 */
function validateField(event) {
    const field = event.target;
    const value = field.value.trim();
    
    // Clear previous errors
    clearFieldError(event);
    
    // Required field validation
    if (field.hasAttribute('required') && !value) {
        showFieldError(field, 'This field is required');
        return false;
    }
    
    // Email validation
    if (field.type === 'email' && value && !isValidEmail(value)) {
        showFieldError(field, 'Please enter a valid email address');
        return false;
    }
    
    // URL validation
    if (field.type === 'url' && value && !isValidURL(value)) {
        showFieldError(field, 'Please enter a valid URL');
        return false;
    }
    
    // Password validation
    if (field.type === 'password' && value && field.value.length < 8) {
        showFieldError(field, 'Password must be at least 8 characters long');
        return false;
    }
    
    return true;
}

/**
 * Clear field error
 */
function clearFieldError(event) {
    const field = event.target;
    field.classList.remove('error');
    
    const errorElement = field.parentElement.querySelector('.field-error');
    if (errorElement) {
        errorElement.remove();
    }
}

/**
 * Show field error
 */
function showFieldError(field, message) {
    field.classList.add('error');
    
    const errorElement = document.createElement('div');
    errorElement.className = 'field-error';
    errorElement.textContent = message;
    errorElement.style.color = 'var(--danger-color)';
    errorElement.style.fontSize = '0.8rem';
    errorElement.style.marginTop = '5px';
    
    field.parentElement.appendChild(errorElement);
}

/**
 * Show alert message
 */
function showAlert(message, type = 'info', duration = 5000) {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible`;
    alert.innerHTML = `
        <i class="icon-${type === 'error' ? 'warning' : type}"></i>
        <span>${message}</span>
        <button type="button" class="alert-close" onclick="this.parentElement.remove()">
            <i class="icon-close"></i>
        </button>
    `;
    
    // Insert at top of page content
    const pageContent = document.querySelector('.page-content');
    if (pageContent) {
        pageContent.insertBefore(alert, pageContent.firstChild);
    }
    
    // Auto-remove after duration
    if (duration > 0) {
        setTimeout(() => {
            if (alert.parentElement) {
                alert.remove();
            }
        }, duration);
    }
}

/**
 * Show loading spinner
 */
function showLoading(element) {
    if (typeof element === 'string') {
        element = document.querySelector(element);
    }
    
    if (element) {
        element.innerHTML = '<div class="loading-spinner"><i class="icon-spinner"></i> Loading...</div>';
    }
}

/**
 * Hide loading spinner
 */
function hideLoading(element) {
    if (typeof element === 'string') {
        element = document.querySelector(element);
    }
    
    if (element) {
        const spinner = element.querySelector('.loading-spinner');
        if (spinner) {
            spinner.remove();
        }
    }
}

/**
 * Confirm dialog
 */
function confirmDialog(message, callback) {
    if (confirm(message)) {
        if (typeof callback === 'function') {
            callback();
        }
    }
}

/**
 * AJAX helper
 */
function ajaxRequest(url, options = {}) {
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': BMS.csrfToken || ''
        }
    };
    
    const mergedOptions = { ...defaultOptions, ...options };
    
    return fetch(url, mergedOptions)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .catch(error => {
            console.error('AJAX request failed:', error);
            showAlert('Request failed: ' + error.message, 'error');
            throw error;
        });
}

/**
 * Format currency
 */
function formatCurrency(amount, currency = null) {
    currency = currency || BMS.currency || 'USD';
    const symbol = BMS.currencySymbol || '$';
    return symbol + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

/**
 * Format date
 */
function formatDate(date, format = null) {
    format = format || BMS.dateFormat || 'Y-m-d';
    const d = new Date(date);
    
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    
    switch(format) {
        case 'Y-m-d':
            return `${year}-${month}-${day}`;
        case 'd/m/Y':
            return `${day}/${month}/${year}`;
        case 'm/d/Y':
            return `${month}/${day}/${year}`;
        case 'd-m-Y':
            return `${day}-${month}-${year}`;
        case 'Y/m/d':
            return `${year}/${month}/${day}`;
        default:
            return d.toLocaleDateString();
    }
}

/**
 * Format datetime
 */
function formatDateTime(datetime, format = null) {
    format = format || (BMS.dateFormat + ' H:i:s') || 'Y-m-d H:i:s';
    const d = new Date(datetime);
    
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    const seconds = String(d.getSeconds()).padStart(2, '0');
    
    return format
        .replace('Y', year)
        .replace('m', month)
        .replace('d', day)
        .replace('H', hours)
        .replace('i', minutes)
        .replace('s', seconds);
}

/**
 * Get time ago string
 */
function timeAgo(datetime) {
    const now = new Date();
    const past = new Date(datetime);
    const diffInSeconds = Math.floor((now - past) / 1000);
    
    if (diffInSeconds < 60) {
        return 'Just now';
    } else if (diffInSeconds < 3600) {
        const minutes = Math.floor(diffInSeconds / 60);
        return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
    } else if (diffInSeconds < 86400) {
        const hours = Math.floor(diffInSeconds / 3600);
        return `${hours} hour${hours > 1 ? 's' : ''} ago`;
    } else if (diffInSeconds < 2592000) {
        const days = Math.floor(diffInSeconds / 86400);
        return `${days} day${days > 1 ? 's' : ''} ago`;
    } else {
        return formatDate(datetime);
    }
}

/**
 * Utility functions
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function isValidURL(url) {
    try {
        new URL(url);
        return true;
    } catch {
        return false;
    }
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

function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

/**
 * Initialize dropdowns when DOM is loaded
 */
document.addEventListener('DOMContentLoaded', function() {
    initializeDropdowns();
});

// Export functions for global access
window.toggleSidebar = toggleSidebar;
window.toggleNotifications = function() {
    const dropdown = document.getElementById('notificationDropdown');
    if (dropdown) {
        dropdown.classList.toggle('show');
    }
};
window.toggleUserMenu = function() {
    const dropdown = document.getElementById('userDropdown');
    if (dropdown) {
        dropdown.classList.toggle('show');
    }
};
window.markAllNotificationsRead = markAllNotificationsRead;
window.showAlert = showAlert;
window.showLoading = showLoading;
window.hideLoading = hideLoading;
window.confirmDialog = confirmDialog;
window.ajaxRequest = ajaxRequest;
window.formatCurrency = formatCurrency;
window.formatDate = formatDate;
window.formatDateTime = formatDateTime;
window.timeAgo = timeAgo;
