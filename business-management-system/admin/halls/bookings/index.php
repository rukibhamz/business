<?php
/**
 * Business Management System - Hall Bookings Management
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
requirePermission('halls.bookings');

// Get database connection
$conn = getDB();

// Get filter parameters
$hallId = (int)($_GET['hall_id'] ?? 0);
$status = $_GET['status'] ?? '';
$paymentStatus = $_GET['payment_status'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$whereConditions = [];
$params = [];
$paramTypes = '';

if ($hallId > 0) {
    $whereConditions[] = 'hb.hall_id = ?';
    $params[] = $hallId;
    $paramTypes .= 'i';
}

if (!empty($status)) {
    $whereConditions[] = 'hb.booking_status = ?';
    $params[] = $status;
    $paramTypes .= 's';
}

if (!empty($paymentStatus)) {
    $whereConditions[] = 'hb.payment_status = ?';
    $params[] = $paymentStatus;
    $paramTypes .= 's';
}

if (!empty($startDate)) {
    $whereConditions[] = 'hb.start_date >= ?';
    $params[] = $startDate;
    $paramTypes .= 's';
}

if (!empty($endDate)) {
    $whereConditions[] = 'hb.end_date <= ?';
    $params[] = $endDate;
    $paramTypes .= 's';
}

if (!empty($search)) {
    $whereConditions[] = '(hb.booking_number LIKE ? OR hb.event_name LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.company_name LIKE ?)';
    $searchParam = '%' . $search . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $paramTypes .= 'sssss';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get bookings
$query = "
    SELECT hb.*, h.hall_name, h.hall_code, h.location,
           c.first_name, c.last_name, c.company_name, c.email as customer_email, c.phone as customer_phone,
           u.first_name as created_by_first, u.last_name as created_by_last
    FROM " . DB_PREFIX . "hall_bookings hb
    JOIN " . DB_PREFIX . "halls h ON hb.hall_id = h.id
    LEFT JOIN " . DB_PREFIX . "customers c ON hb.customer_id = c.id
    LEFT JOIN " . DB_PREFIX . "users u ON hb.created_by = u.id
    {$whereClause}
    ORDER BY hb.start_date DESC, hb.created_at DESC
    LIMIT 50
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get halls for filter dropdown
$halls = $conn->query("
    SELECT id, hall_name, hall_code 
    FROM " . DB_PREFIX . "halls 
    WHERE status = 'Available' 
    ORDER BY hall_name
")->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$totalBookings = count($bookings);
$totalRevenue = array_sum(array_column($bookings, 'total_amount'));
$totalPaid = array_sum(array_column($bookings, 'amount_paid'));
$totalOutstanding = array_sum(array_column($bookings, 'balance_due'));

// Set page title
$pageTitle = 'Hall Bookings';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Hall Bookings</h1>
        <p>Manage hall bookings and payments</p>
    </div>
    <div class="page-actions">
        <?php if (hasPermission('halls.create')): ?>
        <a href="add.php" class="btn btn-primary">
            <i class="icon-plus"></i> Create Booking
        </a>
        <?php endif; ?>
        <a href="../index.php" class="btn btn-secondary">
            <i class="icon-building"></i> View Halls
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo $totalBookings; ?></h3>
                <p>Total Bookings</p>
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
                <h3><?php echo formatCurrency($totalPaid); ?></h3>
                <p>Amount Paid</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card summary-card">
            <div class="card-body">
                <h3><?php echo formatCurrency($totalOutstanding); ?></h3>
                <p>Outstanding</p>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card">
    <div class="card-body">
        <form method="GET" class="form-inline">
            <div class="form-group">
                <label for="hall_id">Hall:</label>
                <select name="hall_id" id="hall_id" class="form-control">
                    <option value="">All Halls</option>
                    <?php foreach ($halls as $hall): ?>
                    <option value="<?php echo $hall['id']; ?>" 
                            <?php echo $hallId == $hall['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($hall['hall_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="status">Booking Status:</label>
                <select name="status" id="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="Pending" <?php echo $status == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Confirmed" <?php echo $status == 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="Cancelled" <?php echo $status == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="Completed" <?php echo $status == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                </select>
            </div>
            <div class="form-group">
                <label for="payment_status">Payment Status:</label>
                <select name="payment_status" id="payment_status" class="form-control">
                    <option value="">All Payment Status</option>
                    <option value="Pending" <?php echo $paymentStatus == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Partial" <?php echo $paymentStatus == 'Partial' ? 'selected' : ''; ?>>Partial</option>
                    <option value="Paid" <?php echo $paymentStatus == 'Paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="Refunded" <?php echo $paymentStatus == 'Refunded' ? 'selected' : ''; ?>>Refunded</option>
                </select>
            </div>
            <div class="form-group">
                <label for="start_date">From Date:</label>
                <input type="date" name="start_date" id="start_date" 
                       value="<?php echo htmlspecialchars($startDate); ?>" class="form-control">
            </div>
            <div class="form-group">
                <label for="end_date">To Date:</label>
                <input type="date" name="end_date" id="end_date" 
                       value="<?php echo htmlspecialchars($endDate); ?>" class="form-control">
            </div>
            <div class="form-group">
                <label for="search">Search:</label>
                <input type="text" name="search" id="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Booking #, event, customer" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="index.php" class="btn btn-secondary">Clear</a>
        </form>
    </div>
</div>

<!-- Bookings Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Booking #</th>
                        <th>Hall</th>
                        <th>Event</th>
                        <th>Customer</th>
                        <th>Date & Time</th>
                        <th>Duration</th>
                        <th>Amount</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                    <tr>
                        <td colspan="11" class="text-center text-muted">No bookings found</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td>
                                <a href="view.php?id=<?php echo $booking['id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($booking['booking_number']); ?>
                                </a>
                            </td>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars($booking['hall_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($booking['hall_code']); ?></small>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars($booking['event_name'] ?: 'Event'); ?></strong>
                                    <?php if ($booking['event_type']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($booking['event_type']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars($booking['company_name'] ?: $booking['first_name'] . ' ' . $booking['last_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($booking['customer_email']); ?></small>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <strong><?php echo date('M d, Y', strtotime($booking['start_date'])); ?></strong>
                                    <br><small class="text-muted">
                                        <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - 
                                        <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                                    </small>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-info">
                                    <?php echo round($booking['duration_hours'], 1); ?> hrs
                                </span>
                            </td>
                            <td>
                                <strong><?php echo formatCurrency($booking['total_amount']); ?></strong>
                            </td>
                            <td>
                                <span class="text-success"><?php echo formatCurrency($booking['amount_paid']); ?></span>
                            </td>
                            <td>
                                <?php if ($booking['balance_due'] > 0): ?>
                                    <span class="text-danger"><?php echo formatCurrency($booking['balance_due']); ?></span>
                                <?php else: ?>
                                    <span class="text-success">₦0.00</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="status-badges">
                                    <span class="badge <?php echo getHallBookingStatusBadgeClass($booking['booking_status']); ?>">
                                        <?php echo $booking['booking_status']; ?>
                                    </span>
                                    <br>
                                    <span class="badge <?php echo getHallPaymentStatusBadgeClass($booking['payment_status']); ?>">
                                        <?php echo $booking['payment_status']; ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="view.php?id=<?php echo $booking['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" title="View">
                                        <i class="icon-eye"></i>
                                    </a>
                                    
                                    <?php if ($booking['booking_status'] == 'Pending'): ?>
                                    <a href="confirm.php?id=<?php echo $booking['id']; ?>" 
                                       class="btn btn-sm btn-outline-success" title="Confirm">
                                        <i class="icon-check"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($booking['balance_due'] > 0): ?>
                                    <a href="record-payment.php?id=<?php echo $booking['id']; ?>" 
                                       class="btn btn-sm btn-outline-info" title="Record Payment">
                                        <i class="icon-credit-card"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($booking['booking_status'] != 'Cancelled' && $booking['booking_status'] != 'Completed'): ?>
                                    <a href="cancel.php?id=<?php echo $booking['id']; ?>" 
                                       class="btn btn-sm btn-outline-warning" title="Cancel">
                                        <i class="icon-x"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($booking['booking_status'] == 'Confirmed'): ?>
                                    <a href="check-in.php?id=<?php echo $booking['id']; ?>" 
                                       class="btn btn-sm btn-outline-secondary" title="Check In">
                                        <i class="icon-log-in"></i>
                                    </a>
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

.status-badges {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.status-badges .badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}
</style>

<?php include '../../includes/footer.php'; ?>
