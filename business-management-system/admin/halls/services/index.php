<?php
/**
 * Business Management System - Hall Services Management
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
requirePermission('halls.view');

// Get database connection
$conn = getDB();

// Get filter parameters
$search = $_GET['search'] ?? '';
$isActive = $_GET['is_active'] ?? '';

// Build query
$whereConditions = [];
$params = [];
$paramTypes = '';

if (!empty($search)) {
    $whereConditions[] = '(service_name LIKE ? OR description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $paramTypes .= 'ss';
}

if ($isActive !== '') {
    $whereConditions[] = 'is_active = ?';
    $params[] = $isActive;
    $paramTypes .= 'i';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get services
$query = "
    SELECT hs.*, u.first_name as created_by_first, u.last_name as created_by_last,
           COUNT(hbi.id) as times_used
    FROM " . DB_PREFIX . "hall_services hs
    LEFT JOIN " . DB_PREFIX . "users u ON hs.created_by = u.id
    LEFT JOIN " . DB_PREFIX . "hall_booking_items hbi ON hs.id = hbi.service_id
    {$whereClause}
    GROUP BY hs.id
    ORDER BY hs.service_name ASC
    LIMIT 50
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$totalServices = count($services);
$activeServices = count(array_filter($services, function($s) { return $s['is_active'] == 1; }));

// Set page title
$pageTitle = 'Hall Services';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Hall Services</h1>
        <p>Manage additional services and amenities</p>
    </div>
    <div class="page-actions">
        <?php if (hasPermission('halls.create')): ?>
        <a href="add.php" class="btn btn-primary">
            <i class="icon-plus"></i> Add Service
        </a>
        <?php endif; ?>
        <a href="../index.php" class="btn btn-secondary">
            <i class="icon-building"></i> Back to Halls
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo $totalServices; ?></h3>
                <p>Total Services</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo $activeServices; ?></h3>
                <p>Active Services</p>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card">
    <div class="card-body">
        <form method="GET" class="form-inline">
            <div class="form-group">
                <label for="search">Search:</label>
                <input type="text" name="search" id="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Service name or description" class="form-control">
            </div>
            <div class="form-group">
                <label for="is_active">Status:</label>
                <select name="is_active" id="is_active" class="form-control">
                    <option value="">All Status</option>
                    <option value="1" <?php echo $isActive === '1' ? 'selected' : ''; ?>>Active</option>
                    <option value="0" <?php echo $isActive === '0' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="index.php" class="btn btn-secondary">Clear</a>
        </form>
    </div>
</div>

<!-- Services Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Service Name</th>
                        <th>Description</th>
                        <th>Price</th>
                        <th>Unit</th>
                        <th>Status</th>
                        <th>Times Used</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($services)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted">No services found</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($services as $service): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($service['service_name']); ?></strong>
                            </td>
                            <td>
                                <?php echo htmlspecialchars(substr($service['description'], 0, 100)); ?>
                                <?php if (strlen($service['description']) > 100): ?>
                                    <span class="text-muted">...</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo formatCurrency($service['price']); ?></strong>
                            </td>
                            <td>
                                <span class="badge badge-info"><?php echo htmlspecialchars($service['unit']); ?></span>
                            </td>
                            <td>
                                <span class="badge <?php echo $service['is_active'] ? 'badge-success' : 'badge-secondary'; ?>">
                                    <?php echo $service['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-primary"><?php echo $service['times_used']; ?></span>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($service['created_by_first'] . ' ' . $service['created_by_last']); ?>
                                <br><small class="text-muted"><?php echo date('M d, Y', strtotime($service['created_at'])); ?></small>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="view.php?id=<?php echo $service['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" title="View">
                                        <i class="icon-eye"></i>
                                    </a>
                                    
                                    <?php if (hasPermission('halls.edit')): ?>
                                    <a href="edit.php?id=<?php echo $service['id']; ?>" 
                                       class="btn btn-sm btn-outline-secondary" title="Edit">
                                        <i class="icon-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (hasPermission('halls.delete') && $service['times_used'] == 0): ?>
                                    <button onclick="deleteService(<?php echo $service['id']; ?>)" 
                                            class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="icon-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function deleteService(serviceId) {
    if (confirm('Are you sure you want to delete this service? This action cannot be undone.')) {
        fetch('delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'service_id=' + serviceId + '&csrf_token=' + document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}
</script>

<style>
.summary-card {
    text-align: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
}

.summary-card h3 {
    margin: 0;
    font-size: 24px;
    font-weight: bold;
}

.summary-card p {
    margin: 5px 0 0 0;
    opacity: 0.9;
}

.badge-success { background-color: #28a745; color: white; }
.badge-secondary { background-color: #6c757d; color: white; }
.badge-info { background-color: #17a2b8; color: white; }
.badge-primary { background-color: #007bff; color: white; }

.form-group {
    margin-right: 15px;
    margin-bottom: 10px;
}

.form-group label {
    margin-right: 5px;
    font-weight: 500;
}

.btn-group .btn {
    margin-right: 2px;
}

.btn-group .btn:last-child {
    margin-right: 0;
}
</style>

<?php include '../../includes/footer.php'; ?>
