<?php
/**
 * Business Management System - View Property
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

// Get property ID
$propertyId = (int)($_GET['id'] ?? 0);

if ($propertyId <= 0) {
    header('Location: index.php');
    exit;
}

// Get property details
$stmt = $conn->prepare("
    SELECT p.*, pt.type_name, u.first_name as created_by_first, u.last_name as created_by_last
    FROM " . DB_PREFIX . "properties p
    JOIN " . DB_PREFIX . "property_types pt ON p.property_type_id = pt.id
    LEFT JOIN " . DB_PREFIX . "users u ON p.created_by = u.id
    WHERE p.id = ?
");
$stmt->bind_param('i', $propertyId);
$stmt->execute();
$property = $stmt->get_result()->fetch_assoc();

if (!$property) {
    header('Location: index.php');
    exit;
}

// Get property features
$stmt = $conn->prepare("
    SELECT pf.* FROM " . DB_PREFIX . "property_features pf
    JOIN " . DB_PREFIX . "property_feature_map pfm ON pf.id = pfm.feature_id
    WHERE pfm.property_id = ?
    ORDER BY pf.feature_category, pf.feature_name
");
$stmt->bind_param('i', $propertyId);
$stmt->execute();
$features = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Group features by category
$featureCategories = [];
foreach ($features as $feature) {
    $featureCategories[$feature['feature_category']][] = $feature;
}

// Get current lease (if occupied)
$currentLease = null;
if ($property['availability_status'] === 'Occupied') {
    $stmt = $conn->prepare("
        SELECT l.*, t.first_name, t.last_name, t.email, t.phone
        FROM " . DB_PREFIX . "leases l
        JOIN " . DB_PREFIX . "tenants t ON l.tenant_id = t.id
        WHERE l.property_id = ? AND l.lease_status = 'Active'
        ORDER BY l.start_date DESC
        LIMIT 1
    ");
    $stmt->bind_param('i', $propertyId);
    $stmt->execute();
    $currentLease = $stmt->get_result()->fetch_assoc();
}

// Get lease history
$stmt = $conn->prepare("
    SELECT l.*, t.first_name, t.last_name
    FROM " . DB_PREFIX . "leases l
    JOIN " . DB_PREFIX . "tenants t ON l.tenant_id = t.id
    WHERE l.property_id = ?
    ORDER BY l.start_date DESC
");
$stmt->bind_param('i', $propertyId);
$stmt->execute();
$leaseHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get rent payments
$stmt = $conn->prepare("
    SELECT rp.*, t.first_name, t.last_name, l.lease_number
    FROM " . DB_PREFIX . "rent_payments rp
    JOIN " . DB_PREFIX . "tenants t ON rp.tenant_id = t.id
    JOIN " . DB_PREFIX . "leases l ON rp.lease_id = l.id
    WHERE rp.property_id = ?
    ORDER BY rp.payment_date DESC
    LIMIT 10
");
$stmt->bind_param('i', $propertyId);
$stmt->execute();
$recentPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get maintenance history
$stmt = $conn->prepare("
    SELECT mr.*, t.first_name, t.last_name
    FROM " . DB_PREFIX . "maintenance_requests mr
    LEFT JOIN " . DB_PREFIX . "tenants t ON mr.tenant_id = t.id
    WHERE mr.property_id = ?
    ORDER BY mr.reported_date DESC
    LIMIT 10
");
$stmt->bind_param('i', $propertyId);
$stmt->execute();
$maintenanceHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get inspections
$stmt = $conn->prepare("
    SELECT pi.*, t.first_name, t.last_name
    FROM " . DB_PREFIX . "property_inspections pi
    LEFT JOIN " . DB_PREFIX . "tenants t ON pi.tenant_id = t.id
    WHERE pi.property_id = ?
    ORDER BY pi.inspection_date DESC
");
$stmt->bind_param('i', $propertyId);
$stmt->execute();
$inspections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get documents
$stmt = $conn->prepare("
    SELECT * FROM " . DB_PREFIX . "property_documents 
    WHERE property_id = ? 
    ORDER BY upload_date DESC
");
$stmt->bind_param('i', $propertyId);
$stmt->execute();
$documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$totalLeases = count($leaseHistory);
$totalRevenue = 0;
$totalMaintenanceCost = 0;

foreach ($recentPayments as $payment) {
    $totalRevenue += $payment['total_amount'];
}

foreach ($maintenanceHistory as $maintenance) {
    if ($maintenance['actual_cost']) {
        $totalMaintenanceCost += $maintenance['actual_cost'];
    }
}

// Calculate days until lease expiry (if occupied)
$daysUntilExpiry = null;
if ($currentLease) {
    $endDate = new DateTime($currentLease['end_date']);
    $today = new DateTime();
    $daysUntilExpiry = $today->diff($endDate)->days;
    if ($endDate < $today) {
        $daysUntilExpiry = -$daysUntilExpiry;
    }
}

// Set page title
$pageTitle = 'Property Details - ' . $property['property_name'];

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1><?php echo htmlspecialchars($property['property_name']); ?></h1>
        <p><?php echo htmlspecialchars($property['property_code']); ?> - <?php echo htmlspecialchars($property['type_name']); ?></p>
    </div>
    <div class="page-actions">
        <?php if (hasPermission('properties.edit')): ?>
        <a href="edit.php?id=<?php echo $property['id']; ?>" class="btn btn-primary">
            <i class="icon-edit"></i> Edit Property
        </a>
        <?php endif; ?>
        
        <?php if ($property['availability_status'] === 'Available' && hasPermission('leases.create')): ?>
        <a href="leases/add.php?property_id=<?php echo $property['id']; ?>" class="btn btn-success">
            <i class="icon-file-plus"></i> Create Lease
        </a>
        <?php endif; ?>
        
        <?php if (hasPermission('inspections.create')): ?>
        <a href="inspections/schedule.php?property_id=<?php echo $property['id']; ?>" class="btn btn-info">
            <i class="icon-search"></i> Schedule Inspection
        </a>
        <?php endif; ?>
        
        <a href="documents/index.php?property_id=<?php echo $property['id']; ?>" class="btn btn-secondary">
            <i class="icon-folder"></i> View Documents
        </a>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success">
    <?php
    switch ($_GET['success']) {
        case 'created':
            echo 'Property created successfully.';
            break;
        case 'updated':
            echo 'Property updated successfully.';
            break;
    }
    ?>
</div>
<?php endif; ?>

<!-- Property Header -->
<div class="row mb-4">
    <div class="col-md-8">
        <?php if ($property['featured_image']): ?>
        <img src="<?php echo htmlspecialchars($property['featured_image']); ?>" 
             class="img-fluid rounded property-header-image" alt="<?php echo htmlspecialchars($property['property_name']); ?>">
        <?php else: ?>
        <div class="property-header-placeholder d-flex align-items-center justify-content-center">
            <i class="icon-home fa-5x text-muted"></i>
        </div>
        <?php endif; ?>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Property Status</h5>
                    <span class="badge <?php echo getAvailabilityBadgeClass($property['availability_status']); ?> fs-6">
                        <?php echo $property['availability_status']; ?>
                    </span>
                </div>
                
                <div class="mb-3">
                    <strong>Monthly Rent:</strong><br>
                    <span class="h4 text-primary"><?php echo formatCurrency($property['monthly_rent']); ?></span>
                </div>
                
                <?php if ($property['bedrooms']): ?>
                <div class="mb-3">
                    <strong>Property Details:</strong><br>
                    <i class="icon-bed"></i> <?php echo $property['bedrooms']; ?> bed
                    <?php if ($property['bathrooms']): ?>
                    <span class="ms-2"><i class="icon-bath"></i> <?php echo $property['bathrooms']; ?> bath</span>
                    <?php endif; ?>
                    <?php if ($property['size_sqm']): ?>
                    <span class="ms-2"><i class="icon-maximize"></i> <?php echo $property['size_sqm']; ?> sqm</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div class="mb-3">
                    <strong>Location:</strong><br>
                    <i class="icon-map-pin"></i> <?php echo htmlspecialchars($property['city'] . ', ' . $property['state']); ?>
                </div>
                
                <?php if ($property['is_featured']): ?>
                <div class="alert alert-warning">
                    <i class="icon-star"></i> Featured Property
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Current Status -->
<?php if ($currentLease): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5>Current Lease Information</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <strong>Tenant:</strong><br>
                    <?php echo htmlspecialchars($currentLease['first_name'] . ' ' . $currentLease['last_name']); ?>
                </div>
                <div class="mb-3">
                    <strong>Contact:</strong><br>
                    <i class="icon-mail"></i> <?php echo htmlspecialchars($currentLease['email']); ?><br>
                    <i class="icon-phone"></i> <?php echo htmlspecialchars($currentLease['phone']); ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <strong>Lease Period:</strong><br>
                    <?php echo date('M d, Y', strtotime($currentLease['start_date'])); ?> - 
                    <?php echo date('M d, Y', strtotime($currentLease['end_date'])); ?>
                </div>
                <div class="mb-3">
                    <strong>Monthly Rent:</strong><br>
                    <?php echo formatCurrency($currentLease['monthly_rent']); ?>
                </div>
                <?php if ($daysUntilExpiry !== null): ?>
                <div class="mb-3">
                    <strong>Days Until Expiry:</strong><br>
                    <span class="badge <?php echo $daysUntilExpiry <= 30 ? ($daysUntilExpiry <= 7 ? 'bg-danger' : 'bg-warning') : 'bg-success'; ?>">
                        <?php echo $daysUntilExpiry; ?> days
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="mt-3">
            <a href="leases/view.php?id=<?php echo $currentLease['id']; ?>" class="btn btn-primary">
                <i class="icon-eye"></i> View Lease Details
            </a>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card mb-4">
    <div class="card-body text-center">
        <i class="icon-home fa-3x text-muted mb-3"></i>
        <h5>Property Available for Rent</h5>
        <p class="text-muted">This property is currently available for new tenants.</p>
        <?php if (hasPermission('leases.create')): ?>
        <a href="leases/add.php?property_id=<?php echo $property['id']; ?>" class="btn btn-success">
            <i class="icon-file-plus"></i> Create New Lease
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Property Information Tabs -->
<ul class="nav nav-tabs mb-4" id="propertyTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
            Overview
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="features-tab" data-bs-toggle="tab" data-bs-target="#features" type="button" role="tab">
            Features
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="leases-tab" data-bs-toggle="tab" data-bs-target="#leases" type="button" role="tab">
            Lease History
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab">
            Rent Payments
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#maintenance" type="button" role="tab">
            Maintenance
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="inspections-tab" data-bs-toggle="tab" data-bs-target="#inspections" type="button" role="tab">
            Inspections
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab">
            Documents
        </button>
    </li>
</ul>

<div class="tab-content" id="propertyTabsContent">
    <!-- Overview Tab -->
    <div class="tab-pane fade show active" id="overview" role="tabpanel">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>Property Description</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($property['description']): ?>
                        <p><?php echo nl2br(htmlspecialchars($property['description'])); ?></p>
                        <?php else: ?>
                        <p class="text-muted">No description available.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5>Location Details</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Address:</strong><br><?php echo nl2br(htmlspecialchars($property['address'])); ?></p>
                        <p><strong>City:</strong> <?php echo htmlspecialchars($property['city']); ?></p>
                        <p><strong>State:</strong> <?php echo htmlspecialchars($property['state']); ?></p>
                        <p><strong>Country:</strong> <?php echo htmlspecialchars($property['country']); ?></p>
                        <?php if ($property['postal_code']): ?>
                        <p><strong>Postal Code:</strong> <?php echo htmlspecialchars($property['postal_code']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Property Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Total Leases:</strong><br>
                            <span class="h5"><?php echo $totalLeases; ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Total Revenue:</strong><br>
                            <span class="h5 text-success"><?php echo formatCurrency($totalRevenue); ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Maintenance Costs:</strong><br>
                            <span class="h5 text-warning"><?php echo formatCurrency($totalMaintenanceCost); ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Created:</strong><br>
                            <?php echo date('M d, Y', strtotime($property['created_at'])); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Created By:</strong><br>
                            <?php echo htmlspecialchars($property['created_by_first'] . ' ' . $property['created_by_last']); ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($property['gallery_images']): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5>Gallery</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        $galleryImages = json_decode($property['gallery_images'], true);
                        if ($galleryImages):
                        ?>
                        <div class="row">
                            <?php foreach ($galleryImages as $index => $image): ?>
                            <div class="col-6 mb-2">
                                <img src="<?php echo htmlspecialchars($image); ?>" 
                                     class="img-fluid rounded gallery-thumb" 
                                     alt="Gallery Image <?php echo $index + 1; ?>"
                                     onclick="openImageModal('<?php echo htmlspecialchars($image); ?>')">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Features Tab -->
    <div class="tab-pane fade" id="features" role="tabpanel">
        <div class="card">
            <div class="card-body">
                <?php if (!empty($featureCategories)): ?>
                <?php foreach ($featureCategories as $category => $categoryFeatures): ?>
                <div class="mb-4">
                    <h6><?php echo htmlspecialchars($category); ?></h6>
                    <div class="row">
                        <?php foreach ($categoryFeatures as $feature): ?>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center mb-2">
                                <i class="icon-check text-success me-2"></i>
                                <span><?php echo htmlspecialchars($feature['feature_name']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <p class="text-muted">No features specified for this property.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Lease History Tab -->
    <div class="tab-pane fade" id="leases" role="tabpanel">
        <div class="card">
            <div class="card-header">
                <h5>Lease History</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($leaseHistory)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Lease #</th>
                                <th>Tenant</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Duration</th>
                                <th>Monthly Rent</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leaseHistory as $lease): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($lease['lease_number']); ?></td>
                                <td><?php echo htmlspecialchars($lease['first_name'] . ' ' . $lease['last_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($lease['start_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($lease['end_date'])); ?></td>
                                <td><?php echo $lease['lease_term_months']; ?> months</td>
                                <td><?php echo formatCurrency($lease['monthly_rent']); ?></td>
                                <td>
                                    <span class="badge <?php echo getLeaseStatusBadgeClass($lease['lease_status']); ?>">
                                        <?php echo $lease['lease_status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="leases/view.php?id=<?php echo $lease['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="icon-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted">No lease history available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Rent Payments Tab -->
    <div class="tab-pane fade" id="payments" role="tabpanel">
        <div class="card">
            <div class="card-header">
                <h5>Recent Rent Payments</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recentPayments)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Payment #</th>
                                <th>Date</th>
                                <th>Tenant</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPayments as $payment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payment['payment_number']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($payment['payment_type']); ?></td>
                                <td><?php echo formatCurrency($payment['total_amount']); ?></td>
                                <td>
                                    <span class="badge <?php echo getPaymentStatusBadgeClass($payment['payment_status']); ?>">
                                        <?php echo $payment['payment_status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="rent/view-payment.php?id=<?php echo $payment['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="icon-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <a href="rent/index.php?property_id=<?php echo $property['id']; ?>" class="btn btn-primary">
                        <i class="icon-list"></i> View All Payments
                    </a>
                </div>
                <?php else: ?>
                <p class="text-muted">No rent payments recorded.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Maintenance Tab -->
    <div class="tab-pane fade" id="maintenance" role="tabpanel">
        <div class="card">
            <div class="card-header">
                <h5>Maintenance History</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($maintenanceHistory)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Request #</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Priority</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Cost</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($maintenanceHistory as $maintenance): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($maintenance['request_number']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($maintenance['reported_date'])); ?></td>
                                <td><?php echo htmlspecialchars($maintenance['request_type']); ?></td>
                                <td>
                                    <span class="badge <?php echo getPriorityBadgeClass($maintenance['priority']); ?>">
                                        <?php echo $maintenance['priority']; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($maintenance['title']); ?></td>
                                <td>
                                    <span class="badge <?php echo getMaintenanceStatusBadgeClass($maintenance['status']); ?>">
                                        <?php echo $maintenance['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo $maintenance['actual_cost'] ? formatCurrency($maintenance['actual_cost']) : '-'; ?></td>
                                <td>
                                    <a href="maintenance/view.php?id=<?php echo $maintenance['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="icon-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <a href="maintenance/index.php?property_id=<?php echo $property['id']; ?>" class="btn btn-primary">
                        <i class="icon-list"></i> View All Requests
                    </a>
                    <?php if (hasPermission('maintenance.create')): ?>
                    <a href="maintenance/add.php?property_id=<?php echo $property['id']; ?>" class="btn btn-success">
                        <i class="icon-plus"></i> New Request
                    </a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <p class="text-muted">No maintenance requests recorded.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Inspections Tab -->
    <div class="tab-pane fade" id="inspections" role="tabpanel">
        <div class="card">
            <div class="card-header">
                <h5>Property Inspections</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($inspections)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Inspection #</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Inspector</th>
                                <th>Condition</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inspections as $inspection): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($inspection['inspection_number']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($inspection['inspection_date'])); ?></td>
                                <td><?php echo htmlspecialchars($inspection['inspection_type']); ?></td>
                                <td><?php echo htmlspecialchars($inspection['inspector_name']); ?></td>
                                <td>
                                    <span class="badge <?php echo getConditionBadgeClass($inspection['overall_condition']); ?>">
                                        <?php echo $inspection['overall_condition']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo getInspectionStatusBadgeClass($inspection['status']); ?>">
                                        <?php echo $inspection['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="inspections/view.php?id=<?php echo $inspection['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="icon-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted">No inspections recorded.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Documents Tab -->
    <div class="tab-pane fade" id="documents" role="tabpanel">
        <div class="card">
            <div class="card-header">
                <h5>Property Documents</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($documents)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Document Name</th>
                                <th>Type</th>
                                <th>Upload Date</th>
                                <th>Size</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $document): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($document['document_name']); ?></td>
                                <td><?php echo htmlspecialchars($document['document_type']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($document['upload_date'])); ?></td>
                                <td><?php echo formatFileSize($document['file_size']); ?></td>
                                <td>
                                    <a href="documents/download.php?id=<?php echo $document['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="icon-download"></i> Download
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted">No documents uploaded.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Property Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" class="img-fluid" alt="Property Image">
            </div>
        </div>
    </div>
</div>

<?php
// Helper functions
function getAvailabilityBadgeClass($status) {
    switch ($status) {
        case 'Available': return 'bg-success';
        case 'Occupied': return 'bg-primary';
        case 'Under Maintenance': return 'bg-warning';
        case 'Reserved': return 'bg-info';
        default: return 'bg-secondary';
    }
}

function getLeaseStatusBadgeClass($status) {
    switch ($status) {
        case 'Draft': return 'bg-secondary';
        case 'Active': return 'bg-success';
        case 'Expired': return 'bg-danger';
        case 'Terminated': return 'bg-warning';
        case 'Renewed': return 'bg-info';
        default: return 'bg-secondary';
    }
}

function getPaymentStatusBadgeClass($status) {
    switch ($status) {
        case 'Completed': return 'bg-success';
        case 'Pending': return 'bg-warning';
        case 'Failed': return 'bg-danger';
        case 'Refunded': return 'bg-info';
        default: return 'bg-secondary';
    }
}

function getPriorityBadgeClass($priority) {
    switch ($priority) {
        case 'Emergency': return 'bg-danger';
        case 'High': return 'bg-warning';
        case 'Medium': return 'bg-info';
        case 'Low': return 'bg-success';
        default: return 'bg-secondary';
    }
}

function getMaintenanceStatusBadgeClass($status) {
    switch ($status) {
        case 'Pending': return 'bg-warning';
        case 'In Progress': return 'bg-info';
        case 'Completed': return 'bg-success';
        case 'Cancelled': return 'bg-danger';
        case 'On Hold': return 'bg-secondary';
        default: return 'bg-secondary';
    }
}

function getConditionBadgeClass($condition) {
    switch ($condition) {
        case 'Excellent': return 'bg-success';
        case 'Good': return 'bg-info';
        case 'Fair': return 'bg-warning';
        case 'Poor': return 'bg-danger';
        case 'Needs Repair': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

function getInspectionStatusBadgeClass($status) {
    switch ($status) {
        case 'Scheduled': return 'bg-info';
        case 'Completed': return 'bg-success';
        case 'Cancelled': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>

<script>
function openImageModal(imageSrc) {
    document.getElementById('modalImage').src = imageSrc;
    new bootstrap.Modal(document.getElementById('imageModal')).show();
}
</script>

<style>
.property-header-image {
    height: 400px;
    object-fit: cover;
}

.property-header-placeholder {
    height: 400px;
    background-color: #f8f9fa;
    border-radius: 0.375rem;
}

.gallery-thumb {
    height: 100px;
    object-fit: cover;
    cursor: pointer;
    transition: opacity 0.2s;
}

.gallery-thumb:hover {
    opacity: 0.8;
}
</style>

<?php include '../../includes/footer.php'; ?>
