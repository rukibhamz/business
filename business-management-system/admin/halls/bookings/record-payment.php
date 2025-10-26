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
           c.first_name, c.last_name, c.company_name
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
            'reference' => trim($_POST['reference'] ?? ''),
            'is_deposit' => isset($_POST['is_deposit']) ? 1 : 0,
            'notes' => trim($_POST['notes'] ?? '')
        ];

        // Validation
        if ($paymentData['amount'] <= 0) {
            $errors[] = 'Payment amount must be greater than 0.';
        }

        if ($paymentData['amount'] > $booking['balance_due']) {
            $errors[] = 'Payment amount cannot exceed balance due (' . formatCurrency($booking['balance_due']) . ').';
        }

        if (empty($paymentData['payment_method'])) {
            $errors[] = 'Payment method is required.';
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
                
                if ($paymentId) {
                    $success = true;
                    // Redirect to booking view page
                    header("Location: view.php?id={$bookingId}&success=payment_recorded");
                    exit;
                } else {
                    $errors[] = 'Failed to record payment. Please try again.';
                }
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Set page title
$pageTitle = 'Record Payment - ' . $booking['booking_number'];

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Record Payment</h1>
        <p>Booking #<?php echo htmlspecialchars($booking['booking_number']); ?></p>
    </div>
    <div class="page-actions">
        <a href="view.php?id=<?php echo $bookingId; ?>" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Booking
        </a>
    </div>
</div>

<div class="row">
    <!-- Payment Form -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>Payment Information</h3>
            </div>
            <div class="card-body">
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

                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="amount">Payment Amount <span class="text-danger">*</span></label>
                                <input type="number" id="amount" name="amount" class="form-control" 
                                       value="<?php echo htmlspecialchars($paymentData['amount'] ?? ''); ?>" 
                                       step="0.01" min="0.01" max="<?php echo $booking['balance_due']; ?>" required>
                                <small class="form-text text-muted">
                                    Maximum: <?php echo formatCurrency($booking['balance_due']); ?>
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
                                <label for="reference">Payment Reference</label>
                                <input type="text" id="reference" name="reference" class="form-control" 
                                       value="<?php echo htmlspecialchars($paymentData['reference'] ?? ''); ?>" 
                                       placeholder="Transaction ID, cheque number, etc.">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" id="is_deposit" name="is_deposit" class="form-check-input" 
                                           <?php echo ($paymentData['is_deposit'] ?? 0) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_deposit">
                                        This is a deposit payment
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3" 
                                  placeholder="Additional notes about this payment..."><?php echo htmlspecialchars($paymentData['notes'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="icon-credit-card"></i> Record Payment
                        </button>
                        <a href="view.php?id=<?php echo $bookingId; ?>" class="btn btn-secondary">
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
                <div class="booking-info">
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
                    <div class="info-group">
                        <label>Date:</label>
                        <span><?php echo date('M d, Y', strtotime($booking['start_date'])); ?></span>
                    </div>
                    <div class="info-group">
                        <label>Time:</label>
                        <span><?php echo date('g:i A', strtotime($booking['start_time'])); ?> - <?php echo date('g:i A', strtotime($booking['end_time'])); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Financial Summary</h3>
            </div>
            <div class="card-body">
                <div class="financial-summary">
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
                        <span class="text-danger"><?php echo formatCurrency($booking['balance_due']); ?></span>
                    </div>
                </div>
                
                <div class="status-badges mt-3">
                    <div class="status-badge">
                        <label>Payment Status:</label>
                        <span class="badge <?php echo getHallPaymentStatusBadgeClass($booking['payment_status']); ?>">
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
                    <button type="button" class="btn btn-outline-primary btn-sm btn-block" 
                            onclick="setAmount(<?php echo $booking['balance_due']; ?>)">
                        Full Balance (<?php echo formatCurrency($booking['balance_due']); ?>)
                    </button>
                    <?php if ($booking['balance_due'] > 0): ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm btn-block" 
                            onclick="setAmount(<?php echo $booking['balance_due'] / 2; ?>)">
                        Half Balance (<?php echo formatCurrency($booking['balance_due'] / 2); ?>)
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

// Set payment amount
function setAmount(amount) {
    document.getElementById('amount').value = amount.toFixed(2);
}

// Validate amount on input
document.getElementById('amount').addEventListener('input', function() {
    const amount = parseFloat(this.value);
    const maxAmount = <?php echo $booking['balance_due']; ?>;
    
    if (amount > maxAmount) {
        this.setCustomValidity('Amount cannot exceed balance due');
    } else {
        this.setCustomValidity('');
    }
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

.status-badges {
    border-top: 1px solid #dee2e6;
    padding-top: 15px;
}

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

.quick-amounts .btn {
    margin-bottom: 10px;
}

.badge-success { background-color: #28a745; color: white; }
.badge-secondary { background-color: #6c757d; color: white; }
.badge-danger { background-color: #dc3545; color: white; }
.badge-info { background-color: #17a2b8; color: white; }
.badge-warning { background-color: #ffc107; color: #333; }
.badge-primary { background-color: #007bff; color: white; }
</style>

<?php include '../../includes/footer.php'; ?>

