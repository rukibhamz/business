<?php
/**
 * Business Management System - Add Property
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
requirePermission('properties.create');

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
        $requiredFields = ['property_name', 'property_type_id', 'monthly_rent', 'address'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
        }
        
        // Validate monthly rent
        if (!empty($_POST['monthly_rent']) && !is_numeric($_POST['monthly_rent'])) {
            $errors[] = 'Monthly rent must be a valid number.';
        }
        
        // Validate property type
        if (!empty($_POST['property_type_id'])) {
            $stmt = $conn->prepare("SELECT id FROM " . DB_PREFIX . "property_types WHERE id = ? AND is_active = 1");
            $stmt->bind_param('i', $_POST['property_type_id']);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) {
                $errors[] = 'Invalid property type selected.';
            }
        }
        
        if (empty($errors)) {
            $conn->begin_transaction();
            
            try {
                // Generate property code
                $propertyCode = generatePropertyCode();
                
                // Handle file uploads
                $featuredImage = null;
                $galleryImages = [];
                $floorPlanImage = null;
                
                // Upload featured image
                if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = '../../../uploads/properties/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $fileName = time() . '_' . $_FILES['featured_image']['name'];
                    $filePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $filePath)) {
                        $featuredImage = 'uploads/properties/' . $fileName;
                    }
                }
                
                // Upload gallery images
                if (isset($_FILES['gallery_images']) && !empty($_FILES['gallery_images']['name'][0])) {
                    $uploadDir = '../../../uploads/properties/gallery/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $count = count($_FILES['gallery_images']['name']);
                    for ($i = 0; $i < $count; $i++) {
                        if ($_FILES['gallery_images']['error'][$i] === UPLOAD_ERR_OK) {
                            $fileName = time() . '_' . $i . '_' . $_FILES['gallery_images']['name'][$i];
                            $filePath = $uploadDir . $fileName;
                            
                            if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $filePath)) {
                                $galleryImages[] = 'uploads/properties/gallery/' . $fileName;
                            }
                        }
                    }
                }
                
                // Upload floor plan image
                if (isset($_FILES['floor_plan_image']) && $_FILES['floor_plan_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = '../../../uploads/properties/floor-plans/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $fileName = time() . '_floorplan_' . $_FILES['floor_plan_image']['name'];
                    $filePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['floor_plan_image']['tmp_name'], $filePath)) {
                        $floorPlanImage = 'uploads/properties/floor-plans/' . $fileName;
                    }
                }
                
                // Insert property
                $stmt = $conn->prepare("
                    INSERT INTO " . DB_PREFIX . "properties (
                        property_code, property_name, property_type_id, description, address,
                        city, state, country, postal_code, latitude, longitude, bedrooms,
                        bathrooms, size_sqm, floor_number, parking_spaces, furnished,
                        pet_friendly, featured_image, gallery_images, floor_plan_image,
                        monthly_rent, security_deposit, agency_fee, service_charge,
                        currency, availability_status, property_status, year_built,
                        last_renovated, owner_name, owner_contact, notes, is_featured,
                        created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->bind_param('ssissssssddiiissssssddddssssssssssi',
                    $propertyCode,
                    $_POST['property_name'],
                    $_POST['property_type_id'],
                    $_POST['description'],
                    $_POST['address'],
                    $_POST['city'],
                    $_POST['state'],
                    $_POST['country'],
                    $_POST['postal_code'],
                    $_POST['latitude'],
                    $_POST['longitude'],
                    $_POST['bedrooms'],
                    $_POST['bathrooms'],
                    $_POST['size_sqm'],
                    $_POST['floor_number'],
                    $_POST['parking_spaces'],
                    $_POST['furnished'],
                    $_POST['pet_friendly'],
                    $featuredImage,
                    json_encode($galleryImages),
                    $floorPlanImage,
                    $_POST['monthly_rent'],
                    $_POST['security_deposit'],
                    $_POST['agency_fee'],
                    $_POST['service_charge'],
                    $_POST['currency'],
                    $_POST['availability_status'],
                    $_POST['property_status'],
                    $_POST['year_built'],
                    $_POST['last_renovated'],
                    $_POST['owner_name'],
                    $_POST['owner_contact'],
                    $_POST['notes'],
                    $_POST['is_featured'],
                    $_SESSION['user_id']
                );
                
                $stmt->execute();
                /** @var mysqli $conn */
                $propertyId = $conn->insert_id;
                
                // Insert property features
                if (!empty($_POST['features'])) {
                    foreach ($_POST['features'] as $featureId) {
                        $stmt = $conn->prepare("
                            INSERT INTO " . DB_PREFIX . "property_feature_map (property_id, feature_id)
                            VALUES (?, ?)
                        ");
                        $stmt->bind_param('ii', $propertyId, $featureId);
                        $stmt->execute();
                    }
                }
                
                $conn->commit();
                $success = true;
                
                // Redirect to property view
                header('Location: view.php?id=' . $propertyId . '&success=created');
                exit;
                
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = 'Error creating property: ' . $e->getMessage();
            }
        }
    }
}

// Get property types
$stmt = $conn->prepare("SELECT * FROM " . DB_PREFIX . "property_types WHERE is_active = 1 ORDER BY display_order");
$stmt->execute();
$propertyTypes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get property features
$stmt = $conn->prepare("SELECT * FROM " . DB_PREFIX . "property_features ORDER BY feature_category, feature_name");
$stmt->execute();
$features = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Group features by category
$featureCategories = [];
foreach ($features as $feature) {
    $featureCategories[$feature['feature_category']][] = $feature;
}

// Set page title
$pageTitle = 'Add Property';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Add Property</h1>
        <p>Create a new rental property</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Properties
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
    Property created successfully!
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    
    <!-- Tab Navigation -->
    <ul class="nav nav-tabs mb-4" id="propertyTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab">
                Basic Information
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="location-tab" data-bs-toggle="tab" data-bs-target="#location" type="button" role="tab">
                Location
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab">
                Property Details
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="pricing-tab" data-bs-toggle="tab" data-bs-target="#pricing" type="button" role="tab">
                Pricing
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="images-tab" data-bs-toggle="tab" data-bs-target="#images" type="button" role="tab">
                Images
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="features-tab" data-bs-toggle="tab" data-bs-target="#features" type="button" role="tab">
                Features
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="owner-tab" data-bs-toggle="tab" data-bs-target="#owner" type="button" role="tab">
                Owner Info
            </button>
        </li>
    </ul>
    
    <!-- Tab Content -->
    <div class="tab-content" id="propertyTabsContent">
        <!-- Basic Information Tab -->
        <div class="tab-pane fade show active" id="basic" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="property_name" class="form-label">Property Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="property_name" name="property_name" 
                                       value="<?php echo isset($_POST['property_name']) ? htmlspecialchars($_POST['property_name']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="property_type_id" class="form-label">Property Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="property_type_id" name="property_type_id" required>
                                    <option value="">Select Property Type</option>
                                    <?php foreach ($propertyTypes as $type): ?>
                                    <option value="<?php echo $type['id']; ?>" <?php echo (isset($_POST['property_type_id']) && $_POST['property_type_id'] == $type['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['type_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4" 
                                  placeholder="Describe the property..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="property_status" class="form-label">Property Status</label>
                                <select class="form-select" id="property_status" name="property_status">
                                    <option value="Active" <?php echo (!isset($_POST['property_status']) || $_POST['property_status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="Inactive" <?php echo (isset($_POST['property_status']) && $_POST['property_status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="availability_status" class="form-label">Availability Status</label>
                                <select class="form-select" id="availability_status" name="availability_status">
                                    <option value="Available" <?php echo (!isset($_POST['availability_status']) || $_POST['availability_status'] == 'Available') ? 'selected' : ''; ?>>Available</option>
                                    <option value="Occupied" <?php echo (isset($_POST['availability_status']) && $_POST['availability_status'] == 'Occupied') ? 'selected' : ''; ?>>Occupied</option>
                                    <option value="Under Maintenance" <?php echo (isset($_POST['availability_status']) && $_POST['availability_status'] == 'Under Maintenance') ? 'selected' : ''; ?>>Under Maintenance</option>
                                    <option value="Reserved" <?php echo (isset($_POST['availability_status']) && $_POST['availability_status'] == 'Reserved') ? 'selected' : ''; ?>>Reserved</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1" 
                               <?php echo (isset($_POST['is_featured']) && $_POST['is_featured']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_featured">
                            Featured Property
                        </label>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Location Tab -->
        <div class="tab-pane fade" id="location" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="address" name="address" rows="3" required 
                                  placeholder="Enter full address..."><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control" id="city" name="city" 
                                       value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="state" class="form-label">State</label>
                                <input type="text" class="form-control" id="state" name="state" 
                                       value="<?php echo isset($_POST['state']) ? htmlspecialchars($_POST['state']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="country" class="form-label">Country</label>
                                <input type="text" class="form-control" id="country" name="country" 
                                       value="<?php echo isset($_POST['country']) ? htmlspecialchars($_POST['country']) : 'Nigeria'; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="postal_code" class="form-label">Postal Code</label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code" 
                                       value="<?php echo isset($_POST['postal_code']) ? htmlspecialchars($_POST['postal_code']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="latitude" class="form-label">Latitude</label>
                                <input type="number" step="any" class="form-control" id="latitude" name="latitude" 
                                       value="<?php echo isset($_POST['latitude']) ? htmlspecialchars($_POST['latitude']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="longitude" class="form-label">Longitude</label>
                                <input type="number" step="any" class="form-control" id="longitude" name="longitude" 
                                       value="<?php echo isset($_POST['longitude']) ? htmlspecialchars($_POST['longitude']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Property Details Tab -->
        <div class="tab-pane fade" id="details" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="bedrooms" class="form-label">Bedrooms</label>
                                <input type="number" class="form-control" id="bedrooms" name="bedrooms" min="0" 
                                       value="<?php echo isset($_POST['bedrooms']) ? htmlspecialchars($_POST['bedrooms']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="bathrooms" class="form-label">Bathrooms</label>
                                <input type="number" class="form-control" id="bathrooms" name="bathrooms" min="0" step="0.5" 
                                       value="<?php echo isset($_POST['bathrooms']) ? htmlspecialchars($_POST['bathrooms']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="size_sqm" class="form-label">Size (SQM)</label>
                                <input type="number" class="form-control" id="size_sqm" name="size_sqm" min="0" step="0.01" 
                                       value="<?php echo isset($_POST['size_sqm']) ? htmlspecialchars($_POST['size_sqm']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="floor_number" class="form-label">Floor Number</label>
                                <input type="number" class="form-control" id="floor_number" name="floor_number" 
                                       value="<?php echo isset($_POST['floor_number']) ? htmlspecialchars($_POST['floor_number']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="parking_spaces" class="form-label">Parking Spaces</label>
                                <input type="number" class="form-control" id="parking_spaces" name="parking_spaces" min="0" 
                                       value="<?php echo isset($_POST['parking_spaces']) ? htmlspecialchars($_POST['parking_spaces']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="furnished" class="form-label">Furnished Status</label>
                                <select class="form-select" id="furnished" name="furnished">
                                    <option value="Unfurnished" <?php echo (!isset($_POST['furnished']) || $_POST['furnished'] == 'Unfurnished') ? 'selected' : ''; ?>>Unfurnished</option>
                                    <option value="Semi-Furnished" <?php echo (isset($_POST['furnished']) && $_POST['furnished'] == 'Semi-Furnished') ? 'selected' : ''; ?>>Semi-Furnished</option>
                                    <option value="Furnished" <?php echo (isset($_POST['furnished']) && $_POST['furnished'] == 'Furnished') ? 'selected' : ''; ?>>Furnished</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="year_built" class="form-label">Year Built</label>
                                <input type="number" class="form-control" id="year_built" name="year_built" min="1800" max="<?php echo date('Y'); ?>" 
                                       value="<?php echo isset($_POST['year_built']) ? htmlspecialchars($_POST['year_built']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_renovated" class="form-label">Last Renovated</label>
                                <input type="number" class="form-control" id="last_renovated" name="last_renovated" min="1800" max="<?php echo date('Y'); ?>" 
                                       value="<?php echo isset($_POST['last_renovated']) ? htmlspecialchars($_POST['last_renovated']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="pet_friendly" name="pet_friendly" value="1" 
                                       <?php echo (isset($_POST['pet_friendly']) && $_POST['pet_friendly']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="pet_friendly">
                                    Pet Friendly
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pricing Tab -->
        <div class="tab-pane fade" id="pricing" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="monthly_rent" class="form-label">Monthly Rent <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">₦</span>
                                    <input type="number" class="form-control" id="monthly_rent" name="monthly_rent" min="0" step="0.01" required 
                                           value="<?php echo isset($_POST['monthly_rent']) ? htmlspecialchars($_POST['monthly_rent']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="security_deposit" class="form-label">Security Deposit</label>
                                <div class="input-group">
                                    <span class="input-group-text">₦</span>
                                    <input type="number" class="form-control" id="security_deposit" name="security_deposit" min="0" step="0.01" 
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
                                    <span class="input-group-text">₦</span>
                                    <input type="number" class="form-control" id="agency_fee" name="agency_fee" min="0" step="0.01" 
                                           value="<?php echo isset($_POST['agency_fee']) ? htmlspecialchars($_POST['agency_fee']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="service_charge" class="form-label">Service Charge</label>
                                <div class="input-group">
                                    <span class="input-group-text">₦</span>
                                    <input type="number" class="form-control" id="service_charge" name="service_charge" min="0" step="0.01" 
                                           value="<?php echo isset($_POST['service_charge']) ? htmlspecialchars($_POST['service_charge']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
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
        
        <!-- Images Tab -->
        <div class="tab-pane fade" id="images" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="featured_image" class="form-label">Featured Image <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="featured_image" name="featured_image" accept="image/*" required>
                        <div class="form-text">Upload the main property image (max 5MB, JPG/PNG)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="gallery_images" class="form-label">Gallery Images</label>
                        <input type="file" class="form-control" id="gallery_images" name="gallery_images[]" accept="image/*" multiple>
                        <div class="form-text">Upload additional property images (max 10 images, 5MB each)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="floor_plan_image" class="form-label">Floor Plan Image</label>
                        <input type="file" class="form-control" id="floor_plan_image" name="floor_plan_image" accept="image/*">
                        <div class="form-text">Upload floor plan or architectural layout (optional)</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Features Tab -->
        <div class="tab-pane fade" id="features" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <?php foreach ($featureCategories as $category => $categoryFeatures): ?>
                    <div class="mb-4">
                        <h6><?php echo htmlspecialchars($category); ?></h6>
                        <div class="row">
                            <?php foreach ($categoryFeatures as $feature): ?>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="feature_<?php echo $feature['id']; ?>" 
                                           name="features[]" value="<?php echo $feature['id']; ?>"
                                           <?php echo (isset($_POST['features']) && in_array($feature['id'], $_POST['features'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="feature_<?php echo $feature['id']; ?>">
                                        <?php echo htmlspecialchars($feature['feature_name']); ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Owner Info Tab -->
        <div class="tab-pane fade" id="owner" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="owner_name" class="form-label">Owner Name</label>
                                <input type="text" class="form-control" id="owner_name" name="owner_name" 
                                       value="<?php echo isset($_POST['owner_name']) ? htmlspecialchars($_POST['owner_name']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="owner_contact" class="form-label">Owner Contact</label>
                                <input type="text" class="form-control" id="owner_contact" name="owner_contact" 
                                       value="<?php echo isset($_POST['owner_contact']) ? htmlspecialchars($_POST['owner_contact']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Internal Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="4" 
                                  placeholder="Any additional notes about the property..."><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Form Actions -->
    <div class="card mt-4">
        <div class="card-body">
            <div class="d-flex justify-content-between">
                <a href="index.php" class="btn btn-secondary">
                    <i class="icon-arrow-left"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="icon-save"></i> Create Property
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

// Tab validation
document.getElementById('propertyTabs').addEventListener('click', function(e) {
    if (e.target.classList.contains('nav-link')) {
        var activeTab = e.target.getAttribute('data-bs-target');
        var form = document.querySelector('form');
        
        // Check if current tab has required fields
        var currentTabPane = document.querySelector(activeTab);
        var requiredFields = currentTabPane.querySelectorAll('[required]');
        
        var isValid = true;
        requiredFields.forEach(function(field) {
            if (!field.value.trim()) {
                isValid = false;
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('Please fill in all required fields in the current tab before proceeding.');
        }
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
