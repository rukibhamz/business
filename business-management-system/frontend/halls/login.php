<?php
/**
 * Business Management System - Customer Login
 * Phase 4: Hall Booking System Module
 */

// Define system constant
define('BMS_SYSTEM', true);

// Start session
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/hall-functions.php';

// Get database connection
$conn = getDB();

// Process form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    // Validation
    if (empty($email)) {
        $errors[] = 'Email address is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    // Check if customer exists
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM " . DB_PREFIX . "customers WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $customer = $stmt->get_result()->fetch_assoc();
        
        if ($customer) {
            // Set session and redirect
            $_SESSION['customer_email'] = $email;
            header('Location: my-bookings.php');
            exit;
        } else {
            $errors[] = 'No bookings found for this email address. Please check your email or make a new booking.';
        }
    }
}

// Get company settings
$companyName = getSetting('company_name', 'Business Management System');
$companyEmail = getSetting('company_email', 'info@example.com');
$companyPhone = getSetting('company_phone', '+234 000 000 0000');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Login - <?php echo htmlspecialchars($companyName); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../public/css/halls.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <h2><?php echo htmlspecialchars($companyName); ?></h2>
                    <p>Hall Rentals & Event Spaces</p>
                </div>
                <div class="header-actions">
                    <a href="index.php" class="btn btn-outline-light">
                        <i class="fas fa-home"></i> Browse Halls
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Login Section -->
    <section class="login-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <div class="login-card">
                        <div class="card-header">
                            <h2><i class="fas fa-sign-in-alt"></i> Customer Login</h2>
                            <p>Access your booking history and manage your reservations</p>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <h4><i class="fas fa-exclamation-triangle"></i> Please correct the following errors:</h4>
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                            
                            <form method="POST" class="login-form">
                                <div class="form-group">
                                    <label for="email">Email Address *</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">
                                                <i class="fas fa-envelope"></i>
                                            </span>
                                        </div>
                                        <input type="email" name="email" id="email" 
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                               class="form-control" placeholder="Enter your email address" required>
                                    </div>
                                    <small class="form-text text-muted">
                                        Enter the email address you used when making your booking
                                    </small>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary btn-lg btn-block">
                                        <i class="fas fa-sign-in-alt"></i> Access My Bookings
                                    </button>
                                </div>
                            </form>
                            
                            <div class="login-help">
                                <h5><i class="fas fa-question-circle"></i> Need Help?</h5>
                                <p>If you can't find your bookings, please check:</p>
                                <ul>
                                    <li>You're using the correct email address</li>
                                    <li>You have made at least one booking</li>
                                    <li>Your booking wasn't cancelled</li>
                                </ul>
                                
                                <div class="contact-info">
                                    <p><strong>Still having trouble?</strong></p>
                                    <div class="contact-methods">
                                        <div class="contact-method">
                                            <i class="fas fa-phone"></i>
                                            <span><?php echo htmlspecialchars($companyPhone); ?></span>
                                        </div>
                                        <div class="contact-method">
                                            <i class="fas fa-envelope"></i>
                                            <span><?php echo htmlspecialchars($companyEmail); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="login-footer">
                        <p>Don't have any bookings yet?</p>
                        <a href="index.php" class="btn btn-outline-primary">
                            <i class="fas fa-search"></i> Browse Available Halls
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-info">
                    <h3><?php echo htmlspecialchars($companyName); ?></h3>
                    <p>Professional hall rentals and event spaces</p>
                </div>
                <div class="footer-links">
                    <a href="index.php">Browse Halls</a>
                    <a href="mailto:<?php echo htmlspecialchars($companyEmail); ?>">Contact Us</a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($companyName); ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="../../public/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<style>
.login-section {
    padding: 60px 0;
    background-color: #f8f9fa;
    min-height: calc(100vh - 200px);
}

.login-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    overflow: hidden;
}

.login-card .card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    text-align: center;
    padding: 30px;
    border: none;
}

.login-card .card-header h2 {
    margin: 0 0 10px 0;
    font-size: 28px;
}

.login-card .card-header p {
    margin: 0;
    opacity: 0.9;
}

.login-card .card-body {
    padding: 40px;
}

.form-group {
    margin-bottom: 25px;
}

.form-group label {
    font-weight: 500;
    margin-bottom: 8px;
    color: #333;
}

.input-group-text {
    background-color: #f8f9fa;
    border-color: #dee2e6;
    color: #6c757d;
}

.form-control {
    border-color: #dee2e6;
    padding: 12px 15px;
    font-size: 16px;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    padding: 15px 30px;
    font-size: 16px;
    font-weight: 500;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

.login-help {
    margin-top: 30px;
    padding-top: 30px;
    border-top: 1px solid #eee;
}

.login-help h5 {
    color: #333;
    margin-bottom: 15px;
}

.login-help ul {
    margin-bottom: 20px;
    padding-left: 20px;
}

.login-help li {
    margin-bottom: 5px;
    color: #666;
}

.contact-info {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-top: 20px;
}

.contact-methods {
    display: flex;
    gap: 20px;
    margin-top: 10px;
}

.contact-method {
    display: flex;
    align-items: center;
    gap: 8px;
}

.contact-method i {
    color: #667eea;
}

.login-footer {
    text-align: center;
    margin-top: 30px;
    padding: 20px;
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.login-footer p {
    margin-bottom: 15px;
    color: #666;
}

.btn-outline-primary {
    border-color: #667eea;
    color: #667eea;
}

.btn-outline-primary:hover {
    background-color: #667eea;
    border-color: #667eea;
}
</style>
