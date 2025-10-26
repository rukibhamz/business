<?php
/**
 * Business Management System - Add Hall
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
requirePermission('halls.create');

// Get database connection
$conn = getDB();

// Initialize variables
$errors = [];
$success = false;
$hallData = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        // Sanitize and validate input
        $hallData = [
            'hall_name' => trim($_POST['hall_name'] ?? ''),
            'category_id' => (int)($_POST['category_id'] ?? 0),
            'description' => trim($_POST['description'] ?? ''),
            'capacity' => (int)($_POST['capacity'] ?? 0),
            'area_sqft' => (float)($_POST['area_sqft'] ?? 0),
            'location' => trim($_POST['location'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'hourly_rate' => (float)($_POST['hourly_rate'] ?? 0),
            'daily_rate' => (float)($_POST['daily_rate'] ?? 0),
            'weekly_rate' => (float)($_POST['weekly_rate'] ?? 0),
            'monthly_rate' => (float)($_POST['monthly_rate'] ?? 0),
            'currency' => trim($_POST['currency'] ?? 'NGN'),
            'status' => trim($_POST['status'] ?? 'Available'),
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
            'enable_booking' => isset($_POST['enable_booking']) ? 1 : 0,
            'booking_advance_days' => (int)($_POST['booking_advance_days'] ?? 30),
            'cancellation_policy' => trim($_POST['cancellation_policy'] ?? ''),
            'terms_conditions' => trim($_POST['terms_conditions'] ?? '')
        ];

        // Validation
        if (empty($hallData['hall_name'])) {
            $errors[] = 'Hall name is required.';
        }

        if ($hallData['category_id'] <= 0) {
            $errors[] = 'Please select a category.';
        }

        if ($hallData['capacity'] <= 0) {
            $errors[] = 'Capacity must be greater than 0.';
        }

        if ($hallData['hourly_rate'] <= 0 && $hallData['daily_rate'] <= 0 && 
            $hallData['weekly_rate'] <= 0 && $hallData['monthly_rate'] <= 0) {
            $errors[] = 'At least one pricing rate must be set.';
        }

        // Check if hall name already exists
        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT id FROM " . DB_PREFIX . "halls WHERE hall_name = ?");
            $stmt->bind_param('s', $hallData['hall_name']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = 'A hall with this name already exists.';
            }
        }

        // Handle file uploads
        $featuredImage = null;
        $galleryImages = [];

        if (empty($errors)) {
            // Handle featured image upload
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = UPLOADS_PATH . '/halls/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $fileExtension = strtolower(pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (in_array($fileExtension, $allowedExtensions)) {
                    $fileName = 'hall_' . time() . '_featured.' . $fileExtension;
                    $filePath = $uploadDir . $fileName;

                    if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $filePath)) {
                        $featuredImage = 'uploads/halls/' . $fileName;
                    } else {
                        $errors[] = 'Failed to upload featured image.';
                    }
                } else {
                    $errors[] = 'Invalid file type for featured image. Allowed: ' . implode(', ', $allowedExtensions);
                }
            }

            // Handle gallery images upload
            if (isset($_FILES['gallery_images']) && !empty($_FILES['gallery_images']['name'][0])) {
                $uploadDir = UPLOADS_PATH . '/halls/gallery/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $maxFiles = 10;

                for ($i = 0; $i < min(count($_FILES['gallery_images']['name']), $maxFiles); $i++) {
                    if ($_FILES['gallery_images']['error'][$i] === UPLOAD_ERR_OK) {
                        $fileExtension = strtolower(pathinfo($_FILES['gallery_images']['name'][$i], PATHINFO_EXTENSION));

                        if (in_array($fileExtension, $allowedExtensions)) {
                            $fileName = 'hall_' . time() . '_gallery_' . $i . '.' . $fileExtension;
                            $filePath = $uploadDir . $fileName;

                            if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $filePath)) {
                                $galleryImages[] = 'uploads/halls/gallery/' . $fileName;
                            }
                        }
                    }
                }
            }
        }

        // Insert hall if no errors
        if (empty($errors)) {
            try {
                $conn->begin_transaction();

                // Generate hall code
                $hallCode = generateHallCode();

                // Insert hall
                $stmt = $conn->prepare("
                    INSERT INTO " . DB_PREFIX . "halls (
                        hall_code, hall_name, category_id, description, capacity, area_sqft,
                        location, address, hourly_rate, daily_rate, weekly_rate, monthly_rate,
                        currency, status, is_featured, enable_booking, booking_advance_days,
                        cancellation_policy, terms_conditions, featured_image, gallery_images,
                        created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $galleryImagesJson = json_encode($galleryImages);
                $createdBy = $_SESSION['user_id'];

                $stmt->bind_param(
                    'ssisissddddsssiisssssi',
                    $hallCode,
                    $hallData['hall_name'],
                    $hallData['category_id'],
                    $hallData['description'],
                    $hallData['capacity'],
                    $hallData['area_sqft'],
                    $hallData['location'],
                    $hallData['address'],
                    $hallData['hourly_rate'],
                    $hallData['daily_rate'],
                    $hallData['weekly_rate'],
                    $hallData['monthly_rate'],
                    $hallData['currency'],
                    $hallData['status'],
                    $hallData['is_featured'],
                    $hallData['enable_booking'],
                    $hallData['booking_advance_days'],
                    $hallData['cancellation_policy'],
                    $hallData['terms_conditions'],
                    $featuredImage,
                    $galleryImagesJson,
                    $createdBy
                );

                if ($stmt->execute()) {
                    $hallId = $conn->getConnection()->lastInsertId();

                    // Insert default booking periods
                    $periods = [
                        ['Hourly', 'Hourly', $hallData['hourly_rate'], 1, null, 1],
                        ['Daily', 'Daily', $hallData['daily_rate'], 1, null, 2],
                        ['Weekly', 'Weekly', $hallData['weekly_rate'], 1, null, 3],
                        ['Monthly', 'Monthly', $hallData['monthly_rate'], 1, null, 4]
                    ];

                    $periodStmt = $conn->prepare("
                        INSERT INTO " . DB_PREFIX . "hall_booking_periods (
                            hall_id, period_name, period_type, price, min_duration, max_duration, is_active, sort_order
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    foreach ($periods as $period) {
                        if ($period[2] > 0) { // Only insert if price > 0
                            $periodStmt->bind_param('issddii', $hallId, $period[0], $period[1], $period[2], $period[3], $period[4], $period[5], $period[6]);
                            $periodStmt->execute();
                        }
                    }

                    // Log activity
                    logActivity("Hall created: {$hallData['hall_name']}", $hallId, 'hall');

                    $conn->commit();
                    $success = true;

                    // Redirect to hall view page
                    header("Location: view.php?id={$hallId}&success=1");
                    exit;
                } else {
                    throw new Exception('Failed to insert hall: ' . $conn->getLastError());
                }
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Get hall categories
$categories = $conn->query("
    SELECT id, category_name 
    FROM " . DB_PREFIX . "hall_categories 
    WHERE is_active = 1 
    ORDER BY category_name
")->fetch_all(MYSQLI_ASSOC);

// Set page title
$pageTitle = 'Add Hall';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Add Hall</h1>
        <p>Create a new hall for booking</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Halls
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

<form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    
    <div class="row">
        <!-- Basic Information -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3>Basic Information</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="hall_name">Hall Name <span class="text-danger">*</span></label>
                                <input type="text" id="hall_name" name="hall_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($hallData['hall_name'] ?? ''); ?>" 
                                       required>
                                <div class="invalid-feedback">
                                    Please provide a hall name.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="category_id">Category <span class="text-danger">*</span></label>
                                <select id="category_id" name="category_id" class="form-control" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                            <?php echo ($hallData['category_id'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">
                                    Please select a category.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="4" 
                                  placeholder="Describe the hall, its features, and any special characteristics"><?php echo htmlspecialchars($hallData['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="capacity">Capacity <span class="text-danger">*</span></label>
                                <input type="number" id="capacity" name="capacity" class="form-control" 
                                       value="<?php echo htmlspecialchars($hallData['capacity'] ?? ''); ?>" 
                                       min="1" required>
                                <small class="form-text text-muted">Maximum number of people</small>
                                <div class="invalid-feedback">
                                    Please provide a valid capacity.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="area_sqft">Area (sq ft)</label>
                                <input type="number" id="area_sqft" name="area_sqft" class="form-control" 
                                       value="<?php echo htmlspecialchars($hallData['area_sqft'] ?? ''); ?>" 
                                       step="0.01" min="0">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" class="form-control" 
                               value="<?php echo htmlspecialchars($hallData['location'] ?? ''); ?>" 
                               placeholder="e.g., Lagos, Abuja, Port Harcourt">
                    </div>

                    <div class="form-group">
                        <label for="address">Full Address</label>
                        <textarea id="address" name="address" class="form-control" rows="3" 
                                  placeholder="Complete address including street, city, state"><?php echo htmlspecialchars($hallData['address'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Pricing -->
            <div class="card">
                <div class="card-header">
                    <h3>Pricing</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="hourly_rate">Hourly Rate (<?php echo CURRENCY; ?>)</label>
                                <input type="number" id="hourly_rate" name="hourly_rate" class="form-control" 
                                       value="<?php echo htmlspecialchars($hallData['hourly_rate'] ?? ''); ?>" 
                                       step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="daily_rate">Daily Rate (<?php echo CURRENCY; ?>)</label>
                                <input type="number" id="daily_rate" name="daily_rate" class="form-control" 
                                       value="<?php echo htmlspecialchars($hallData['daily_rate'] ?? ''); ?>" 
                                       step="0.01" min="0">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="weekly_rate">Weekly Rate (<?php echo CURRENCY; ?>)</label>
                                <input type="number" id="weekly_rate" name="weekly_rate" class="form-control" 
                                       value="<?php echo htmlspecialchars($hallData['weekly_rate'] ?? ''); ?>" 
                                       step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="monthly_rate">Monthly Rate (<?php echo CURRENCY; ?>)</label>
                                <input type="number" id="monthly_rate" name="monthly_rate" class="form-control" 
                                       value="<?php echo htmlspecialchars($hallData['monthly_rate'] ?? ''); ?>" 
                                       step="0.01" min="0">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="currency">Currency</label>
                        <select id="currency" name="currency" class="form-control">
                            <option value="NGN" <?php echo ($hallData['currency'] ?? 'NGN') == 'NGN' ? 'selected' : ''; ?>>NGN (Nigerian Naira)</option>
                            <option value="USD" <?php echo ($hallData['currency'] ?? '') == 'USD' ? 'selected' : ''; ?>>USD (US Dollar)</option>
                            <option value="EUR" <?php echo ($hallData['currency'] ?? '') == 'EUR' ? 'selected' : ''; ?>>EUR (Euro)</option>
                            <option value="GBP" <?php echo ($hallData['currency'] ?? '') == 'GBP' ? 'selected' : ''; ?>>GBP (British Pound)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Images -->
            <div class="card">
                <div class="card-header">
                    <h3>Images</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="featured_image">Featured Image</label>
                        <input type="file" id="featured_image" name="featured_image" class="form-control-file" 
                               accept="image/*">
                        <small class="form-text text-muted">
                            Main image for the hall. Recommended size: 800x600px. Max size: 5MB.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="gallery_images">Gallery Images</label>
                        <input type="file" id="gallery_images" name="gallery_images[]" class="form-control-file" 
                               accept="image/*" multiple>
                        <small class="form-text text-muted">
                            Additional images for the hall gallery. You can select multiple files. Max 10 images.
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3>Settings</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="Available" <?php echo ($hallData['status'] ?? 'Available') == 'Available' ? 'selected' : ''; ?>>Available</option>
                            <option value="Maintenance" <?php echo ($hallData['status'] ?? '') == 'Maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                            <option value="Unavailable" <?php echo ($hallData['status'] ?? '') == 'Unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" id="is_featured" name="is_featured" class="form-check-input" 
                                   <?php echo ($hallData['is_featured'] ?? 0) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_featured">
                                Featured Hall
                            </label>
                            <small class="form-text text-muted">Show this hall prominently on the website</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" id="enable_booking" name="enable_booking" class="form-check-input" 
                                   <?php echo ($hallData['enable_booking'] ?? 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="enable_booking">
                                Enable Online Booking
                            </label>
                            <small class="form-text text-muted">Allow customers to book this hall online</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="booking_advance_days">Booking Advance Days</label>
                        <input type="number" id="booking_advance_days" name="booking_advance_days" class="form-control" 
                               value="<?php echo htmlspecialchars($hallData['booking_advance_days'] ?? '30'); ?>" 
                               min="1" max="365">
                        <small class="form-text text-muted">How many days in advance can customers book</small>
                    </div>
                </div>
            </div>

            <!-- Policies -->
            <div class="card">
                <div class="card-header">
                    <h3>Policies</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="cancellation_policy">Cancellation Policy</label>
                        <textarea id="cancellation_policy" name="cancellation_policy" class="form-control" rows="4" 
                                  placeholder="Describe the cancellation policy for this hall"><?php echo htmlspecialchars($hallData['cancellation_policy'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="terms_conditions">Terms & Conditions</label>
                        <textarea id="terms_conditions" name="terms_conditions" class="form-control" rows="4" 
                                  placeholder="Terms and conditions for booking this hall"><?php echo htmlspecialchars($hallData['terms_conditions'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <i class="icon-save"></i> Create Hall
        </button>
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-times"></i> Cancel
        </a>
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

// Pricing validation
document.addEventListener('DOMContentLoaded', function() {
    const pricingInputs = ['hourly_rate', 'daily_rate', 'weekly_rate', 'monthly_rate'];
    const form = document.querySelector('form');
    
    form.addEventListener('submit', function(e) {
        let hasPricing = false;
        
        pricingInputs.forEach(inputId => {
            const input = document.getElementById(inputId);
            if (input && parseFloat(input.value) > 0) {
                hasPricing = true;
            }
        });
        
        if (!hasPricing) {
            e.preventDefault();
            alert('Please set at least one pricing rate.');
        }
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
</style>

<?php include '../../includes/footer.php'; ?>
