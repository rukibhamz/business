<?php
/**
 * Business Management System - Activity Logs
 * Phase 2: User Management & Settings System
 */

// Define system constant
define('BMS_SYSTEM', true);

// Start session
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';

// Check authentication and permissions
requireLogin();
requirePermission('activity.view');

// Get database connection
$conn = getDB();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCSRFToken($_POST['csrf_token'] ?? '');
    
    switch ($_POST['action']) {
        case 'clear_old_logs':
            handleClearOldLogs();
            break;
        case 'export_logs':
            handleExportLogs();
            break;
    }
    exit;
}

/**
 * Handle clearing old logs
 */
function handleClearOldLogs() {
    global $conn;
    
    $days = (int)($_POST['days'] ?? 30);
    
    if ($days < 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid number of days']);
        return;
    }
    
    $stmt = $conn->prepare("DELETE FROM " . DB_PREFIX . "activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->bind_param('i', $days);
    
    if ($stmt->execute()) {
        $deletedCount = $stmt->affected_rows;
        logActivity('activity.clear_logs', "Cleared {$deletedCount} old activity logs", ['days' => $days]);
        echo json_encode(['success' => true, 'message' => "Cleared {$deletedCount} old logs"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to clear old logs']);
    }
}

/**
 * Handle exporting logs
 */
function handleExportLogs() {
    global $conn;
    
    $format = $_POST['format'] ?? 'csv';
    $filters = $_POST['filters'] ?? [];
    
    // Build query based on filters
    $whereConditions = [];
    $params = [];
    $paramTypes = '';
    
    if (!empty($filters['user_id'])) {
        $whereConditions[] = "al.user_id = ?";
        $params[] = (int)$filters['user_id'];
        $paramTypes .= 'i';
    }
    
    if (!empty($filters['action'])) {
        $whereConditions[] = "al.action = ?";
        $params[] = $filters['action'];
        $paramTypes .= 's';
    }
    
    if (!empty($filters['date_from'])) {
        $whereConditions[] = "DATE(al.created_at) >= ?";
        $params[] = $filters['date_from'];
        $paramTypes .= 's';
    }
    
    if (!empty($filters['date_to'])) {
        $whereConditions[] = "DATE(al.created_at) <= ?";
        $params[] = $filters['date_to'];
        $paramTypes .= 's';
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $query = "
        SELECT al.*, u.first_name, u.last_name, u.username
        FROM " . DB_PREFIX . "activity_logs al
        LEFT JOIN " . DB_PREFIX . "users u ON al.user_id = u.id
        {$whereClause}
        ORDER BY al.created_at DESC
    ";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($paramTypes, ...$params);
    }
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if ($format === 'csv') {
        exportToCSV($logs);
    } else {
        echo json_encode(['success' => false, 'message' => 'Unsupported export format']);
    }
}

/**
 * Export logs to CSV
 */
function exportToCSV($logs) {
    $filename = 'activity_logs_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, [
        'ID', 'User', 'Action', 'Description', 'IP Address', 'User Agent', 'Date/Time', 'Additional Data'
    ]);
    
    // CSV data
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['id'],
            $log['first_name'] . ' ' . $log['last_name'] . ' (' . $log['username'] . ')',
            $log['action'],
            $log['description'],
            $log['ip_address'],
            $log['user_agent'],
            $log['created_at'],
            $log['additional_data']
        ]);
    }
    
    fclose($output);
    exit;
}

// Get pagination parameters
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(10, min(100, (int)($_GET['limit'] ?? getSetting('records_per_page', 25))));
$offset = ($page - 1) * $limit;

// Get search and filter parameters
$search = sanitizeInput($_GET['search'] ?? '');
$userId = (int)($_GET['user'] ?? 0);
$action = sanitizeInput($_GET['action'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$sortBy = sanitizeInput($_GET['sort'] ?? 'created_at');
$sortOrder = strtoupper($_GET['order'] ?? 'DESC');

// Validate sort parameters
$allowedSorts = ['id', 'user_id', 'action', 'created_at'];
if (!in_array($sortBy, $allowedSorts)) {
    $sortBy = 'created_at';
}
if (!in_array($sortOrder, ['ASC', 'DESC'])) {
    $sortOrder = 'DESC';
}

// Build query
$whereConditions = [];
$params = [];
$paramTypes = '';

// Search condition
if (!empty($search)) {
    $whereConditions[] = "(al.description LIKE ? OR al.action LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $paramTypes .= 'sssss';
}

// User filter
if ($userId > 0) {
    $whereConditions[] = "al.user_id = ?";
    $params[] = $userId;
    $paramTypes .= 'i';
}

// Action filter
if (!empty($action)) {
    $whereConditions[] = "al.action = ?";
    $params[] = $action;
    $paramTypes .= 's';
}

// Date filters
if (!empty($dateFrom)) {
    $whereConditions[] = "DATE(al.created_at) >= ?";
    $params[] = $dateFrom;
    $paramTypes .= 's';
}

if (!empty($dateTo)) {
    $whereConditions[] = "DATE(al.created_at) <= ?";
    $params[] = $dateTo;
    $paramTypes .= 's';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$countQuery = "
    SELECT COUNT(*) as total 
    FROM " . DB_PREFIX . "activity_logs al 
    LEFT JOIN " . DB_PREFIX . "users u ON al.user_id = u.id 
    {$whereClause}
";

$stmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$totalLogs = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalLogs / $limit);

// Get logs
$logsQuery = "
    SELECT al.*, u.first_name, u.last_name, u.username
    FROM " . DB_PREFIX . "activity_logs al 
    LEFT JOIN " . DB_PREFIX . "users u ON al.user_id = u.id 
    {$whereClause}
    ORDER BY al.{$sortBy} {$sortOrder}
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;
$paramTypes .= 'ii';

$stmt = $conn->prepare($logsQuery);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get users for filter dropdown
$usersQuery = "SELECT id, first_name, last_name, username FROM " . DB_PREFIX . "users ORDER BY first_name, last_name";
$users = $conn->query($usersQuery)->fetch_all(MYSQLI_ASSOC);

// Get unique actions for filter dropdown
$actionsQuery = "SELECT DISTINCT action FROM " . DB_PREFIX . "activity_logs ORDER BY action";
$actions = $conn->query($actionsQuery)->fetch_all(MYSQLI_ASSOC);

// Include header
include '../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Activity Logs</h1>
        <p>Monitor system activity and user actions</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-warning" onclick="clearOldLogs()">
            <i class="icon-trash"></i> Clear Old Logs
        </button>
        <button class="btn btn-info" onclick="exportLogs()">
            <i class="icon-download"></i> Export Logs
        </button>
    </div>
</div>

<!-- Filters and Search -->
<div class="card">
    <div class="card-header">
        <h3>Search & Filter</h3>
    </div>
    <div class="card-body">
        <form method="GET" class="filter-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by description, action, or user">
                </div>
                <div class="form-group">
                    <label for="user">User</label>
                    <select id="user" name="user">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $userId == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="action">Action</label>
                    <select id="action" name="action">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $actionItem): ?>
                        <option value="<?php echo $actionItem['action']; ?>" <?php echo $action == $actionItem['action'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($actionItem['action']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="date_from">Date From</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                </div>
                <div class="form-group">
                    <label for="date_to">Date To</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="index.php" class="btn btn-secondary">Clear</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Activity Logs Table -->
<div class="card">
    <div class="card-header">
        <h3>Activity Logs (<?php echo number_format($totalLogs); ?> total)</h3>
    </div>
    <div class="card-body">
        <?php if (empty($logs)): ?>
        <div class="empty-state">
            <i class="icon-activity"></i>
            <h3>No activity logs found</h3>
            <p>No logs match your search criteria.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'id', 'order' => $sortBy == 'id' && $sortOrder == 'ASC' ? 'DESC' : 'ASC'])); ?>">
                                ID <?php echo $sortBy == 'id' ? ($sortOrder == 'ASC' ? '↑' : '↓') : ''; ?>
                            </a>
                        </th>
                        <th>User</th>
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'action', 'order' => $sortBy == 'action' && $sortOrder == 'ASC' ? 'DESC' : 'ASC'])); ?>">
                                Action <?php echo $sortBy == 'action' ? ($sortOrder == 'ASC' ? '↑' : '↓') : ''; ?>
                            </a>
                        </th>
                        <th>Description</th>
                        <th>IP Address</th>
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'created_at', 'order' => $sortBy == 'created_at' && $sortOrder == 'ASC' ? 'DESC' : 'ASC'])); ?>">
                                Date/Time <?php echo $sortBy == 'created_at' ? ($sortOrder == 'ASC' ? '↑' : '↓') : ''; ?>
                            </a>
                        </th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo $log['id']; ?></td>
                        <td>
                            <?php if ($log['user_id']): ?>
                            <div class="user-info">
                                <strong><?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?></strong>
                                <small class="text-muted"><?php echo htmlspecialchars($log['username']); ?></small>
                            </div>
                            <?php else: ?>
                            <span class="text-muted">System</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo getActionBadgeClass($log['action']); ?>">
                                <?php echo htmlspecialchars($log['action']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($log['description']); ?></td>
                        <td>
                            <code><?php echo htmlspecialchars($log['ip_address']); ?></code>
                        </td>
                        <td>
                            <?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?>
                        </td>
                        <td>
                            <?php if (!empty($log['additional_data'])): ?>
                            <a href="view.php?id=<?php echo $log['id']; ?>" class="btn btn-sm btn-info">
                                <i class="icon-eye"></i> View
                            </a>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="btn btn-secondary">
                <i class="icon-chevron-left"></i> Previous
            </a>
            <?php endif; ?>
            
            <span class="pagination-info">
                Page <?php echo $page; ?> of <?php echo $totalPages; ?>
            </span>
            
            <?php if ($page < $totalPages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="btn btn-secondary">
                Next <i class="icon-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function clearOldLogs() {
    const days = prompt('Enter number of days (logs older than this will be deleted):', '30');
    if (days && !isNaN(days) && days > 0) {
        if (confirm(`Are you sure you want to delete logs older than ${days} days? This action cannot be undone.`)) {
            const formData = new FormData();
            formData.append('action', 'clear_old_logs');
            formData.append('days', days);
            formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while clearing logs.');
            });
        }
    }
}

function exportLogs() {
    if (confirm('Export current filtered logs to CSV?')) {
        const formData = new FormData();
        formData.append('action', 'export_logs');
        formData.append('format', 'csv');
        formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
        
        // Add current filters
        const urlParams = new URLSearchParams(window.location.search);
        const filters = {};
        for (const [key, value] of urlParams) {
            if (['user', 'action', 'date_from', 'date_to'].includes(key)) {
                filters[key] = value;
            }
        }
        formData.append('filters', JSON.stringify(filters));
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (response.ok) {
                return response.blob();
            }
            throw new Error('Export failed');
        })
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'activity_logs_' + new Date().toISOString().slice(0, 19).replace(/:/g, '-') + '.csv';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while exporting logs.');
        });
    }
}
</script>

<style>
.user-info {
    display: flex;
    flex-direction: column;
}

.user-info strong {
    font-size: 14px;
}

.user-info small {
    font-size: 12px;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

code {
    background-color: #f8f9fa;
    padding: 2px 4px;
    border-radius: 3px;
    font-size: 12px;
}
</style>

<?php
/**
 * Get badge class based on action type
 */
function getActionBadgeClass($action) {
    $classes = [
        'login' => 'success',
        'logout' => 'secondary',
        'users.create' => 'primary',
        'users.edit' => 'warning',
        'users.delete' => 'danger',
        'roles.create' => 'primary',
        'roles.edit' => 'warning',
        'roles.delete' => 'danger',
        'settings.edit' => 'info',
        'password_change' => 'warning',
        'profile_update' => 'info'
    ];
    
    return $classes[$action] ?? 'secondary';
}

include '../includes/footer.php';
?>
