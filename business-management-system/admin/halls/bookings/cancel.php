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

// Check if booking can be cancelled
if ($booking['booking_status'] == 'Cancelled') {
    $_SESSION['error_message'] = 'This booking is already cancelled.';
    header('Location: view.php?id=' . $bookingId);
    exit;
}

if ($booking['booking_status'] == 'Completed') {
    $_SESSION['error_message'] = 'Cannot cancel a completed booking.';
    header('Location: view.php?id=' . $bookingId);
    exit;
}

// Initialize variables
$errors = [];
$cancellationData = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        // Sanitize and validate input
        $cancellationData = [
            'reason' => trim($_POST['reason'] ?? ''),
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
            $errors[] = 'Refund amount cannot exceed the amount paid.';
        }

        // Cancel booking if no errors
        if (empty($errors)) {
            try {
                cancelBooking($bookingId, $cancellationData['reason'], $cancellationData['refund_amount']);

                // Log activity
                logActivity("Booking cancelled: {$booking['booking_number']} - Reason: {$cancellationData['reason']}", $bookingId, 'hall_booking');

                $_SESSION['success_message'] = 'Booking has been cancelled successfully!';
                header('Location: view.php?id=' . $bookingId);
                exit;
            } catch (Exception $e) {
                $errors[] = 'Error cancelling booking: ' . $e->getMessage();
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
        <p><?php echo htmlspecialchars($booking['booking_number']); ?> • <?php echo htmlspecialchars($booking['event_name']); ?></p>
    </div>
    <div class="page-actions">
        <a href="view.php?id=<?php echo $booking['id']; ?>" class="btn btn-secondary">
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
    <!-- Cancellation Form -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>Cancel Booking</h3>
            </div>
            <div class="card-body">
                <div class="alert alert-danger">
                    <i class="icon-warning"></i>
                    <strong>Warning: This action cannot be undone!</strong>
                    <p class="mb-0">Cancelling this booking will free up the hall for the specified date and time, and may trigger refunds if applicable.</p>
                </div>

                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-group">
                        <label for="reason">Cancellation Reason <span class="text-danger">*</span></label>
                        <textarea id="reason" name="reason" class="form-control" rows="4" 
                                  placeholder="Please provide a detailed reason for cancelling this booking" required><?php echo htmlspecialchars($cancellationData['reason'] ?? ''); ?></textarea>
                        <div class="invalid-feedback">
                            Please provide a cancellation reason.
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="refund_amount">Refund Amount</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><?php echo $booking['currency']; ?></span>
                            </div>
                            <input type="number" id="refund_amount" name="refund_amount" class="form-control" 
                                   value="<?php echo htmlspecialchars($cancellationData['refund_amount'] ?? '0'); ?>" 
                                   step="0.01" min="0" max="<?php echo $booking['amount_paid']; ?>">
                        </div>
                        <small class="form-text text-muted">
                            Maximum refundable: <?php echo formatCurrency($booking['amount_paid'], $booking['currency']); ?>
                        </small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-danger">
                            <i class="icon-x"></i> Cancel Booking
                        </button>
                        <a href="view.php?id=<?php echo $booking['id']; ?>" class="btn btn-secondary">
                            <i class="icon-times"></i> Keep Booking
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
                        <span class="badge <?php echo getBookingStatusBadgeClass($booking['booking_status']); ?>">
                            <?php echo $booking['booking_status']; ?>
                        </span>
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

        <!-- Refund Options -->
        <?php if ($booking['amount_paid'] > 0): ?>
        <div class="card">
            <div class="card-header">
                <h3>Refund Options</h3>
            </div>
            <div class="card-body">
                <div class="refund-options">
                    <button type="button" class="btn btn-outline-primary btn-sm quick-refund" data-amount="<?php echo $booking['amount_paid']; ?>">
                        Full Refund (<?php echo formatCurrency($booking['amount_paid'], $booking['currency']); ?>)
                    </button>
                    
                    <button type="button" class="btn btn-outline-secondary btn-sm quick-refund" data-amount="<?php echo $booking['amount_paid'] * 0.5; ?>">
                        50% Refund (<?php echo formatCurrency($booking['amount_paid'] * 0.5, $booking['currency']); ?>)
                    </button>
                    
                    <button type="button" class="btn btn-outline-secondary btn-sm quick-refund" data-amount="0">
                        No Refund
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
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

// Quick refund buttons
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.quick-refund').forEach(button => {
        button.addEventListener('click', function() {
            const amount = parseFloat(this.dataset.amount);
            document.getElementById('refund_amount').value = amount.toFixed(2);
        });
    });
});
</script>

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

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    font-weight: 500;
    margin-bottom: 5px;
}

.text-danger {
    color: #dc3545 !important;
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

.alert-danger {
    background-color: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}

.refund-options {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.quick-refund {
    width: 100%;
    text-align: left;
}

.quick-refund:hover {
    background-color: #007bff;
    color: white;
}
</style>

<?php
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

include '../../includes/footer.php'; ?>
