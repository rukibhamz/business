<?php
/**
 * Business Management System - Hall Booking Form
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
require_once '../../includes/csrf.php';

// Get hall ID
$hallId = (int)($_GET['hall_id'] ?? 0);

if (!$hallId) {
    header('Location: index.php');
    exit;
}

// Get database connection
$conn = getDB();

// Get hall details
$stmt = $conn->prepare("
    SELECT h.*, hc.category_name
    FROM " . DB_PREFIX . "halls h
    JOIN " . DB_PREFIX . "hall_categories hc ON h.category_id = hc.id
    WHERE h.id = ? AND h.status = 'Available' AND h.enable_booking = 1
");
$stmt->bind_param('i', $hallId);
$stmt->execute();
$hall = $stmt->get_result()->fetch_assoc();

if (!$hall) {
    header('Location: index.php');
    exit;
}

// Get hall settings
$serviceFeePercentage = (float)getHallSetting('service_fee_percentage', 2.5);
$taxRate = (float)getHallSetting('tax_rate', 7.5);
$minDepositPercentage = (float)getHallSetting('min_deposit_percentage', 30);
$maxInstallments = (int)getHallSetting('max_installments', 3);

// Process form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        // Get form data
        $eventName = trim($_POST['event_name'] ?? '');
        $eventType = trim($_POST['event_type'] ?? '');
        $startDate = $_POST['start_date'] ?? '';
        $startTime = $_POST['start_time'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $endTime = $_POST['end_time'] ?? '';
        $attendeeCount = (int)($_POST['attendee_count'] ?? 0);
        $customerName = trim($_POST['customer_name'] ?? '');
        $customerEmail = trim($_POST['customer_email'] ?? '');
        $customerPhone = trim($_POST['customer_phone'] ?? '');
        $customerCompany = trim($_POST['customer_company'] ?? '');
        $paymentType = $_POST['payment_type'] ?? 'Full Payment';
        $specialRequirements = trim($_POST['special_requirements'] ?? '');
        
        // Validation
        if (empty($eventName)) {
            $errors[] = 'Event name is required';
        }
        
        if (empty($startDate) || empty($startTime)) {
            $errors[] = 'Start date and time are required';
        }
        
        if (empty($endDate) || empty($endTime)) {
            $errors[] = 'End date and time are required';
        }
        
        if ($startDate && $endDate && $startTime && $endTime) {
            $startDateTime = $startDate . ' ' . $startTime;
            $endDateTime = $endDate . ' ' . $endTime;
            
            if (strtotime($endDateTime) <= strtotime($startDateTime)) {
                $errors[] = 'End date and time must be after start date and time';
            }
        }
        
        if (empty($customerName)) {
            $errors[] = 'Customer name is required';
        }
        
        if (empty($customerEmail) || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid customer email is required';
        }
        
        if (empty($customerPhone)) {
            $errors[] = 'Customer phone number is required';
        }
        
        if ($attendeeCount > $hall['capacity']) {
            $errors[] = 'Attendee count cannot exceed hall capacity (' . number_format($hall['capacity']) . ')';
        }
        
        // Check availability
        if (empty($errors) && !isHallAvailable($hallId, $startDate, $startTime, $endDate, $endTime)) {
            $errors[] = 'Hall is not available for the selected date and time';
        }
        
        // Process booking if no errors
        if (empty($errors)) {
            // Check if customer exists, create if not
            $stmt = $conn->prepare("
                SELECT id FROM " . DB_PREFIX . "customers 
                WHERE email = ?
            ");
            $stmt->bind_param('s', $customerEmail);
            $stmt->execute();
            $customer = $stmt->get_result()->fetch_assoc();
            
            if (!$customer) {
                // Create new customer
                $stmt = $conn->prepare("
                    INSERT INTO " . DB_PREFIX . "customers 
                    (first_name, last_name, company_name, customer_type, email, phone, created_at) 
                    VALUES (?, ?, ?, 'Individual', ?, ?, NOW())
                ");
                
                $nameParts = explode(' ', $customerName, 2);
                $firstName = $nameParts[0];
                $lastName = isset($nameParts[1]) ? $nameParts[1] : '';
                
                $stmt->bind_param('sssss', $firstName, $lastName, $customerCompany, $customerEmail, $customerPhone);
                $stmt->execute();
                $customerId = $conn->getConnection()->lastInsertId();
            } else {
                $customerId = $customer['id'];
            }
            
            // Create booking
            $bookingData = [
                'hall_id' => $hallId,
                'customer_id' => $customerId,
                'event_name' => $eventName,
                'event_type' => $eventType,
                'start_date' => $startDate,
                'start_time' => $startTime,
                'end_date' => $endDate,
                'end_time' => $endTime,
                'attendee_count' => $attendeeCount,
                'hall_rental' => calculateHallRental($hallId, $startDate, $startTime, $endDate, $endTime),
                'service_fee' => 0,
                'tax_rate' => $taxRate,
                'payment_type' => $paymentType,
                'special_requirements' => $specialRequirements,
                'booking_source' => 'Online',
                'created_by' => null,
                'duration_hours' => 0 // Will be calculated
            ];
            
            $bookingId = createHallBooking($bookingData);
            
            if ($bookingId) {
                $success = true;
                // Redirect to confirmation page
                header('Location: booking-confirmation.php?booking_id=' . $bookingId);
                exit;
            } else {
                $errors[] = 'Failed to create booking. Please try again.';
            }
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
    <title>Book <?php echo htmlspecialchars($hall['hall_name']); ?> - Hall Booking</title>
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
                    <a href="view.php?id=<?php echo $hallId; ?>" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left"></i> Back to Hall
                    </a>
                    <a href="index.php" class="btn btn-outline-light">
                        <i class="fas fa-home"></i> All Halls
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Booking Form -->
    <section class="booking-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <div class="booking-form-card">
                        <div class="form-header">
                            <h1><i class="fas fa-calendar-plus"></i> Book <?php echo htmlspecialchars($hall['hall_name']); ?></h1>
                            <p>Fill out the form below to book this hall for your event</p>
                        </div>
                        
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
                        
                        <form method="POST" class="booking-form">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <!-- Event Information -->
                            <div class="form-section">
                                <h3><i class="fas fa-calendar"></i> Event Information</h3>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="event_name">Event Name *</label>
                                            <input type="text" name="event_name" id="event_name" 
                                                   value="<?php echo htmlspecialchars($_POST['event_name'] ?? ''); ?>" 
                                                   class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="event_type">Event Type</label>
                                            <select name="event_type" id="event_type" class="form-control">
                                                <option value="">Select Event Type</option>
                                                <option value="Conference" <?php echo ($_POST['event_type'] ?? '') == 'Conference' ? 'selected' : ''; ?>>Conference</option>
                                                <option value="Meeting" <?php echo ($_POST['event_type'] ?? '') == 'Meeting' ? 'selected' : ''; ?>>Meeting</option>
                                                <option value="Wedding" <?php echo ($_POST['event_type'] ?? '') == 'Wedding' ? 'selected' : ''; ?>>Wedding</option>
                                                <option value="Birthday Party" <?php echo ($_POST['event_type'] ?? '') == 'Birthday Party' ? 'selected' : ''; ?>>Birthday Party</option>
                                                <option value="Corporate Event" <?php echo ($_POST['event_type'] ?? '') == 'Corporate Event' ? 'selected' : ''; ?>>Corporate Event</option>
                                                <option value="Training" <?php echo ($_POST['event_type'] ?? '') == 'Training' ? 'selected' : ''; ?>>Training</option>
                                                <option value="Other" <?php echo ($_POST['event_type'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="start_date">Start Date *</label>
                                            <input type="date" name="start_date" id="start_date" 
                                                   value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>" 
                                                   class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="start_time">Start Time *</label>
                                            <input type="time" name="start_time" id="start_time" 
                                                   value="<?php echo htmlspecialchars($_POST['start_time'] ?? ''); ?>" 
                                                   class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="end_date">End Date *</label>
                                            <input type="date" name="end_date" id="end_date" 
                                                   value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>" 
                                                   class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="end_time">End Time *</label>
                                            <input type="time" name="end_time" id="end_time" 
                                                   value="<?php echo htmlspecialchars($_POST['end_time'] ?? ''); ?>" 
                                                   class="form-control" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="attendee_count">Expected Attendees</label>
                                            <input type="number" name="attendee_count" id="attendee_count" 
                                                   value="<?php echo htmlspecialchars($_POST['attendee_count'] ?? ''); ?>" 
                                                   min="1" max="<?php echo $hall['capacity']; ?>" 
                                                   class="form-control">
                                            <small class="form-text text-muted">
                                                Maximum capacity: <?php echo number_format($hall['capacity']); ?> people
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Customer Information -->
                            <div class="form-section">
                                <h3><i class="fas fa-user"></i> Customer Information</h3>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="customer_name">Full Name *</label>
                                            <input type="text" name="customer_name" id="customer_name" 
                                                   value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>" 
                                                   class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="customer_company">Company/Organization</label>
                                            <input type="text" name="customer_company" id="customer_company" 
                                                   value="<?php echo htmlspecialchars($_POST['customer_company'] ?? ''); ?>" 
                                                   class="form-control">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="customer_email">Email Address *</label>
                                            <input type="email" name="customer_email" id="customer_email" 
                                                   value="<?php echo htmlspecialchars($_POST['customer_email'] ?? ''); ?>" 
                                                   class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="customer_phone">Phone Number *</label>
                                            <input type="tel" name="customer_phone" id="customer_phone" 
                                                   value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? ''); ?>" 
                                                   class="form-control" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payment Information -->
                            <div class="form-section">
                                <h3><i class="fas fa-credit-card"></i> Payment Information</h3>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Payment Type *</label>
                                            <div class="payment-options">
                                                <div class="payment-option">
                                                    <input type="radio" name="payment_type" id="full_payment" 
                                                           value="Full Payment" 
                                                           <?php echo ($_POST['payment_type'] ?? 'Full Payment') == 'Full Payment' ? 'checked' : ''; ?>>
                                                    <label for="full_payment">
                                                        <i class="fas fa-check-circle"></i>
                                                        Full Payment
                                                        <span>Pay the full amount upfront</span>
                                                    </label>
                                                </div>
                                                <div class="payment-option">
                                                    <input type="radio" name="payment_type" id="partial_payment" 
                                                           value="Partial Payment" 
                                                           <?php echo ($_POST['payment_type'] ?? '') == 'Partial Payment' ? 'checked' : ''; ?>>
                                                    <label for="partial_payment">
                                                        <i class="fas fa-calendar-alt"></i>
                                                        Partial Payment
                                                        <span>Pay <?php echo $minDepositPercentage; ?>% deposit, balance later</span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Special Requirements -->
                            <div class="form-section">
                                <h3><i class="fas fa-clipboard-list"></i> Special Requirements</h3>
                                <div class="form-group">
                                    <label for="special_requirements">Special Requirements or Notes</label>
                                    <textarea name="special_requirements" id="special_requirements" 
                                              rows="4" class="form-control" 
                                              placeholder="Any special requirements, dietary needs, accessibility needs, or additional notes..."><?php echo htmlspecialchars($_POST['special_requirements'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Terms and Conditions -->
                            <div class="form-section">
                                <div class="terms-checkbox">
                                    <input type="checkbox" name="agree_terms" id="agree_terms" required>
                                    <label for="agree_terms">
                                        I agree to the <a href="#" target="_blank">Terms and Conditions</a> 
                                        and <a href="#" target="_blank">Cancellation Policy</a>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-calendar-plus"></i> Complete Booking
                                </button>
                                <a href="view.php?id=<?php echo $hallId; ?>" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-arrow-left"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Booking Summary -->
                <div class="col-lg-4">
                    <div class="booking-summary">
                        <h3><i class="fas fa-receipt"></i> Booking Summary</h3>
                        
                        <div class="hall-summary">
                            <div class="hall-image">
                                <?php if ($hall['featured_image']): ?>
                                <img src="../../uploads/halls/<?php echo htmlspecialchars($hall['featured_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($hall['hall_name']); ?>">
                                <?php else: ?>
                                <div class="placeholder-image">
                                    <i class="fas fa-building"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="hall-info">
                                <h4><?php echo htmlspecialchars($hall['hall_name']); ?></h4>
                                <p><?php echo htmlspecialchars($hall['category_name']); ?></p>
                                <p><i class="fas fa-users"></i> Capacity: <?php echo number_format($hall['capacity']); ?> people</p>
                            </div>
                        </div>
                        
                        <div class="pricing-summary">
                            <h4>Pricing Options</h4>
                            <div class="price-list">
                                <?php if ($hall['hourly_rate']): ?>
                                <div class="price-item">
                                    <span>Hourly Rate:</span>
                                    <span><?php echo formatCurrency($hall['hourly_rate']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($hall['daily_rate']): ?>
                                <div class="price-item">
                                    <span>Daily Rate:</span>
                                    <span><?php echo formatCurrency($hall['daily_rate']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($hall['weekly_rate']): ?>
                                <div class="price-item">
                                    <span>Weekly Rate:</span>
                                    <span><?php echo formatCurrency($hall['weekly_rate']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($hall['monthly_rate']): ?>
                                <div class="price-item">
                                    <span>Monthly Rate:</span>
                                    <span><?php echo formatCurrency($hall['monthly_rate']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="booking-info">
                            <h4><i class="fas fa-info-circle"></i> Booking Information</h4>
                            <ul>
                                <li><i class="fas fa-shield-alt"></i> Secure booking process</li>
                                <li><i class="fas fa-envelope"></i> Instant email confirmation</li>
                                <li><i class="fas fa-credit-card"></i> Multiple payment options</li>
                                <li><i class="fas fa-headset"></i> 24/7 customer support</li>
                            </ul>
                        </div>
                        
                        <div class="contact-info">
                            <h4><i class="fas fa-phone"></i> Need Help?</h4>
                            <p>Contact our booking team:</p>
                            <div class="contact-details">
                                <div class="contact-item">
                                    <i class="fas fa-phone"></i>
                                    <span><?php echo htmlspecialchars($companyPhone); ?></span>
                                </div>
                                <div class="contact-item">
                                    <i class="fas fa-envelope"></i>
                                    <span><?php echo htmlspecialchars($companyEmail); ?></span>
                                </div>
                            </div>
                        </div>
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
                    <a href="index.php">All Halls</a>
                    <a href="my-bookings.php">My Bookings</a>
                    <a href="mailto:<?php echo htmlspecialchars($companyEmail); ?>">Contact Us</a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($companyName); ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Auto-set end date when start date changes
        document.getElementById('start_date').addEventListener('change', function() {
            const endDateInput = document.getElementById('end_date');
            if (!endDateInput.value) {
                endDateInput.value = this.value;
            }
        });
        
        // Auto-set end time when start time changes
        document.getElementById('start_time').addEventListener('change', function() {
            const endTimeInput = document.getElementById('end_time');
            if (!endTimeInput.value) {
                const startTime = new Date('2000-01-01 ' + this.value);
                const endTime = new Date(startTime.getTime() + 2 * 60 * 60 * 1000); // Add 2 hours
                endTimeInput.value = endTime.toTimeString().slice(0, 5);
            }
        });
        
        // Form validation
        document.querySelector('.booking-form').addEventListener('submit', function(e) {
            const startDate = document.getElementById('start_date').value;
            const startTime = document.getElementById('start_time').value;
            const endDate = document.getElementById('end_date').value;
            const endTime = document.getElementById('end_time').value;
            
            if (startDate && startTime && endDate && endTime) {
                const startDateTime = new Date(startDate + ' ' + startTime);
                const endDateTime = new Date(endDate + ' ' + endTime);
                
                if (endDateTime <= startDateTime) {
                    e.preventDefault();
                    alert('End date and time must be after start date and time');
                    return false;
                }
            }
            
            const agreeTerms = document.getElementById('agree_terms');
            if (!agreeTerms.checked) {
                e.preventDefault();
                alert('Please agree to the terms and conditions');
                return false;
            }
        });
        
        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('start_date').setAttribute('min', today);
        document.getElementById('end_date').setAttribute('min', today);
    </script>
</body>
</html>
