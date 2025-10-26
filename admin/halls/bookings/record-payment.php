<?php
/**
 * Business Management System - Record Hall Booking Payment
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

// Initialize variables
$errors = [];
$success = false;
$paymentData = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        // Sanitize and validate input
        $paymentData = [
            'amount' => (float)($_POST['amount'] ?? 0),
            'payment_method' => trim($_POST['payment_method'] ?? ''),
            'payment_date' => trim($_POST['payment_date'] ?? ''),
            'reference' => trim($_POST['reference'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
            'is_deposit' => isset($_POST['is_deposit']) ? 1 : 0
        ];

        // Validation
        if ($paymentData['amount'] <= 0) {
            $errors[] = 'Payment amount must be greater than 0.';
        }

        if ($paymentData['amount'] > $booking['balance_due']) {
            $errors[] = 'Payment amount cannot exceed the balance due.';
        }

        if (empty($paymentData['payment_method'])) {
            $errors[] = 'Payment method is required.';
        }

        if (empty($paymentData['payment_date'])) {
            $errors[] = 'Payment date is required.';
        }

        // Record payment if no errors
        if (empty($errors)) {
            try {
                $paymentId = recordBookingPayment(
                    $bookingId,
                    $paymentData['amount'],
                    $paymentData['payment_method'],
                    $paymentData['reference'],
                    $paymentData['is_deposit']
                );

                // Update payment record with additional details
                $stmt = $conn->prepare("
                    UPDATE " . DB_PREFIX . "hall_booking_payments 
                    SET payment_date = ?, notes = ?
                    WHERE id = ?
                ");
                $stmt->bind_param('ssi', $paymentData['payment_date'], $paymentData['notes'], $paymentId);
                $stmt->execute();

                // Log activity
                logActivity("Payment recorded for booking {$booking['booking_number']}: " . formatCurrency($paymentData['amount'], $booking['currency']), $bookingId, 'hall_booking');

                $success = true;

                // Redirect to booking view page
                header("Location: view.php?id={$bookingId}&success=1");
                exit;
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Set default payment date to today
if (empty($paymentData['payment_date'])) {
    $paymentData['payment_date'] = date('Y-m-d');
}

// Set page title
$pageTitle = 'Record Payment - ' . $booking['booking_number'];

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Record Payment</h1>
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
    <!-- Payment Form -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>Payment Details</h3>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="amount">Payment Amount <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo $booking['currency']; ?></span>
                                    </div>
                                    <input type="number" id="amount" name="amount" class="form-control" 
                                           value="<?php echo htmlspecialchars($paymentData['amount'] ?? ''); ?>" 
                                           step="0.01" min="0.01" max="<?php echo $booking['balance_due']; ?>" required>
                                </div>
                                <small class="form-text text-muted">
                                    Maximum: <?php echo formatCurrency($booking['balance_due'], $booking['currency']); ?>
                                </small>
                                <div class="invalid-feedback">
                                    Please provide a valid payment amount.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="payment_method">Payment Method <span class="text-danger">*</span></label>
                                <select id="payment_method" name="payment_method" class="form-control" required>
                                    <option value="">Select Payment Method</option>
                                    <option value="Cash" <?php echo ($paymentData['payment_method'] ?? '') == 'Cash' ? 'selected' : ''; ?>>Cash</option>
                                    <option value="Bank Transfer" <?php echo ($paymentData['payment_method'] ?? '') == 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                    <option value="Cheque" <?php echo ($paymentData['payment_method'] ?? '') == 'Cheque' ? 'selected' : ''; ?>>Cheque</option>
                                    <option value="Credit Card" <?php echo ($paymentData['payment_method'] ?? '') == 'Credit Card' ? 'selected' : ''; ?>>Credit Card</option>
                                    <option value="Debit Card" <?php echo ($paymentData['payment_method'] ?? '') == 'Debit Card' ? 'selected' : ''; ?>>Debit Card</option>
                                    <option value="Mobile Money" <?php echo ($paymentData['payment_method'] ?? '') == 'Mobile Money' ? 'selected' : ''; ?>>Mobile Money</option>
                                    <option value="Paystack" <?php echo ($paymentData['payment_method'] ?? '') == 'Paystack' ? 'selected' : ''; ?>>Paystack</option>
                                    <option value="Flutterwave" <?php echo ($paymentData['payment_method'] ?? '') == 'Flutterwave' ? 'selected' : ''; ?>>Flutterwave</option>
                                    <option value="Other" <?php echo ($paymentData['payment_method'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                                <div class="invalid-feedback">
                                    Please select a payment method.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="payment_date">Payment Date <span class="text-danger">*</span></label>
                                <input type="date" id="payment_date" name="payment_date" class="form-control" 
                                       value="<?php echo htmlspecialchars($paymentData['payment_date'] ?? ''); ?>" 
                                       required>
                                <div class="invalid-feedback">
                                    Please provide a payment date.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="reference">Reference Number</label>
                                <input type="text" id="reference" name="reference" class="form-control" 
                                       value="<?php echo htmlspecialchars($paymentData['reference'] ?? ''); ?>" 
                                       placeholder="Transaction reference, cheque number, etc.">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3" 
                                  placeholder="Additional notes about this payment"><?php echo htmlspecialchars($paymentData['notes'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" id="is_deposit" name="is_deposit" class="form-check-input" 
                                   <?php echo ($paymentData['is_deposit'] ?? 0) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_deposit">
                                This is a deposit payment
                            </label>
                            <small class="form-text text-muted">Check this if this payment is a deposit for the booking</small>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="icon-save"></i> Record Payment
                        </button>
                        <a href="view.php?id=<?php echo $booking['id']; ?>" class="btn btn-secondary">
                            <i class="icon-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Booking Summary -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3>Booking Summary</h3>
            </div>
            <div class="card-body">
                <div class="booking-summary">
                    <div class="summary-item">
                        <label>Booking Number:</label>
                        <span><?php echo htmlspecialchars($booking['booking_number']); ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <label>Event:</label>
                        <span><?php echo htmlspecialchars($booking['event_name']); ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <label>Hall:</label>
                        <span><?php echo htmlspecialchars($booking['hall_name']); ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <label>Date:</label>
                        <span><?php echo date('M d, Y', strtotime($booking['start_date'])); ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <label>Time:</label>
                        <span>
                            <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - 
                            <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                        </span>
                    </div>
                    
                    <?php if ($booking['customer_id']): ?>
                    <div class="summary-item">
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
                        <span class="text-danger"><?php echo formatCurrency($booking['balance_due'], $booking['currency']); ?></span>
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

        <!-- Quick Payment Amounts -->
        <div class="card">
            <div class="card-header">
                <h3>Quick Amounts</h3>
            </div>
            <div class="card-body">
                <div class="quick-amounts">
                    <button type="button" class="btn btn-outline-primary btn-sm quick-amount" data-amount="<?php echo $booking['balance_due']; ?>">
                        Full Balance (<?php echo formatCurrency($booking['balance_due'], $booking['currency']); ?>)
                    </button>
                    
                    <?php if ($booking['balance_due'] > 0): ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm quick-amount" data-amount="<?php echo $booking['balance_due'] * 0.5; ?>">
                        50% (<?php echo formatCurrency($booking['balance_due'] * 0.5, $booking['currency']); ?>)
                    </button>
                    
                    <button type="button" class="btn btn-outline-secondary btn-sm quick-amount" data-amount="<?php echo $booking['balance_due'] * 0.25; ?>">
                        25% (<?php echo formatCurrency($booking['balance_due'] * 0.25, $booking['currency']); ?>)
                    </button>
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

// Quick amount buttons
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.quick-amount').forEach(button => {
        button.addEventListener('click', function() {
            const amount = parseFloat(this.dataset.amount);
            document.getElementById('amount').value = amount.toFixed(2);
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

.summary-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    padding-bottom: 5px;
}

.summary-item:not(:last-child) {
    border-bottom: 1px solid #f0f0f0;
}

.summary-item label {
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

.quick-amounts {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.quick-amount {
    width: 100%;
    text-align: left;
}

.quick-amount:hover {
    background-color: #007bff;
    color: white;
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
