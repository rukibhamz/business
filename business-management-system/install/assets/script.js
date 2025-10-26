/**
 * Business Management System - Installation Wizard JavaScript
 * Minimal functionality for form handling
 */

document.addEventListener('DOMContentLoaded', function() {
    // Auto-focus first input in forms
    const firstInput = document.querySelector('.install-form input[type="text"], .install-form input[type="email"], .install-form input[type="password"]');
    if (firstInput) {
        firstInput.focus();
    }
    
    // Form validation
    const forms = document.querySelectorAll('.install-form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('input[required], select[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = '#e74c3c';
                } else {
                    field.style.borderColor = '#e1e5e9';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    });
    
    // Real-time validation for email fields
    const emailFields = document.querySelectorAll('input[type="email"]');
    emailFields.forEach(field => {
        field.addEventListener('blur', function() {
            const email = this.value.trim();
            if (email && !isValidEmail(email)) {
                this.style.borderColor = '#e74c3c';
                showFieldError(this, 'Please enter a valid email address.');
            } else {
                this.style.borderColor = '#e1e5e9';
                hideFieldError(this);
            }
        });
    });
    
    // Real-time validation for password fields
    const passwordFields = document.querySelectorAll('input[type="password"]');
    passwordFields.forEach(field => {
        field.addEventListener('blur', function() {
            const password = this.value;
            if (password && password.length < 6) {
                this.style.borderColor = '#e74c3c';
                showFieldError(this, 'Password must be at least 6 characters long.');
            } else {
                this.style.borderColor = '#e1e5e9';
                hideFieldError(this);
            }
        });
    });
    
    // Auto-generate site URL from company name
    const companyNameField = document.querySelector('input[name="company_name"]');
    const siteUrlField = document.querySelector('input[name="site_url"]');
    
    if (companyNameField && siteUrlField) {
        companyNameField.addEventListener('input', function() {
            if (!siteUrlField.value) {
                const url = this.value.toLowerCase()
                    .replace(/[^a-z0-9\s]/g, '')
                    .replace(/\s+/g, '-')
                    .substring(0, 50);
                siteUrlField.value = 'http://' + url + '.local';
            }
        });
    }
});

/**
 * Validate email format
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Show field error message
 */
function showFieldError(field, message) {
    hideFieldError(field);
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.style.color = '#e74c3c';
    errorDiv.style.fontSize = '0.85rem';
    errorDiv.style.marginTop = '5px';
    errorDiv.textContent = message;
    
    field.parentNode.appendChild(errorDiv);
}

/**
 * Hide field error message
 */
function hideFieldError(field) {
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
}

/**
 * Show loading state for buttons
 */
function showButtonLoading(button) {
    button.disabled = true;
    button.dataset.originalText = button.textContent;
    button.textContent = 'Processing...';
}

/**
 * Hide loading state for buttons
 */
function hideButtonLoading(button) {
    button.disabled = false;
    if (button.dataset.originalText) {
        button.textContent = button.dataset.originalText;
        delete button.dataset.originalText;
    }
}