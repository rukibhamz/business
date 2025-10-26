<?php
/**
 * Business Management System - Create Lease
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
requirePermission('leases.create');

// Get database connection
$conn = getDB();

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        // Validate required fields
        $requiredFields = ['property_id', 'tenant_id', 'start_date', 'end_date', 'monthly_rent'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
        }
        
        // Validate dates
        if (!empty($_POST['start_date']) && !empty($_POST['end_date'])) {
            $startDate = strtotime($_POST['start_date']);
            $endDate = strtotime($_POST['end_date']);
            
            if ($startDate >= $endDate) {
                $errors[] = 'End date must be after start date.';
            }
            
            if ($startDate < strtotime('today')) {
                $errors[] = 'Start date cannot be in the past.';
            }
        }
        
        // Validate monthly rent
        if (!empty($_POST['monthly_rent']) && (!is_numeric($_POST['monthly_rent']) || $_POST['monthly_rent'] <= 0)) {
            $errors[] = 'Monthly rent must be a valid positive number.';
        }
        
        // Check if property is available
        if (!empty($_POST['property_id'])) {
            $stmt = $conn->prepare("SELECT availability_status FROM " . DB_PREFIX . "properties WHERE id = ?");
            $stmt->bind_param('i', $_POST['property_id']);
            $stmt->execute();
            $property = $stmt->get_result()->fetch_assoc();
            
            if ($property && $property['availability_status'] !== 'Available') {
                $errors[] = 'Selected property is not available for lease.';
            }
        }
        
        // Check if tenant has active lease
        if (!empty($_POST['tenant_id'])) {
            $stmt = $conn->prepare("SELECT id FROM " . DB_PREFIX . "leases WHERE tenant_id = ? AND lease_status = 'Active'");
            $stmt->bind_param('i', $_POST['tenant_id']);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) {
                $errors[] = 'Selected tenant already has an active lease.';
            }
        }
        
        if (empty($errors)) {
            $conn->begin_transaction();
            
            try {
                // Generate lease code
                $leaseCode = generateLeaseCode();
                
                // Calculate lease duration in months
                $startDate = new DateTime($_POST['start_date']);
                $endDate = new DateTime($_POST['end_date']);
                $durationMonths = $startDate->diff($endDate)->m + ($startDate->diff($endDate)->y * 12);
                
                // Insert lease
                $stmt = $conn->prepare("
                    INSERT INTO " . DB_PREFIX . "leases (
                        lease_code, property_id, tenant_id, start_date, end_date,
                        monthly_rent, security_deposit, agency_fee, service_charge,
                        currency, duration_months, lease_type, payment_frequency,
                        late_fee_amount, late_fee_percentage, grace_period_days,
                        special_clauses, lease_status, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->bind_param('siisssdddsiisssssi',
                    $leaseCode,
                    $_POST['property_id'],
                    $_POST['tenant_id'],
                    $_POST['start_date'],
                    $_POST['end_date'],
                    $_POST['monthly_rent'],
                    $_POST['security_deposit'],
                    $_POST['agency_fee'],
                    $_POST['service_charge'],
                    $_POST['currency'],
                    $durationMonths,
                    $_POST['lease_type'],
                    $_POST['payment_frequency'],
                    $_POST['late_fee_amount'],
                    $_POST['late_fee_percentage'],
                    $_POST['grace_period_days'],
                    $_POST['special_clauses'],
                    'Active',
                    $_SESSION['user_id']
                );
                
                $stmt->execute();
                /** @var mysqli $conn */
                $leaseId = $conn->insert_id;
                
                // Update property availability status
                $stmt = $conn->prepare("UPDATE " . DB_PREFIX . "properties SET availability_status = 'Occupied' WHERE id = ?");
                $stmt->bind_param('i', $_POST['property_id']);
                $stmt->execute();
                
                // Create initial rent reminders
                createRentReminders($leaseId);
                
                $conn->commit();
                $success = true;
                
                // Redirect to lease view
                header('Location: view.php?id=' . $leaseId . '&success=created');
                exit;
                
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = 'Error creating lease: ' . $e->getMessage();
            }
        }
    }
}

// Get available properties
$stmt = $conn->prepare("
    SELECT p.*, pt.type_name 
    FROM " . DB_PREFIX . "properties p
    LEFT JOIN " . DB_PREFIX . "property_types pt ON p.property_type_id = pt.id
    WHERE p.property_status = 'Active' AND p.availability_status = 'Available'
    ORDER BY p.property_name
");
$stmt->execute();
$properties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get available tenants
$stmt = $conn->prepare("
    SELECT t.*, 
           CASE WHEN l.id IS NULL THEN 'Available' ELSE 'Has Active Lease' END as lease_status
    FROM " . DB_PREFIX . "tenants t
    LEFT JOIN " . DB_PREFIX . "leases l ON t.id = l.tenant_id AND l.lease_status = 'Active'
    WHERE t.status = 'Active'
    ORDER BY t.first_name, t.last_name
");
$stmt->execute();
$tenants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Set page title
$pageTitle = 'Create Lease';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Create Lease</h1>
        <p>Create a new rental lease agreement</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Leases
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
    Lease created successfully!
</div>
<?php endif; ?>

<form method="POST" class="needs-validation" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    
    <!-- Basic Information -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Basic Information</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="property_id" class="form-label">Property <span class="text-danger">*</span></label>
                        <select class="form-select" id="property_id" name="property_id" required>
                            <option value="">Select Property</option>
                            <?php foreach ($properties as $property): ?>
                            <option value="<?php echo $property['id']; ?>" 
                                    data-rent="<?php echo $property['monthly_rent']; ?>"
                                    data-deposit="<?php echo $property['security_deposit']; ?>"
                                    data-agency="<?php echo $property['agency_fee']; ?>"
                                    data-service="<?php echo $property['service_charge']; ?>"
                                    data-currency="<?php echo $property['currency']; ?>"
                                    <?php echo (isset($_POST['property_id']) && $_POST['property_id'] == $property['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($property['property_name'] . ' - ' . $property['type_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="tenant_id" class="form-label">Tenant <span class="text-danger">*</span></label>
                        <select class="form-select" id="tenant_id" name="tenant_id" required>
                            <option value="">Select Tenant</option>
                            <?php foreach ($tenants as $tenant): ?>
                            <option value="<?php echo $tenant['id']; ?>" 
                                    <?php echo (isset($_POST['tenant_id']) && $_POST['tenant_id'] == $tenant['id']) ? 'selected' : ''; ?>
                                    <?php echo $tenant['lease_status'] === 'Has Active Lease' ? 'disabled' : ''; ?>>
                                <?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']); ?>
                                <?php if ($tenant['lease_status'] === 'Has Active Lease'): ?>
                                (Has Active Lease)
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="start_date" name="start_date" required 
                               value="<?php echo isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : ''; ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="end_date" name="end_date" required 
                               value="<?php echo isset($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : ''; ?>">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="lease_type" class="form-label">Lease Type</label>
                        <select class="form-select" id="lease_type" name="lease_type">
                            <option value="Standard" <?php echo (!isset($_POST['lease_type']) || $_POST['lease_type'] == 'Standard') ? 'selected' : ''; ?>>Standard</option>
                            <option value="Short-term" <?php echo (isset($_POST['lease_type']) && $_POST['lease_type'] == 'Short-term') ? 'selected' : ''; ?>>Short-term</option>
                            <option value="Long-term" <?php echo (isset($_POST['lease_type']) && $_POST['lease_type'] == 'Long-term') ? 'selected' : ''; ?>>Long-term</option>
                            <option value="Corporate" <?php echo (isset($_POST['lease_type']) && $_POST['lease_type'] == 'Corporate') ? 'selected' : ''; ?>>Corporate</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="payment_frequency" class="form-label">Payment Frequency</label>
                        <select class="form-select" id="payment_frequency" name="payment_frequency">
                            <option value="Monthly" <?php echo (!isset($_POST['payment_frequency']) || $_POST['payment_frequency'] == 'Monthly') ? 'selected' : ''; ?>>Monthly</option>
                            <option value="Quarterly" <?php echo (isset($_POST['payment_frequency']) && $_POST['payment_frequency'] == 'Quarterly') ? 'selected' : ''; ?>>Quarterly</option>
                            <option value="Semi-annually" <?php echo (isset($_POST['payment_frequency']) && $_POST['payment_frequency'] == 'Semi-annually') ? 'selected' : ''; ?>>Semi-annually</option>
                            <option value="Annually" <?php echo (isset($_POST['payment_frequency']) && $_POST['payment_frequency'] == 'Annually') ? 'selected' : ''; ?>>Annually</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Financial Information -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Financial Information</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="monthly_rent" class="form-label">Monthly Rent <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text" id="currency_symbol">₦</span>
                            <input type="number" class="form-control" id="monthly_rent" name="monthly_rent" 
                                   min="0" step="0.01" required 
                                   value="<?php echo isset($_POST['monthly_rent']) ? htmlspecialchars($_POST['monthly_rent']) : ''; ?>">
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="security_deposit" class="form-label">Security Deposit</label>
                        <div class="input-group">
                            <span class="input-group-text" id="currency_symbol_deposit">₦</span>
                            <input type="number" class="form-control" id="security_deposit" name="security_deposit" 
                                   min="0" step="0.01" 
                                   value="<?php echo isset($_POST['security_deposit']) ? htmlspecialchars($_POST['security_deposit']) : ''; ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="agency_fee" class="form-label">Agency Fee</label>
                        <div class="input-group">
                            <span class="input-group-text" id="currency_symbol_agency">₦</span>
                            <input type="number" class="form-control" id="agency_fee" name="agency_fee" 
                                   min="0" step="0.01" 
                                   value="<?php echo isset($_POST['agency_fee']) ? htmlspecialchars($_POST['agency_fee']) : ''; ?>">
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="service_charge" class="form-label">Service Charge</label>
                        <div class="input-group">
                            <span class="input-group-text" id="currency_symbol_service">₦</span>
                            <input type="number" class="form-control" id="service_charge" name="service_charge" 
                                   min="0" step="0.01" 
                                   value="<?php echo isset($_POST['service_charge']) ? htmlspecialchars($_POST['service_charge']) : ''; ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="late_fee_amount" class="form-label">Late Fee Amount</label>
                        <div class="input-group">
                            <span class="input-group-text" id="currency_symbol_late">₦</span>
                            <input type="number" class="form-control" id="late_fee_amount" name="late_fee_amount" 
                                   min="0" step="0.01" 
                                   value="<?php echo isset($_POST['late_fee_amount']) ? htmlspecialchars($_POST['late_fee_amount']) : ''; ?>">
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="late_fee_percentage" class="form-label">Late Fee Percentage</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="late_fee_percentage" name="late_fee_percentage" 
                                   min="0" max="100" step="0.01" 
                                   value="<?php echo isset($_POST['late_fee_percentage']) ? htmlspecialchars($_POST['late_fee_percentage']) : ''; ?>">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="grace_period_days" class="form-label">Grace Period (Days)</label>
                        <input type="number" class="form-control" id="grace_period_days" name="grace_period_days" 
                               min="0" max="30" 
                               value="<?php echo isset($_POST['grace_period_days']) ? htmlspecialchars($_POST['grace_period_days']) : '5'; ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="currency" class="form-label">Currency</label>
                        <select class="form-select" id="currency" name="currency">
                            <option value="NGN" <?php echo (!isset($_POST['currency']) || $_POST['currency'] == 'NGN') ? 'selected' : ''; ?>>NGN (Nigerian Naira)</option>
                            <option value="USD" <?php echo (isset($_POST['currency']) && $_POST['currency'] == 'USD') ? 'selected' : ''; ?>>USD (US Dollar)</option>
                            <option value="EUR" <?php echo (isset($_POST['currency']) && $_POST['currency'] == 'EUR') ? 'selected' : ''; ?>>EUR (Euro)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Special Clauses -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Special Clauses</h5>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label for="special_clauses" class="form-label">Special Terms & Conditions</label>
                <textarea class="form-control" id="special_clauses" name="special_clauses" rows="6" 
                          placeholder="Enter any special terms, conditions, or clauses for this lease..."><?php echo isset($_POST['special_clauses']) ? htmlspecialchars($_POST['special_clauses']) : ''; ?></textarea>
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
                    <i class="icon-save"></i> Create Lease
                </button>
            </div>
        </div>
    </div>
</form>

<script>
// Auto-populate financial fields when property is selected
document.getElementById('property_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    if (selectedOption.value) {
        document.getElementById('monthly_rent').value = selectedOption.dataset.rent || '';
        document.getElementById('security_deposit').value = selectedOption.dataset.deposit || '';
        document.getElementById('agency_fee').value = selectedOption.dataset.agency || '';
        document.getElementById('service_charge').value = selectedOption.dataset.service || '';
        
        // Update currency symbols
        const currency = selectedOption.dataset.currency || 'NGN';
        const currencySymbol = currency === 'NGN' ? '₦' : (currency === 'USD' ? '$' : '€');
        document.querySelectorAll('[id^="currency_symbol"]').forEach(el => {
            el.textContent = currencySymbol;
        });
        document.getElementById('currency').value = currency;
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
