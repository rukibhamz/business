<?php
/**
 * Business Management System - Edit User
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
requirePermission('users.edit');

// Get database connection
$conn = getDB();

// Get user ID
$userId = (int)($_GET['id'] ?? 0);

if ($userId <= 0) {
    $_SESSION['error'] = 'Invalid user ID';
    header('Location: index.php');
    exit;
}

// Get user data
$stmt = $conn->prepare("
    SELECT u.*, r.name as role_name 
    FROM " . DB_PREFIX . "users u 
    LEFT JOIN " . DB_PREFIX . "roles r ON u.role_id = r.id 
    WHERE u.id = ?
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    $_SESSION['error'] = 'User not found';
    header('Location: index.php');
    exit;
}

// Check if user is trying to edit their own profile
$isOwnProfile = $userId == $_SESSION['user_id'];

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
        // Check if email already exists (excluding current user)
        $stmt = $conn->prepare("SELECT id FROM " . DB_PREFIX . "users WHERE email = ? AND id != ?");
        $stmt->bind_param('si', $email, $userId);
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
        // Check if username already exists (excluding current user)
        $stmt = $conn->prepare("SELECT id FROM " . DB_PREFIX . "users WHERE username = ? AND id != ?");
        $stmt->bind_param('si', $username, $userId);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $errors[] = 'Username already exists';
        }
    }
    
    // Password validation (only if provided)
    if (!empty($password)) {
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        } elseif ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match';
        }
        
        // Check password strength
        $passwordStrength = validatePasswordStrength($password);
        if (!$passwordStrength['is_valid']) {
            $errors[] = 'Password is too weak: ' . implode(', ', $passwordStrength['messages']);
        }
    }
    
    if ($roleId <= 0) {
        $errors[] = 'Please select a role';
    }
    
    // Security checks
    if ($isOwnProfile) {
        // Users cannot change their own role
        if ($roleId != $user['role_id']) {
            $errors[] = 'You cannot change your own role';
        }
        // Users cannot deactivate themselves
        if (!$isActive) {
            $errors[] = 'You cannot deactivate your own account';
        }
    }
    
    // Prevent demoting Super Admin
    if ($user['role_id'] == 1 && $roleId != 1 && $_SESSION['role_id'] != 1) {
        $errors[] = 'Cannot modify Super Admin role';
    }
    
    // Handle profile picture upload
    $profilePicture = $user['profile_picture'];
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = handleProfilePictureUpload($_FILES['profile_picture']);
        if ($uploadResult['success']) {
            // Delete old profile picture
            if (!empty($user['profile_picture'])) {
                $oldFile = '../../uploads/profiles/' . $user['profile_picture'];
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }
            $profilePicture = $uploadResult['filename'];
        } else {
            $errors[] = $uploadResult['message'];
        }
    }
    
    // If no errors, update user
    if (empty($errors)) {
        $updateFields = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'username' => $username,
            'role_id' => $roleId,
            'is_active' => $isActive,
            'phone' => $phone,
            'profile_picture' => $profilePicture,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Add password if provided
        if (!empty($password)) {
            $updateFields['password'] = password_hash($password, PASSWORD_DEFAULT);
        }
        
        $setClause = implode(' = ?, ', array_keys($updateFields)) . ' = ?';
        $values = array_values($updateFields);
        $values[] = $userId;
        
        $stmt = $conn->prepare("UPDATE " . DB_PREFIX . "users SET {$setClause} WHERE id = ?");
        $stmt->bind_param(str_repeat('s', count($updateFields)) . 'i', ...$values);
        
        if ($stmt->execute()) {
            // Log activity
            logActivity('users.edit', "User updated: {$firstName} {$lastName}", [
                'user_id' => $userId,
                'email' => $email,
                'username' => $username,
                'role_id' => $roleId
            ]);
            
            $success = true;
            $_SESSION['success'] = 'User updated successfully';
            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Failed to update user. Please try again.';
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
        <h1><?php echo $isOwnProfile ? 'Edit My Profile' : 'Edit User'; ?></h1>
        <p><?php echo $isOwnProfile ? 'Update your profile information' : 'Modify user account details'; ?></p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Users
        </a>
        <?php if (!$isOwnProfile): ?>
        <a href="view.php?id=<?php echo $userId; ?>" class="btn btn-info">
            <i class="icon-eye"></i> View User
        </a>
        <?php endif; ?>
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
                           value="<?php echo htmlspecialchars($firstName ?? $user['first_name']); ?>" 
                           required class="form-control">
                </div>
                <div class="form-group">
                    <label for="last_name" class="required">Last Name</label>
                    <input type="text" id="last_name" name="last_name" 
                           value="<?php echo htmlspecialchars($lastName ?? $user['last_name']); ?>" 
                           required class="form-control">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="email" class="required">Email Address</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($email ?? $user['email']); ?>" 
                           required class="form-control">
                </div>
                <div class="form-group">
                    <label for="username" class="required">Username</label>
                    <input type="text" id="username" name="username" 
                           value="<?php echo htmlspecialchars($username ?? $user['username']); ?>" 
                           required class="form-control" minlength="4">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" 
                           class="form-control" minlength="8">
                    <small class="form-text">Leave blank to keep current password</small>
                    <div class="password-strength" id="password-strength"></div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" 
                           class="form-control" minlength="8">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="role_id" class="required">Role</label>
                    <select id="role_id" name="role_id" required class="form-control" 
                            <?php echo $isOwnProfile ? 'disabled' : ''; ?>>
                        <option value="">Select a role</option>
                        <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['id']; ?>" 
                                <?php echo ($roleId ?? $user['role_id']) == $role['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($role['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($isOwnProfile): ?>
                    <input type="hidden" name="role_id" value="<?php echo $user['role_id']; ?>">
                    <small class="form-text">You cannot change your own role</small>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" 
                           value="<?php echo htmlspecialchars($phone ?? $user['phone']); ?>" 
                           class="form-control">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="profile_picture">Profile Picture</label>
                    <?php if (!empty($user['profile_picture'])): ?>
                    <div class="current-picture">
                        <img src="../../uploads/profiles/<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                             alt="Current Profile Picture" class="profile-preview">
                        <small class="form-text">Current profile picture</small>
                    </div>
                    <?php endif; ?>
                    <input type="file" id="profile_picture" name="profile_picture" 
                           accept="image/jpeg,image/png,image/gif" class="form-control">
                    <small class="form-text">JPG, PNG, or GIF. Maximum 2MB.</small>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <div class="form-check">
                        <input type="checkbox" id="is_active" name="is_active" 
                               <?php echo ($isActive ?? $user['is_active']) ? 'checked' : ''; ?> 
                               class="form-check-input" <?php echo $isOwnProfile ? 'disabled' : ''; ?>>
                        <label for="is_active" class="form-check-label">Active</label>
                        <?php if ($isOwnProfile): ?>
                        <input type="hidden" name="is_active" value="1">
                        <small class="form-text">You cannot deactivate your own account</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Account Information (Read-only) -->
            <div class="form-row">
                <div class="form-group">
                    <label>Created At</label>
                    <input type="text" value="<?php echo date('M d, Y H:i', strtotime($user['created_at'])); ?>" 
                           readonly class="form-control">
                </div>
                <div class="form-group">
                    <label>Last Login</label>
                    <input type="text" value="<?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?>" 
                           readonly class="form-control">
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="icon-save"></i> Update User
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

.current-picture {
    margin-bottom: 10px;
}

.profile-preview {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 50%;
    border: 2px solid #dee2e6;
}

.form-control[disabled] {
    background-color: #f8f9fa;
    opacity: 0.6;
}
</style>

<?php include '../includes/footer.php'; ?>
