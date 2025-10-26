<?php
/**
 * Business Management System - Property Inspections List
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
requirePermission('inspections.view');

// Get database connection
$conn = getDB();

// Get filters
$status = $_GET['status'] ?? '';
$property_id = $_GET['property_id'] ?? '';
$inspector_id = $_GET['inspector_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$whereConditions = [];
$params = [];
$paramTypes = '';

if (!empty($status)) {
    $whereConditions[] = "pi.inspection_status = ?";
    $params[] = $status;
    $paramTypes .= 's';
}

if (!empty($property_id)) {
    $whereConditions[] = "pi.property_id = ?";
    $params[] = $property_id;
    $paramTypes .= 'i';
}

if (!empty($inspector_id)) {
    $whereConditions[] = "pi.inspector_id = ?";
    $params[] = $inspector_id;
    $paramTypes .= 'i';
}

if (!empty($date_from)) {
    $whereConditions[] = "pi.inspection_date >= ?";
    $params[] = $date_from;
    $paramTypes .= 's';
}

if (!empty($date_to)) {
    $whereConditions[] = "pi.inspection_date <= ?";
    $params[] = $date_to;
    $paramTypes .= 's';
}

if (!empty($search)) {
    $whereConditions[] = "(p.property_name LIKE ? OR pi.inspection_type LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $paramTypes .= 'ssss';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get inspections
$query = "
    SELECT pi.*, 
           p.property_name, p.property_code,
           u.first_name as inspector_name, u.last_name as inspector_last,
           t.first_name as tenant_name, t.last_name as tenant_last
    FROM " . DB_PREFIX . "property_inspections pi
    LEFT JOIN " . DB_PREFIX . "properties p ON pi.property_id = p.id
    LEFT JOIN " . DB_PREFIX . "users u ON pi.inspector_id = u.id
    LEFT JOIN " . DB_PREFIX . "tenants t ON pi.tenant_id = t.id
    $whereClause
    ORDER BY pi.inspection_date DESC
    LIMIT 50
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$inspections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get quick stats
$statsQuery = "
    SELECT 
        COUNT(*) as total_inspections,
        SUM(CASE WHEN inspection_status = 'Scheduled' THEN 1 ELSE 0 END) as scheduled_inspections,
        SUM(CASE WHEN inspection_status = 'Completed' THEN 1 ELSE 0 END) as completed_inspections,
        SUM(CASE WHEN inspection_status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_inspections
    FROM " . DB_PREFIX . "property_inspections
";
$stmt = $conn->prepare($statsQuery);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get properties for filter
$stmt = $conn->prepare("SELECT id, property_name FROM " . DB_PREFIX . "properties WHERE property_status = 'Active' ORDER BY property_name");
$stmt->execute();
$properties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get users for inspector filter
$stmt = $conn->prepare("SELECT id, first_name, last_name FROM " . DB_PREFIX . "users WHERE status = 'Active' ORDER BY first_name, last_name");
$stmt->execute();
$inspectors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Set page title
$pageTitle = 'Property Inspections';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Property Inspections</h1>
        <p>Manage property inspection schedules and reports</p>
    </div>
    <div class="page-actions">
        <a href="add.php" class="btn btn-primary">
            <i class="icon-plus"></i> Schedule Inspection
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
                        <h4 class="mb-0"><?php echo $stats['total_inspections']; ?></h4>
                        <p class="mb-0">Total Inspections</p>
                    </div>
                    <div class="align-self-center">
                        <i class="icon-search fs-1"></i>
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
                        <h4 class="mb-0"><?php echo $stats['scheduled_inspections']; ?></h4>
                        <p class="mb-0">Scheduled</p>
                    </div>
                    <div class="align-self-center">
                        <i class="icon-calendar fs-1"></i>
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
                        <h4 class="mb-0"><?php echo $stats['completed_inspections']; ?></h4>
                        <p class="mb-0">Completed</p>
                    </div>
                    <div class="align-self-center">
                        <i class="icon-check-circle fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-secondary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo $stats['cancelled_inspections']; ?></h4>
                        <p class="mb-0">Cancelled</p>
                    </div>
                    <div class="align-self-center">
                        <i class="icon-x-circle fs-1"></i>
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
                       value="<?php echo htmlspecialchars($search); ?>" placeholder="Search inspections...">
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <option value="Scheduled" <?php echo $status === 'Scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                    <option value="In Progress" <?php echo $status === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="Completed" <?php echo $status === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="Cancelled" <?php echo $status === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
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
                <label for="inspector_id" class="form-label">Inspector</label>
                <select class="form-select" id="inspector_id" name="inspector_id">
                    <option value="">All Inspectors</option>
                    <?php foreach ($inspectors as $inspector): ?>
                    <option value="<?php echo $inspector['id']; ?>" <?php echo $inspector_id == $inspector['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($inspector['first_name'] . ' ' . $inspector['last_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label for="date_from" class="form-label">From</label>
                <input type="date" class="form-control" id="date_from" name="date_from" 
                       value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="col-md-1">
                <label for="date_to" class="form-label">To</label>
                <input type="date" class="form-control" id="date_to" name="date_to" 
                       value="<?php echo htmlspecialchars($date_to); ?>">
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

<!-- Inspections Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Property Inspections</h5>
    </div>
    <div class="card-body">
        <?php if (empty($inspections)): ?>
        <div class="text-center py-4">
            <i class="icon-search fs-1 text-muted"></i>
            <p class="text-muted mt-2">No inspections found</p>
            <a href="add.php" class="btn btn-primary">Schedule First Inspection</a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Inspection Date</th>
                        <th>Property</th>
                        <th>Type</th>
                        <th>Inspector</th>
                        <th>Tenant Present</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inspections as $inspection): ?>
                    <tr>
                        <td>
                            <strong><?php echo date('M d, Y', strtotime($inspection['inspection_date'])); ?></strong>
                            <?php if ($inspection['inspection_time']): ?>
                            <br>
                            <small class="text-muted"><?php echo date('H:i', strtotime($inspection['inspection_time'])); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($inspection['property_name']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($inspection['property_code']); ?></small>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-info"><?php echo htmlspecialchars($inspection['inspection_type']); ?></span>
                        </td>
                        <td>
                            <?php if ($inspection['inspector_id']): ?>
                            <?php echo htmlspecialchars($inspection['inspector_name'] . ' ' . $inspection['inspector_last']); ?>
                            <?php else: ?>
                            <span class="text-muted">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($inspection['tenant_id']): ?>
                            <span class="badge bg-<?php echo $inspection['tenant_present'] ? 'success' : 'warning'; ?>">
                                <?php echo $inspection['tenant_present'] ? 'Yes' : 'No'; ?>
                            </span>
                            <br>
                            <small class="text-muted"><?php echo htmlspecialchars($inspection['tenant_name'] . ' ' . $inspection['tenant_last']); ?></small>
                            <?php else: ?>
                            <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $statusClass = '';
                            switch ($inspection['inspection_status']) {
                                case 'Scheduled':
                                    $statusClass = 'warning';
                                    break;
                                case 'In Progress':
                                    $statusClass = 'info';
                                    break;
                                case 'Completed':
                                    $statusClass = 'success';
                                    break;
                                case 'Cancelled':
                                    $statusClass = 'secondary';
                                    break;
                            }
                            ?>
                            <span class="badge bg-<?php echo $statusClass; ?>"><?php echo htmlspecialchars($inspection['inspection_status']); ?></span>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="view.php?id=<?php echo $inspection['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="icon-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $inspection['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="icon-edit"></i>
                                </a>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="icon-more-horizontal"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <?php if ($inspection['inspection_status'] === 'Scheduled'): ?>
                                        <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $inspection['id']; ?>, 'In Progress')">
                                            <i class="icon-play"></i> Start Inspection
                                        </a></li>
                                        <?php elseif ($inspection['inspection_status'] === 'In Progress'): ?>
                                        <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $inspection['id']; ?>, 'Completed')">
                                            <i class="icon-check"></i> Complete Inspection
                                        </a></li>
                                        <?php endif; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="#" onclick="printReport(<?php echo $inspection['id']; ?>)">
                                            <i class="icon-printer"></i> Print Report
                                        </a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteInspection(<?php echo $inspection['id']; ?>)">
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
function updateStatus(inspectionId, newStatus) {
    if (confirm('Are you sure you want to update the status to "' + newStatus + '"?')) {
        // Implement status update functionality
        console.log('Update status:', inspectionId, newStatus);
    }
}

function printReport(inspectionId) {
    window.open('report.php?id=' + inspectionId, '_blank');
}

function deleteInspection(inspectionId) {
    if (confirm('Are you sure you want to delete this inspection? This action cannot be undone.')) {
        // Implement delete functionality
        console.log('Delete inspection:', inspectionId);
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
