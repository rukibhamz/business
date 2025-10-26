<?php
/**
 * Business Management System - Lease List
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
require_once '../../../includes/property-functions.php';

// Check authentication and permissions
requireLogin();
requirePermission('leases.view');

// Get database connection
$conn = getDB();

// Get filters
$status = $_GET['status'] ?? '';
$property_id = $_GET['property_id'] ?? '';
$tenant_id = $_GET['tenant_id'] ?? '';
$expiry_filter = $_GET['expiry_filter'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$whereConditions = [];
$params = [];
$paramTypes = '';

if (!empty($status)) {
    $whereConditions[] = "l.lease_status = ?";
    $params[] = $status;
    $paramTypes .= 's';
}

if (!empty($property_id)) {
    $whereConditions[] = "l.property_id = ?";
    $params[] = $property_id;
    $paramTypes .= 'i';
}

if (!empty($tenant_id)) {
    $whereConditions[] = "l.tenant_id = ?";
    $params[] = $tenant_id;
    $paramTypes .= 'i';
}

if (!empty($expiry_filter)) {
    switch ($expiry_filter) {
        case 'expiring_30':
            $whereConditions[] = "l.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
            break;
        case 'expiring_60':
            $whereConditions[] = "l.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)";
            break;
        case 'expired':
            $whereConditions[] = "l.end_date < CURDATE()";
            break;
    }
}

if (!empty($search)) {
    $whereConditions[] = "(p.property_name LIKE ? OR t.first_name LIKE ? OR t.last_name LIKE ? OR l.lease_code LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $paramTypes .= 'ssss';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get leases
$query = "
    SELECT l.*, 
           p.property_name, p.property_code, p.monthly_rent,
           t.first_name, t.last_name, t.email, t.phone,
           pt.type_name
    FROM " . DB_PREFIX . "leases l
    LEFT JOIN " . DB_PREFIX . "properties p ON l.property_id = p.id
    LEFT JOIN " . DB_PREFIX . "tenants t ON l.tenant_id = t.id
    LEFT JOIN " . DB_PREFIX . "property_types pt ON p.property_type_id = pt.id
    $whereClause
    ORDER BY l.start_date DESC
    LIMIT 50
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$leases = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get quick stats
$statsQuery = "
    SELECT 
        COUNT(*) as total_leases,
        SUM(CASE WHEN lease_status = 'Active' THEN 1 ELSE 0 END) as active_leases,
        SUM(CASE WHEN end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiring_30,
        SUM(CASE WHEN end_date < CURDATE() THEN 1 ELSE 0 END) as expired_leases
    FROM " . DB_PREFIX . "leases
";
$stmt = $conn->prepare($statsQuery);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get properties for filter
$stmt = $conn->prepare("SELECT id, property_name FROM " . DB_PREFIX . "properties WHERE property_status = 'Active' ORDER BY property_name");
$stmt->execute();
$properties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get tenants for filter
$stmt = $conn->prepare("SELECT id, first_name, last_name FROM " . DB_PREFIX . "tenants WHERE status = 'Active' ORDER BY first_name, last_name");
$stmt->execute();
$tenants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Set page title
$pageTitle = 'Lease Management';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Lease Management</h1>
        <p>Manage property leases and rental agreements</p>
    </div>
    <div class="page-actions">
        <a href="add.php" class="btn btn-primary">
            <i class="icon-plus"></i> Create Lease
        </a>
    </div>
</div>

<!-- Quick Stats -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo $stats['total_leases']; ?></h4>
                        <p class="mb-0">Total Leases</p>
                    </div>
                    <div class="align-self-center">
                        <i class="icon-file-text fs-1"></i>
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
                        <h4 class="mb-0"><?php echo $stats['active_leases']; ?></h4>
                        <p class="mb-0">Active Leases</p>
                    </div>
                    <div class="align-self-center">
                        <i class="icon-check-circle fs-1"></i>
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
                        <h4 class="mb-0"><?php echo $stats['expiring_30']; ?></h4>
                        <p class="mb-0">Expiring (30 days)</p>
                    </div>
                    <div class="align-self-center">
                        <i class="icon-clock fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo $stats['expired_leases']; ?></h4>
                        <p class="mb-0">Expired Leases</p>
                    </div>
                    <div class="align-self-center">
                        <i class="icon-alert-triangle fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" placeholder="Search leases...">
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <option value="Active" <?php echo $status === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Expired" <?php echo $status === 'Expired' ? 'selected' : ''; ?>>Expired</option>
                    <option value="Terminated" <?php echo $status === 'Terminated' ? 'selected' : ''; ?>>Terminated</option>
                    <option value="Renewed" <?php echo $status === 'Renewed' ? 'selected' : ''; ?>>Renewed</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="property_id" class="form-label">Property</label>
                <select class="form-select" id="property_id" name="property_id">
                    <option value="">All Properties</option>
                    <?php foreach ($properties as $property): ?>
                    <option value="<?php echo $property['id']; ?>" <?php echo $property_id == $property['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($property['property_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="tenant_id" class="form-label">Tenant</label>
                <select class="form-select" id="tenant_id" name="tenant_id">
                    <option value="">All Tenants</option>
                    <?php foreach ($tenants as $tenant): ?>
                    <option value="<?php echo $tenant['id']; ?>" <?php echo $tenant_id == $tenant['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="expiry_filter" class="form-label">Expiry</label>
                <select class="form-select" id="expiry_filter" name="expiry_filter">
                    <option value="">All</option>
                    <option value="expiring_30" <?php echo $expiry_filter === 'expiring_30' ? 'selected' : ''; ?>>Expiring in 30 days</option>
                    <option value="expiring_60" <?php echo $expiry_filter === 'expiring_60' ? 'selected' : ''; ?>>Expiring in 60 days</option>
                    <option value="expired" <?php echo $expiry_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="icon-search"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Leases Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Leases</h5>
    </div>
    <div class="card-body">
        <?php if (empty($leases)): ?>
        <div class="text-center py-4">
            <i class="icon-file-text fs-1 text-muted"></i>
            <p class="text-muted mt-2">No leases found</p>
            <a href="add.php" class="btn btn-primary">Create First Lease</a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Lease Code</th>
                        <th>Property</th>
                        <th>Tenant</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Monthly Rent</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leases as $lease): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($lease['lease_code']); ?></strong>
                        </td>
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($lease['property_name']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($lease['type_name']); ?></small>
                            </div>
                        </td>
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($lease['first_name'] . ' ' . $lease['last_name']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($lease['email']); ?></small>
                            </div>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($lease['start_date'])); ?></td>
                        <td>
                            <?php 
                            $endDate = strtotime($lease['end_date']);
                            $isExpired = $endDate < time();
                            $isExpiringSoon = $endDate < strtotime('+30 days');
                            
                            if ($isExpired) {
                                echo '<span class="text-danger">' . date('M d, Y', $endDate) . '</span>';
                            } elseif ($isExpiringSoon) {
                                echo '<span class="text-warning">' . date('M d, Y', $endDate) . '</span>';
                            } else {
                                echo date('M d, Y', $endDate);
                            }
                            ?>
                        </td>
                        <td>₦<?php echo number_format($lease['monthly_rent'], 2); ?></td>
                        <td>
                            <?php
                            $statusClass = '';
                            switch ($lease['lease_status']) {
                                case 'Active':
                                    $statusClass = 'success';
                                    break;
                                case 'Expired':
                                    $statusClass = 'danger';
                                    break;
                                case 'Terminated':
                                    $statusClass = 'secondary';
                                    break;
                                case 'Renewed':
                                    $statusClass = 'info';
                                    break;
                            }
                            ?>
                            <span class="badge bg-<?php echo $statusClass; ?>"><?php echo htmlspecialchars($lease['lease_status']); ?></span>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="view.php?id=<?php echo $lease['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="icon-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $lease['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="icon-edit"></i>
                                </a>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="icon-more-horizontal"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="renew.php?id=<?php echo $lease['id']; ?>">
                                            <i class="icon-refresh-cw"></i> Renew Lease
                                        </a></li>
                                        <li><a class="dropdown-item" href="terminate.php?id=<?php echo $lease['id']; ?>">
                                            <i class="icon-x-circle"></i> Terminate Lease
                                        </a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteLease(<?php echo $lease['id']; ?>)">
                                            <i class="icon-trash-2"></i> Delete
                                        </a></li>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function deleteLease(leaseId) {
    if (confirm('Are you sure you want to delete this lease? This action cannot be undone.')) {
        // Implement delete functionality
        console.log('Delete lease:', leaseId);
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
