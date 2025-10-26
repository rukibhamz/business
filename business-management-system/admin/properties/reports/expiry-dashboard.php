<?php
/**
 * Business Management System - Rent Expiry Dashboard
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

// Get expiring leases (next 30 days)
$stmt = $conn->prepare("
    SELECT l.*, 
           p.property_name, p.property_code,
           t.first_name, t.last_name, t.email, t.phone,
           DATEDIFF(l.end_date, CURDATE()) as days_remaining
    FROM " . DB_PREFIX . "leases l
    LEFT JOIN " . DB_PREFIX . "properties p ON l.property_id = p.id
    LEFT JOIN " . DB_PREFIX . "tenants t ON l.tenant_id = t.id
    WHERE l.lease_status = 'Active' 
    AND l.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY l.end_date ASC
");
$stmt->execute();
$expiringLeases = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get expired leases
$stmt = $conn->prepare("
    SELECT l.*, 
           p.property_name, p.property_code,
           t.first_name, t.last_name, t.email, t.phone,
           DATEDIFF(CURDATE(), l.end_date) as days_overdue
    FROM " . DB_PREFIX . "leases l
    LEFT JOIN " . DB_PREFIX . "properties p ON l.property_id = p.id
    LEFT JOIN " . DB_PREFIX . "tenants t ON l.tenant_id = t.id
    WHERE l.lease_status = 'Active' 
    AND l.end_date < CURDATE()
    ORDER BY l.end_date ASC
");
$stmt->execute();
$expiredLeases = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get overdue rent payments
$stmt = $conn->prepare("
    SELECT rp.*, 
           l.lease_code, l.monthly_rent, l.currency,
           p.property_name, p.property_code,
           t.first_name, t.last_name, t.email, t.phone,
           DATEDIFF(CURDATE(), rp.payment_date) as days_overdue
    FROM " . DB_PREFIX . "rent_payments rp
    LEFT JOIN " . DB_PREFIX . "leases l ON rp.lease_id = l.id
    LEFT JOIN " . DB_PREFIX . "properties p ON l.property_id = p.id
    LEFT JOIN " . DB_PREFIX . "tenants t ON l.tenant_id = t.id
    WHERE rp.payment_status = 'Overdue'
    ORDER BY rp.payment_date ASC
");
$stmt->execute();
$overduePayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$statsQuery = "
    SELECT 
        COUNT(CASE WHEN l.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as expiring_30,
        COUNT(CASE WHEN l.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY) THEN 1 END) as expiring_60,
        COUNT(CASE WHEN l.end_date < CURDATE() THEN 1 END) as expired,
        COUNT(CASE WHEN rp.payment_status = 'Overdue' THEN 1 END) as overdue_payments,
        SUM(CASE WHEN rp.payment_status = 'Overdue' THEN rp.amount_due - rp.amount_paid ELSE 0 END) as total_overdue_amount
    FROM " . DB_PREFIX . "leases l
    LEFT JOIN " . DB_PREFIX . "rent_payments rp ON l.id = rp.lease_id
    WHERE l.lease_status = 'Active'
";
$stmt = $conn->prepare($statsQuery);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Set page title
$pageTitle = 'Rent Expiry Dashboard';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Rent Expiry Dashboard</h1>
        <p>Monitor lease expirations and overdue payments</p>
    </div>
    <div class="page-actions">
        <a href="../leases/index.php" class="btn btn-outline-primary">
            <i class="icon-file-text"></i> View All Leases
        </a>
        <a href="../rent/index.php" class="btn btn-outline-primary">
            <i class="icon-dollar-sign"></i> View All Payments
        </a>
    </div>
</div>

<!-- Alert Statistics -->
<div class="row mb-4">
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
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo $stats['expiring_60']; ?></h4>
                        <p class="mb-0">Expiring (60 days)</p>
                    </div>
                    <div class="align-self-center">
                        <i class="icon-calendar fs-1"></i>
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
                        <h4 class="mb-0"><?php echo $stats['expired']; ?></h4>
                        <p class="mb-0">Expired Leases</p>
                    </div>
                    <div class="align-self-center">
                        <i class="icon-alert-triangle fs-1"></i>
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
                        <h4 class="mb-0">₦<?php echo number_format($stats['total_overdue_amount'], 2); ?></h4>
                        <p class="mb-0">Overdue Amount</p>
                    </div>
                    <div class="align-self-center">
                        <i class="icon-dollar-sign fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Expiring Leases -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Leases Expiring Soon (Next 30 Days)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($expiringLeases)): ?>
                <div class="text-center py-3">
                    <i class="icon-check-circle fs-1 text-success"></i>
                    <p class="text-muted mt-2">No leases expiring in the next 30 days</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($expiringLeases as $lease): ?>
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><?php echo htmlspecialchars($lease['lease_code']); ?></h6>
                                <p class="mb-1">
                                    <strong>Property:</strong> <?php echo htmlspecialchars($lease['property_name']); ?><br>
                                    <strong>Tenant:</strong> <?php echo htmlspecialchars($lease['first_name'] . ' ' . $lease['last_name']); ?><br>
                                    <strong>End Date:</strong> <?php echo date('M d, Y', strtotime($lease['end_date'])); ?>
                                </p>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-warning"><?php echo $lease['days_remaining']; ?> days</span>
                                <div class="mt-2">
                                    <a href="../leases/view.php?id=<?php echo $lease['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="icon-eye"></i>
                                    </a>
                                    <a href="../leases/renew.php?id=<?php echo $lease['id']; ?>" class="btn btn-sm btn-success">
                                        <i class="icon-refresh-cw"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Expired Leases -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Expired Leases</h5>
            </div>
            <div class="card-body">
                <?php if (empty($expiredLeases)): ?>
                <div class="text-center py-3">
                    <i class="icon-check-circle fs-1 text-success"></i>
                    <p class="text-muted mt-2">No expired leases</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($expiredLeases as $lease): ?>
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><?php echo htmlspecialchars($lease['lease_code']); ?></h6>
                                <p class="mb-1">
                                    <strong>Property:</strong> <?php echo htmlspecialchars($lease['property_name']); ?><br>
                                    <strong>Tenant:</strong> <?php echo htmlspecialchars($lease['first_name'] . ' ' . $lease['last_name']); ?><br>
                                    <strong>End Date:</strong> <?php echo date('M d, Y', strtotime($lease['end_date'])); ?>
                                </p>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-danger"><?php echo $lease['days_overdue']; ?> days overdue</span>
                                <div class="mt-2">
                                    <a href="../leases/view.php?id=<?php echo $lease['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="icon-eye"></i>
                                    </a>
                                    <a href="../leases/terminate.php?id=<?php echo $lease['id']; ?>" class="btn btn-sm btn-danger">
                                        <i class="icon-x-circle"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Overdue Payments -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Overdue Rent Payments</h5>
    </div>
    <div class="card-body">
        <?php if (empty($overduePayments)): ?>
        <div class="text-center py-4">
            <i class="icon-check-circle fs-1 text-success"></i>
            <p class="text-muted mt-2">No overdue payments</p>
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
                        <th>Overdue Days</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($overduePayments as $payment): ?>
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
                        <td><?php echo $payment['currency']; ?> <?php echo number_format($payment['amount_paid'], 2); ?></td>
                        <td>
                            <span class="badge bg-danger"><?php echo $payment['days_overdue']; ?> days</span>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="../rent/view.php?id=<?php echo $payment['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="icon-eye"></i>
                                </a>
                                <a href="../rent/add.php?lease_id=<?php echo $payment['lease_id']; ?>" class="btn btn-sm btn-success">
                                    <i class="icon-dollar-sign"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-warning" onclick="sendReminder(<?php echo $payment['tenant_id']; ?>)">
                                    <i class="icon-mail"></i>
                                </button>
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

<!-- Automated Actions -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Automated Actions</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <i class="icon-mail fs-1 text-primary mb-3"></i>
                        <h6>Send Expiry Reminders</h6>
                        <p class="text-muted">Send automated reminders to tenants with expiring leases</p>
                        <button type="button" class="btn btn-primary" onclick="sendExpiryReminders()">
                            <i class="icon-send"></i> Send Reminders
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <i class="icon-refresh-cw fs-1 text-success mb-3"></i>
                        <h6>Auto-Renew Leases</h6>
                        <p class="text-muted">Automatically renew leases for tenants who opt-in</p>
                        <button type="button" class="btn btn-success" onclick="autoRenewLeases()">
                            <i class="icon-refresh-cw"></i> Auto Renew
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <i class="icon-file-text fs-1 text-info mb-3"></i>
                        <h6>Generate Reports</h6>
                        <p class="text-muted">Generate expiry and overdue payment reports</p>
                        <button type="button" class="btn btn-info" onclick="generateReports()">
                            <i class="icon-download"></i> Generate Reports
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function sendReminder(tenantId) {
    if (confirm('Send payment reminder to this tenant?')) {
        // Implement send reminder functionality
        console.log('Send reminder to tenant:', tenantId);
    }
}

function sendExpiryReminders() {
    if (confirm('Send expiry reminders to all tenants with expiring leases?')) {
        // Implement send expiry reminders functionality
        console.log('Send expiry reminders');
    }
}

function autoRenewLeases() {
    if (confirm('Automatically renew leases for tenants who have opted in?')) {
        // Implement auto-renew functionality
        console.log('Auto-renew leases');
    }
}

function generateReports() {
    // Implement generate reports functionality
    console.log('Generate reports');
    window.open('reports.php', '_blank');
}
</script>

<?php include '../../includes/footer.php'; ?>
