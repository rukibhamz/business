<?php
/**
 * Business Management System - Hall Booking Confirmation
 * Phase 4: Hall Booking System Module
 */

// Define system constant
define('BMS_SYSTEM', true);

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/hall-functions.php';

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
           c.first_name, c.last_name, c.company_name, c.email as customer_email, c.phone as customer_phone
    FROM " . DB_PREFIX . "hall_bookings hb
    JOIN " . DB_PREFIX . "halls h ON hb.hall_id = h.id
    LEFT JOIN " . DB_PREFIX . "customers c ON hb.customer_id = c.id
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed - <?php echo htmlspecialchars($companyName); ?></title>
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
                    <a href="my-bookings.php" class="btn btn-outline-light">
                        <i class="fas fa-calendar-check"></i> My Bookings
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Success Message -->
    <section class="success-section">
        <div class="container">
            <div class="success-content">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1>Booking Confirmed!</h1>
                <p class="success-message">Your hall booking has been successfully confirmed. You will receive a confirmation email shortly.</p>
                
                <div class="booking-number">
                    <strong>Booking Number:</strong>
                    <span class="booking-code"><?php echo htmlspecialchars($booking['booking_number']); ?></span>
                </div>
            </div>
        </div>
    </section>

    <!-- Booking Details -->
    <section class="booking-details-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <div class="booking-summary-card">
                        <h2><i class="fas fa-calendar-alt"></i> Booking Summary</h2>
                        
                        <div class="booking-info">
                            <div class="info-row">
                                <div class="info-label">Hall:</div>
                                <div class="info-value"><?php echo htmlspecialchars($booking['hall_name']); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Event:</div>
                                <div class="info-value"><?php echo htmlspecialchars($booking['event_name'] ?: 'Event'); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Date:</div>
                                <div class="info-value">
                                    <?php echo date('M d, Y', strtotime($booking['start_date'])); ?>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Time:</div>
                                <div class="info-value">
                                    <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - 
                                    <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Duration:</div>
                                <div class="info-value"><?php echo round($booking['duration_hours'], 1); ?> hours</div>
                            </div>
                            <?php if ($booking['attendee_count']): ?>
                            <div class="info-row">
                                <div class="info-label">Expected Attendees:</div>
                                <div class="info-value"><?php echo number_format($booking['attendee_count']); ?> people</div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="hall-location">
                            <h3><i class="fas fa-map-marker-alt"></i> Hall Location</h3>
                            <p><strong><?php echo htmlspecialchars($booking['hall_name']); ?></strong></p>
                            <?php if ($booking['location']): ?>
                            <p><?php echo htmlspecialchars($booking['location']); ?></p>
                            <?php endif; ?>
                            <?php if ($booking['address']): ?>
                            <p><?php echo nl2br(htmlspecialchars($booking['address'])); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($booking['special_requirements']): ?>
                        <div class="special-requirements">
                            <h3><i class="fas fa-clipboard-list"></i> Special Requirements</h3>
                            <p><?php echo nl2br(htmlspecialchars($booking['special_requirements'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="payment-summary-card">
                        <h3><i class="fas fa-receipt"></i> Payment Summary</h3>
                        
                        <div class="payment-breakdown">
                            <div class="payment-item">
                                <span>Subtotal:</span>
                                <span><?php echo formatCurrency($booking['subtotal']); ?></span>
                            </div>
                            <?php if ($booking['service_fee'] > 0): ?>
                            <div class="payment-item">
                                <span>Service Fee:</span>
                                <span><?php echo formatCurrency($booking['service_fee']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($booking['tax_amount'] > 0): ?>
                            <div class="payment-item">
                                <span>Tax:</span>
                                <span><?php echo formatCurrency($booking['tax_amount']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="payment-item total">
                                <span>Total Amount:</span>
                                <span><?php echo formatCurrency($booking['total_amount']); ?></span>
                            </div>
                            <div class="payment-item">
                                <span>Amount Paid:</span>
                                <span class="text-success"><?php echo formatCurrency($booking['amount_paid']); ?></span>
                            </div>
                            <div class="payment-item">
                                <span>Balance Due:</span>
                                <span class="<?php echo $booking['balance_due'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo formatCurrency($booking['balance_due']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="payment-status">
                            <div class="status-badge <?php echo getHallPaymentStatusBadgeClass($booking['payment_status']); ?>">
                                <?php echo $booking['payment_status']; ?>
                            </div>
                        </div>
                        
                        <?php if ($booking['balance_due'] > 0): ?>
                        <div class="payment-instructions">
                            <h4><i class="fas fa-info-circle"></i> Payment Instructions</h4>
                            <p>Please complete your payment to confirm your booking. You can pay the balance due before your event date.</p>
                            <div class="contact-info">
                                <p><strong>Contact us for payment:</strong></p>
                                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($companyPhone); ?></p>
                                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($companyEmail); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="next-steps-card">
                        <h3><i class="fas fa-list-check"></i> Next Steps</h3>
                        <div class="steps-list">
                            <div class="step-item">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <strong>Confirmation Email</strong>
                                    <p>You will receive a confirmation email with all booking details</p>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <strong>Payment</strong>
                                    <p><?php echo $booking['balance_due'] > 0 ? 'Complete your payment to secure your booking' : 'Your payment has been processed'; ?></p>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <strong>Event Day</strong>
                                    <p>Arrive at the hall on your scheduled date and time</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="contact-card">
                        <h3><i class="fas fa-headset"></i> Need Help?</h3>
                        <p>Our team is here to assist you with any questions or concerns.</p>
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
    </section>
    
    <!-- Action Buttons -->
    <section class="action-section">
        <div class="container">
            <div class="action-buttons">
                <a href="my-bookings.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-calendar-check"></i> View My Bookings
                </a>
                <a href="index.php" class="btn btn-outline-primary btn-lg">
                    <i class="fas fa-plus"></i> Book Another Hall
                </a>
                <button onclick="window.print()" class="btn btn-outline-secondary btn-lg">
                    <i class="fas fa-print"></i> Print Confirmation
                </button>
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

    <style>
    .success-section {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        padding: 4rem 0;
        text-align: center;
    }
    
    .success-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
    }
    
    .success-content h1 {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        font-weight: 700;
    }
    
    .success-message {
        font-size: 1.2rem;
        margin-bottom: 2rem;
        opacity: 0.9;
    }
    
    .booking-number {
        background: rgba(255,255,255,0.2);
        padding: 1rem 2rem;
        border-radius: 10px;
        display: inline-block;
    }
    
    .booking-code {
        font-size: 1.5rem;
        font-weight: 700;
        margin-left: 1rem;
    }
    
    .booking-details-section {
        padding: 3rem 0;
    }
    
    .booking-summary-card, .payment-summary-card, .next-steps-card, .contact-card {
        background: white;
        border-radius: 10px;
        padding: 2rem;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
    }
    
    .booking-summary-card h2, .payment-summary-card h3, .next-steps-card h3, .contact-card h3 {
        margin-bottom: 1.5rem;
        color: #495057;
        font-weight: 600;
    }
    
    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 0.75rem 0;
        border-bottom: 1px solid #e9ecef;
    }
    
    .info-row:last-child {
        border-bottom: none;
    }
    
    .info-label {
        font-weight: 600;
        color: #495057;
    }
    
    .info-value {
        color: #6c757d;
    }
    
    .payment-breakdown {
        border-top: 1px solid #e9ecef;
        padding-top: 1rem;
    }
    
    .payment-item {
        display: flex;
        justify-content: space-between;
        padding: 0.5rem 0;
    }
    
    .payment-item.total {
        border-top: 1px solid #e9ecef;
        padding-top: 1rem;
        margin-top: 0.5rem;
        font-weight: 600;
        font-size: 1.1rem;
    }
    
    .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 600;
        text-align: center;
        margin-top: 1rem;
    }
    
    .badge-success { background-color: #28a745; color: white; }
    .badge-secondary { background-color: #6c757d; color: white; }
    .badge-danger { background-color: #dc3545; color: white; }
    .badge-info { background-color: #17a2b8; color: white; }
    .badge-warning { background-color: #ffc107; color: #333; }
    
    .steps-list {
        margin-top: 1rem;
    }
    
    .step-item {
        display: flex;
        align-items: flex-start;
        margin-bottom: 1.5rem;
    }
    
    .step-item:last-child {
        margin-bottom: 0;
    }
    
    .step-number {
        background: #667eea;
        color: white;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        margin-right: 1rem;
        flex-shrink: 0;
    }
    
    .step-content strong {
        display: block;
        margin-bottom: 0.25rem;
        color: #495057;
    }
    
    .step-content p {
        margin: 0;
        color: #6c757d;
        font-size: 0.9rem;
    }
    
    .contact-details {
        margin-top: 1rem;
    }
    
    .contact-item {
        display: flex;
        align-items: center;
        margin-bottom: 0.5rem;
    }
    
    .contact-item i {
        margin-right: 0.5rem;
        color: #667eea;
        width: 16px;
    }
    
    .action-section {
        background: #f8f9fa;
        padding: 2rem 0;
        text-align: center;
    }
    
    .action-buttons {
        display: flex;
        gap: 1rem;
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .action-buttons .btn {
        padding: 1rem 2rem;
        border-radius: 8px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .action-buttons .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    
    @media (max-width: 768px) {
        .success-content h1 {
            font-size: 2rem;
        }
        
        .booking-code {
            font-size: 1.2rem;
        }
        
        .action-buttons {
            flex-direction: column;
            align-items: center;
        }
        
        .action-buttons .btn {
            width: 100%;
            max-width: 300px;
        }
    }
    </style>

    <script>
        // Auto-redirect to my bookings after 30 seconds
        setTimeout(function() {
            if (confirm('Would you like to view your bookings now?')) {
                window.location.href = 'my-bookings.php';
            }
        }, 30000);
    </script>
</body>
</html>
