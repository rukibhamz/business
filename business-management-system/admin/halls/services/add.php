<?php
/**
 * Business Management System - Add Hall Service
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
requirePermission('halls.create');

// Get database connection
$conn = getDB();

// Process form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        // Get form data
        $serviceName = trim($_POST['service_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $unit = trim($_POST['unit'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        // Validation
        if (empty($serviceName)) {
            $errors[] = 'Service name is required';
        }
        
        if ($price <= 0) {
            $errors[] = 'Price must be greater than 0';
        }
        
        if (empty($unit)) {
            $errors[] = 'Unit is required';
        }
        
        // Check if service name already exists
        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT id FROM " . DB_PREFIX . "hall_services WHERE service_name = ?");
            $stmt->bind_param('s', $serviceName);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) {
                $errors[] = 'Service name already exists';
            }
        }
        
        // Insert service if no errors
        if (empty($errors)) {
            $stmt = $conn->prepare("
                INSERT INTO " . DB_PREFIX . "hall_services 
                (service_name, description, price, unit, is_active, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $userId = $_SESSION['user_id'];
            $stmt->bind_param('ssdsi', $serviceName, $description, $price, $unit, $isActive, $userId);
            
            if ($stmt->execute()) {
                $success = true;
                // Redirect to services list
                header('Location: index.php?success=1');
                exit;
            } else {
                $errors[] = 'Failed to create service. Please try again.';
            }
        }
    }
}

// Set page title
$pageTitle = 'Add Hall Service';

include '../../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Add Hall Service</h1>
        <p>Create a new service or amenity</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Services
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h4><i class="icon-exclamation-triangle"></i> Please correct the following errors:</h4>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <form method="POST" class="service-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-group">
                        <label for="service_name">Service Name *</label>
                        <input type="text" name="service_name" id="service_name" 
                               value="<?php echo htmlspecialchars($_POST['service_name'] ?? ''); ?>" 
                               class="form-control" required>
                        <small class="form-text text-muted">Enter a descriptive name for the service</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" 
                                  rows="4" class="form-control" 
                                  placeholder="Describe what this service includes..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="price">Price *</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₦</span>
                                    </div>
                                    <input type="number" name="price" id="price" 
                                           value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>" 
                                           step="0.01" min="0" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="unit">Unit *</label>
                                <select name="unit" id="unit" class="form-control" required>
                                    <option value="">Select Unit</option>
                                    <option value="Per Event" <?php echo ($_POST['unit'] ?? '') == 'Per Event' ? 'selected' : ''; ?>>Per Event</option>
                                    <option value="Per Hour" <?php echo ($_POST['unit'] ?? '') == 'Per Hour' ? 'selected' : ''; ?>>Per Hour</option>
                                    <option value="Per Day" <?php echo ($_POST['unit'] ?? '') == 'Per Day' ? 'selected' : ''; ?>>Per Day</option>
                                    <option value="Per Person" <?php echo ($_POST['unit'] ?? '') == 'Per Person' ? 'selected' : ''; ?>>Per Person</option>
                                    <option value="Per Item" <?php echo ($_POST['unit'] ?? '') == 'Per Item' ? 'selected' : ''; ?>>Per Item</option>
                                    <option value="Fixed" <?php echo ($_POST['unit'] ?? '') == 'Fixed' ? 'selected' : ''; ?>>Fixed</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="is_active" 
                                   class="form-check-input" 
                                   <?php echo isset($_POST['is_active']) ? 'checked' : 'checked'; ?>>
                            <label for="is_active" class="form-check-label">
                                Active Service
                            </label>
                            <small class="form-text text-muted">Active services can be selected when booking halls</small>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="icon-plus"></i> Create Service
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="icon-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="icon-info-circle"></i> Service Information</h5>
            </div>
            <div class="card-body">
                <p>Services are additional amenities or features that can be added to hall bookings.</p>
                
                <h6>Common Service Types:</h6>
                <ul class="list-unstyled">
                    <li><i class="icon-check text-success"></i> Catering Services</li>
                    <li><i class="icon-check text-success"></i> Audio/Visual Equipment</li>
                    <li><i class="icon-check text-success"></i> Decorations</li>
                    <li><i class="icon-check text-success"></i> Security Services</li>
                    <li><i class="icon-check text-success"></i> Cleaning Services</li>
                    <li><i class="icon-check text-success"></i> Transportation</li>
                </ul>
                
                <h6>Pricing Units:</h6>
                <ul class="list-unstyled">
                    <li><strong>Per Event:</strong> Fixed price for entire event</li>
                    <li><strong>Per Hour:</strong> Charged by hour</li>
                    <li><strong>Per Day:</strong> Charged by day</li>
                    <li><strong>Per Person:</strong> Charged per attendee</li>
                    <li><strong>Per Item:</strong> Charged per item/unit</li>
                    <li><strong>Fixed:</strong> One-time fixed cost</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
document.querySelector('.service-form').addEventListener('submit', function(e) {
    const serviceName = document.getElementById('service_name').value.trim();
    const price = parseFloat(document.getElementById('price').value);
    const unit = document.getElementById('unit').value;
    
    if (!serviceName) {
        e.preventDefault();
        alert('Service name is required');
        return false;
    }
    
    if (price <= 0) {
        e.preventDefault();
        alert('Price must be greater than 0');
        return false;
    }
    
    if (!unit) {
        e.preventDefault();
        alert('Please select a unit');
        return false;
    }
});
</script>

<?php include '../../../includes/footer.php'; ?>
