<?php
/**
 * Business Management System - Add User
 * Phase 2: User Management & Settings System
 */

// Define system constant
define('BMS_SYSTEM', true);

// Start session
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';

// Check authentication and permissions
requireLogin();
requirePermission('users.create');

// Get database connection
$conn = getDB();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken($_POST['csrf_token'] ?? '');
    
    $errors = [];
    $success = false;
    
    // Get form data
    $firstName = sanitizeInput($_POST['first_name'] ?? '');
    $lastName = sanitizeInput($_POST['last_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $roleId = (int)($_POST['role_id'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $phone = sanitizeInput($_POST['phone'] ?? '');
    
    // Validation
    if (empty($firstName)) {
        $errors[] = 'First name is required';
    }
    
    if (empty($lastName)) {
        $errors[] = 'Last name is required';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!isValidEmail($email)) {
        $errors[] = 'Invalid email format';
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM " . DB_PREFIX . "users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $errors[] = 'Email address already exists';
        }
    }
    
    if (empty($username)) {
        $errors[] = 'Username is required';
    } elseif (strlen($username) < 4) {
        $errors[] = 'Username must be at least 4 characters';
    } else {
        // Check if username already exists
        $stmt = $conn->prepare("SELECT id FROM " . DB_PREFIX . "users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $errors[] = 'Username already exists';
        }
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }
    
    if ($roleId <= 0) {
        $errors[] = 'Please select a role';
    }
    
    // Check password strength
    $passwordStrength = validatePasswordStrength($password);
    if (!$passwordStrength['is_valid']) {
        $errors[] = 'Password is too weak: ' . implode(', ', $passwordStrength['messages']);
    }
    
    // Handle profile picture upload
    $profilePicture = '';
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = handleProfilePictureUpload($_FILES['profile_picture']);
        if ($uploadResult['success']) {
            $profilePicture = $uploadResult['filename'];
        } else {
            $errors[] = $uploadResult['message'];
        }
    }
    
    // If no errors, create user
    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("
            INSERT INTO " . DB_PREFIX . "users 
            (first_name, last_name, email, username, password, role_id, is_active, phone, profile_picture, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->bind_param('sssssiiss', $firstName, $lastName, $email, $username, $hashedPassword, $roleId, $isActive, $phone, $profilePicture);
        
        if ($stmt->execute()) {
            $userId = $conn->getConnection()->lastInsertId();
            
            // Log activity
            logActivity('users.create', "User created: {$firstName} {$lastName}", [
                'user_id' => $userId,
                'email' => $email,
                'username' => $username,
                'role_id' => $roleId
            ]);
            
            $success = true;
            $_SESSION['success'] = 'User created successfully';
            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Failed to create user. Please try again.';
        }
    }
}

/**
 * Validate password strength
 */
function validatePasswordStrength($password) {
    $messages = [];
    
    if (strlen($password) < 8) {
        $messages[] = 'At least 8 characters';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $messages[] = 'At least one uppercase letter';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $messages[] = 'At least one lowercase letter';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $messages[] = 'At least one number';
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $messages[] = 'At least one special character';
    }
    
    return [
        'is_valid' => empty($messages),
        'messages' => $messages
    ];
}

/**
 * Handle profile picture upload
 */
function handleProfilePictureUpload($file) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 2 * 1024 * 1024; // 2MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File size too large. Maximum 2MB allowed.'];
    }
    
    $uploadDir = '../../uploads/profiles/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename];
    } else {
        return ['success' => false, 'message' => 'Failed to upload file.'];
    }
}

// Get roles for dropdown
$rolesQuery = "SELECT id, name FROM " . DB_PREFIX . "roles ORDER BY name";
$roles = $conn->query($rolesQuery)->fetch_all(MYSQLI_ASSOC);

// Include header
include '../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Add New User</h1>
        <p>Create a new user account</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Users
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <h4>Please correct the following errors:</h4>
    <ul>
        <?php foreach ($errors as $error): ?>
        <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>User Information</h3>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" class="form-horizontal">
            <?php csrfField(); ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name" class="required">First Name</label>
                    <input type="text" id="first_name" name="first_name" 
                           value="<?php echo htmlspecialchars($firstName ?? ''); ?>" 
                           required class="form-control">
                </div>
                <div class="form-group">
                    <label for="last_name" class="required">Last Name</label>
                    <input type="text" id="last_name" name="last_name" 
                           value="<?php echo htmlspecialchars($lastName ?? ''); ?>" 
                           required class="form-control">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="email" class="required">Email Address</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($email ?? ''); ?>" 
                           required class="form-control">
                </div>
                <div class="form-group">
                    <label for="username" class="required">Username</label>
                    <input type="text" id="username" name="username" 
                           value="<?php echo htmlspecialchars($username ?? ''); ?>" 
                           required class="form-control" minlength="4">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password" class="required">Password</label>
                    <input type="password" id="password" name="password" 
                           required class="form-control" minlength="8">
                    <div class="password-strength" id="password-strength"></div>
                </div>
                <div class="form-group">
                    <label for="confirm_password" class="required">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" 
                           required class="form-control" minlength="8">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="role_id" class="required">Role</label>
                    <select id="role_id" name="role_id" required class="form-control">
                        <option value="">Select a role</option>
                        <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['id']; ?>" 
                                <?php echo ($roleId ?? 0) == $role['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($role['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" 
                           value="<?php echo htmlspecialchars($phone ?? ''); ?>" 
                           class="form-control">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="profile_picture">Profile Picture</label>
                    <input type="file" id="profile_picture" name="profile_picture" 
                           accept="image/jpeg,image/png,image/gif" class="form-control">
                    <small class="form-text">JPG, PNG, or GIF. Maximum 2MB.</small>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <div class="form-check">
                        <input type="checkbox" id="is_active" name="is_active" 
                               <?php echo ($isActive ?? 1) ? 'checked' : ''; ?> class="form-check-input">
                        <label for="is_active" class="form-check-label">Active</label>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="icon-plus"></i> Create User
                </button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const strengthDiv = document.getElementById('password-strength');
    
    if (password.length === 0) {
        strengthDiv.innerHTML = '';
        return;
    }
    
    const checks = {
        length: password.length >= 8,
        uppercase: /[A-Z]/.test(password),
        lowercase: /[a-z]/.test(password),
        number: /[0-9]/.test(password),
        special: /[^A-Za-z0-9]/.test(password)
    };
    
    const passedChecks = Object.values(checks).filter(Boolean).length;
    const strength = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'][passedChecks - 1] || 'Very Weak';
    const color = ['#dc3545', '#fd7e14', '#ffc107', '#20c997', '#198754'][passedChecks - 1] || '#dc3545';
    
    strengthDiv.innerHTML = `
        <div class="strength-bar">
            <div class="strength-fill" style="width: ${(passedChecks / 5) * 100}%; background-color: ${color};"></div>
        </div>
        <small style="color: ${color};">${strength}</small>
    `;
});

document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (confirmPassword && password !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<style>
.password-strength {
    margin-top: 5px;
}

.strength-bar {
    width: 100%;
    height: 4px;
    background-color: #e9ecef;
    border-radius: 2px;
    overflow: hidden;
}

.strength-fill {
    height: 100%;
    transition: width 0.3s ease;
}

.form-check {
    margin-top: 8px;
}

.form-check-input {
    margin-right: 8px;
}
</style>

<?php include '../includes/footer.php'; ?>
