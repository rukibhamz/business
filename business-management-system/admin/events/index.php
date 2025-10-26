<?php
/**
 * Business Management System - Events List
 * Phase 4: Event Booking System Module
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
require_once '../../../includes/event-functions.php';

// Check authentication and permissions
requireLogin();
requirePermission('events.view');

// Get database connection
$conn = getDB();

// Get filter parameters
$status = $_GET['status'] ?? '';
$categoryId = (int)($_GET['category_id'] ?? 0);
$dateFilter = $_GET['date_filter'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$whereConditions = [];
$params = [];
$paramTypes = '';

if (!empty($status)) {
    $whereConditions[] = 'e.status = ?';
    $params[] = $status;
    $paramTypes .= 's';
}

if ($categoryId > 0) {
    $whereConditions[] = 'e.category_id = ?';
    $params[] = $categoryId;
    $paramTypes .= 'i';
}

if (!empty($search)) {
    $whereConditions[] = '(e.event_name LIKE ? OR e.event_code LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $paramTypes .= 'ss';
}

// Date filter
if ($dateFilter == 'upcoming') {
    $whereConditions[] = 'e.start_date >= CURDATE()';
} elseif ($dateFilter == 'past') {
    $whereConditions[] = 'e.start_date < CURDATE()';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get events
$query = "
    SELECT e.*, ec.category_name, u.first_name as created_by_first, u.last_name as created_by_last,
           COUNT(eb.id) as total_bookings,
           SUM(eb.total_amount) as total_revenue,
           SUM(et.quantity_sold) as tickets_sold,
           SUM(et.quantity_available) as total_capacity
    FROM " . DB_PREFIX . "events e
    JOIN " . DB_PREFIX . "event_categories ec ON e.category_id = ec.id
    LEFT JOIN " . DB_PREFIX . "users u ON e.created_by = u.id
    LEFT JOIN " . DB_PREFIX . "event_bookings eb ON e.id = eb.event_id AND eb.booking_status != 'Cancelled'
    LEFT JOIN " . DB_PREFIX . "event_tickets et ON e.id = et.event_id
    {$whereClause}
    GROUP BY e.id
    ORDER BY e.start_date DESC, e.created_at DESC
    LIMIT 50
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get event categories for filter dropdown
$categories = $conn->query("
    SELECT id, category_name 
    FROM " . DB_PREFIX . "event_categories 
    WHERE is_active = 1 
    ORDER BY category_name
")->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$totalEvents = count($events);
$upcomingEvents = count(array_filter($events, function($e) { return $e['start_date'] >= date('Y-m-d'); }));
$totalRevenue = array_sum(array_column($events, 'total_revenue'));
$totalBookings = array_sum(array_column($events, 'total_bookings'));

// Set page title
$pageTitle = 'Events';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Events</h1>
        <p>Manage events and bookings</p>
    </div>
    <div class="page-actions">
        <?php if (hasPermission('events.create')): ?>
        <a href="add.php" class="btn btn-primary">
            <i class="icon-plus"></i> Add Event
        </a>
        <?php endif; ?>
        <a href="bookings/index.php" class="btn btn-secondary">
            <i class="icon-ticket"></i> View Bookings
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo $totalEvents; ?></h3>
                <p>Total Events</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo $upcomingEvents; ?></h3>
                <p>Upcoming Events</p>
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
                    <option value="Draft" <?php echo $status == 'Draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="Published" <?php echo $status == 'Published' ? 'selected' : ''; ?>>Published</option>
                    <option value="Cancelled" <?php echo $status == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="Completed" <?php echo $status == 'Completed' ? 'selected' : ''; ?>>Completed</option>
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
                <label for="date_filter">Date:</label>
                <select name="date_filter" id="date_filter" class="form-control">
                    <option value="">All Dates</option>
                    <option value="upcoming" <?php echo $dateFilter == 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                    <option value="past" <?php echo $dateFilter == 'past' ? 'selected' : ''; ?>>Past</option>
                </select>
            </div>
            <div class="form-group">
                <label for="search">Search:</label>
                <input type="text" name="search" id="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Event name or code" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="index.php" class="btn btn-secondary">Clear</a>
        </form>
    </div>
</div>

<!-- Events Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Event Code</th>
                        <th>Event Name</th>
                        <th>Category</th>
                        <th>Date & Time</th>
                        <th>Venue</th>
                        <th>Status</th>
                        <th>Tickets</th>
                        <th>Revenue</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($events)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted">No events found</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($events as $event): ?>
                        <tr>
                            <td>
                                <a href="view.php?id=<?php echo $event['id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($event['event_code']); ?>
                                </a>
                            </td>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars($event['event_name']); ?></strong>
                                    <?php if ($event['is_featured']): ?>
                                        <span class="badge badge-warning ml-2">Featured</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($event['category_name']); ?></td>
                            <td>
                                <?php echo formatEventDateTime($event['start_date'], $event['start_time']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($event['venue_name'] ?: '-'); ?></td>
                            <td>
                                <span class="badge <?php echo getEventStatusBadgeClass($event['status']); ?>">
                                    <?php echo $event['status']; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($event['total_capacity'] > 0): ?>
                                    <?php echo $event['tickets_sold']; ?> / <?php echo $event['total_capacity']; ?>
                                    <?php 
                                    $percentage = ($event['tickets_sold'] / $event['total_capacity']) * 100;
                                    if ($percentage >= 100): ?>
                                        <span class="badge badge-danger ml-1">Sold Out</span>
                                    <?php elseif ($percentage >= 80): ?>
                                        <span class="badge badge-warning ml-1">Almost Full</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php echo $event['tickets_sold']; ?> sold
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatCurrency($event['total_revenue']); ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="view.php?id=<?php echo $event['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" title="View">
                                        <i class="icon-eye"></i>
                                    </a>
                                    
                                    <?php if (hasPermission('events.edit')): ?>
                                    <a href="edit.php?id=<?php echo $event['id']; ?>" 
                                       class="btn btn-sm btn-outline-secondary" title="Edit">
                                        <i class="icon-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <a href="bookings/index.php?event_id=<?php echo $event['id']; ?>" 
                                       class="btn btn-sm btn-outline-info" title="Bookings">
                                        <i class="icon-ticket"></i>
                                        <?php if ($event['total_bookings'] > 0): ?>
                                            <span class="badge badge-primary"><?php echo $event['total_bookings']; ?></span>
                                        <?php endif; ?>
                                    </a>
                                    
                                    <?php if (hasPermission('events.create')): ?>
                                    <a href="duplicate.php?id=<?php echo $event['id']; ?>" 
                                       class="btn btn-sm btn-outline-warning" title="Duplicate">
                                        <i class="icon-copy"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (hasPermission('events.delete') && $event['status'] == 'Draft' && $event['total_bookings'] == 0): ?>
                                    <button onclick="deleteEvent(<?php echo $event['id']; ?>)" 
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
function deleteEvent(eventId) {
    if (confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
        fetch('delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'event_id=' + eventId + '&csrf_token=' + document.querySelector('meta[name="csrf-token"]').getAttribute('content')
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

