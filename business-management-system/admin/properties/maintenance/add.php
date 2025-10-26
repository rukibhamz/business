<?php
/**
 * Business Management System - Create Maintenance Request
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
requirePermission('maintenance.create');

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
        $requiredFields = ['property_id', 'request_title', 'description', 'priority'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
        }
        
        // Validate property exists
        if (!empty($_POST['property_id'])) {
            $stmt = $conn->prepare("SELECT id FROM " . DB_PREFIX . "properties WHERE id = ? AND property_status = 'Active'");
            $stmt->bind_param('i', $_POST['property_id']);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) {
                $errors[] = 'Selected property does not exist or is inactive.';
            }
        }
        
        // Validate tenant if provided
        if (!empty($_POST['tenant_id'])) {
            $stmt = $conn->prepare("SELECT id FROM " . DB_PREFIX . "tenants WHERE id = ? AND status = 'Active'");
            $stmt->bind_param('i', $_POST['tenant_id']);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) {
                $errors[] = 'Selected tenant does not exist or is inactive.';
            }
        }
        
        // Validate assigned user if provided
        if (!empty($_POST['assigned_to'])) {
            $stmt = $conn->prepare("SELECT id FROM " . DB_PREFIX . "users WHERE id = ? AND status = 'Active'");
            $stmt->bind_param('i', $_POST['assigned_to']);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) {
                $errors[] = 'Selected user does not exist or is inactive.';
            }
        }
        
        if (empty($errors)) {
            $conn->begin_transaction();
            
            try {
                // Generate request code
                $requestCode = generateMaintenanceRequestCode();
                
                // Insert maintenance request
                $stmt = $conn->prepare("
                    INSERT INTO " . DB_PREFIX . "maintenance_requests (
                        request_code, property_id, tenant_id, request_title, description,
                        priority, request_type, estimated_cost, requires_entry,
                        entry_permission_granted, assigned_to, request_status, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->bind_param('siisssdiiisii',
                    $requestCode,
                    $_POST['property_id'],
                    $_POST['tenant_id'] ?: null,
                    $_POST['request_title'],
                    $_POST['description'],
                    $_POST['priority'],
                    $_POST['request_type'],
                    $_POST['estimated_cost'],
                    $_POST['requires_entry'],
                    $_POST['entry_permission_granted'],
                    $_POST['assigned_to'] ?: null,
                    'Open',
                    $_SESSION['user_id']
                );
                
                $stmt->execute();
                /** @var mysqli $conn */
                $requestId = $conn->insert_id;
                
                // Update property status if needed
                if ($_POST['priority'] === 'Urgent' || $_POST['priority'] === 'High') {
                    $stmt = $conn->prepare("UPDATE " . DB_PREFIX . "properties SET availability_status = 'Under Maintenance' WHERE id = ?");
                    $stmt->bind_param('i', $_POST['property_id']);
                    $stmt->execute();
                }
                
                $conn->commit();
                $success = true;
                
                // Redirect to request view
                header('Location: view.php?id=' . $requestId . '&success=created');
                exit;
                
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = 'Error creating maintenance request: ' . $e->getMessage();
            }
        }
    }
}

// Get properties
$stmt = $conn->prepare("SELECT id, property_name, property_code FROM " . DB_PREFIX . "properties WHERE property_status = 'Active' ORDER BY property_name");
$stmt->execute();
$properties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get tenants
$stmt = $conn->prepare("SELECT id, first_name, last_name, email FROM " . DB_PREFIX . "tenants WHERE status = 'Active' ORDER BY first_name, last_name");
$stmt->execute();
$tenants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get users for assignment
$stmt = $conn->prepare("SELECT id, first_name, last_name FROM " . DB_PREFIX . "users WHERE status = 'Active' ORDER BY first_name, last_name");
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Set page title
$pageTitle = 'Create Maintenance Request';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Create Maintenance Request</h1>
        <p>Create a new maintenance request or work order</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Requests
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
    Maintenance request created successfully!
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
                            <option value="<?php echo $property['id']; ?>" <?php echo (isset($_POST['property_id']) && $_POST['property_id'] == $property['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($property['property_name'] . ' (' . $property['property_code'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="tenant_id" class="form-label">Tenant (Optional)</label>
                        <select class="form-select" id="tenant_id" name="tenant_id">
                            <option value="">No Tenant</option>
                            <?php foreach ($tenants as $tenant): ?>
                            <option value="<?php echo $tenant['id']; ?>" <?php echo (isset($_POST['tenant_id']) && $_POST['tenant_id'] == $tenant['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name'] . ' (' . $tenant['email'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="request_title" class="form-label">Request Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="request_title" name="request_title" required 
                               placeholder="Brief description of the issue"
                               value="<?php echo isset($_POST['request_title']) ? htmlspecialchars($_POST['request_title']) : ''; ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="request_type" class="form-label">Request Type</label>
                        <select class="form-select" id="request_type" name="request_type">
                            <option value="Repair" <?php echo (!isset($_POST['request_type']) || $_POST['request_type'] == 'Repair') ? 'selected' : ''; ?>>Repair</option>
                            <option value="Maintenance" <?php echo (isset($_POST['request_type']) && $_POST['request_type'] == 'Maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                            <option value="Installation" <?php echo (isset($_POST['request_type']) && $_POST['request_type'] == 'Installation') ? 'selected' : ''; ?>>Installation</option>
                            <option value="Inspection" <?php echo (isset($_POST['request_type']) && $_POST['request_type'] == 'Inspection') ? 'selected' : ''; ?>>Inspection</option>
                            <option value="Emergency" <?php echo (isset($_POST['request_type']) && $_POST['request_type'] == 'Emergency') ? 'selected' : ''; ?>>Emergency</option>
                            <option value="Other" <?php echo (isset($_POST['request_type']) && $_POST['request_type'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                <textarea class="form-control" id="description" name="description" rows="4" required 
                          placeholder="Detailed description of the maintenance issue..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
            </div>
        </div>
    </div>
    
    <!-- Priority and Assignment -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Priority and Assignment</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="priority" class="form-label">Priority <span class="text-danger">*</span></label>
                        <select class="form-select" id="priority" name="priority" required>
                            <option value="">Select Priority</option>
                            <option value="Low" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'Low') ? 'selected' : ''; ?>>Low</option>
                            <option value="Medium" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'Medium') ? 'selected' : ''; ?>>Medium</option>
                            <option value="High" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'High') ? 'selected' : ''; ?>>High</option>
                            <option value="Urgent" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'Urgent') ? 'selected' : ''; ?>>Urgent</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="assigned_to" class="form-label">Assign To</label>
                        <select class="form-select" id="assigned_to" name="assigned_to">
                            <option value="">Unassigned</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo (isset($_POST['assigned_to']) && $_POST['assigned_to'] == $user['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="estimated_cost" class="form-label">Estimated Cost</label>
                        <div class="input-group">
                            <span class="input-group-text">₦</span>
                            <input type="number" class="form-control" id="estimated_cost" name="estimated_cost" 
                                   min="0" step="0.01" 
                                   value="<?php echo isset($_POST['estimated_cost']) ? htmlspecialchars($_POST['estimated_cost']) : ''; ?>">
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="requires_entry" class="form-label">Requires Property Entry</label>
                        <select class="form-select" id="requires_entry" name="requires_entry">
                            <option value="0" <?php echo (!isset($_POST['requires_entry']) || $_POST['requires_entry'] == '0') ? 'selected' : ''; ?>>No</option>
                            <option value="1" <?php echo (isset($_POST['requires_entry']) && $_POST['requires_entry'] == '1') ? 'selected' : ''; ?>>Yes</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="entry_permission_granted" class="form-label">Entry Permission Granted</label>
                        <select class="form-select" id="entry_permission_granted" name="entry_permission_granted">
                            <option value="0" <?php echo (!isset($_POST['entry_permission_granted']) || $_POST['entry_permission_granted'] == '0') ? 'selected' : ''; ?>>No</option>
                            <option value="1" <?php echo (isset($_POST['entry_permission_granted']) && $_POST['entry_permission_granted'] == '1') ? 'selected' : ''; ?>>Yes</option>
                        </select>
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
                    <i class="icon-save"></i> Create Request
                </button>
            </div>
        </div>
    </div>
</form>

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

// Auto-enable entry permission if requires entry is yes
document.getElementById('requires_entry').addEventListener('change', function() {
    if (this.value === '1') {
        document.getElementById('entry_permission_granted').value = '1';
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
