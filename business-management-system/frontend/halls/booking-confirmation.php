<?php
/**
 * Business Management System - Booking Confirmation
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
require_once '../../includes/hall-email-functions.php';

// Get booking ID
$bookingId = (int)($_GET['booking_id'] ?? 0);

if (!$bookingId) {
    header('Location: index.php');
    exit;
}

// Get database connection
$conn = getDB();

// Get booking details
$stmt = $conn->prepare("
    SELECT hb.*, h.hall_name, h.hall_code, h.location, h.address,
           hc.category_name,
           c.first_name, c.last_name, c.email as customer_email, c.company_name, c.phone as customer_phone
    FROM " . DB_PREFIX . "hall_bookings hb
    JOIN " . DB_PREFIX . "halls h ON hb.hall_id = h.id
    JOIN " . DB_PREFIX . "hall_categories hc ON h.category_id = hc.id
    JOIN " . DB_PREFIX . "customers c ON hb.customer_id = c.id
    WHERE hb.id = ?
");
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header('Location: index.php');
    exit;
}

// Get company settings
$companyName = getSetting('company_name', 'Business Management System');
$companyEmail = getSetting('company_email', 'info@example.com');
$companyPhone = getSetting('company_phone', '+234 000 000 0000');

// Send confirmation email if enabled
if (getHallSetting('booking_confirmation_email', 1)) {
    sendHallBookingConfirmation($bookingId);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed - <?php echo htmlspecialchars($companyName); ?></title>
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
                        <i class="fas fa-home"></i> Browse More Halls
                    </a>
                    <a href="my-bookings.php" class="btn btn-outline-light">
                        <i class="fas fa-calendar-check"></i> My Bookings
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Confirmation Section -->
    <section class="confirmation-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="confirmation-card">
                        <div class="confirmation-header">
                            <div class="success-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h1>Booking Confirmed!</h1>
                            <p>Your hall booking has been successfully confirmed</p>
                        </div>
                        
                        <div class="confirmation-body">
                            <!-- Booking Details -->
                            <div class="booking-details">
                                <h3><i class="fas fa-calendar-check"></i> Booking Details</h3>
                                <div class="details-grid">
                                    <div class="detail-item">
                                        <strong>Booking Number:</strong>
                                        <span><?php echo htmlspecialchars($booking['booking_number']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <strong>Hall:</strong>
                                        <span><?php echo htmlspecialchars($booking['hall_name']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <strong>Event:</strong>
                                        <span><?php echo htmlspecialchars($booking['event_name']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <strong>Date:</strong>
                                        <span><?php echo date('M d, Y', strtotime($booking['start_date'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <strong>Time:</strong>
                                        <span>
                                            <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - 
                                            <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <strong>Duration:</strong>
                                        <span><?php echo round($booking['duration_hours'], 1); ?> hours</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Customer Information -->
                            <div class="customer-details">
                                <h3><i class="fas fa-user"></i> Customer Information</h3>
                                <div class="details-grid">
                                    <div class="detail-item">
                                        <strong>Name:</strong>
                                        <span><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <strong>Email:</strong>
                                        <span><?php echo htmlspecialchars($booking['customer_email']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <strong>Phone:</strong>
                                        <span><?php echo htmlspecialchars($booking['customer_phone']); ?></span>
                                    </div>
                                    <?php if ($booking['company_name']): ?>
                                    <div class="detail-item">
                                        <strong>Company:</strong>
                                        <span><?php echo htmlspecialchars($booking['company_name']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Financial Summary -->
                            <div class="financial-summary">
                                <h3><i class="fas fa-receipt"></i> Financial Summary</h3>
                                <div class="summary-table">
                                    <div class="summary-row">
                                        <span>Hall Rental:</span>
                                        <span><?php echo formatCurrency($booking['subtotal']); ?></span>
                                    </div>
                                    <?php if ($booking['service_fee'] > 0): ?>
                                    <div class="summary-row">
                                        <span>Service Fee:</span>
                                        <span><?php echo formatCurrency($booking['service_fee']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($booking['tax_amount'] > 0): ?>
                                    <div class="summary-row">
                                        <span>Tax:</span>
                                        <span><?php echo formatCurrency($booking['tax_amount']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="summary-row total">
                                        <span><strong>Total Amount:</strong></span>
                                        <span><strong><?php echo formatCurrency($booking['total_amount']); ?></strong></span>
                                    </div>
                                    <div class="summary-row">
                                        <span>Amount Paid:</span>
                                        <span><?php echo formatCurrency($booking['amount_paid']); ?></span>
                                    </div>
                                    <div class="summary-row">
                                        <span>Balance Due:</span>
                                        <span><?php echo formatCurrency($booking['balance_due']); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payment Information -->
                            <div class="payment-info">
                                <h3><i class="fas fa-credit-card"></i> Payment Information</h3>
                                <div class="payment-status">
                                    <span class="badge <?php echo getHallPaymentStatusBadgeClass($booking['payment_status']); ?>">
                                        <?php echo $booking['payment_status']; ?>
                                    </span>
                                </div>
                                
                                <?php if ($booking['balance_due'] > 0): ?>
                                <div class="payment-actions">
                                    <a href="make-payment.php?booking_id=<?php echo $bookingId; ?>" class="btn btn-primary">
                                        <i class="fas fa-credit-card"></i> Make Payment
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Next Steps -->
                            <div class="next-steps">
                                <h3><i class="fas fa-list-check"></i> Next Steps</h3>
                                <ul>
                                    <li><i class="fas fa-envelope"></i> A confirmation email has been sent to your email address</li>
                                    <li><i class="fas fa-calendar"></i> Add this event to your calendar</li>
                                    <li><i class="fas fa-phone"></i> Contact us if you have any questions or special requirements</li>
                                    <?php if ($booking['balance_due'] > 0): ?>
                                    <li><i class="fas fa-credit-card"></i> Complete your payment to secure your booking</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="confirmation-footer">
                            <div class="action-buttons">
                                <a href="my-bookings.php" class="btn btn-primary">
                                    <i class="fas fa-calendar-check"></i> View My Bookings
                                </a>
                                <a href="index.php" class="btn btn-outline-primary">
                                    <i class="fas fa-search"></i> Book Another Hall
                                </a>
                            </div>
                            
                            <div class="contact-info">
                                <h4><i class="fas fa-headset"></i> Need Help?</h4>
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
                    <a href="my-bookings.php">My Bookings</a>
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
.confirmation-section {
    padding: 60px 0;
    background-color: #f8f9fa;
    min-height: calc(100vh - 200px);
}

.confirmation-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    overflow: hidden;
}

.confirmation-header {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    text-align: center;
    padding: 40px;
}

.success-icon {
    font-size: 64px;
    margin-bottom: 20px;
}

.confirmation-header h1 {
    margin: 0 0 10px 0;
    font-size: 32px;
}

.confirmation-header p {
    margin: 0;
    font-size: 18px;
    opacity: 0.9;
}

.confirmation-body {
    padding: 40px;
}

.booking-details,
.customer-details,
.financial-summary,
.payment-info,
.next-steps {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.booking-details:last-child,
.customer-details:last-child,
.financial-summary:last-child,
.payment-info:last-child,
.next-steps:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.booking-details h3,
.customer-details h3,
.financial-summary h3,
.payment-info h3,
.next-steps h3 {
    margin-bottom: 20px;
    color: #333;
    font-size: 20px;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}

.detail-item:last-child {
    border-bottom: none;
}

.summary-table {
    background-color: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid #dee2e6;
}

.summary-row:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.summary-row.total {
    font-size: 18px;
    font-weight: bold;
    color: #333;
    border-top: 2px solid #007bff;
    padding-top: 15px;
    margin-top: 15px;
}

.payment-status {
    margin-bottom: 20px;
}

.payment-actions {
    margin-top: 20px;
}

.next-steps ul {
    list-style: none;
    padding: 0;
}

.next-steps li {
    margin-bottom: 15px;
    padding-left: 30px;
    position: relative;
}

.next-steps li i {
    position: absolute;
    left: 0;
    top: 0;
    color: #28a745;
    font-size: 16px;
}

.confirmation-footer {
    background-color: #f8f9fa;
    padding: 30px 40px;
    border-top: 1px solid #eee;
}

.action-buttons {
    text-align: center;
    margin-bottom: 30px;
}

.action-buttons .btn {
    margin: 0 10px;
    padding: 12px 30px;
}

.contact-info {
    text-align: center;
}

.contact-info h4 {
    margin-bottom: 15px;
    color: #333;
}

.contact-methods {
    display: flex;
    justify-content: center;
    gap: 30px;
}

.contact-method {
    display: flex;
    align-items: center;
    gap: 8px;
}

.contact-method i {
    color: #007bff;
}

.badge-success { background-color: #28a745; color: white; }
.badge-warning { background-color: #ffc107; color: #333; }
.badge-danger { background-color: #dc3545; color: white; }
.badge-info { background-color: #17a2b8; color: white; }
</style>