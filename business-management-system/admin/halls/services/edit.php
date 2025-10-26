<?php
/**
 * Business Management System - Edit Hall Service
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
requirePermission('halls.edit');

// Get service ID
$serviceId = (int)($_GET['id'] ?? 0);

if (!$serviceId) {
    header('Location: index.php');
    exit;
}

// Get database connection
$conn = getDB();

// Get service details
$stmt = $conn->prepare("SELECT * FROM " . DB_PREFIX . "hall_services WHERE id = ?");
$stmt->bind_param('i', $serviceId);
$stmt->execute();
$service = $stmt->get_result()->fetch_assoc();

if (!$service) {
    header('Location: index.php');
    exit;
}

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
        
        // Check if service name already exists (excluding current service)
        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT id FROM " . DB_PREFIX . "hall_services WHERE service_name = ? AND id != ?");
            $stmt->bind_param('si', $serviceName, $serviceId);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) {
                $errors[] = 'Service name already exists';
            }
        }
        
        // Update service if no errors
        if (empty($errors)) {
            $stmt = $conn->prepare("
                UPDATE " . DB_PREFIX . "hall_services 
                SET service_name = ?, description = ?, price = ?, unit = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->bind_param('ssdsii', $serviceName, $description, $price, $unit, $isActive, $serviceId);
            
            if ($stmt->execute()) {
                $success = true;
                // Update local service data
                $service['service_name'] = $serviceName;
                $service['description'] = $description;
                $service['price'] = $price;
                $service['unit'] = $unit;
                $service['is_active'] = $isActive;
            } else {
                $errors[] = 'Failed to update service. Please try again.';
            }
        }
    }
}

// Set page title
$pageTitle = 'Edit Hall Service';

include '../../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Edit Hall Service</h1>
        <p>Update service information</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Services
        </a>
        <a href="view.php?id=<?php echo $serviceId; ?>" class="btn btn-outline-primary">
            <i class="icon-eye"></i> View Service
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
                
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="icon-check-circle"></i> Service updated successfully!
                </div>
                <?php endif; ?>
                
                <form method="POST" class="service-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-group">
                        <label for="service_name">Service Name *</label>
                        <input type="text" name="service_name" id="service_name" 
                               value="<?php echo htmlspecialchars($service['service_name']); ?>" 
                               class="form-control" required>
                        <small class="form-text text-muted">Enter a descriptive name for the service</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" 
                                  rows="4" class="form-control" 
                                  placeholder="Describe what this service includes..."><?php echo htmlspecialchars($service['description']); ?></textarea>
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
                                           value="<?php echo $service['price']; ?>" 
                                           step="0.01" min="0" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="unit">Unit *</label>
                                <select name="unit" id="unit" class="form-control" required>
                                    <option value="">Select Unit</option>
                                    <option value="Per Event" <?php echo $service['unit'] == 'Per Event' ? 'selected' : ''; ?>>Per Event</option>
                                    <option value="Per Hour" <?php echo $service['unit'] == 'Per Hour' ? 'selected' : ''; ?>>Per Hour</option>
                                    <option value="Per Day" <?php echo $service['unit'] == 'Per Day' ? 'selected' : ''; ?>>Per Day</option>
                                    <option value="Per Person" <?php echo $service['unit'] == 'Per Person' ? 'selected' : ''; ?>>Per Person</option>
                                    <option value="Per Item" <?php echo $service['unit'] == 'Per Item' ? 'selected' : ''; ?>>Per Item</option>
                                    <option value="Fixed" <?php echo $service['unit'] == 'Fixed' ? 'selected' : ''; ?>>Fixed</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="is_active" 
                                   class="form-check-input" 
                                   <?php echo $service['is_active'] ? 'checked' : ''; ?>>
                            <label for="is_active" class="form-check-label">
                                Active Service
                            </label>
                            <small class="form-text text-muted">Active services can be selected when booking halls</small>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="icon-save"></i> Update Service
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
                <h5><i class="icon-info-circle"></i> Service Details</h5>
            </div>
            <div class="card-body">
                <div class="service-info">
                    <div class="info-item">
                        <strong>Created:</strong>
                        <span><?php echo date('M d, Y g:i A', strtotime($service['created_at'])); ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Last Updated:</strong>
                        <span><?php echo date('M d, Y g:i A', strtotime($service['updated_at'])); ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Status:</strong>
                        <span class="badge <?php echo $service['is_active'] ? 'badge-success' : 'badge-secondary'; ?>">
                            <?php echo $service['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5><i class="icon-chart-bar"></i> Usage Statistics</h5>
            </div>
            <div class="card-body">
                <?php
                // Get usage statistics
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as times_used, SUM(line_total) as total_revenue
                    FROM " . DB_PREFIX . "hall_booking_items 
                    WHERE service_id = ?
                ");
                $stmt->bind_param('i', $serviceId);
                $stmt->execute();
                $stats = $stmt->get_result()->fetch_assoc();
                ?>
                
                <div class="stats-item">
                    <strong>Times Used:</strong>
                    <span><?php echo $stats['times_used']; ?></span>
                </div>
                <div class="stats-item">
                    <strong>Total Revenue:</strong>
                    <span><?php echo formatCurrency($stats['total_revenue']); ?></span>
                </div>
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

<style>
.service-info .info-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.service-info .info-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.stats-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.stats-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
}
</style>

<?php include '../../../includes/footer.php'; ?>
