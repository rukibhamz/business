<?php
/**
 * Business Management System - Halls List
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
$status = $_GET['status'] ?? '';
$categoryId = (int)($_GET['category_id'] ?? 0);
$search = $_GET['search'] ?? '';

// Build query
$whereConditions = [];
$params = [];
$paramTypes = '';

if (!empty($status)) {
    $whereConditions[] = 'h.status = ?';
    $params[] = $status;
    $paramTypes .= 's';
}

if ($categoryId > 0) {
    $whereConditions[] = 'h.category_id = ?';
    $params[] = $categoryId;
    $paramTypes .= 'i';
}

if (!empty($search)) {
    $whereConditions[] = '(h.hall_name LIKE ? OR h.hall_code LIKE ? OR h.location LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $paramTypes .= 'sss';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get halls
$query = "
    SELECT h.*, hc.category_name, u.first_name as created_by_first, u.last_name as created_by_last,
           COUNT(hb.id) as total_bookings,
           SUM(hb.total_amount) as total_revenue,
           SUM(hb.duration_hours) as total_hours_booked
    FROM " . DB_PREFIX . "halls h
    JOIN " . DB_PREFIX . "hall_categories hc ON h.category_id = hc.id
    LEFT JOIN " . DB_PREFIX . "users u ON h.created_by = u.id
    LEFT JOIN " . DB_PREFIX . "hall_bookings hb ON h.id = hb.hall_id AND hb.booking_status != 'Cancelled'
    {$whereClause}
    GROUP BY h.id
    ORDER BY h.created_at DESC
    LIMIT 50
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$halls = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get hall categories for filter dropdown
$categories = $conn->query("
    SELECT id, category_name 
    FROM " . DB_PREFIX . "hall_categories 
    WHERE is_active = 1 
    ORDER BY category_name
")->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$totalHalls = count($halls);
$availableHalls = count(array_filter($halls, function($h) { return $h['status'] == 'Available'; }));
$totalRevenue = array_sum(array_column($halls, 'total_revenue'));
$totalBookings = array_sum(array_column($halls, 'total_bookings'));

// Set page title
$pageTitle = 'Halls';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Halls</h1>
        <p>Manage halls and bookings</p>
    </div>
    <div class="page-actions">
        <?php if (hasPermission('halls.create')): ?>
        <a href="add.php" class="btn btn-primary">
            <i class="icon-plus"></i> Add Hall
        </a>
        <?php endif; ?>
        <a href="bookings/index.php" class="btn btn-secondary">
            <i class="icon-calendar"></i> View Bookings
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo $totalHalls; ?></h3>
                <p>Total Halls</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo $availableHalls; ?></h3>
                <p>Available Halls</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo formatCurrency($totalRevenue); ?></h3>
                <p>Total Revenue</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo $totalBookings; ?></h3>
                <p>Total Bookings</p>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card">
    <div class="card-body">
        <form method="GET" class="form-inline">
            <div class="form-group">
                <label for="status">Status:</label>
                <select name="status" id="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="Available" <?php echo $status == 'Available' ? 'selected' : ''; ?>>Available</option>
                    <option value="Maintenance" <?php echo $status == 'Maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                    <option value="Unavailable" <?php echo $status == 'Unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                </select>
            </div>
            <div class="form-group">
                <label for="category_id">Category:</label>
                <select name="category_id" id="category_id" class="form-control">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>" 
                            <?php echo $categoryId == $category['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['category_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="search">Search:</label>
                <input type="text" name="search" id="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Hall name, code, or location" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="index.php" class="btn btn-secondary">Clear</a>
        </form>
    </div>
</div>

<!-- Halls Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Hall Code</th>
                        <th>Hall Name</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Capacity</th>
                        <th>Status</th>
                        <th>Bookings</th>
                        <th>Revenue</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($halls)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted">No halls found</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($halls as $hall): ?>
                        <tr>
                            <td>
                                <a href="view.php?id=<?php echo $hall['id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($hall['hall_code']); ?>
                                </a>
                            </td>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars($hall['hall_name']); ?></strong>
                                    <?php if ($hall['is_featured']): ?>
                                        <span class="badge badge-warning ml-2">Featured</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($hall['category_name']); ?></td>
                            <td><?php echo htmlspecialchars($hall['location'] ?: '-'); ?></td>
                            <td><?php echo number_format($hall['capacity']); ?> people</td>
                            <td>
                                <span class="badge <?php echo getHallStatusBadgeClass($hall['status']); ?>">
                                    <?php echo $hall['status']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-info"><?php echo $hall['total_bookings']; ?></span>
                                <?php if ($hall['total_hours_booked'] > 0): ?>
                                    <br><small class="text-muted"><?php echo round($hall['total_hours_booked'], 1); ?> hrs</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatCurrency($hall['total_revenue']); ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="view.php?id=<?php echo $hall['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" title="View">
                                        <i class="icon-eye"></i>
                                    </a>
                                    
                                    <?php if (hasPermission('halls.edit')): ?>
                                    <a href="edit.php?id=<?php echo $hall['id']; ?>" 
                                       class="btn btn-sm btn-outline-secondary" title="Edit">
                                        <i class="icon-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <a href="bookings/index.php?hall_id=<?php echo $hall['id']; ?>" 
                                       class="btn btn-sm btn-outline-info" title="Bookings">
                                        <i class="icon-calendar"></i>
                                        <?php if ($hall['total_bookings'] > 0): ?>
                                            <span class="badge badge-primary"><?php echo $hall['total_bookings']; ?></span>
                                        <?php endif; ?>
                                    </a>
                                    
                                    <?php if (hasPermission('halls.create')): ?>
                                    <a href="duplicate.php?id=<?php echo $hall['id']; ?>" 
                                       class="btn btn-sm btn-outline-warning" title="Duplicate">
                                        <i class="icon-copy"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (hasPermission('halls.delete') && $hall['total_bookings'] == 0): ?>
                                    <button onclick="deleteHall(<?php echo $hall['id']; ?>)" 
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
function deleteHall(hallId) {
    if (confirm('Are you sure you want to delete this hall? This action cannot be undone.')) {
        fetch('delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'hall_id=' + hallId + '&csrf_token=' + document.querySelector('meta[name="csrf-token"]').getAttribute('content')
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
.badge-danger { background-color: #dc3545; color: white; }
.badge-info { background-color: #17a2b8; color: white; }
.badge-warning { background-color: #ffc107; color: #333; }
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
