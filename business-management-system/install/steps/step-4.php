<?php
/**
 * Step 4: Admin Account Setup
 */

// Get admin data from session if available
$adminData = $_SESSION['admin_data'] ?? [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'username' => '',
    'password' => '',
    'confirm_password' => ''
];

// Pre-fill email from site config if available
if (empty($adminData['email']) && isset($_SESSION['site_config']['admin_email'])) {
    $adminData['email'] = $_SESSION['site_config']['admin_email'];
}
?>

<div class="admin-account">
    <div class="config-header">
        <h3><i class="icon-user"></i> Administrator Account Setup</h3>
        <p>Create your administrator account. This account will have full access to the system.</p>
    </div>

    <form id="admin-account-form" method="POST" action="">
        <input type="hidden" name="action" value="create_admin">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <div class="form-section">
            <h4><i class="icon-user"></i> Personal Information</h4>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name *</label>
                    <input type="text" 
                           id="first_name" 
                           name="first_name" 
                           value="<?php echo htmlspecialchars($adminData['first_name']); ?>" 
                           placeholder="John" 
                           required>
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name *</label>
                    <input type="text" 
                           id="last_name" 
                           name="last_name" 
                           value="<?php echo htmlspecialchars($adminData['last_name']); ?>" 
                           placeholder="Doe" 
                           required>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       value="<?php echo htmlspecialchars($adminData['email']); ?>" 
                       placeholder="admin@yourcompany.com" 
                       required>
                <small>This will be used for system notifications and password recovery</small>
            </div>
        </div>

        <div class="form-section">
            <h4><i class="icon-lock"></i> Login Credentials</h4>
            
            <div class="form-group">
                <label for="username">Username *</label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       value="<?php echo htmlspecialchars($adminData['username']); ?>" 
                       placeholder="admin" 
                       minlength="4" 
                       required>
                <small>Minimum 4 characters, letters and numbers only</small>
            </div>

            <div class="form-group">
                <label for="password">Password *</label>
                <div class="password-input">
                    <input type="password" 
                           id="password" 
                           name="password" 
                           placeholder="Enter password" 
                           minlength="8" 
                           required>
                    <button type="button" class="toggle-password" onclick="togglePassword('password')">
                        <i class="icon-eye"></i>
                    </button>
                </div>
                <div id="password-strength" class="password-strength">
                    <div class="strength-bar">
                        <div class="strength-fill" id="strength-fill"></div>
                    </div>
                    <div class="strength-text" id="strength-text">Enter a password</div>
                </div>
                <small>Minimum 8 characters with uppercase, lowercase, number, and special character</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password *</label>
                <div class="password-input">
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password" 
                           placeholder="Confirm password" 
                           required>
                    <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                        <i class="icon-eye"></i>
                    </button>
                </div>
                <div id="password-match" class="password-match"></div>
            </div>
        </div>

        <div class="form-section">
            <h4><i class="icon-shield"></i> Security Information</h4>
            <div class="security-info">
                <div class="security-item">
                    <i class="icon-check"></i>
                    <span>Your password will be encrypted using PHP's password_hash() function</span>
                </div>
                <div class="security-item">
                    <i class="icon-check"></i>
                    <span>All login attempts will be logged for security monitoring</span>
                </div>
                <div class="security-item">
                    <i class="icon-check"></i>
                    <span>Session security is implemented with CSRF protection</span>
                </div>
                <div class="security-item">
                    <i class="icon-check"></i>
                    <span>Failed login attempts are limited to prevent brute force attacks</span>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a href="?step=3" class="btn btn-secondary">
                <i class="icon-arrow-left"></i> Back to Site Configuration
            </a>
            
            <button type="submit" class="btn btn-primary" id="create-admin-btn">
                <i class="icon-user-plus"></i> Create Admin Account
            </button>
        </div>
    </form>
</div>

<div class="account-help">
    <h4>Account Setup Help</h4>
    <div class="help-content">
        <div class="help-item">
            <strong>Username Requirements:</strong>
            <ul>
                <li>Minimum 4 characters</li>
                <li>Letters and numbers only</li>
                <li>Must be unique (no other user can have the same username)</li>
                <li>Case sensitive</li>
            </ul>
        </div>
        <div class="help-item">
            <strong>Password Requirements:</strong>
            <ul>
                <li>Minimum 8 characters</li>
                <li>At least one uppercase letter (A-Z)</li>
                <li>At least one lowercase letter (a-z)</li>
                <li>At least one number (0-9)</li>
                <li>At least one special character (!@#$%^&*)</li>
            </ul>
        </div>
        <div class="help-item">
            <strong>Security Features:</strong>
            <ul>
                <li>Passwords are hashed using PHP's secure password_hash() function</li>
                <li>Login attempts are logged and monitored</li>
                <li>Session management with automatic timeout</li>
                <li>CSRF protection on all forms</li>
                <li>Rate limiting on login attempts</li>
            </ul>
        </div>
        <div class="help-item">
            <strong>Account Management:</strong>
            <ul>
                <li>You can change your password after installation</li>
                <li>Profile information can be updated from the admin panel</li>
                <li>Additional users can be created after installation</li>
                <li>Role-based permissions are available</li>
            </ul>
        </div>
    </div>
</div>

<script>
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

// Password strength checker
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const strengthFill = document.getElementById('strength-fill');
    const strengthText = document.getElementById('strength-text');
    
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
});

// Password confirmation checker
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    const matchDiv = document.getElementById('password-match');
    
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
});

// Form validation
document.getElementById('admin-account-form').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const username = document.getElementById('username').value;
    
    let isValid = true;
    let errors = [];
    
    // Check username
    if (username.length < 4) {
        errors.push('Username must be at least 4 characters');
        isValid = false;
    }
    
    if (!/^[a-zA-Z0-9]+$/.test(username)) {
        errors.push('Username can only contain letters and numbers');
        isValid = false;
    }
    
    // Check password strength
    const strength = document.getElementById('strength-text').textContent;
    if (strength.includes('Weak') || strength.includes('Very Weak')) {
        errors.push('Password is too weak');
        isValid = false;
    }
    
    // Check password match
    if (password !== confirmPassword) {
        errors.push('Passwords do not match');
        isValid = false;
    }
    
    if (!isValid) {
        e.preventDefault();
        alert('Please fix the following errors:\n\n' + errors.join('\n'));
    }
});
</script>
