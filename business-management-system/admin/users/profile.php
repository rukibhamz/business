<?php
/**
 * Business Management System - User Profile
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

// Check authentication
requireLogin();

// Get database connection
$conn = getDB();

// Get current user data
$userId = $_SESSION['user_id'];
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
    header('Location: ../index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken($_POST['csrf_token'] ?? '');
    
    $errors = [];
    $success = false;
    
    // Get form data
    $firstName = sanitizeInput($_POST['first_name'] ?? '');
    $lastName = sanitizeInput($_POST['last_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
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
    
    // Password change validation (only if password fields are provided)
    if (!empty($currentPassword) || !empty($newPassword) || !empty($confirmPassword)) {
        if (empty($currentPassword)) {
            $errors[] = 'Current password is required to change password';
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $errors[] = 'Current password is incorrect';
        }
        
        if (empty($newPassword)) {
            $errors[] = 'New password is required';
        } elseif (strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'New passwords do not match';
        }
        
        // Check password strength
        if (!empty($newPassword)) {
            $passwordStrength = validatePasswordStrength($newPassword);
            if (!$passwordStrength['is_valid']) {
                $errors[] = 'Password is too weak: ' . implode(', ', $passwordStrength['messages']);
            }
        }
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
    
    // If no errors, update profile
    if (empty($errors)) {
        $updateFields = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'profile_picture' => $profilePicture,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Add password if provided
        if (!empty($newPassword)) {
            $updateFields['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }
        
        $setClause = implode(' = ?, ', array_keys($updateFields)) . ' = ?';
        $values = array_values($updateFields);
        $values[] = $userId;
        
        $stmt = $conn->prepare("UPDATE " . DB_PREFIX . "users SET {$setClause} WHERE id = ?");
        $stmt->bind_param(str_repeat('s', count($updateFields)) . 'i', ...$values);
        
        if ($stmt->execute()) {
            // Update session data
            $_SESSION['first_name'] = $firstName;
            $_SESSION['last_name'] = $lastName;
            $_SESSION['email'] = $email;
            
            // Log activity
            logActivity('profile_update', 'Profile updated successfully');
            
            $success = true;
            $_SESSION['success'] = 'Profile updated successfully';
            
            // Refresh user data
            $stmt = $conn->prepare("
                SELECT u.*, r.name as role_name 
                FROM " . DB_PREFIX . "users u 
                LEFT JOIN " . DB_PREFIX . "roles r ON u.role_id = r.id 
                WHERE u.id = ?
            ");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
        } else {
            $errors[] = 'Failed to update profile. Please try again.';
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

// Include header
include '../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>My Profile</h1>
        <p>Manage your account information</p>
    </div>
    <div class="page-actions">
        <a href="../index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Dashboard
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

<?php if ($success): ?>
<div class="alert alert-success">
    <i class="icon-check"></i> Profile updated successfully!
</div>
<?php endif; ?>

<div class="row">
    <!-- Profile Information -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>Profile Information</h3>
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
                            <label>Account Information</label>
                            <div class="account-info">
                                <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                                <p><strong>Role:</strong> <?php echo htmlspecialchars($user['role_name']); ?></p>
                                <p><strong>Member since:</strong> <?php echo date('M d, Y', strtotime($user['created_at'])); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="icon-save"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Change Password -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3>Change Password</h3>
            </div>
            <div class="card-body">
                <form method="POST" class="form-horizontal">
                    <?php csrfField(); ?>
                    
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" 
                               class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" 
                               class="form-control" minlength="8">
                        <div class="password-strength" id="password-strength"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               class="form-control" minlength="8">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-warning btn-block">
                            <i class="icon-key"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Account Security -->
        <div class="card">
            <div class="card-header">
                <h3>Account Security</h3>
            </div>
            <div class="card-body">
                <div class="security-info">
                    <div class="security-item">
                        <i class="icon-shield"></i>
                        <div>
                            <strong>Last Login</strong>
                            <p><?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></p>
                        </div>
                    </div>
                    <div class="security-item">
                        <i class="icon-clock"></i>
                        <div>
                            <strong>Account Created</strong>
                            <p><?php echo date('M d, Y', strtotime($user['created_at'])); ?></p>
                        </div>
                    </div>
                    <div class="security-item">
                        <i class="icon-check-circle"></i>
                        <div>
                            <strong>Account Status</strong>
                            <p class="text-success">Active</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('new_password').addEventListener('input', function() {
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
    const password = document.getElementById('new_password').value;
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

.account-info p {
    margin-bottom: 8px;
    color: #6c757d;
}

.security-info {
    space-y: 15px;
}

.security-item {
    display: flex;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f1f3f4;
}

.security-item:last-child {
    border-bottom: none;
}

.security-item i {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    color: #6c757d;
}

.security-item strong {
    display: block;
    font-size: 14px;
    color: #333;
    margin-bottom: 2px;
}

.security-item p {
    margin: 0;
    font-size: 12px;
    color: #6c757d;
}
</style>

<?php include '../includes/footer.php'; ?>
