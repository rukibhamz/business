<?php
/**
 * Business Management System - View Lease
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

// Get lease ID
$leaseId = $_GET['id'] ?? 0;

if (!$leaseId) {
    header('Location: index.php');
    exit;
}

// Get lease details
$stmt = $conn->prepare("
    SELECT l.*, 
           p.property_name, p.property_code, p.address, p.city, p.state,
           p.monthly_rent as property_rent, p.currency as property_currency,
           t.first_name, t.last_name, t.email, t.phone, t.current_address,
           pt.type_name,
           u.first_name as created_by_name, u.last_name as created_by_last
    FROM " . DB_PREFIX . "leases l
    LEFT JOIN " . DB_PREFIX . "properties p ON l.property_id = p.id
    LEFT JOIN " . DB_PREFIX . "tenants t ON l.tenant_id = t.id
    LEFT JOIN " . DB_PREFIX . "property_types pt ON p.property_type_id = pt.id
    LEFT JOIN " . DB_PREFIX . "users u ON l.created_by = u.id
    WHERE l.id = ?
");
$stmt->bind_param('i', $leaseId);
$stmt->execute();
$lease = $stmt->get_result()->fetch_assoc();

if (!$lease) {
    header('Location: index.php');
    exit;
}

// Get rent payments
$stmt = $conn->prepare("
    SELECT * FROM " . DB_PREFIX . "rent_payments 
    WHERE lease_id = ? 
    ORDER BY payment_date DESC
");
$stmt->bind_param('i', $leaseId);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get lease history
$stmt = $conn->prepare("
    SELECT * FROM " . DB_PREFIX . "lease_history 
    WHERE lease_id = ? 
    ORDER BY created_at DESC
");
$stmt->bind_param('i', $leaseId);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate lease statistics
$totalRent = $lease['monthly_rent'] * $lease['duration_months'];
$paidAmount = array_sum(array_column($payments, 'amount_paid'));
$outstandingAmount = $totalRent - $paidAmount;
$daysRemaining = (strtotime($lease['end_date']) - time()) / (60 * 60 * 24);

// Set page title
$pageTitle = 'View Lease - ' . $lease['lease_code'];

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Lease Details</h1>
        <p><?php echo htmlspecialchars($lease['lease_code']); ?></p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Leases
        </a>
        <a href="edit.php?id=<?php echo $lease['id']; ?>" class="btn btn-outline-primary">
            <i class="icon-edit"></i> Edit Lease
        </a>
        <?php if ($lease['lease_status'] === 'Active'): ?>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
                <i class="icon-more-horizontal"></i> Actions
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="renew.php?id=<?php echo $lease['id']; ?>">
                    <i class="icon-refresh-cw"></i> Renew Lease
                </a></li>
                <li><a class="dropdown-item" href="terminate.php?id=<?php echo $lease['id']; ?>">
                    <i class="icon-x-circle"></i> Terminate Lease
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="../rent/add.php?lease_id=<?php echo $lease['id']; ?>">
                    <i class="icon-dollar-sign"></i> Record Payment
                </a></li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Lease Status Alert -->
<?php if ($lease['lease_status'] === 'Expired'): ?>
<div class="alert alert-danger">
    <i class="icon-alert-triangle"></i>
    <strong>Lease Expired:</strong> This lease expired on <?php echo date('M d, Y', strtotime($lease['end_date'])); ?>.
</div>
<?php elseif ($daysRemaining <= 30 && $daysRemaining > 0): ?>
<div class="alert alert-warning">
    <i class="icon-clock"></i>
    <strong>Lease Expiring Soon:</strong> This lease expires in <?php echo ceil($daysRemaining); ?> days.
</div>
<?php endif; ?>

<div class="row">
    <!-- Lease Information -->
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Lease Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Lease Code:</strong></td>
                                <td><?php echo htmlspecialchars($lease['lease_code']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
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
                            </tr>
                            <tr>
                                <td><strong>Start Date:</strong></td>
                                <td><?php echo date('M d, Y', strtotime($lease['start_date'])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>End Date:</strong></td>
                                <td><?php echo date('M d, Y', strtotime($lease['end_date'])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Duration:</strong></td>
                                <td><?php echo $lease['duration_months']; ?> months</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Lease Type:</strong></td>
                                <td><?php echo htmlspecialchars($lease['lease_type']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Payment Frequency:</strong></td>
                                <td><?php echo htmlspecialchars($lease['payment_frequency']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Currency:</strong></td>
                                <td><?php echo htmlspecialchars($lease['currency']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Grace Period:</strong></td>
                                <td><?php echo $lease['grace_period_days']; ?> days</td>
                            </tr>
                            <tr>
                                <td><strong>Created:</strong></td>
                                <td><?php echo date('M d, Y', strtotime($lease['created_at'])); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Property Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Property Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><?php echo htmlspecialchars($lease['property_name']); ?></h6>
                        <p class="text-muted mb-2">
                            <strong>Code:</strong> <?php echo htmlspecialchars($lease['property_code']); ?><br>
                            <strong>Type:</strong> <?php echo htmlspecialchars($lease['type_name']); ?><br>
                            <strong>Address:</strong> <?php echo htmlspecialchars($lease['address']); ?><br>
                            <strong>Location:</strong> <?php echo htmlspecialchars($lease['city'] . ', ' . $lease['state']); ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <a href="../view.php?id=<?php echo $lease['property_id']; ?>" class="btn btn-outline-primary">
                            <i class="icon-eye"></i> View Property
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tenant Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Tenant Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><?php echo htmlspecialchars($lease['first_name'] . ' ' . $lease['last_name']); ?></h6>
                        <p class="text-muted mb-2">
                            <strong>Email:</strong> <?php echo htmlspecialchars($lease['email']); ?><br>
                            <strong>Phone:</strong> <?php echo htmlspecialchars($lease['phone']); ?><br>
                            <strong>Address:</strong> <?php echo htmlspecialchars($lease['current_address']); ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <a href="../tenants/view.php?id=<?php echo $lease['tenant_id']; ?>" class="btn btn-outline-primary">
                            <i class="icon-eye"></i> View Tenant
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Special Clauses -->
        <?php if (!empty($lease['special_clauses'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Special Clauses</h5>
            </div>
            <div class="card-body">
                <p><?php echo nl2br(htmlspecialchars($lease['special_clauses'])); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Financial Summary -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Financial Summary</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Monthly Rent:</strong></td>
                        <td class="text-end"><?php echo $lease['currency']; ?> <?php echo number_format($lease['monthly_rent'], 2); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Security Deposit:</strong></td>
                        <td class="text-end"><?php echo $lease['currency']; ?> <?php echo number_format($lease['security_deposit'], 2); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Agency Fee:</strong></td>
                        <td class="text-end"><?php echo $lease['currency']; ?> <?php echo number_format($lease['agency_fee'], 2); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Service Charge:</strong></td>
                        <td class="text-end"><?php echo $lease['currency']; ?> <?php echo number_format($lease['service_charge'], 2); ?></td>
                    </tr>
                    <tr class="border-top">
                        <td><strong>Total Lease Value:</strong></td>
                        <td class="text-end"><strong><?php echo $lease['currency']; ?> <?php echo number_format($totalRent, 2); ?></strong></td>
                    </tr>
                    <tr>
                        <td><strong>Amount Paid:</strong></td>
                        <td class="text-end text-success"><?php echo $lease['currency']; ?> <?php echo number_format($paidAmount, 2); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Outstanding:</strong></td>
                        <td class="text-end text-danger"><?php echo $lease['currency']; ?> <?php echo number_format($outstandingAmount, 2); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Payment History -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Payments</h5>
            </div>
            <div class="card-body">
                <?php if (empty($payments)): ?>
                <p class="text-muted">No payments recorded yet.</p>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach (array_slice($payments, 0, 5) as $payment): ?>
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong><?php echo $lease['currency']; ?> <?php echo number_format($payment['amount_paid'], 2); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></small>
                            </div>
                            <div>
                                <?php
                                $paymentStatusClass = '';
                                switch ($payment['payment_status']) {
                                    case 'Paid':
                                        $paymentStatusClass = 'success';
                                        break;
                                    case 'Partial':
                                        $paymentStatusClass = 'warning';
                                        break;
                                    case 'Overdue':
                                        $paymentStatusClass = 'danger';
                                        break;
                                }
                                ?>
                                <span class="badge bg-<?php echo $paymentStatusClass; ?>"><?php echo htmlspecialchars($payment['payment_status']); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($payments) > 5): ?>
                <div class="text-center mt-3">
                    <a href="../rent/index.php?lease_id=<?php echo $lease['id']; ?>" class="btn btn-sm btn-outline-primary">
                        View All Payments
                    </a>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Lease History -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Lease History</h5>
    </div>
    <div class="card-body">
        <?php if (empty($history)): ?>
        <p class="text-muted">No history recorded for this lease.</p>
        <?php else: ?>
        <div class="timeline">
            <?php foreach ($history as $entry): ?>
            <div class="timeline-item">
                <div class="timeline-marker"></div>
                <div class="timeline-content">
                    <h6><?php echo htmlspecialchars($entry['action']); ?></h6>
                    <p class="text-muted mb-1"><?php echo htmlspecialchars($entry['description']); ?></p>
                    <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($entry['created_at'])); ?></small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -30px;
    top: 5px;
    width: 12px;
    height: 12px;
    background-color: #007bff;
    border-radius: 50%;
    border: 3px solid #fff;
    box-shadow: 0 0 0 2px #007bff;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    border-left: 3px solid #007bff;
}
</style>

<?php include '../../includes/footer.php'; ?>
