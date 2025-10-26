<?php
/**
 * Business Management System - Maintenance Requests List
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
requirePermission('maintenance.view');

// Get database connection
$conn = getDB();

// Get filters
$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$property_id = $_GET['property_id'] ?? '';
$tenant_id = $_GET['tenant_id'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$whereConditions = [];
$params = [];
$paramTypes = '';

if (!empty($status)) {
    $whereConditions[] = "mr.request_status = ?";
    $params[] = $status;
    $paramTypes .= 's';
}

if (!empty($priority)) {
    $whereConditions[] = "mr.priority = ?";
    $params[] = $priority;
    $paramTypes .= 's';
}

if (!empty($property_id)) {
    $whereConditions[] = "mr.property_id = ?";
    $params[] = $property_id;
    $paramTypes .= 'i';
}

if (!empty($tenant_id)) {
    $whereConditions[] = "mr.tenant_id = ?";
    $params[] = $tenant_id;
    $paramTypes .= 'i';
}

if (!empty($search)) {
    $whereConditions[] = "(p.property_name LIKE ? OR t.first_name LIKE ? OR t.last_name LIKE ? OR mr.request_title LIKE ? OR mr.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $paramTypes .= 'sssss';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get maintenance requests
$query = "
    SELECT mr.*, 
           p.property_name, p.property_code,
           t.first_name, t.last_name, t.email, t.phone,
           u.first_name as assigned_to_name, u.last_name as assigned_to_last
    FROM " . DB_PREFIX . "maintenance_requests mr
    LEFT JOIN " . DB_PREFIX . "properties p ON mr.property_id = p.id
    LEFT JOIN " . DB_PREFIX . "tenants t ON mr.tenant_id = t.id
    LEFT JOIN " . DB_PREFIX . "users u ON mr.assigned_to = u.id
    $whereClause
    ORDER BY mr.created_at DESC
    LIMIT 50
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get quick stats
$statsQuery = "
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN request_status = 'Open' THEN 1 ELSE 0 END) as open_requests,
        SUM(CASE WHEN request_status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_requests,
        SUM(CASE WHEN request_status = 'Completed' THEN 1 ELSE 0 END) as completed_requests,
        SUM(CASE WHEN priority = 'High' AND request_status != 'Completed' THEN 1 ELSE 0 END) as high_priority_open
    FROM " . DB_PREFIX . "maintenance_requests
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
$pageTitle = 'Maintenance Requests';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Maintenance Requests</h1>
        <p>Manage property maintenance requests and work orders</p>
    </div>
    <div class="page-actions">
        <a href="add.php" class="btn btn-primary">
            <i class="icon-plus"></i> Create Request
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
                        <h4 class="mb-0"><?php echo $stats['total_requests']; ?></h4>
                        <p class="mb-0">Total Requests</p>
                    </div>
                    <div class="align-self-center">
                        <i class="icon-tool fs-1"></i>
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
                        <h4 class="mb-0"><?php echo $stats['open_requests']; ?></h4>
                        <p class="mb-0">Open Requests</p>
                    </div>
                    <div class="align-self-center">
                        <i class="icon-clock fs-1"></i>
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
                        <h4 class="mb-0"><?php echo $stats['in_progress_requests']; ?></h4>
                        <p class="mb-0">In Progress</p>
                    </div>
                    <div class="align-self-center">
                        <i class="icon-settings fs-1"></i>
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
                        <h4 class="mb-0"><?php echo $stats['high_priority_open']; ?></h4>
                        <p class="mb-0">High Priority</p>
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
                       value="<?php echo htmlspecialchars($search); ?>" placeholder="Search requests...">
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <option value="Open" <?php echo $status === 'Open' ? 'selected' : ''; ?>>Open</option>
                    <option value="In Progress" <?php echo $status === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="Completed" <?php echo $status === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="Cancelled" <?php echo $status === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="priority" class="form-label">Priority</label>
                <select class="form-select" id="priority" name="priority">
                    <option value="">All Priority</option>
                    <option value="Low" <?php echo $priority === 'Low' ? 'selected' : ''; ?>>Low</option>
                    <option value="Medium" <?php echo $priority === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="High" <?php echo $priority === 'High' ? 'selected' : ''; ?>>High</option>
                    <option value="Urgent" <?php echo $priority === 'Urgent' ? 'selected' : ''; ?>>Urgent</option>
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

<!-- Requests Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Maintenance Requests</h5>
    </div>
    <div class="card-body">
        <?php if (empty($requests)): ?>
        <div class="text-center py-4">
            <i class="icon-tool fs-1 text-muted"></i>
            <p class="text-muted mt-2">No maintenance requests found</p>
            <a href="add.php" class="btn btn-primary">Create First Request</a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Request #</th>
                        <th>Title</th>
                        <th>Property</th>
                        <th>Tenant</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Assigned To</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($request['request_code']); ?></strong>
                        </td>
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($request['request_title']); ?></strong>
                                <?php if (!empty($request['description'])): ?>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars(substr($request['description'], 0, 50)) . (strlen($request['description']) > 50 ? '...' : ''); ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($request['property_name']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($request['property_code']); ?></small>
                            </div>
                        </td>
                        <td>
                            <?php if ($request['tenant_id']): ?>
                            <div>
                                <strong><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($request['email']); ?></small>
                            </div>
                            <?php else: ?>
                            <span class="text-muted">System</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $priorityClass = '';
                            switch ($request['priority']) {
                                case 'Low':
                                    $priorityClass = 'secondary';
                                    break;
                                case 'Medium':
                                    $priorityClass = 'info';
                                    break;
                                case 'High':
                                    $priorityClass = 'warning';
                                    break;
                                case 'Urgent':
                                    $priorityClass = 'danger';
                                    break;
                            }
                            ?>
                            <span class="badge bg-<?php echo $priorityClass; ?>"><?php echo htmlspecialchars($request['priority']); ?></span>
                        </td>
                        <td>
                            <?php
                            $statusClass = '';
                            switch ($request['request_status']) {
                                case 'Open':
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
                            <span class="badge bg-<?php echo $statusClass; ?>"><?php echo htmlspecialchars($request['request_status']); ?></span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                        <td>
                            <?php if ($request['assigned_to']): ?>
                            <?php echo htmlspecialchars($request['assigned_to_name'] . ' ' . $request['assigned_to_last']); ?>
                            <?php else: ?>
                            <span class="text-muted">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="view.php?id=<?php echo $request['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="icon-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $request['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="icon-edit"></i>
                                </a>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="icon-more-horizontal"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <?php if ($request['request_status'] === 'Open'): ?>
                                        <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $request['id']; ?>, 'In Progress')">
                                            <i class="icon-play"></i> Start Work
                                        </a></li>
                                        <?php elseif ($request['request_status'] === 'In Progress'): ?>
                                        <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $request['id']; ?>, 'Completed')">
                                            <i class="icon-check"></i> Mark Complete
                                        </a></li>
                                        <?php endif; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteRequest(<?php echo $request['id']; ?>)">
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
function updateStatus(requestId, newStatus) {
    if (confirm('Are you sure you want to update the status to "' + newStatus + '"?')) {
        // Implement status update functionality
        console.log('Update status:', requestId, newStatus);
    }
}

function deleteRequest(requestId) {
    if (confirm('Are you sure you want to delete this maintenance request? This action cannot be undone.')) {
        // Implement delete functionality
        console.log('Delete request:', requestId);
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
