<?php
/**
 * Business Management System - Hall Booking Details
 * Phase 4: Hall Booking System Module
 */

// Define system constant
define('BMS_SYSTEM', true);

// Start session
session_start();

// Include required files
require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/auth.php';
require_once '../../../../includes/csrf.php';
require_once '../../../../includes/hall-functions.php';

// Check authentication and permissions
requireLogin();
requirePermission('halls.bookings');

// Get booking ID
$bookingId = (int)($_GET['id'] ?? 0);

if (!$bookingId) {
    header('Location: index.php');
    exit;
}

// Get database connection
$conn = getDB();

// Get booking details
$stmt = $conn->prepare("
    SELECT hb.*, h.hall_name, h.hall_code, h.location, h.address, h.capacity,
           c.first_name, c.last_name, c.company_name, c.customer_type, 
           c.email as customer_email, c.phone as customer_phone, c.address as customer_address,
           u.first_name as created_by_first, u.last_name as created_by_last,
           i.invoice_number, i.invoice_date, i.due_date
    FROM " . DB_PREFIX . "hall_bookings hb
    JOIN " . DB_PREFIX . "halls h ON hb.hall_id = h.id
    LEFT JOIN " . DB_PREFIX . "customers c ON hb.customer_id = c.id
    LEFT JOIN " . DB_PREFIX . "users u ON hb.created_by = u.id
    LEFT JOIN " . DB_PREFIX . "invoices i ON hb.invoice_id = i.id
    WHERE hb.id = ?
");
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header('Location: index.php');
    exit;
}

// Get booking items (additional services)
$stmt = $conn->prepare("
    SELECT * FROM " . DB_PREFIX . "hall_booking_items 
    WHERE booking_id = ?
    ORDER BY created_at
");
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$bookingItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get payment history
$stmt = $conn->prepare("
    SELECT hbp.*, p.payment_number, p.payment_date, p.payment_method, p.status as payment_status
    FROM " . DB_PREFIX . "hall_booking_payments hbp
    LEFT JOIN " . DB_PREFIX . "payments p ON hbp.payment_id = p.id
    WHERE hbp.booking_id = ?
    ORDER BY hbp.payment_date DESC
");
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Set page title
$pageTitle = 'Booking Details - ' . $booking['booking_number'];

include '../../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Booking Details</h1>
        <p><?php echo htmlspecialchars($booking['booking_number']); ?></p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Bookings
        </a>
        
        <?php if ($booking['booking_status'] == 'Pending'): ?>
        <a href="confirm.php?id=<?php echo $bookingId; ?>" class="btn btn-success">
            <i class="icon-check"></i> Confirm Booking
        </a>
        <?php endif; ?>
        
        <?php if ($booking['balance_due'] > 0): ?>
        <a href="record-payment.php?id=<?php echo $bookingId; ?>" class="btn btn-primary">
            <i class="icon-credit-card"></i> Record Payment
        </a>
        <?php endif; ?>
        
        <?php if ($booking['booking_status'] != 'Cancelled' && $booking['booking_status'] != 'Completed'): ?>
        <a href="cancel.php?id=<?php echo $bookingId; ?>" class="btn btn-warning">
            <i class="icon-x"></i> Cancel Booking
        </a>
        <?php endif; ?>
        
        <a href="edit.php?id=<?php echo $bookingId; ?>" class="btn btn-outline-primary">
            <i class="icon-edit"></i> Edit Booking
        </a>
    </div>
</div>

<div class="row">
    <!-- Booking Information -->
    <div class="col-lg-8">
        <!-- Booking Overview -->
        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="icon-calendar"></i> Booking Overview</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-group">
                            <label>Booking Number:</label>
                            <span class="info-value"><?php echo htmlspecialchars($booking['booking_number']); ?></span>
                        </div>
                        <div class="info-group">
                            <label>Event Name:</label>
                            <span class="info-value"><?php echo htmlspecialchars($booking['event_name'] ?: 'Event'); ?></span>
                        </div>
                        <div class="info-group">
                            <label>Event Type:</label>
                            <span class="info-value"><?php echo htmlspecialchars($booking['event_type'] ?: '-'); ?></span>
                        </div>
                        <div class="info-group">
                            <label>Booking Date:</label>
                            <span class="info-value"><?php echo date('M d, Y g:i A', strtotime($booking['booking_date'])); ?></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <label>Booking Status:</label>
                            <span class="badge <?php echo getHallBookingStatusBadgeClass($booking['booking_status']); ?>">
                                <?php echo $booking['booking_status']; ?>
                            </span>
                        </div>
                        <div class="info-group">
                            <label>Payment Status:</label>
                            <span class="badge <?php echo getHallPaymentStatusBadgeClass($booking['payment_status']); ?>">
                                <?php echo $booking['payment_status']; ?>
                            </span>
                        </div>
                        <div class="info-group">
                            <label>Payment Type:</label>
                            <span class="info-value"><?php echo $booking['payment_type']; ?></span>
                        </div>
                        <div class="info-group">
                            <label>Booking Source:</label>
                            <span class="info-value"><?php echo $booking['booking_source']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Hall Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="icon-building"></i> Hall Information</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-group">
                            <label>Hall Name:</label>
                            <span class="info-value">
                                <a href="../view.php?id=<?php echo $booking['hall_id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($booking['hall_name']); ?>
                                </a>
                            </span>
                        </div>
                        <div class="info-group">
                            <label>Hall Code:</label>
                            <span class="info-value"><?php echo htmlspecialchars($booking['hall_code']); ?></span>
                        </div>
                        <div class="info-group">
                            <label>Location:</label>
                            <span class="info-value"><?php echo htmlspecialchars($booking['location'] ?: '-'); ?></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <label>Capacity:</label>
                            <span class="info-value"><?php echo number_format($booking['capacity']); ?> people</span>
                        </div>
                        <div class="info-group">
                            <label>Expected Attendees:</label>
                            <span class="info-value"><?php echo number_format($booking['attendee_count'] ?: 0); ?> people</span>
                        </div>
                        <div class="info-group">
                            <label>Duration:</label>
                            <span class="info-value"><?php echo round($booking['duration_hours'], 1); ?> hours</span>
                        </div>
                    </div>
                </div>
                
                <?php if ($booking['address']): ?>
                <div class="info-group mt-3">
                    <label>Hall Address:</label>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($booking['address'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Event Schedule -->
        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="icon-clock"></i> Event Schedule</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-group">
                            <label>Start Date & Time:</label>
                            <span class="info-value">
                                <?php echo date('M d, Y', strtotime($booking['start_date'])); ?> at 
                                <?php echo date('g:i A', strtotime($booking['start_time'])); ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <label>End Date & Time:</label>
                            <span class="info-value">
                                <?php echo date('M d, Y', strtotime($booking['end_date'])); ?> at 
                                <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <?php if ($booking['checked_in_at']): ?>
                <div class="info-group mt-3">
                    <label>Checked In:</label>
                    <span class="info-value text-success">
                        <?php echo date('M d, Y g:i A', strtotime($booking['checked_in_at'])); ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if ($booking['checked_out_at']): ?>
                <div class="info-group">
                    <label>Checked Out:</label>
                    <span class="info-value text-success">
                        <?php echo date('M d, Y g:i A', strtotime($booking['checked_out_at'])); ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Customer Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="icon-user"></i> Customer Information</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-group">
                            <label>Customer Name:</label>
                            <span class="info-value">
                                <?php echo htmlspecialchars($booking['company_name'] ?: $booking['first_name'] . ' ' . $booking['last_name']); ?>
                            </span>
                        </div>
                        <div class="info-group">
                            <label>Customer Type:</label>
                            <span class="info-value"><?php echo htmlspecialchars($booking['customer_type'] ?: 'Individual'); ?></span>
                        </div>
                        <div class="info-group">
                            <label>Email:</label>
                            <span class="info-value">
                                <a href="mailto:<?php echo htmlspecialchars($booking['customer_email']); ?>">
                                    <?php echo htmlspecialchars($booking['customer_email']); ?>
                                </a>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <label>Phone:</label>
                            <span class="info-value">
                                <a href="tel:<?php echo htmlspecialchars($booking['customer_phone']); ?>">
                                    <?php echo htmlspecialchars($booking['customer_phone']); ?>
                                </a>
                            </span>
                        </div>
                        <?php if ($booking['customer_address']): ?>
                        <div class="info-group">
                            <label>Address:</label>
                            <div class="info-value"><?php echo nl2br(htmlspecialchars($booking['customer_address'])); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Special Requirements -->
        <?php if ($booking['special_requirements']): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="icon-clipboard"></i> Special Requirements</h3>
            </div>
            <div class="card-body">
                <div class="info-value"><?php echo nl2br(htmlspecialchars($booking['special_requirements'])); ?></div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Cancellation Information -->
        <?php if ($booking['cancelled_at']): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="icon-x-circle"></i> Cancellation Information</h3>
            </div>
            <div class="card-body">
                <div class="info-group">
                    <label>Cancelled On:</label>
                    <span class="info-value"><?php echo date('M d, Y g:i A', strtotime($booking['cancelled_at'])); ?></span>
                </div>
                <?php if ($booking['cancellation_reason']): ?>
                <div class="info-group">
                    <label>Cancellation Reason:</label>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($booking['cancellation_reason'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Financial Summary -->
        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="icon-dollar-sign"></i> Financial Summary</h3>
            </div>
            <div class="card-body">
                <div class="financial-summary">
                    <div class="financial-item">
                        <label>Subtotal:</label>
                        <span><?php echo formatCurrency($booking['subtotal']); ?></span>
                    </div>
                    <?php if ($booking['service_fee'] > 0): ?>
                    <div class="financial-item">
                        <label>Service Fee:</label>
                        <span><?php echo formatCurrency($booking['service_fee']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($booking['tax_amount'] > 0): ?>
                    <div class="financial-item">
                        <label>Tax:</label>
                        <span><?php echo formatCurrency($booking['tax_amount']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="financial-item total">
                        <label>Total Amount:</label>
                        <span><?php echo formatCurrency($booking['total_amount']); ?></span>
                    </div>
                    <div class="financial-item">
                        <label>Amount Paid:</label>
                        <span class="text-success"><?php echo formatCurrency($booking['amount_paid']); ?></span>
                    </div>
                    <div class="financial-item">
                        <label>Balance Due:</label>
                        <span class="<?php echo $booking['balance_due'] > 0 ? 'text-danger' : 'text-success'; ?>">
                            <?php echo formatCurrency($booking['balance_due']); ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($booking['invoice_number']): ?>
                <div class="invoice-info mt-3">
                    <h4>Invoice Information</h4>
                    <div class="info-group">
                        <label>Invoice Number:</label>
                        <span class="info-value"><?php echo htmlspecialchars($booking['invoice_number']); ?></span>
                    </div>
                    <div class="info-group">
                        <label>Invoice Date:</label>
                        <span class="info-value"><?php echo date('M d, Y', strtotime($booking['invoice_date'])); ?></span>
                    </div>
                    <div class="info-group">
                        <label>Due Date:</label>
                        <span class="info-value"><?php echo date('M d, Y', strtotime($booking['due_date'])); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Additional Services -->
        <?php if (!empty($bookingItems)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="icon-list"></i> Additional Services</h3>
            </div>
            <div class="card-body">
                <?php foreach ($bookingItems as $item): ?>
                <div class="service-item">
                    <div class="service-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                    <div class="service-details">
                        <span>Qty: <?php echo $item['quantity']; ?></span>
                        <span>Price: <?php echo formatCurrency($item['unit_price']); ?></span>
                        <span>Total: <?php echo formatCurrency($item['line_total']); ?></span>
                    </div>
                    <?php if ($item['item_description']): ?>
                    <div class="service-description"><?php echo htmlspecialchars($item['item_description']); ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Payment History -->
        <?php if (!empty($payments)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="icon-credit-card"></i> Payment History</h3>
            </div>
            <div class="card-body">
                <?php foreach ($payments as $payment): ?>
                <div class="payment-item">
                    <div class="payment-header">
                        <span class="payment-date"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></span>
                        <span class="payment-amount"><?php echo formatCurrency($payment['amount']); ?></span>
                    </div>
                    <div class="payment-details">
                        <span>Method: <?php echo htmlspecialchars($payment['payment_method']); ?></span>
                        <span class="badge <?php echo $payment['status'] == 'Completed' ? 'badge-success' : 'badge-warning'; ?>">
                            <?php echo $payment['status']; ?>
                        </span>
                    </div>
                    <?php if ($payment['notes']): ?>
                    <div class="payment-notes"><?php echo htmlspecialchars($payment['notes']); ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Booking Actions -->
        <div class="card">
            <div class="card-header">
                <h3><i class="icon-settings"></i> Quick Actions</h3>
            </div>
            <div class="card-body">
                <div class="action-buttons">
                    <?php if ($booking['booking_status'] == 'Pending'): ?>
                    <a href="confirm.php?id=<?php echo $bookingId; ?>" class="btn btn-success btn-block mb-2">
                        <i class="icon-check"></i> Confirm Booking
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($booking['balance_due'] > 0): ?>
                    <a href="record-payment.php?id=<?php echo $bookingId; ?>" class="btn btn-primary btn-block mb-2">
                        <i class="icon-credit-card"></i> Record Payment
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($booking['booking_status'] == 'Confirmed'): ?>
                    <a href="check-in.php?id=<?php echo $bookingId; ?>" class="btn btn-info btn-block mb-2">
                        <i class="icon-log-in"></i> Check In
                    </a>
                    <?php endif; ?>
                    
                    <a href="edit.php?id=<?php echo $bookingId; ?>" class="btn btn-outline-primary btn-block mb-2">
                        <i class="icon-edit"></i> Edit Booking
                    </a>
                    
                    <?php if ($booking['booking_status'] != 'Cancelled' && $booking['booking_status'] != 'Completed'): ?>
                    <a href="cancel.php?id=<?php echo $bookingId; ?>" class="btn btn-outline-warning btn-block mb-2">
                        <i class="icon-x"></i> Cancel Booking
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($booking['invoice_number']): ?>
                    <a href="../../accounting/invoices/view.php?id=<?php echo $booking['invoice_id']; ?>" class="btn btn-outline-secondary btn-block mb-2">
                        <i class="icon-file-text"></i> View Invoice
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.info-group {
    margin-bottom: 1rem;
}

.info-group label {
    font-weight: 600;
    color: #495057;
    display: block;
    margin-bottom: 0.25rem;
}

.info-value {
    color: #6c757d;
}

.financial-summary {
    border-top: 1px solid #e9ecef;
    padding-top: 1rem;
}

.financial-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    padding: 0.25rem 0;
}

.financial-item.total {
    border-top: 1px solid #e9ecef;
    padding-top: 0.5rem;
    margin-top: 0.5rem;
    font-weight: 600;
    font-size: 1.1rem;
}

.service-item {
    border-bottom: 1px solid #e9ecef;
    padding-bottom: 1rem;
    margin-bottom: 1rem;
}

.service-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.service-name {
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.service-details {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
    color: #6c757d;
    margin-bottom: 0.5rem;
}

.service-description {
    font-size: 0.9rem;
    color: #6c757d;
    font-style: italic;
}

.payment-item {
    border-bottom: 1px solid #e9ecef;
    padding-bottom: 1rem;
    margin-bottom: 1rem;
}

.payment-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.payment-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
}

.payment-details {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
    color: #6c757d;
}

.payment-notes {
    font-size: 0.9rem;
    color: #6c757d;
    font-style: italic;
    margin-top: 0.5rem;
}

.action-buttons .btn {
    margin-bottom: 0.5rem;
}

.action-buttons .btn:last-child {
    margin-bottom: 0;
}

.badge-success { background-color: #28a745; color: white; }
.badge-secondary { background-color: #6c757d; color: white; }
.badge-danger { background-color: #dc3545; color: white; }
.badge-info { background-color: #17a2b8; color: white; }
.badge-warning { background-color: #ffc107; color: #333; }
.badge-primary { background-color: #007bff; color: white; }
</style>

<?php include '../../../includes/footer.php'; ?>

