<?php
/**
 * Business Management System - Record Rent Payment
 * Phase 5: Property Management & Rent Expiry System
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
require_once '../../../includes/property-functions.php';

// Check authentication and permissions
requireLogin();
requirePermission('rent.create');

// Get database connection
$conn = getDB();

$errors = [];
$success = false;

// Get lease ID from URL if provided
$leaseId = $_GET['lease_id'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        // Validate required fields
        $requiredFields = ['lease_id', 'payment_date', 'amount_due', 'amount_paid'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
        }
        
        // Validate amounts
        if (!empty($_POST['amount_due']) && (!is_numeric($_POST['amount_due']) || $_POST['amount_due'] <= 0)) {
            $errors[] = 'Amount due must be a valid positive number.';
        }
        
        if (!empty($_POST['amount_paid']) && (!is_numeric($_POST['amount_paid']) || $_POST['amount_paid'] <= 0)) {
            $errors[] = 'Amount paid must be a valid positive number.';
        }
        
        if (!empty($_POST['amount_paid']) && !empty($_POST['amount_due']) && $_POST['amount_paid'] > $_POST['amount_due']) {
            $errors[] = 'Amount paid cannot exceed amount due.';
        }
        
        // Validate payment date
        if (!empty($_POST['payment_date'])) {
            $paymentDate = strtotime($_POST['payment_date']);
            if ($paymentDate > time()) {
                $errors[] = 'Payment date cannot be in the future.';
            }
        }
        
        // Check if lease exists and is active
        if (!empty($_POST['lease_id'])) {
            $stmt = $conn->prepare("SELECT id, lease_status FROM " . DB_PREFIX . "leases WHERE id = ?");
            $stmt->bind_param('i', $_POST['lease_id']);
            $stmt->execute();
            $lease = $stmt->get_result()->fetch_assoc();
            
            if (!$lease) {
                $errors[] = 'Selected lease does not exist.';
            } elseif ($lease['lease_status'] !== 'Active') {
                $errors[] = 'Cannot record payment for inactive lease.';
            }
        }
        
        if (empty($errors)) {
            $conn->begin_transaction();
            
            try {
                // Determine payment status
                $paymentStatus = 'Paid';
                if ($_POST['amount_paid'] < $_POST['amount_due']) {
                    $paymentStatus = 'Partial';
                }
                
                // Check if payment is overdue
                $stmt = $conn->prepare("SELECT end_date FROM " . DB_PREFIX . "leases WHERE id = ?");
                $stmt->bind_param('i', $_POST['lease_id']);
                $stmt->execute();
                $leaseEndDate = $stmt->get_result()->fetch_assoc()['end_date'];
                
                if (strtotime($_POST['payment_date']) > strtotime($leaseEndDate)) {
                    $paymentStatus = 'Overdue';
                }
                
                // Insert payment record
                $stmt = $conn->prepare("
                    INSERT INTO " . DB_PREFIX . "rent_payments (
                        lease_id, payment_date, amount_due, amount_paid, payment_method,
                        reference_number, notes, payment_status, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->bind_param('isddssssi',
                    $_POST['lease_id'],
                    $_POST['payment_date'],
                    $_POST['amount_due'],
                    $_POST['amount_paid'],
                    $_POST['payment_method'],
                    $_POST['reference_number'],
                    $_POST['notes'],
                    $paymentStatus,
                    $_SESSION['user_id']
                );
                
                $stmt->execute();
                /** @var mysqli $conn */
                $paymentId = $conn->insert_id;
                
                // Create accounting entry
                createRentRevenueEntry($paymentId);
                
                // Update lease payment status if needed
                updateLeasePaymentStatus($_POST['lease_id']);
                
                $conn->commit();
                $success = true;
                
                // Redirect to payment view
                header('Location: view.php?id=' . $paymentId . '&success=created');
                exit;
                
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = 'Error recording payment: ' . $e->getMessage();
            }
        }
    }
}

// Get active leases
$stmt = $conn->prepare("
    SELECT l.*, 
           p.property_name, p.property_code,
           t.first_name, t.last_name, t.email, t.phone
    FROM " . DB_PREFIX . "leases l
    LEFT JOIN " . DB_PREFIX . "properties p ON l.property_id = p.id
    LEFT JOIN " . DB_PREFIX . "tenants t ON l.tenant_id = t.id
    WHERE l.lease_status = 'Active'
    ORDER BY l.lease_code
");
$stmt->execute();
$leases = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get lease details if lease ID is provided
$selectedLease = null;
if (!empty($leaseId)) {
    $stmt = $conn->prepare("
        SELECT l.*, 
               p.property_name, p.property_code,
               t.first_name, t.last_name, t.email, t.phone
        FROM " . DB_PREFIX . "leases l
        LEFT JOIN " . DB_PREFIX . "properties p ON l.property_id = p.id
        LEFT JOIN " . DB_PREFIX . "tenants t ON l.tenant_id = t.id
        WHERE l.id = ? AND l.lease_status = 'Active'
    ");
    $stmt->bind_param('i', $leaseId);
    $stmt->execute();
    $selectedLease = $stmt->get_result()->fetch_assoc();
}

// Set page title
$pageTitle = 'Record Rent Payment';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Record Rent Payment</h1>
        <p>Record a new rent payment</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Payments
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
        <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success">
    Payment recorded successfully!
</div>
<?php endif; ?>

<form method="POST" class="needs-validation" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    
    <!-- Lease Selection -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Lease Selection</h5>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label for="lease_id" class="form-label">Lease <span class="text-danger">*</span></label>
                <select class="form-select" id="lease_id" name="lease_id" required>
                    <option value="">Select Lease</option>
                    <?php foreach ($leases as $lease): ?>
                    <option value="<?php echo $lease['id']; ?>" 
                            data-rent="<?php echo $lease['monthly_rent']; ?>"
                            data-currency="<?php echo $lease['currency']; ?>"
                            <?php echo (isset($_POST['lease_id']) && $_POST['lease_id'] == $lease['id']) || $leaseId == $lease['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($lease['lease_code'] . ' - ' . $lease['property_name'] . ' (' . $lease['first_name'] . ' ' . $lease['last_name'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if ($selectedLease): ?>
            <div class="alert alert-info">
                <h6><?php echo htmlspecialchars($selectedLease['lease_code']); ?></h6>
                <p class="mb-1">
                    <strong>Property:</strong> <?php echo htmlspecialchars($selectedLease['property_name']); ?><br>
                    <strong>Tenant:</strong> <?php echo htmlspecialchars($selectedLease['first_name'] . ' ' . $selectedLease['last_name']); ?><br>
                    <strong>Monthly Rent:</strong> <?php echo $selectedLease['currency']; ?> <?php echo number_format($selectedLease['monthly_rent'], 2); ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Payment Information -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Payment Information</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="payment_date" class="form-label">Payment Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date" required 
                               value="<?php echo isset($_POST['payment_date']) ? htmlspecialchars($_POST['payment_date']) : date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Payment Method</label>
                        <select class="form-select" id="payment_method" name="payment_method">
                            <option value="Cash" <?php echo (!isset($_POST['payment_method']) || $_POST['payment_method'] == 'Cash') ? 'selected' : ''; ?>>Cash</option>
                            <option value="Bank Transfer" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'Bank Transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                            <option value="Cheque" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'Cheque') ? 'selected' : ''; ?>>Cheque</option>
                            <option value="POS" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'POS') ? 'selected' : ''; ?>>POS</option>
                            <option value="Mobile Money" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'Mobile Money') ? 'selected' : ''; ?>>Mobile Money</option>
                            <option value="Other" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="amount_due" class="form-label">Amount Due <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text" id="currency_symbol">₦</span>
                            <input type="number" class="form-control" id="amount_due" name="amount_due" 
                                   min="0" step="0.01" required 
                                   value="<?php echo isset($_POST['amount_due']) ? htmlspecialchars($_POST['amount_due']) : ''; ?>">
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="amount_paid" class="form-label">Amount Paid <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text" id="currency_symbol_paid">₦</span>
                            <input type="number" class="form-control" id="amount_paid" name="amount_paid" 
                                   min="0" step="0.01" required 
                                   value="<?php echo isset($_POST['amount_paid']) ? htmlspecialchars($_POST['amount_paid']) : ''; ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="reference_number" class="form-label">Reference Number</label>
                        <input type="text" class="form-control" id="reference_number" name="reference_number" 
                               placeholder="Transaction reference, cheque number, etc."
                               value="<?php echo isset($_POST['reference_number']) ? htmlspecialchars($_POST['reference_number']) : ''; ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <input type="text" class="form-control" id="notes" name="notes" 
                               placeholder="Additional notes about this payment"
                               value="<?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Form Actions -->
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between">
                <a href="index.php" class="btn btn-secondary">
                    <i class="icon-arrow-left"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="icon-save"></i> Record Payment
                </button>
            </div>
        </div>
    </div>
</form>

<script>
// Auto-populate amount due when lease is selected
document.getElementById('lease_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    if (selectedOption.value) {
        document.getElementById('amount_due').value = selectedOption.dataset.rent || '';
        
        // Update currency symbols
        const currency = selectedOption.dataset.currency || 'NGN';
        const currencySymbol = currency === 'NGN' ? '₦' : (currency === 'USD' ? '$' : '€');
        document.getElementById('currency_symbol').textContent = currencySymbol;
        document.getElementById('currency_symbol_paid').textContent = currencySymbol;
    }
});

// Auto-fill amount paid with amount due
document.getElementById('amount_due').addEventListener('input', function() {
    const amountDue = parseFloat(this.value);
    if (amountDue > 0) {
        document.getElementById('amount_paid').value = amountDue;
    }
});

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
</script>

<?php include '../../includes/footer.php'; ?>
