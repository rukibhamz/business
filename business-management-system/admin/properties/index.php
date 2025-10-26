<?php
/**
 * Business Management System - Property List
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
requirePermission('properties.view');

// Get database connection
$conn = getDB();

// Handle filters
$filters = [];
if (isset($_GET['property_type']) && !empty($_GET['property_type'])) {
    $filters['property_type'] = (int)$_GET['property_type'];
}
if (isset($_GET['availability_status']) && !empty($_GET['availability_status'])) {
    $filters['availability_status'] = $_GET['availability_status'];
}
if (isset($_GET['min_rent']) && !empty($_GET['min_rent'])) {
    $filters['min_rent'] = (float)$_GET['min_rent'];
}
if (isset($_GET['max_rent']) && !empty($_GET['max_rent'])) {
    $filters['max_rent'] = (float)$_GET['max_rent'];
}
if (isset($_GET['city']) && !empty($_GET['city'])) {
    $filters['city'] = $_GET['city'];
}
if (isset($_GET['bedrooms']) && !empty($_GET['bedrooms'])) {
    $filters['bedrooms'] = (int)$_GET['bedrooms'];
}
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

// Get properties
$sql = "SELECT p.*, pt.type_name, 
               (SELECT COUNT(*) FROM " . DB_PREFIX . "leases l WHERE l.property_id = p.id AND l.lease_status = 'Active') as active_leases,
               (SELECT CONCAT(t.first_name, ' ', t.last_name) FROM " . DB_PREFIX . "leases l 
                JOIN " . DB_PREFIX . "tenants t ON l.tenant_id = t.id 
                WHERE l.property_id = p.id AND l.lease_status = 'Active' LIMIT 1) as current_tenant
        FROM " . DB_PREFIX . "properties p
        JOIN " . DB_PREFIX . "property_types pt ON p.property_type_id = pt.id
        WHERE p.property_status = 'Active'";

$params = [];
$types = '';

if (!empty($filters['property_type'])) {
    $sql .= " AND p.property_type_id = ?";
    $params[] = $filters['property_type'];
    $types .= 'i';
}

if (!empty($filters['availability_status'])) {
    $sql .= " AND p.availability_status = ?";
    $params[] = $filters['availability_status'];
    $types .= 's';
}

if (!empty($filters['min_rent'])) {
    $sql .= " AND p.monthly_rent >= ?";
    $params[] = $filters['min_rent'];
    $types .= 'd';
}

if (!empty($filters['max_rent'])) {
    $sql .= " AND p.monthly_rent <= ?";
    $params[] = $filters['max_rent'];
    $types .= 'd';
}

if (!empty($filters['city'])) {
    $sql .= " AND p.city = ?";
    $params[] = $filters['city'];
    $types .= 's';
}

if (!empty($filters['bedrooms'])) {
    $sql .= " AND p.bedrooms >= ?";
    $params[] = $filters['bedrooms'];
    $types .= 'i';
}

if (!empty($filters['search'])) {
    $sql .= " AND (p.property_name LIKE ? OR p.property_code LIKE ? OR p.address LIKE ?)";
    $searchTerm = '%' . $filters['search'] . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

$sql .= " ORDER BY p.is_featured DESC, p.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$properties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get property types for filter
$stmt = $conn->prepare("SELECT * FROM " . DB_PREFIX . "property_types WHERE is_active = 1 ORDER BY display_order");
$stmt->execute();
$propertyTypes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get cities for filter
$stmt = $conn->prepare("SELECT DISTINCT city FROM " . DB_PREFIX . "properties WHERE city IS NOT NULL AND city != '' ORDER BY city");
$stmt->execute();
$cities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_properties,
        SUM(CASE WHEN availability_status = 'Available' THEN 1 ELSE 0 END) as available_properties,
        SUM(CASE WHEN availability_status = 'Occupied' THEN 1 ELSE 0 END) as occupied_properties,
        SUM(CASE WHEN availability_status = 'Under Maintenance' THEN 1 ELSE 0 END) as maintenance_properties,
        SUM(CASE WHEN availability_status = 'Occupied' THEN monthly_rent ELSE 0 END) as monthly_revenue
    FROM " . DB_PREFIX . "properties 
    WHERE property_status = 'Active'
");
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$occupancyRate = $stats['total_properties'] > 0 ? 
    round(($stats['occupied_properties'] / $stats['total_properties']) * 100, 1) : 0;

// Set page title
$pageTitle = 'Property Management';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Property Management</h1>
        <p>Manage rental properties, tenants, and leases</p>
    </div>
    <div class="page-actions">
        <?php if (hasPermission('properties.create')): ?>
        <a href="add.php" class="btn btn-primary">
            <i class="icon-plus"></i> Add Property
        </a>
        <?php endif; ?>
        <a href="reports/" class="btn btn-secondary">
            <i class="icon-chart"></i> Reports
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['total_properties']; ?></h4>
                        <p class="mb-0">Total Properties</p>
                    </div>
                    <div class="align-self-center">
                        <i class="icon-home fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['occupied_properties']; ?></h4>
                        <p class="mb-0">Occupied (<?php echo $occupancyRate; ?>%)</p>
                    </div>
                    <div class="align-self-center">
                        <i class="icon-user fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['available_properties']; ?></h4>
                        <p class="mb-0">Available</p>
                    </div>
                    <div class="align-self-center">
                        <i class="icon-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo formatCurrency($stats['monthly_revenue']); ?></h4>
                        <p class="mb-0">Monthly Revenue</p>
                    </div>
                    <div class="align-self-center">
                        <i class="icon-dollar-sign fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h5>Filter Properties</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="property_type" class="form-label">Property Type</label>
                <select name="property_type" id="property_type" class="form-select">
                    <option value="">All Types</option>
                    <?php foreach ($propertyTypes as $type): ?>
                    <option value="<?php echo $type['id']; ?>" <?php echo (isset($filters['property_type']) && $filters['property_type'] == $type['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($type['type_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="availability_status" class="form-label">Availability</label>
                <select name="availability_status" id="availability_status" class="form-select">
                    <option value="">All Status</option>
                    <option value="Available" <?php echo (isset($filters['availability_status']) && $filters['availability_status'] == 'Available') ? 'selected' : ''; ?>>Available</option>
                    <option value="Occupied" <?php echo (isset($filters['availability_status']) && $filters['availability_status'] == 'Occupied') ? 'selected' : ''; ?>>Occupied</option>
                    <option value="Under Maintenance" <?php echo (isset($filters['availability_status']) && $filters['availability_status'] == 'Under Maintenance') ? 'selected' : ''; ?>>Under Maintenance</option>
                    <option value="Reserved" <?php echo (isset($filters['availability_status']) && $filters['availability_status'] == 'Reserved') ? 'selected' : ''; ?>>Reserved</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="min_rent" class="form-label">Min Rent</label>
                <input type="number" name="min_rent" id="min_rent" class="form-control" 
                       value="<?php echo isset($filters['min_rent']) ? $filters['min_rent'] : ''; ?>" 
                       placeholder="0">
            </div>
            <div class="col-md-2">
                <label for="max_rent" class="form-label">Max Rent</label>
                <input type="number" name="max_rent" id="max_rent" class="form-control" 
                       value="<?php echo isset($filters['max_rent']) ? $filters['max_rent'] : ''; ?>" 
                       placeholder="0">
            </div>
            <div class="col-md-2">
                <label for="city" class="form-label">City</label>
                <select name="city" id="city" class="form-select">
                    <option value="">All Cities</option>
                    <?php foreach ($cities as $city): ?>
                    <option value="<?php echo htmlspecialchars($city['city']); ?>" <?php echo (isset($filters['city']) && $filters['city'] == $city['city']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($city['city']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="bedrooms" class="form-label">Min Bedrooms</label>
                <select name="bedrooms" id="bedrooms" class="form-select">
                    <option value="">Any</option>
                    <option value="1" <?php echo (isset($filters['bedrooms']) && $filters['bedrooms'] == 1) ? 'selected' : ''; ?>>1+</option>
                    <option value="2" <?php echo (isset($filters['bedrooms']) && $filters['bedrooms'] == 2) ? 'selected' : ''; ?>>2+</option>
                    <option value="3" <?php echo (isset($filters['bedrooms']) && $filters['bedrooms'] == 3) ? 'selected' : ''; ?>>3+</option>
                    <option value="4" <?php echo (isset($filters['bedrooms']) && $filters['bedrooms'] == 4) ? 'selected' : ''; ?>>4+</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="search" class="form-label">Search</label>
                <input type="text" name="search" id="search" class="form-control" 
                       value="<?php echo isset($filters['search']) ? htmlspecialchars($filters['search']) : ''; ?>" 
                       placeholder="Search by name, code, or address">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <a href="index.php" class="btn btn-secondary">Clear</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Properties Grid -->
<div class="row">
    <?php if (empty($properties)): ?>
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="icon-home fa-3x text-muted mb-3"></i>
                <h4>No Properties Found</h4>
                <p class="text-muted">No properties match your current filters.</p>
                <?php if (hasPermission('properties.create')): ?>
                <a href="add.php" class="btn btn-primary">Add First Property</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php else: ?>
    <?php foreach ($properties as $property): ?>
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card property-card h-100">
            <?php if ($property['featured_image']): ?>
            <img src="<?php echo htmlspecialchars($property['featured_image']); ?>" 
                 class="card-img-top property-image" alt="<?php echo htmlspecialchars($property['property_name']); ?>">
            <?php else: ?>
            <div class="card-img-top property-image-placeholder d-flex align-items-center justify-content-center">
                <i class="icon-home fa-3x text-muted"></i>
            </div>
            <?php endif; ?>
            
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h5 class="card-title mb-0"><?php echo htmlspecialchars($property['property_name']); ?></h5>
                    <?php if ($property['is_featured']): ?>
                    <span class="badge bg-warning">Featured</span>
                    <?php endif; ?>
                </div>
                
                <p class="text-muted small mb-2">
                    <i class="icon-tag"></i> <?php echo htmlspecialchars($property['type_name']); ?>
                    <span class="ms-2"><?php echo htmlspecialchars($property['property_code']); ?></span>
                </p>
                
                <p class="text-muted small mb-2">
                    <i class="icon-map-pin"></i> <?php echo htmlspecialchars($property['city'] . ', ' . $property['state']); ?>
                </p>
                
                <?php if ($property['bedrooms']): ?>
                <p class="text-muted small mb-2">
                    <i class="icon-bed"></i> <?php echo $property['bedrooms']; ?> bed
                    <?php if ($property['bathrooms']): ?>
                    <span class="ms-2"><i class="icon-bath"></i> <?php echo $property['bathrooms']; ?> bath</span>
                    <?php endif; ?>
                </p>
                <?php endif; ?>
                
                <div class="mt-auto">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="h5 text-primary mb-0"><?php echo formatCurrency($property['monthly_rent']); ?></span>
                        <span class="badge <?php echo getAvailabilityBadgeClass($property['availability_status']); ?>">
                            <?php echo $property['availability_status']; ?>
                        </span>
                    </div>
                    
                    <?php if ($property['current_tenant']): ?>
                    <p class="text-muted small mb-2">
                        <i class="icon-user"></i> <?php echo htmlspecialchars($property['current_tenant']); ?>
                    </p>
                    <?php endif; ?>
                    
                    <div class="btn-group w-100" role="group">
                        <a href="view.php?id=<?php echo $property['id']; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="icon-eye"></i> View
                        </a>
                        <?php if (hasPermission('properties.edit')): ?>
                        <a href="edit.php?id=<?php echo $property['id']; ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="icon-edit"></i> Edit
                        </a>
                        <?php endif; ?>
                        <?php if ($property['availability_status'] == 'Available' && hasPermission('leases.create')): ?>
                        <a href="leases/add.php?property_id=<?php echo $property['id']; ?>" class="btn btn-outline-success btn-sm">
                            <i class="icon-file-plus"></i> Lease
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
// Helper function for availability badge class
function getAvailabilityBadgeClass($status) {
    switch ($status) {
        case 'Available': return 'bg-success';
        case 'Occupied': return 'bg-primary';
        case 'Under Maintenance': return 'bg-warning';
        case 'Reserved': return 'bg-info';
        default: return 'bg-secondary';
    }
}
?>

<style>
.property-card {
    transition: transform 0.2s ease-in-out;
}

.property-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.property-image {
    height: 200px;
    object-fit: cover;
}

.property-image-placeholder {
    height: 200px;
    background-color: #f8f9fa;
}

.card-img-top {
    border-radius: 0.375rem 0.375rem 0 0;
}

.btn-group .btn {
    flex: 1;
}
</style>

<?php include '../../includes/footer.php'; ?>
