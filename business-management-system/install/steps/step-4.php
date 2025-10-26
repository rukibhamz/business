<?php
// Step 4: Admin Account Setup
$adminData = $_SESSION['admin_data'] ?? [];
?>

<form method="POST" class="install-form">
    <input type="hidden" name="action" value="create_admin">
    
    <div class="form-group">
        <label for="username">Username *</label>
        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($adminData['username'] ?? ''); ?>" required>
        <small>Choose a unique username for your admin account (minimum 4 characters)</small>
    </div>

    <div class="form-group">
        <label for="email">Email Address *</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($adminData['email'] ?? ''); ?>" required>
        <small>Your email address for login and notifications</small>
    </div>

    <div class="form-group">
        <label for="password">Password *</label>
        <input type="password" id="password" name="password" required>
        <small>Choose a strong password (minimum 6 characters)</small>
    </div>

    <div class="form-group">
        <label for="password_confirm">Confirm Password *</label>
        <input type="password" id="password_confirm" name="password_confirm" required>
        <small>Re-enter your password to confirm</small>
    </div>

    <div class="form-group">
        <label for="first_name">First Name *</label>
        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($adminData['first_name'] ?? ''); ?>" required>
        <small>Your first name</small>
    </div>

    <div class="form-group">
        <label for="last_name">Last Name *</label>
        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($adminData['last_name'] ?? ''); ?>" required>
        <small>Your last name</small>
    </div>

    <div class="form-group">
        <label for="phone">Phone Number</label>
        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($adminData['phone'] ?? ''); ?>">
        <small>Your phone number (optional)</small>
    </div>

    <div class="form-actions">
        <a href="?step=3" class="btn btn-secondary">Previous</a>
        <button type="submit" class="btn btn-primary">Create Admin Account</button>
    </div>
</form>

<script>
// Password confirmation validation
document.getElementById('password_confirm').addEventListener('blur', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (password && confirmPassword && password !== confirmPassword) {
        this.style.borderColor = '#e74c3c';
        showFieldError(this, 'Passwords do not match.');
    } else {
        this.style.borderColor = '#e1e5e9';
        hideFieldError(this);
    }
});
</script>