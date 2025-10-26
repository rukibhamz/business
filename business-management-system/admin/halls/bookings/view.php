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
<<<<<<< HEAD
    SELECT hb.*, h.hall_name, h.hall_code, h.location, h.capacity,
           c.first_name, c.last_name, c.company_name, c.email as customer_email, c.phone as customer_phone,
           u.first_name as created_by_first, u.last_name as created_by_last
=======
    SELECT hb.*, h.hall_name, h.hall_code, h.location, h.address, h.capacity, h.currency,
           c.first_name, c.last_name, c.company_name, c.email as customer_email, c.phone as customer_phone,
           u.first_name as created_by_first, u.last_name as created_by_last
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
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
<<<<<<< HEAD
    SELECT * FROM " . DB_PREFIX . "hall_booking_payments 
    WHERE booking_id = ? 
    ORDER BY payment_date DESC
=======
    SELECT hbp.*, p.payment_number as accounting_payment_number
    FROM " . DB_PREFIX . "hall_booking_payments hbp
    LEFT JOIN " . DB_PREFIX . "payments p ON hbp.payment_id = p.id
    WHERE hbp.booking_id = ? 
    ORDER BY hbp.payment_date DESC
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
");
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Process actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'confirm':
                if (updateBookingStatus($bookingId, 'Confirmed')) {
                    header('Location: view.php?id=' . $bookingId . '&success=confirmed');
                    exit;
                }
                break;
                
            case 'cancel':
                $reason = trim($_POST['cancellation_reason'] ?? '');
                if (empty($reason)) {
                    $errors[] = 'Cancellation reason is required.';
                } else {
                    if (cancelBooking($bookingId, $reason)) {
                        header('Location: view.php?id=' . $bookingId . '&success=cancelled');
                        exit;
                    }
                }
                break;
                
            case 'complete':
                if (completeBooking($bookingId)) {
                    header('Location: view.php?id=' . $bookingId . '&success=completed');
                    exit;
                }
                break;
        }
    }
}

// Set page title
$pageTitle = 'View Booking - ' . $booking['booking_number'];

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Booking Details</h1>
<<<<<<< HEAD
        <p>Booking #<?php echo htmlspecialchars($booking['booking_number']); ?></p>
=======
        <p><?php echo htmlspecialchars($booking['booking_number']); ?> • <?php echo htmlspecialchars($booking['event_name']); ?></p>
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
    </div>
    <div class="page-actions">
        <?php if (hasPermission('halls.edit')): ?>
        <a href="edit.php?id=<?php echo $booking['id']; ?>" class="btn btn-primary">
            <i class="icon-edit"></i> Edit Booking
        </a>
<<<<<<< HEAD
=======
        <?php endif; ?>
        
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
        <?php if ($booking['booking_status'] == 'Pending'): ?>
<<<<<<< HEAD
        <form method="POST" style="display: inline;">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="confirm">
            <button type="submit" class="btn btn-success">
                <i class="icon-check"></i> Confirm Booking
            </button>
        </form>
=======
        <a href="confirm.php?id=<?php echo $booking['id']; ?>" class="btn btn-success">
            <i class="icon-check"></i> Confirm Booking
        </a>
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
        <?php endif; ?>
        
<<<<<<< HEAD
        <?php if ($booking['booking_status'] == 'Confirmed'): ?>
        <form method="POST" style="display: inline;">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="complete">
            <button type="submit" class="btn btn-primary">
                <i class="icon-check-circle"></i> Mark Complete
            </button>
        </form>
=======
        <?php if ($booking['balance_due'] > 0): ?>
        <a href="record-payment.php?id=<?php echo $booking['id']; ?>" class="btn btn-info">
            <i class="icon-credit-card"></i> Record Payment
        </a>
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
        <?php endif; ?>
        
        <?php if ($booking['booking_status'] != 'Cancelled' && $booking['booking_status'] != 'Completed'): ?>
<<<<<<< HEAD
        <button type="button" class="btn btn-warning" onclick="showCancelModal()">
            <i class="icon-times"></i> Cancel Booking
        </button>
=======
        <a href="cancel.php?id=<?php echo $booking['id']; ?>" class="btn btn-warning">
            <i class="icon-x"></i> Cancel Booking
        </a>
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
        <?php endif; ?>
<<<<<<< HEAD
=======
        
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Bookings
        </a>
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
    </div>
</div>

<<<<<<< HEAD
<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success">
    <?php
    switch ($_GET['success']) {
        case 'confirmed':
            echo 'Booking has been confirmed successfully.';
            break;
        case 'cancelled':
            echo 'Booking has been cancelled successfully.';
            break;
        case 'completed':
            echo 'Booking has been marked as completed.';
            break;
    }
    ?>
</div>
<?php endif; ?>

=======
<?php if ($success): ?>
<div class="alert alert-success">
    <i class="icon-check"></i> Booking updated successfully!
</div>
<?php endif; ?>

>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
<div class="row">
    <!-- Booking Information -->
<<<<<<< HEAD
    <div class="col-md-8">
        <div class="card">
=======
    <div class="col-md-8">
        <!-- Basic Information -->
        <div class="card">
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
            <div class="card-header">
                <h3>Booking Information</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
<<<<<<< HEAD
                        <div class="info-group">
                            <label>Booking Number:</label>
                            <span><?php echo htmlspecialchars($booking['booking_number']); ?></span>
                        </div>
                        <div class="info-group">
                            <label>Hall:</label>
                            <span><?php echo htmlspecialchars($booking['hall_name'] . ' (' . $booking['hall_code'] . ')'); ?></span>
                        </div>
                        <div class="info-group">
                            <label>Event Name:</label>
                            <span><?php echo htmlspecialchars($booking['event_name'] ?: 'Not specified'); ?></span>
                        </div>
                        <div class="info-group">
                            <label>Event Type:</label>
                            <span><?php echo htmlspecialchars($booking['event_type'] ?: 'Not specified'); ?></span>
                        </div>
=======
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
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
                    </div>
                    <div class="col-md-6">
<<<<<<< HEAD
                        <div class="info-group">
                            <label>Start Date & Time:</label>
                            <span><?php echo date('M d, Y g:i A', strtotime($booking['start_date'] . ' ' . $booking['start_time'])); ?></span>
                        </div>
                        <div class="info-group">
                            <label>End Date & Time:</label>
                            <span><?php echo date('M d, Y g:i A', strtotime($booking['end_date'] . ' ' . $booking['end_time'])); ?></span>
                        </div>
                        <div class="info-group">
                            <label>Duration:</label>
                            <span><?php echo round($booking['duration_hours'], 1); ?> hours</span>
                        </div>
                        <div class="info-group">
                            <label>Attendees:</label>
                            <span><?php echo $booking['attendee_count'] ? number_format($booking['attendee_count']) : 'Not specified'; ?></span>
                        </div>
                    </div>
=======
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
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
                </div>
<<<<<<< HEAD
                
                <?php if ($booking['special_requirements']): ?>
                <div class="info-group">
                    <label>Special Requirements:</label>
                    <p><?php echo nl2br(htmlspecialchars($booking['special_requirements'])); ?></p>
                </div>
                <?php endif; ?>
=======
                <?php endif; ?>
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
            </div>
        </div>

        <!-- Customer Information -->
<<<<<<< HEAD
        <div class="card">
=======
        <?php if ($booking['customer_id']): ?>
        <div class="card">
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
            <div class="card-header">
                <h3>Customer Information</h3>
            </div>
            <div class="card-body">
                <?php if ($booking['customer_id']): ?>
                <div class="row">
                    <div class="col-md-6">
<<<<<<< HEAD
                        <div class="info-group">
                            <label>Name:</label>
                            <span><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></span>
                        </div>
                        <div class="info-group">
                            <label>Company:</label>
                            <span><?php echo htmlspecialchars($booking['company_name'] ?: 'Not specified'); ?></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <label>Email:</label>
                            <span><?php echo htmlspecialchars($booking['customer_email']); ?></span>
                        </div>
                        <div class="info-group">
                            <label>Phone:</label>
                            <span><?php echo htmlspecialchars($booking['customer_phone']); ?></span>
                        </div>
=======
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
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
                    </div>
                </div>
                <?php else: ?>
                <p class="text-muted">No customer information available.</p>
                <?php endif; ?>
            </div>
        </div>
<<<<<<< HEAD

        <!-- Booking Items -->
        <?php if (!empty($bookingItems)): ?>
        <div class="card">
=======
        <?php endif; ?>

        <!-- Booking Items -->
        <?php if (!empty($bookingItems)): ?>
        <div class="card">
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
            <div class="card-header">
                <h3>Additional Items</h3>
            </div>
            <div class="card-body">
<<<<<<< HEAD
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
                                <td><?php echo formatCurrency($item['unit_price']); ?></td>
                                <td><?php echo formatCurrency($item['line_total']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
=======
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
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
                </div>
<<<<<<< HEAD
=======
                
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
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
            </div>
        </div>
<<<<<<< HEAD
        <?php endif; ?>
    </div>

    <!-- Financial Summary -->
    <div class="col-md-4">
        <div class="card">
=======

        <!-- Payment Summary -->
        <div class="card">
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
            <div class="card-header">
<<<<<<< HEAD
                <h3>Financial Summary</h3>
=======
                <h3>Payment Summary</h3>
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
            </div>
            <div class="card-body">
<<<<<<< HEAD
                <div class="financial-summary">
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span><?php echo formatCurrency($booking['subtotal']); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Service Fee:</span>
                        <span><?php echo formatCurrency($booking['service_fee']); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Tax:</span>
                        <span><?php echo formatCurrency($booking['tax_amount']); ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total Amount:</span>
                        <span><?php echo formatCurrency($booking['total_amount']); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Amount Paid:</span>
                        <span class="text-success"><?php echo formatCurrency($booking['amount_paid']); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Balance Due:</span>
                        <span class="<?php echo $booking['balance_due'] > 0 ? 'text-danger' : 'text-success'; ?>">
                            <?php echo formatCurrency($booking['balance_due']); ?>
                        </span>
                    </div>
=======
                <div class="payment-item">
                    <label>Subtotal:</label>
                    <span><?php echo formatCurrency($booking['subtotal'], $booking['currency']); ?></span>
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
                </div>
                
<<<<<<< HEAD
                <div class="status-badges mt-3">
                    <div class="status-badge">
                        <label>Booking Status:</label>
                        <span class="badge <?php echo getHallBookingStatusBadgeClass($booking['booking_status']); ?>">
                            <?php echo $booking['booking_status']; ?>
                        </span>
                    </div>
                    <div class="status-badge">
                        <label>Payment Status:</label>
                        <span class="badge <?php echo getHallPaymentStatusBadgeClass($booking['payment_status']); ?>">
                            <?php echo $booking['payment_status']; ?>
                        </span>
                    </div>
=======
                <div class="payment-item">
                    <label>Service Fee:</label>
                    <span><?php echo formatCurrency($booking['service_fee'], $booking['currency']); ?></span>
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
                </div>
<<<<<<< HEAD
                
                <?php if ($booking['balance_due'] > 0): ?>
                <div class="mt-3">
                    <a href="record-payment.php?id=<?php echo $bookingId; ?>" class="btn btn-primary btn-block">
                        <i class="icon-credit-card"></i> Record Payment
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

=======
                
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

>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
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
<<<<<<< HEAD
                        <span class="payment-amount"><?php echo formatCurrency($payment['amount']); ?></span>
                        <span class="payment-date"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></span>
                    </div>
                    <div class="payment-details">
                        <span class="payment-method"><?php echo htmlspecialchars($payment['payment_method']); ?></span>
                        <span class="payment-status badge <?php echo $payment['status'] == 'Completed' ? 'badge-success' : 'badge-warning'; ?>">
=======
                        <strong><?php echo formatCurrency($payment['amount'], $booking['currency']); ?></strong>
                        <span class="badge <?php echo getPaymentStatusBadgeClass($payment['status']); ?>">
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
                            <?php echo $payment['status']; ?>
                        </span>
                    </div>
<<<<<<< HEAD
                    <?php if ($payment['notes']): ?>
                    <div class="payment-notes">
                        <small class="text-muted"><?php echo htmlspecialchars($payment['notes']); ?></small>
                    </div>
                    <?php endif; ?>
=======
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
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
<<<<<<< HEAD

        <!-- Booking Details -->
        <div class="card">
            <div class="card-header">
                <h3>Booking Details</h3>
            </div>
            <div class="card-body">
                <div class="info-group">
                    <label>Created:</label>
                    <span><?php echo date('M d, Y g:i A', strtotime($booking['created_at'])); ?></span>
                </div>
                <div class="info-group">
                    <label>Created By:</label>
                    <span><?php echo htmlspecialchars($booking['created_by_first'] . ' ' . $booking['created_by_last']); ?></span>
                </div>
                <div class="info-group">
                    <label>Booking Source:</label>
                    <span><?php echo htmlspecialchars($booking['booking_source']); ?></span>
                </div>
                <?php if ($booking['cancelled_at']): ?>
                <div class="info-group">
                    <label>Cancelled:</label>
                    <span><?php echo date('M d, Y g:i A', strtotime($booking['cancelled_at'])); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($booking['cancellation_reason']): ?>
                <div class="info-group">
                    <label>Cancellation Reason:</label>
                    <p><?php echo htmlspecialchars($booking['cancellation_reason']); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
=======
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
    </div>
</div>

<!-- Cancel Booking Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Booking</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="cancel">
                    
                    <div class="form-group">
                        <label for="cancellation_reason">Cancellation Reason <span class="text-danger">*</span></label>
                        <textarea id="cancellation_reason" name="cancellation_reason" class="form-control" rows="4" 
                                  placeholder="Please provide a reason for cancelling this booking..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Cancel Booking</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showCancelModal() {
    $('#cancelModal').modal('show');
}
</script>

<style>
.info-group {
    margin-bottom: 15px;
}

.info-group label {
    font-weight: 600;
    color: #666;
    display: block;
    margin-bottom: 5px;
}
?>

<<<<<<< HEAD
.financial-summary {
    border-top: 1px solid #dee2e6;
    padding-top: 15px;
}

.summary-row {
=======
<style>
.status-item, .payment-item {
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
    display: flex;
    justify-content: space-between;
<<<<<<< HEAD
    margin-bottom: 10px;
    padding: 5px 0;
=======
    margin-bottom: 10px;
    padding-bottom: 5px;
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
}

<<<<<<< HEAD
.summary-row.total {
    border-top: 1px solid #dee2e6;
    font-weight: 600;
    font-size: 1.1em;
    margin-top: 10px;
    padding-top: 10px;
=======
.status-item:not(:last-child), .payment-item:not(:last-child) {
    border-bottom: 1px solid #f0f0f0;
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
}

<<<<<<< HEAD
.status-badges {
    border-top: 1px solid #dee2e6;
    padding-top: 15px;
=======
.payment-item.total {
    font-weight: bold;
    font-size: 1.1em;
    border-top: 1px solid #dee2e6;
    padding-top: 10px;
    margin-top: 10px;
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
}

<<<<<<< HEAD
.status-badge {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.status-badge label {
    font-weight: 500;
    margin: 0;
}

.payment-item {
    border-bottom: 1px solid #f0f0f0;
    padding: 10px 0;
}

.payment-item:last-child {
    border-bottom: none;
=======
.payment-history-item {
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 15px;
    background: #f8f9fa;
}

.payment-history-item:last-child {
    margin-bottom: 0;
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
}

.payment-header {
    display: flex;
    justify-content: space-between;
<<<<<<< HEAD
    align-items: center;
    margin-bottom: 5px;
=======
    align-items: center;
    margin-bottom: 10px;
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
}

<<<<<<< HEAD
.payment-amount {
    font-weight: 600;
    font-size: 1.1em;
}

.payment-date {
    color: #666;
    font-size: 0.9em;
}

.payment-details {
    display: flex;
    justify-content: space-between;
    align-items: center;
=======
.payment-details p {
    font-size: 13px;
    margin-bottom: 5px;
>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
}

<<<<<<< HEAD
.payment-method {
    color: #666;
}

.payment-notes {
    margin-top: 5px;
}

=======
.payment-details p:last-child {
    margin-bottom: 0;
}

>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea
.badge-success { background-color: #28a745; color: white; }
.badge-warning { background-color: #ffc107; color: #333; }
.badge-danger { background-color: #dc3545; color: white; }
.badge-info { background-color: #17a2b8; color: white; }
.badge-secondary { background-color: #6c757d; color: white; }

.text-success { color: #28a745 !important; }
.text-danger { color: #dc3545 !important; }
</style>

<<<<<<< HEAD
<?php include '../../includes/footer.php'; ?>
=======
<?php include '../../includes/footer.php'; ?>

>>>>>>> 92deb744202d19607783b73a86fcda1197b34cea