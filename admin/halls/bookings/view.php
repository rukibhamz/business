<?php
/**
 * Business Management System - View Hall Booking
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
    SELECT hb.*, h.hall_name, h.hall_code, h.location, h.address, h.capacity, h.currency,
           c.first_name, c.last_name, c.company_name, c.email as customer_email, c.phone as customer_phone,
           u.first_name as created_by_first, u.last_name as created_by_last
    FROM " . DB_PREFIX . "hall_bookings hb
    JOIN " . DB_PREFIX . "halls h ON hb.hall_id = h.id
    LEFT JOIN " . DB_PREFIX . "customers c ON hb.customer_id = c.id
    LEFT JOIN " . DB_PREFIX . "users u ON hb.created_by = u.id
    WHERE hb.id = ?
");
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header('Location: index.php');
    exit;
}

// Get booking items
$stmt = $conn->prepare("
    SELECT * FROM " . DB_PREFIX . "hall_booking_items 
    WHERE booking_id = ? 
    ORDER BY id
");
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$bookingItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get booking payments
$stmt = $conn->prepare("
    SELECT hbp.*, p.payment_number as accounting_payment_number
    FROM " . DB_PREFIX . "hall_booking_payments hbp
    LEFT JOIN " . DB_PREFIX . "payments p ON hbp.payment_id = p.id
    WHERE hbp.booking_id = ? 
    ORDER BY hbp.payment_date DESC
");
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Check for success message
$success = isset($_GET['success']) && $_GET['success'] == '1';

// Set page title
$pageTitle = 'View Booking - ' . $booking['booking_number'];

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Booking Details</h1>
        <p><?php echo htmlspecialchars($booking['booking_number']); ?> • <?php echo htmlspecialchars($booking['event_name']); ?></p>
    </div>
    <div class="page-actions">
        <?php if (hasPermission('halls.edit')): ?>
        <a href="edit.php?id=<?php echo $booking['id']; ?>" class="btn btn-primary">
            <i class="icon-edit"></i> Edit Booking
        </a>
        <?php endif; ?>
        
        <?php if ($booking['booking_status'] == 'Pending'): ?>
        <a href="confirm.php?id=<?php echo $booking['id']; ?>" class="btn btn-success">
            <i class="icon-check"></i> Confirm Booking
        </a>
        <?php endif; ?>
        
        <?php if ($booking['balance_due'] > 0): ?>
        <a href="record-payment.php?id=<?php echo $booking['id']; ?>" class="btn btn-info">
            <i class="icon-credit-card"></i> Record Payment
        </a>
        <?php endif; ?>
        
        <?php if ($booking['booking_status'] != 'Cancelled' && $booking['booking_status'] != 'Completed'): ?>
        <a href="cancel.php?id=<?php echo $booking['id']; ?>" class="btn btn-warning">
            <i class="icon-x"></i> Cancel Booking
        </a>
        <?php endif; ?>
        
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Bookings
        </a>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success">
    <i class="icon-check"></i> Booking updated successfully!
</div>
<?php endif; ?>

<div class="row">
    <!-- Booking Information -->
    <div class="col-md-8">
        <!-- Basic Information -->
        <div class="card">
            <div class="card-header">
                <h3>Booking Information</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Booking Number:</strong></td>
                                <td><?php echo htmlspecialchars($booking['booking_number']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Event Name:</strong></td>
                                <td><?php echo htmlspecialchars($booking['event_name']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Event Type:</strong></td>
                                <td><?php echo htmlspecialchars($booking['event_type'] ?: 'Not specified'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Hall:</strong></td>
                                <td>
                                    <a href="../view.php?id=<?php echo $booking['hall_id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($booking['hall_name']); ?>
                                    </a>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($booking['hall_code']); ?></small>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Location:</strong></td>
                                <td><?php echo htmlspecialchars($booking['location'] ?: 'Not specified'); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Start Date:</strong></td>
                                <td><?php echo date('M d, Y', strtotime($booking['start_date'])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Start Time:</strong></td>
                                <td><?php echo date('g:i A', strtotime($booking['start_time'])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>End Date:</strong></td>
                                <td><?php echo date('M d, Y', strtotime($booking['end_date'])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>End Time:</strong></td>
                                <td><?php echo date('g:i A', strtotime($booking['end_time'])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Duration:</strong></td>
                                <td><?php echo round($booking['duration_hours'], 1); ?> hours</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php if (!empty($booking['attendee_count'])): ?>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Expected Attendees:</strong> <?php echo number_format($booking['attendee_count']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Hall Capacity:</strong> <?php echo number_format($booking['capacity']); ?> people</p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($booking['special_requirements'])): ?>
                <div class="mt-3">
                    <h5>Special Requirements</h5>
                    <p><?php echo nl2br(htmlspecialchars($booking['special_requirements'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Customer Information -->
        <?php if ($booking['customer_id']): ?>
        <div class="card">
            <div class="card-header">
                <h3>Customer Information</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Name:</strong></td>
                                <td><?php echo htmlspecialchars($booking['company_name'] ?: $booking['first_name'] . ' ' . $booking['last_name']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td>
                                    <a href="mailto:<?php echo htmlspecialchars($booking['customer_email']); ?>">
                                        <?php echo htmlspecialchars($booking['customer_email']); ?>
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Phone:</strong></td>
                                <td>
                                    <?php if ($booking['customer_phone']): ?>
                                        <a href="tel:<?php echo htmlspecialchars($booking['customer_phone']); ?>">
                                            <?php echo htmlspecialchars($booking['customer_phone']); ?>
                                        </a>
                                    <?php else: ?>
                                        Not provided
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Customer ID:</strong></td>
                                <td><?php echo $booking['customer_id']; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Booking Items -->
        <?php if (!empty($bookingItems)): ?>
        <div class="card">
            <div class="card-header">
                <h3>Additional Items</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Description</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookingItems as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($item['item_description'] ?: '-'); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td><?php echo formatCurrency($item['unit_price'], $booking['currency']); ?></td>
                                <td><?php echo formatCurrency($item['line_total'], $booking['currency']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Status & Payment -->
    <div class="col-md-4">
        <!-- Status -->
        <div class="card">
            <div class="card-header">
                <h3>Status</h3>
            </div>
            <div class="card-body">
                <div class="status-item">
                    <label>Booking Status:</label>
                    <span class="badge <?php echo getBookingStatusBadgeClass($booking['booking_status']); ?>">
                        <?php echo $booking['booking_status']; ?>
                    </span>
                </div>
                
                <div class="status-item">
                    <label>Payment Status:</label>
                    <span class="badge <?php echo getPaymentStatusBadgeClass($booking['payment_status']); ?>">
                        <?php echo $booking['payment_status']; ?>
                    </span>
                </div>
                
                <div class="status-item">
                    <label>Payment Type:</label>
                    <span><?php echo $booking['payment_type']; ?></span>
                </div>
                
                <div class="status-item">
                    <label>Booking Source:</label>
                    <span><?php echo $booking['booking_source']; ?></span>
                </div>
                
                <div class="status-item">
                    <label>Created:</label>
                    <span><?php echo date('M d, Y g:i A', strtotime($booking['created_at'])); ?></span>
                </div>
                
                <?php if ($booking['created_by_first']): ?>
                <div class="status-item">
                    <label>Created By:</label>
                    <span><?php echo htmlspecialchars($booking['created_by_first'] . ' ' . $booking['created_by_last']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($booking['cancelled_at']): ?>
                <div class="status-item">
                    <label>Cancelled:</label>
                    <span><?php echo date('M d, Y g:i A', strtotime($booking['cancelled_at'])); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($booking['cancellation_reason']): ?>
                <div class="status-item">
                    <label>Cancellation Reason:</label>
                    <span><?php echo htmlspecialchars($booking['cancellation_reason']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payment Summary -->
        <div class="card">
            <div class="card-header">
                <h3>Payment Summary</h3>
            </div>
            <div class="card-body">
                <div class="payment-item">
                    <label>Subtotal:</label>
                    <span><?php echo formatCurrency($booking['subtotal'], $booking['currency']); ?></span>
                </div>
                
                <div class="payment-item">
                    <label>Service Fee:</label>
                    <span><?php echo formatCurrency($booking['service_fee'], $booking['currency']); ?></span>
                </div>
                
                <div class="payment-item">
                    <label>Tax:</label>
                    <span><?php echo formatCurrency($booking['tax_amount'], $booking['currency']); ?></span>
                </div>
                
                <hr>
                
                <div class="payment-item total">
                    <label>Total Amount:</label>
                    <span><?php echo formatCurrency($booking['total_amount'], $booking['currency']); ?></span>
                </div>
                
                <div class="payment-item">
                    <label>Amount Paid:</label>
                    <span class="text-success"><?php echo formatCurrency($booking['amount_paid'], $booking['currency']); ?></span>
                </div>
                
                <div class="payment-item">
                    <label>Balance Due:</label>
                    <span class="<?php echo $booking['balance_due'] > 0 ? 'text-danger' : 'text-success'; ?>">
                        <?php echo formatCurrency($booking['balance_due'], $booking['currency']); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Payment History -->
        <?php if (!empty($payments)): ?>
        <div class="card">
            <div class="card-header">
                <h3>Payment History</h3>
            </div>
            <div class="card-body">
                <?php foreach ($payments as $payment): ?>
                <div class="payment-history-item">
                    <div class="payment-header">
                        <strong><?php echo formatCurrency($payment['amount'], $booking['currency']); ?></strong>
                        <span class="badge <?php echo getPaymentStatusBadgeClass($payment['status']); ?>">
                            <?php echo $payment['status']; ?>
                        </span>
                    </div>
                    <div class="payment-details">
                        <p class="mb-1">
                            <strong>Method:</strong> <?php echo htmlspecialchars($payment['payment_method']); ?>
                        </p>
                        <p class="mb-1">
                            <strong>Date:</strong> <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>
                        </p>
                        <?php if ($payment['payment_number']): ?>
                        <p class="mb-1">
                            <strong>Reference:</strong> <?php echo htmlspecialchars($payment['payment_number']); ?>
                        </p>
                        <?php endif; ?>
                        <?php if ($payment['is_deposit']): ?>
                        <p class="mb-1">
                            <span class="badge badge-info">Deposit</span>
                        </p>
                        <?php endif; ?>
                        <?php if ($payment['notes']): ?>
                        <p class="mb-0">
                            <strong>Notes:</strong> <?php echo htmlspecialchars($payment['notes']); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Helper functions for badge classes
function getBookingStatusBadgeClass($status) {
    switch ($status) {
        case 'Confirmed':
            return 'badge-success';
        case 'Pending':
            return 'badge-warning';
        case 'Completed':
            return 'badge-info';
        case 'Cancelled':
            return 'badge-danger';
        default:
            return 'badge-secondary';
    }
}

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
?>

<style>
.status-item, .payment-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    padding-bottom: 5px;
}

.status-item:not(:last-child), .payment-item:not(:last-child) {
    border-bottom: 1px solid #f0f0f0;
}

.payment-item.total {
    font-weight: bold;
    font-size: 1.1em;
    border-top: 1px solid #dee2e6;
    padding-top: 10px;
    margin-top: 10px;
}

.payment-history-item {
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 15px;
    background: #f8f9fa;
}

.payment-history-item:last-child {
    margin-bottom: 0;
}

.payment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.payment-details p {
    font-size: 13px;
    margin-bottom: 5px;
}

.payment-details p:last-child {
    margin-bottom: 0;
}

.badge-success { background-color: #28a745; color: white; }
.badge-warning { background-color: #ffc107; color: #333; }
.badge-danger { background-color: #dc3545; color: white; }
.badge-info { background-color: #17a2b8; color: white; }
.badge-secondary { background-color: #6c757d; color: white; }

.text-success { color: #28a745 !important; }
.text-danger { color: #dc3545 !important; }
</style>

<?php include '../../includes/footer.php'; ?>
