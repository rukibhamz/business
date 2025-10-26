<?php
/**
 * Business Management System - Confirm Hall Booking
 * Phase 4: Hall Booking System Module
 */

// Define system constant
define('BMS_SYSTEM', true);

// Start session
session_start();

// Include required files
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/csrf.php';
require_once '../../../includes/hall-functions.php';

// Check authentication and permissions
requireLogin();
requirePermission('halls.bookings');

// Get database connection
$conn = getDB();

// Get booking ID
$bookingId = (int)($_GET['id'] ?? 0);

if ($bookingId <= 0) {
    header('Location: index.php');
    exit;
}

// Get booking details
$stmt = $conn->prepare("
    SELECT hb.*, h.hall_name, h.hall_code, h.currency,
           c.first_name, c.last_name, c.company_name, c.email as customer_email
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

// Check if booking can be confirmed
if ($booking['booking_status'] != 'Pending') {
    $_SESSION['error_message'] = 'This booking cannot be confirmed. Current status: ' . $booking['booking_status'];
    header('Location: view.php?id=' . $bookingId);
    exit;
}

// Process confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error_message'] = 'Invalid security token. Please try again.';
        header('Location: view.php?id=' . $bookingId);
        exit;
    }

    try {
        // Confirm the booking
        confirmBooking($bookingId);

        // Log activity
        logActivity("Booking confirmed: {$booking['booking_number']}", $bookingId, 'hall_booking');

        $_SESSION['success_message'] = 'Booking has been confirmed successfully!';
        header('Location: view.php?id=' . $bookingId);
        exit;
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error confirming booking: ' . $e->getMessage();
        header('Location: view.php?id=' . $bookingId);
        exit;
    }
}

// Set page title
$pageTitle = 'Confirm Booking - ' . $booking['booking_number'];

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Confirm Booking</h1>
        <p><?php echo htmlspecialchars($booking['booking_number']); ?> • <?php echo htmlspecialchars($booking['event_name']); ?></p>
    </div>
    <div class="page-actions">
        <a href="view.php?id=<?php echo $booking['id']; ?>" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Booking
        </a>
    </div>
</div>

<div class="row">
    <!-- Confirmation Form -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>Confirm Booking</h3>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="icon-warning"></i>
                    <strong>Are you sure you want to confirm this booking?</strong>
                    <p class="mb-0">Once confirmed, the hall will be reserved for the specified date and time, and confirmation emails will be sent to the customer.</p>
                </div>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">
                            <i class="icon-check"></i> Confirm Booking
                        </button>
                        <a href="view.php?id=<?php echo $booking['id']; ?>" class="btn btn-secondary">
                            <i class="icon-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Booking Details -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3>Booking Details</h3>
            </div>
            <div class="card-body">
                <div class="booking-details">
                    <div class="detail-item">
                        <label>Booking Number:</label>
                        <span><?php echo htmlspecialchars($booking['booking_number']); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <label>Event:</label>
                        <span><?php echo htmlspecialchars($booking['event_name']); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <label>Hall:</label>
                        <span><?php echo htmlspecialchars($booking['hall_name']); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <label>Date:</label>
                        <span><?php echo date('M d, Y', strtotime($booking['start_date'])); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <label>Time:</label>
                        <span>
                            <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - 
                            <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                        </span>
                    </div>
                    
                    <div class="detail-item">
                        <label>Duration:</label>
                        <span><?php echo round($booking['duration_hours'], 1); ?> hours</span>
                    </div>
                    
                    <?php if ($booking['attendee_count']): ?>
                    <div class="detail-item">
                        <label>Attendees:</label>
                        <span><?php echo number_format($booking['attendee_count']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($booking['customer_id']): ?>
                    <div class="detail-item">
                        <label>Customer:</label>
                        <span><?php echo htmlspecialchars($booking['company_name'] ?: $booking['first_name'] . ' ' . $booking['last_name']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Payment Summary -->
        <div class="card">
            <div class="card-header">
                <h3>Payment Summary</h3>
            </div>
            <div class="card-body">
                <div class="payment-summary">
                    <div class="summary-item">
                        <label>Total Amount:</label>
                        <span><?php echo formatCurrency($booking['total_amount'], $booking['currency']); ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <label>Amount Paid:</label>
                        <span class="text-success"><?php echo formatCurrency($booking['amount_paid'], $booking['currency']); ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <label>Balance Due:</label>
                        <span class="<?php echo $booking['balance_due'] > 0 ? 'text-danger' : 'text-success'; ?>">
                            <?php echo formatCurrency($booking['balance_due'], $booking['currency']); ?>
                        </span>
                    </div>
                    
                    <div class="summary-item">
                        <label>Payment Status:</label>
                        <span class="badge <?php echo getPaymentStatusBadgeClass($booking['payment_status']); ?>">
                            <?php echo $booking['payment_status']; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Status -->
        <div class="card">
            <div class="card-header">
                <h3>Current Status</h3>
            </div>
            <div class="card-body">
                <div class="status-info">
                    <div class="status-item">
                        <label>Booking Status:</label>
                        <span class="badge badge-warning"><?php echo $booking['booking_status']; ?></span>
                    </div>
                    
                    <div class="status-item">
                        <label>Created:</label>
                        <span><?php echo date('M d, Y g:i A', strtotime($booking['created_at'])); ?></span>
                    </div>
                    
                    <div class="status-item">
                        <label>Booking Source:</label>
                        <span><?php echo $booking['booking_source']; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.form-actions {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #dee2e6;
}

.form-actions .btn {
    margin-right: 10px;
}

.card {
    margin-bottom: 20px;
}

.detail-item, .summary-item, .status-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    padding-bottom: 5px;
}

.detail-item:not(:last-child), .summary-item:not(:last-child), .status-item:not(:last-child) {
    border-bottom: 1px solid #f0f0f0;
}

.detail-item label, .summary-item label, .status-item label {
    font-weight: 500;
    margin-bottom: 0;
}

.text-success { color: #28a745 !important; }
.text-danger { color: #dc3545 !important; }

.badge-success { background-color: #28a745; color: white; }
.badge-warning { background-color: #ffc107; color: #333; }
.badge-danger { background-color: #dc3545; color: white; }
.badge-info { background-color: #17a2b8; color: white; }
.badge-secondary { background-color: #6c757d; color: white; }

.alert-warning {
    background-color: #fff3cd;
    border-color: #ffeaa7;
    color: #856404;
}
</style>

<?php
function getPaymentStatusBadgeClass($status) {
    switch ($status) {
        case 'Paid':
            return 'badge-success';
        case 'Partial':
            return 'badge-warning';
        case 'Pending':
            return 'badge-secondary';
        case 'Refunded':
            return 'badge-info';
        default:
            return 'badge-secondary';
    }
}

include '../../includes/footer.php'; ?>

