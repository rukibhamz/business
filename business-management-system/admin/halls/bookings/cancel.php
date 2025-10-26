<?php
/**
 * Business Management System - Cancel Hall Booking
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
    SELECT hb.*, h.hall_name, h.hall_code,
           c.first_name, c.last_name, c.company_name, c.email
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

// Check if booking can be cancelled
if ($booking['booking_status'] == 'Cancelled') {
    header('Location: view.php?id=' . $bookingId . '&error=already_cancelled');
    exit;
}

if ($booking['booking_status'] == 'Completed') {
    header('Location: view.php?id=' . $bookingId . '&error=cannot_cancel_completed');
    exit;
}

// Initialize variables
$errors = [];
$success = false;
$cancellationData = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        // Sanitize and validate input
        $cancellationData = [
            'reason' => trim($_POST['cancellation_reason'] ?? ''),
            'refund_amount' => (float)($_POST['refund_amount'] ?? 0)
        ];

        // Validation
        if (empty($cancellationData['reason'])) {
            $errors[] = 'Cancellation reason is required.';
        }

        if ($cancellationData['refund_amount'] < 0) {
            $errors[] = 'Refund amount cannot be negative.';
        }

        if ($cancellationData['refund_amount'] > $booking['amount_paid']) {
            $errors[] = 'Refund amount cannot exceed amount paid (' . formatCurrency($booking['amount_paid']) . ').';
        }

        // Cancel booking if no errors
        if (empty($errors)) {
            try {
                if (cancelBooking($bookingId, $cancellationData['reason'], $cancellationData['refund_amount'])) {
                    $success = true;
                    // Redirect to booking view page
                    header("Location: view.php?id={$bookingId}&success=cancelled");
                    exit;
                } else {
                    $errors[] = 'Failed to cancel booking. Please try again.';
                }
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Set page title
$pageTitle = 'Cancel Booking - ' . $booking['booking_number'];

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Cancel Booking</h1>
        <p>Booking #<?php echo htmlspecialchars($booking['booking_number']); ?></p>
    </div>
    <div class="page-actions">
        <a href="view.php?id=<?php echo $bookingId; ?>" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Booking
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <h4>Please correct the following errors:</h4>
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
        <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>Booking Cancellation</h3>
            </div>
            <div class="card-body">
                <div class="cancellation-warning">
                    <div class="alert alert-danger">
                        <h4><i class="icon-warning"></i> Cancel Booking</h4>
                        <p>You are about to cancel this booking. This action cannot be undone and will free up the hall for other bookings.</p>
                    </div>
                </div>

                <div class="booking-details">
                    <h4>Booking Details</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-group">
                                <label>Booking Number:</label>
                                <span><?php echo htmlspecialchars($booking['booking_number']); ?></span>
                            </div>
                            <div class="info-group">
                                <label>Hall:</label>
                                <span><?php echo htmlspecialchars($booking['hall_name'] . ' (' . $booking['hall_code'] . ')'); ?></span>
                            </div>
                            <div class="info-group">
                                <label>Event:</label>
                                <span><?php echo htmlspecialchars($booking['event_name'] ?: 'Not specified'); ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
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
                        </div>
                    </div>
                </div>

                <div class="customer-details">
                    <h4>Customer Information</h4>
                    <?php if ($booking['customer_id']): ?>
                    <div class="row">
                        <div class="col-md-6">
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
                                <span><?php echo htmlspecialchars($booking['email']); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">No customer information available.</p>
                    <?php endif; ?>
                </div>

                <div class="financial-summary">
                    <h4>Financial Summary</h4>
                    <div class="summary-row">
                        <span>Total Amount:</span>
                        <span><?php echo formatCurrency($booking['total_amount']); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Amount Paid:</span>
                        <span class="text-success"><?php echo formatCurrency($booking['amount_paid']); ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Balance Due:</span>
                        <span class="<?php echo $booking['balance_due'] > 0 ? 'text-danger' : 'text-success'; ?>">
                            <?php echo formatCurrency($booking['balance_due']); ?>
                        </span>
                    </div>
                </div>

                <form method="POST" class="cancellation-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-group">
                        <label for="cancellation_reason">Cancellation Reason <span class="text-danger">*</span></label>
                        <textarea id="cancellation_reason" name="cancellation_reason" class="form-control" rows="4" 
                                  placeholder="Please provide a detailed reason for cancelling this booking..." required><?php echo htmlspecialchars($cancellationData['reason'] ?? ''); ?></textarea>
                        <div class="invalid-feedback">
                            Please provide a cancellation reason.
                        </div>
                    </div>

                    <?php if ($booking['amount_paid'] > 0): ?>
                    <div class="form-group">
                        <label for="refund_amount">Refund Amount</label>
                        <input type="number" id="refund_amount" name="refund_amount" class="form-control" 
                               value="<?php echo htmlspecialchars($cancellationData['refund_amount'] ?? $booking['amount_paid']); ?>" 
                               step="0.01" min="0" max="<?php echo $booking['amount_paid']; ?>">
                        <small class="form-text text-muted">
                            Maximum refund: <?php echo formatCurrency($booking['amount_paid']); ?>
                        </small>
                    </div>
                    <?php endif; ?>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-danger btn-lg">
                            <i class="icon-times"></i> Cancel Booking
                        </button>
                        <a href="view.php?id=<?php echo $bookingId; ?>" class="btn btn-secondary btn-lg">
                            <i class="icon-arrow-left"></i> Back to Booking
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3>Cancellation Effects</h3>
            </div>
            <div class="card-body">
                <ul class="cancellation-effects">
                    <li><i class="icon-times text-danger"></i> Booking status will change to "Cancelled"</li>
                    <li><i class="icon-calendar text-info"></i> Hall will be available for other bookings</li>
                    <li><i class="icon-envelope text-warning"></i> Customer will receive cancellation email</li>
                    <li><i class="icon-credit-card text-success"></i> Refund will be processed if applicable</li>
                </ul>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Refund Information</h3>
            </div>
            <div class="card-body">
                <?php if ($booking['amount_paid'] > 0): ?>
                <div class="refund-info">
                    <div class="refund-item">
                        <label>Amount Paid:</label>
                        <span><?php echo formatCurrency($booking['amount_paid']); ?></span>
                    </div>
                    <div class="refund-item">
                        <label>Available for Refund:</label>
                        <span class="text-success"><?php echo formatCurrency($booking['amount_paid']); ?></span>
                    </div>
                    <div class="refund-item">
                        <label>Refund Policy:</label>
                        <span class="text-muted">Full refund available</span>
                    </div>
                </div>
                <?php else: ?>
                <p class="text-muted">No payments have been made for this booking.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Alternative Actions</h3>
            </div>
            <div class="card-body">
                <div class="alternative-actions">
                    <a href="view.php?id=<?php echo $bookingId; ?>" class="btn btn-outline-primary btn-sm btn-block">
                        <i class="icon-eye"></i> View Booking Details
                    </a>
                    <?php if ($booking['booking_status'] == 'Pending'): ?>
                    <a href="confirm.php?id=<?php echo $bookingId; ?>" class="btn btn-outline-success btn-sm btn-block">
                        <i class="icon-check"></i> Confirm Booking Instead
                    </a>
                    <?php endif; ?>
                    <?php if ($booking['balance_due'] > 0): ?>
                    <a href="record-payment.php?id=<?php echo $bookingId; ?>" class="btn btn-outline-info btn-sm btn-block">
                        <i class="icon-credit-card"></i> Record Payment
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('cancellation-form');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Validate refund amount
document.getElementById('refund_amount').addEventListener('input', function() {
    const amount = parseFloat(this.value);
    const maxAmount = <?php echo $booking['amount_paid']; ?>;
    
    if (amount > maxAmount) {
        this.setCustomValidity('Refund amount cannot exceed amount paid');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<style>
.cancellation-warning {
    margin-bottom: 30px;
}

.booking-details,
.customer-details,
.financial-summary {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #dee2e6;
}

.info-group {
    margin-bottom: 15px;
}

.info-group label {
    font-weight: 600;
    color: #666;
    display: block;
    margin-bottom: 5px;
}

.financial-summary {
    border-top: 1px solid #dee2e6;
    padding-top: 15px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    padding: 5px 0;
}

.summary-row.total {
    border-top: 1px solid #dee2e6;
    font-weight: 600;
    font-size: 1.1em;
    margin-top: 10px;
    padding-top: 10px;
}

.form-actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #dee2e6;
    text-align: center;
}

.form-actions .btn {
    margin: 0 10px;
}

.cancellation-effects {
    list-style: none;
    padding: 0;
}

.cancellation-effects li {
    margin-bottom: 10px;
    padding: 5px 0;
}

.refund-info {
    margin-top: 15px;
}

.refund-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    padding: 5px 0;
}

.refund-item label {
    font-weight: 500;
}

.alternative-actions .btn {
    margin-bottom: 10px;
}

.invalid-feedback {
    display: none;
    width: 100%;
    margin-top: 0.25rem;
    font-size: 0.875em;
    color: #dc3545;
}

.was-validated .form-control:invalid ~ .invalid-feedback {
    display: block;
}
</style>

<?php include '../../includes/footer.php'; ?>

