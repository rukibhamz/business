/**
 * Business Management System - Installation Wizard JavaScript
 * Phase 1: Core Foundation
 */

// Global variables
let currentStep = 1;
let totalSteps = 5;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeInstallation();
    setupEventListeners();
    setupFormValidation();
});

/**
 * Initialize installation wizard
 */
function initializeInstallation() {
    // Get current step from URL
    const urlParams = new URLSearchParams(window.location.search);
    currentStep = parseInt(urlParams.get('step')) || 1;
    
    // Update progress bar
    updateProgressBar();
    
    // Initialize step-specific functionality
    initializeStep(currentStep);
}

/**
 * Setup event listeners
 */
function setupEventListeners() {
    // Form submissions
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', handleFormSubmit);
    });
    
    // Button clicks
    const buttons = document.querySelectorAll('button[type="button"]');
    buttons.forEach(button => {
        button.addEventListener('click', handleButtonClick);
    });
    
    // Input changes
    const inputs = document.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.addEventListener('input', handleInputChange);
        input.addEventListener('change', handleInputChange);
    });
    
    // Password strength checker
    const passwordInput = document.getElementById('password');
    if (passwordInput) {
        passwordInput.addEventListener('input', checkPasswordStrength);
    }
    
    // Password confirmation checker
    const confirmPasswordInput = document.getElementById('confirm_password');
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);
    }
    
    // Username validation
    const usernameInput = document.getElementById('username');
    if (usernameInput) {
        usernameInput.addEventListener('input', validateUsername);
    }
    
    // Email validation
    const emailInputs = document.querySelectorAll('input[type="email"]');
    emailInputs.forEach(input => {
        input.addEventListener('blur', validateEmail);
    });
}

/**
 * Setup form validation
 */
function setupFormValidation() {
    // Real-time validation for required fields
    const requiredFields = document.querySelectorAll('input[required], select[required], textarea[required]');
    requiredFields.forEach(field => {
        field.addEventListener('blur', validateRequiredField);
    });
    
    // URL validation
    const urlInput = document.getElementById('site_url');
    if (urlInput) {
        urlInput.addEventListener('blur', validateURL);
    }
}

/**
 * Initialize step-specific functionality
 */
function initializeStep(step) {
    switch(step) {
        case 1:
            initializeRequirementsStep();
            break;
        case 2:
            initializeDatabaseStep();
            break;
        case 3:
            initializeSiteConfigStep();
            break;
        case 4:
            initializeAdminAccountStep();
            break;
        case 5:
            initializeCompleteStep();
            break;
    }
}

/**
 * Initialize requirements step
 */
function initializeRequirementsStep() {
    // Add refresh functionality
    const refreshBtn = document.querySelector('.btn-secondary');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            location.reload();
        });
    }
}

/**
 * Initialize database step
 */
function initializeDatabaseStep() {
    // Test connection button
    const testBtn = document.querySelector('button[onclick="testConnection()"]');
    if (testBtn) {
        testBtn.addEventListener('click', testDatabaseConnection);
    }
    
    // Form validation
    const form = document.getElementById('database-form');
    if (form) {
        form.addEventListener('submit', validateDatabaseForm);
    }
}

/**
 * Initialize site configuration step
 */
function initializeSiteConfigStep() {
    // Update preview as user types
    const inputs = ['company_name', 'site_url', 'admin_email', 'currency', 'timezone', 'date_format'];
    inputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('input', updatePreview);
        }
    });
}

/**
 * Initialize admin account step
 */
function initializeAdminAccountStep() {
    // Password strength checker
    const passwordInput = document.getElementById('password');
    if (passwordInput) {
        passwordInput.addEventListener('input', checkPasswordStrength);
    }
    
    // Password confirmation
    const confirmPasswordInput = document.getElementById('confirm_password');
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);
    }
}

/**
 * Initialize complete step
 */
function initializeCompleteStep() {
    // Auto-submit after countdown
    let countdown = 5;
    const countdownElement = document.getElementById('countdown');
    const form = document.querySelector('form[method="POST"]');
    
    if (countdownElement && form) {
        const countdownInterval = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(countdownInterval);
                form.submit();
            }
        }, 1000);
        
        // Clear countdown if user interacts
        document.addEventListener('click', () => {
            clearInterval(countdownInterval);
            if (countdownElement.parentElement) {
                countdownElement.parentElement.style.display = 'none';
            }
        });
    }
}

/**
 * Update progress bar
 */
function updateProgressBar() {
    const progressFill = document.querySelector('.progress-fill');
    if (progressFill) {
        const percentage = (currentStep / totalSteps) * 100;
        progressFill.style.width = percentage + '%';
    }
}

/**
 * Handle form submission
 */
function handleFormSubmit(event) {
    const form = event.target;
    const action = form.querySelector('input[name="action"]').value;
    
    // Validate form before submission
    if (!validateForm(form)) {
        event.preventDefault();
        return false;
    }
    
    // Show loading state
    showLoadingState(form);
}

/**
 * Handle button clicks
 */
function handleButtonClick(event) {
    const button = event.target;
    const action = button.getAttribute('onclick');
    
    if (action) {
        // Execute onclick function
        eval(action);
    }
}

/**
 * Handle input changes
 */
function handleInputChange(event) {
    const input = event.target;
    
    // Remove error state
    input.classList.remove('error');
    
    // Validate specific input types
    if (input.type === 'email') {
        validateEmail(event);
    } else if (input.type === 'url') {
        validateURL(event);
    } else if (input.hasAttribute('required')) {
        validateRequiredField(event);
    }
}

/**
 * Validate form
 */
function validateForm(form) {
    let isValid = true;
    const requiredFields = form.querySelectorAll('input[required], select[required], textarea[required]');
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
        } else {
            field.classList.remove('error');
        }
    });
    
    // Step-specific validation
    switch(currentStep) {
        case 2:
            isValid = validateDatabaseForm(form) && isValid;
            break;
        case 3:
            isValid = validateSiteConfigForm(form) && isValid;
            break;
        case 4:
            isValid = validateAdminAccountForm(form) && isValid;
            break;
    }
    
    if (!isValid) {
        showMessage('Please fix the errors before continuing.', 'error');
    }
    
    return isValid;
}

/**
 * Validate database form
 */
function validateDatabaseForm(form) {
    let isValid = true;
    const host = form.querySelector('input[name="db_host"]').value.trim();
    const port = form.querySelector('input[name="db_port"]').value.trim();
    const username = form.querySelector('input[name="db_username"]').value.trim();
    const password = form.querySelector('input[name="db_password"]').value;
    const database = form.querySelector('input[name="db_name"]').value.trim();
    
    if (!host || !port || !username || !database) {
        isValid = false;
    }
    
    if (port && (isNaN(port) || port < 1 || port > 65535)) {
        form.querySelector('input[name="db_port"]').classList.add('error');
        isValid = false;
    }
    
    return isValid;
}

/**
 * Validate site configuration form
 */
function validateSiteConfigForm(form) {
    let isValid = true;
    const companyName = form.querySelector('input[name="company_name"]').value.trim();
    const siteUrl = form.querySelector('input[name="site_url"]').value.trim();
    const adminEmail = form.querySelector('input[name="admin_email"]').value.trim();
    
    if (!companyName || !siteUrl || !adminEmail) {
        isValid = false;
    }
    
    if (siteUrl && !isValidURL(siteUrl)) {
        form.querySelector('input[name="site_url"]').classList.add('error');
        isValid = false;
    }
    
    if (adminEmail && !isValidEmail(adminEmail)) {
        form.querySelector('input[name="admin_email"]').classList.add('error');
        isValid = false;
    }
    
    return isValid;
}

/**
 * Validate admin account form
 */
function validateAdminAccountForm(form) {
    let isValid = true;
    const firstName = form.querySelector('input[name="first_name"]').value.trim();
    const lastName = form.querySelector('input[name="last_name"]').value.trim();
    const email = form.querySelector('input[name="email"]').value.trim();
    const username = form.querySelector('input[name="username"]').value.trim();
    const password = form.querySelector('input[name="password"]').value;
    const confirmPassword = form.querySelector('input[name="confirm_password"]').value;
    
    if (!firstName || !lastName || !email || !username || !password || !confirmPassword) {
        isValid = false;
    }
    
    if (username && username.length < 4) {
        form.querySelector('input[name="username"]').classList.add('error');
        isValid = false;
    }
    
    if (password && password.length < 8) {
        form.querySelector('input[name="password"]').classList.add('error');
        isValid = false;
    }
    
    if (password !== confirmPassword) {
        form.querySelector('input[name="confirm_password"]').classList.add('error');
        isValid = false;
    }
    
    return isValid;
}

/**
 * Test database connection
 */
function testDatabaseConnection() {
    const form = document.getElementById('database-form');
    const formData = new FormData(form);
    const resultDiv = document.getElementById('connection-result');
    const installBtn = document.getElementById('install-db-btn');
    
    // Show loading state
    resultDiv.innerHTML = '<div class="loading"><i class="icon-spinner"></i> Testing connection...</div>';
    resultDiv.style.display = 'block';
    installBtn.disabled = true;
    
    // Make AJAX request
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        if (data.includes('Database connection successful')) {
            resultDiv.innerHTML = '<div class="success-message"><i class="icon-check"></i> Database connection successful! You can now install the database.</div>';
            installBtn.disabled = false;
        } else {
            resultDiv.innerHTML = '<div class="error-message"><i class="icon-warning"></i> Database connection failed. Please check your credentials.</div>';
            installBtn.disabled = true;
        }
    })
    .catch(error => {
        resultDiv.innerHTML = '<div class="error-message"><i class="icon-warning"></i> Connection test failed: ' + error.message + '</div>';
        installBtn.disabled = true;
    });
}

/**
 * Check password strength
 */
function checkPasswordStrength(event) {
    const password = event.target.value;
    const strengthFill = document.getElementById('strength-fill');
    const strengthText = document.getElementById('strength-text');
    
    if (!strengthFill || !strengthText) return;
    
    let strength = 0;
    let messages = [];
    
    if (password.length >= 8) strength++;
    else messages.push('8+ characters');
    
    if (/[a-z]/.test(password)) strength++;
    else messages.push('lowercase letter');
    
    if (/[A-Z]/.test(password)) strength++;
    else messages.push('uppercase letter');
    
    if (/[0-9]/.test(password)) strength++;
    else messages.push('number');
    
    if (/[^a-zA-Z0-9]/.test(password)) strength++;
    else messages.push('special character');
    
    const levels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
    const colors = ['#ff4444', '#ff8800', '#ffbb00', '#88cc00', '#44aa44', '#00aa44'];
    
    strengthFill.style.width = (strength / 5) * 100 + '%';
    strengthFill.style.backgroundColor = colors[strength];
    strengthText.textContent = levels[strength];
    strengthText.className = 'strength-text ' + (strength >= 3 ? 'strong' : 'weak');
    
    if (messages.length > 0) {
        strengthText.textContent += ' (needs: ' + messages.join(', ') + ')';
    }
}

/**
 * Check password match
 */
function checkPasswordMatch(event) {
    const password = document.getElementById('password').value;
    const confirmPassword = event.target.value;
    const matchDiv = document.getElementById('password-match');
    
    if (!matchDiv) return;
    
    if (confirmPassword.length === 0) {
        matchDiv.innerHTML = '';
        return;
    }
    
    if (password === confirmPassword) {
        matchDiv.innerHTML = '<i class="icon-check"></i> Passwords match';
        matchDiv.className = 'password-match success';
    } else {
        matchDiv.innerHTML = '<i class="icon-warning"></i> Passwords do not match';
        matchDiv.className = 'password-match error';
    }
}

/**
 * Validate username
 */
function validateUsername(event) {
    const username = event.target.value;
    const input = event.target;
    
    if (username.length > 0 && username.length < 4) {
        input.classList.add('error');
        showFieldError(input, 'Username must be at least 4 characters');
    } else if (username.length > 0 && !/^[a-zA-Z0-9]+$/.test(username)) {
        input.classList.add('error');
        showFieldError(input, 'Username can only contain letters and numbers');
    } else {
        input.classList.remove('error');
        clearFieldError(input);
    }
}

/**
 * Validate email
 */
function validateEmail(event) {
    const email = event.target.value;
    const input = event.target;
    
    if (email.length > 0 && !isValidEmail(email)) {
        input.classList.add('error');
        showFieldError(input, 'Please enter a valid email address');
    } else {
        input.classList.remove('error');
        clearFieldError(input);
    }
}

/**
 * Validate URL
 */
function validateURL(event) {
    const url = event.target.value;
    const input = event.target;
    
    if (url.length > 0 && !isValidURL(url)) {
        input.classList.add('error');
        showFieldError(input, 'Please enter a valid URL');
    } else {
        input.classList.remove('error');
        clearFieldError(input);
    }
}

/**
 * Validate required field
 */
function validateRequiredField(event) {
    const field = event.target;
    const value = field.value.trim();
    
    if (field.hasAttribute('required') && !value) {
        field.classList.add('error');
        showFieldError(field, 'This field is required');
    } else {
        field.classList.remove('error');
        clearFieldError(field);
    }
}

/**
 * Update preview
 */
function updatePreview(event) {
    const input = event.target;
    const previewId = 'preview-' + input.id.replace('_', '-');
    const preview = document.getElementById(previewId);
    
    if (preview) {
        preview.textContent = input.value || 'Not set';
    }
}

/**
 * Show loading state
 */
function showLoadingState(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="icon-spinner"></i> Processing...';
    }
}

/**
 * Show message
 */
function showMessage(message, type = 'info') {
    // Remove existing messages
    const existingMessages = document.querySelectorAll('.message');
    existingMessages.forEach(msg => msg.remove());
    
    // Create new message
    const messageDiv = document.createElement('div');
    messageDiv.className = `message message-${type}`;
    messageDiv.innerHTML = `<i class="icon-${type === 'error' ? 'warning' : type}"></i> ${message}`;
    
    // Insert message
    const content = document.querySelector('.install-content');
    if (content) {
        content.insertBefore(messageDiv, content.firstChild);
    }
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        if (messageDiv.parentNode) {
            messageDiv.remove();
        }
    }, 5000);
}

/**
 * Show field error
 */
function showFieldError(field, message) {
    clearFieldError(field);
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    errorDiv.style.color = '#dc3545';
    errorDiv.style.fontSize = '0.85rem';
    errorDiv.style.marginTop = '5px';
    
    field.parentNode.appendChild(errorDiv);
}

/**
 * Clear field error
 */
function clearFieldError(field) {
    const errorDiv = field.parentNode.querySelector('.field-error');
    if (errorDiv) {
        errorDiv.remove();
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

/**
 * Toggle password visibility
 */
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const button = field.nextElementSibling;
    const icon = button.querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.className = 'icon-eye-off';
    } else {
        field.type = 'password';
        icon.className = 'icon-eye';
    }
}

/**
 * Copy to clipboard
 */
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showMessage('Copied to clipboard!', 'success');
        });
    } else {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showMessage('Copied to clipboard!', 'success');
    }
}

/**
 * Format currency
 */
function formatCurrency(amount, currency = 'USD') {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency
    }).format(amount);
}

/**
 * Format date
 */
function formatDate(date, format = 'Y-m-d') {
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
 * Debounce function
 */
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

/**
 * Throttle function
 */
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

// Export functions for global access
window.togglePassword = togglePassword;
window.testConnection = testDatabaseConnection;
window.copyToClipboard = copyToClipboard;
