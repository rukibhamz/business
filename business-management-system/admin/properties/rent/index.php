<?php
/**
 * Business Management System - Rent Payments List
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
requirePermission('rent.view');

// Get database connection
$conn = getDB();

// Get filters
$lease_id = $_GET['lease_id'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$whereConditions = [];
$params = [];
$paramTypes = '';

if (!empty($lease_id)) {
    $whereConditions[] = "rp.lease_id = ?";
    $params[] = $lease_id;
    $paramTypes .= 'i';
}

if (!empty($status)) {
    $whereConditions[] = "rp.payment_status = ?";
    $params[] = $status;
    $paramTypes .= 's';
}

if (!empty($date_from)) {
    $whereConditions[] = "rp.payment_date >= ?";
    $params[] = $date_from;
    $paramTypes .= 's';
}

if (!empty($date_to)) {
    $whereConditions[] = "rp.payment_date <= ?";
    $params[] = $date_to;
    $paramTypes .= 's';
}

if (!empty($search)) {
    $whereConditions[] = "(l.lease_code LIKE ? OR p.property_name LIKE ? OR t.first_name LIKE ? OR t.last_name LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $paramTypes .= 'ssss';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get rent payments
$query = "
    SELECT rp.*, 
           l.lease_code, l.monthly_rent, l.currency,
           p.property_name, p.property_code,
           t.first_name, t.last_name, t.email, t.phone
    FROM " . DB_PREFIX . "rent_payments rp
    LEFT JOIN " . DB_PREFIX . "leases l ON rp.lease_id = l.id
    LEFT JOIN " . DB_PREFIX . "properties p ON l.property_id = p.id
    LEFT JOIN " . DB_PREFIX . "tenants t ON l.tenant_id = t.id
    $whereClause
    ORDER BY rp.payment_date DESC
    LIMIT 50
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get quick stats
$statsQuery = "
    SELECT 
        COUNT(*) as total_payments,
        SUM(CASE WHEN payment_status = 'Paid' THEN 1 ELSE 0 END) as paid_payments,
        SUM(CASE WHEN payment_status = 'Partial' THEN 1 ELSE 0 END) as partial_payments,
        SUM(CASE WHEN payment_status = 'Overdue' THEN 1 ELSE 0 END) as overdue_payments,
        SUM(amount_paid) as total_amount_paid
    FROM " . DB_PREFIX . "rent_payments
    " . (!empty($whereConditions) ? 'WHERE ' . implode(' AND ', str_replace('rp.', '', $whereConditions)) : '');
$stmt = $conn->prepare($statsQuery);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get leases for filter
$stmt = $conn->prepare("
    SELECT l.id, l.lease_code, p.property_name, t.first_name, t.last_name
    FROM " . DB_PREFIX . "leases l
    LEFT JOIN " . DB_PREFIX . "properties p ON l.property_id = p.id
    LEFT JOIN " . DB_PREFIX . "tenants t ON l.tenant_id = t.id
    WHERE l.lease_status = 'Active'
    ORDER BY l.lease_code
");
$stmt->execute();
$leases = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Set page title
$pageTitle = 'Rent Payments';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Rent Payments</h1>
        <p>Manage rent payment records and tracking</p>
    </div>
    <div class="page-actions">
        <a href="add.php" class="btn btn-primary">
            <i class="icon-plus"></i> Record Payment
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
                        <h4 class="mb-0"><?php echo $stats['total_payments']; ?></h4>
                        <p class="mb-0">Total Payments</p>
                    </div>
                    <div class="align-self-center">
                        <i class="icon-dollar-sign fs-1"></i>
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
                        <h4 class="mb-0"><?php echo $stats['paid_payments']; ?></h4>
                        <p class="mb-0">Paid</p>
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
                        <h4 class="mb-0"><?php echo $stats['partial_payments']; ?></h4>
                        <p class="mb-0">Partial</p>
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
                        <h4 class="mb-0">₦<?php echo number_format($stats['total_amount_paid'], 2); ?></h4>
                        <p class="mb-0">Total Collected</p>
                    </div>
                    <div class="align-self-center">
                        <i class="icon-trending-up fs-1"></i>
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
                       value="<?php echo htmlspecialchars($search); ?>" placeholder="Search payments...">
            </div>
            <div class="col-md-2">
                <label for="lease_id" class="form-label">Lease</label>
                <select class="form-select" id="lease_id" name="lease_id">
                    <option value="">All Leases</option>
                    <?php foreach ($leases as $lease): ?>
                    <option value="<?php echo $lease['id']; ?>" <?php echo $lease_id == $lease['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($lease['lease_code']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <option value="Paid" <?php echo $status === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="Partial" <?php echo $status === 'Partial' ? 'selected' : ''; ?>>Partial</option>
                    <option value="Overdue" <?php echo $status === 'Overdue' ? 'selected' : ''; ?>>Overdue</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="date_from" class="form-label">From Date</label>
                <input type="date" class="form-control" id="date_from" name="date_from" 
                       value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="col-md-2">
                <label for="date_to" class="form-label">To Date</label>
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

<!-- Payments Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Rent Payments</h5>
    </div>
    <div class="card-body">
        <?php if (empty($payments)): ?>
        <div class="text-center py-4">
            <i class="icon-dollar-sign fs-1 text-muted"></i>
            <p class="text-muted mt-2">No payments found</p>
            <a href="add.php" class="btn btn-primary">Record First Payment</a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Payment Date</th>
                        <th>Lease</th>
                        <th>Property</th>
                        <th>Tenant</th>
                        <th>Amount Due</th>
                        <th>Amount Paid</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($payment['lease_code']); ?></strong>
                        </td>
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($payment['property_name']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($payment['property_code']); ?></small>
                            </div>
                        </td>
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($payment['email']); ?></small>
                            </div>
                        </td>
                        <td><?php echo $payment['currency']; ?> <?php echo number_format($payment['amount_due'], 2); ?></td>
                        <td>
                            <strong><?php echo $payment['currency']; ?> <?php echo number_format($payment['amount_paid'], 2); ?></strong>
                        </td>
                        <td>
                            <?php
                            $statusClass = '';
                            switch ($payment['payment_status']) {
                                case 'Paid':
                                    $statusClass = 'success';
                                    break;
                                case 'Partial':
                                    $statusClass = 'warning';
                                    break;
                                case 'Overdue':
                                    $statusClass = 'danger';
                                    break;
                            }
                            ?>
                            <span class="badge bg-<?php echo $statusClass; ?>"><?php echo htmlspecialchars($payment['payment_status']); ?></span>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="view.php?id=<?php echo $payment['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="icon-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $payment['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="icon-edit"></i>
                                </a>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="icon-more-horizontal"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="#" onclick="printReceipt(<?php echo $payment['id']; ?>)">
                                            <i class="icon-printer"></i> Print Receipt
                                        </a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="deletePayment(<?php echo $payment['id']; ?>)">
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
function printReceipt(paymentId) {
    window.open('receipt.php?id=' + paymentId, '_blank');
}

function deletePayment(paymentId) {
    if (confirm('Are you sure you want to delete this payment record? This action cannot be undone.')) {
        // Implement delete functionality
        console.log('Delete payment:', paymentId);
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
