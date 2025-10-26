<?php
/**
 * Business Management System - Tenant List
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
require_once '../../../includes/csrf.php';
require_once '../../../includes/property-functions.php';

// Check authentication and permissions
requireLogin();
requirePermission('tenants.view');

// Get database connection
$conn = getDB();

// Handle filters
$filters = [];
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (isset($_GET['has_active_lease']) && !empty($_GET['has_active_lease'])) {
    $filters['has_active_lease'] = $_GET['has_active_lease'];
}
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

// Build query
$sql = "SELECT t.*, 
               (SELECT COUNT(*) FROM " . DB_PREFIX . "leases l WHERE l.tenant_id = t.id AND l.lease_status = 'Active') as active_leases,
               (SELECT CONCAT(p.property_name, ' (', p.property_code, ')') FROM " . DB_PREFIX . "leases l 
                JOIN " . DB_PREFIX . "properties p ON l.property_id = p.id 
                WHERE l.tenant_id = t.id AND l.lease_status = 'Active' LIMIT 1) as current_property,
               (SELECT l.end_date FROM " . DB_PREFIX . "leases l 
                WHERE l.tenant_id = t.id AND l.lease_status = 'Active' LIMIT 1) as lease_expiry,
               (SELECT SUM(l.monthly_rent * l.lease_term_months) - COALESCE(SUM(rp.total_amount), 0) 
                FROM " . DB_PREFIX . "leases l 
                LEFT JOIN " . DB_PREFIX . "rent_payments rp ON l.id = rp.lease_id AND rp.payment_status = 'Completed'
                WHERE l.tenant_id = t.id AND l.lease_status = 'Active') as outstanding_balance
        FROM " . DB_PREFIX . "tenants t
        WHERE 1=1";

$params = [];
$types = '';

if (!empty($filters['status'])) {
    $sql .= " AND t.status = ?";
    $params[] = $filters['status'];
    $types .= 's';
}

if (!empty($filters['has_active_lease'])) {
    if ($filters['has_active_lease'] === 'yes') {
        $sql .= " AND EXISTS (SELECT 1 FROM " . DB_PREFIX . "leases l WHERE l.tenant_id = t.id AND l.lease_status = 'Active')";
    } else {
        $sql .= " AND NOT EXISTS (SELECT 1 FROM " . DB_PREFIX . "leases l WHERE l.tenant_id = t.id AND l.lease_status = 'Active')";
    }
}

if (!empty($filters['search'])) {
    $sql .= " AND (t.first_name LIKE ? OR t.last_name LIKE ? OR t.email LIKE ? OR t.phone LIKE ? OR t.tenant_code LIKE ?)";
    $searchTerm = '%' . $filters['search'] . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sssss';
}

$sql .= " ORDER BY t.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$tenants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_tenants,
        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_tenants,
        SUM(CASE WHEN status = 'Inactive' THEN 1 ELSE 0 END) as inactive_tenants,
        SUM(CASE WHEN status = 'Blacklisted' THEN 1 ELSE 0 END) as blacklisted_tenants,
        SUM(CASE WHEN EXISTS (SELECT 1 FROM " . DB_PREFIX . "leases l WHERE l.tenant_id = t.id AND l.lease_status = 'Active') THEN 1 ELSE 0 END) as tenants_with_leases
    FROM " . DB_PREFIX . "tenants t
");
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Calculate total outstanding balances
$stmt = $conn->prepare("
    SELECT SUM(l.monthly_rent * l.lease_term_months) - COALESCE(SUM(rp.total_amount), 0) as total_outstanding
    FROM " . DB_PREFIX . "leases l 
    LEFT JOIN " . DB_PREFIX . "rent_payments rp ON l.id = rp.lease_id AND rp.payment_status = 'Completed'
    WHERE l.lease_status = 'Active'
");
$stmt->execute();
$outstandingResult = $stmt->get_result()->fetch_assoc();
$totalOutstanding = $outstandingResult['total_outstanding'] ?? 0;

// Set page title
$pageTitle = 'Tenant Management';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Tenant Management</h1>
        <p>Manage tenant information and lease agreements</p>
    </div>
    <div class="page-actions">
        <?php if (hasPermission('tenants.create')): ?>
        <a href="add.php" class="btn btn-primary">
            <i class="icon-plus"></i> Add Tenant
        </a>
        <?php endif; ?>
        <a href="reports/" class="btn btn-secondary">
            <i class="icon-chart"></i> Reports
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['total_tenants']; ?></h4>
                        <p class="mb-0">Total Tenants</p>
                    </div>
                    <div class="align-self-center">
                        <i class="icon-users fa-2x"></i>
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
                        <h4><?php echo $stats['active_tenants']; ?></h4>
                        <p class="mb-0">Active Tenants</p>
                    </div>
                    <div class="align-self-center">
                        <i class="icon-user-check fa-2x"></i>
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
                        <h4><?php echo $stats['tenants_with_leases']; ?></h4>
                        <p class="mb-0">With Active Leases</p>
                    </div>
                    <div class="align-self-center">
                        <i class="icon-home fa-2x"></i>
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
                        <h4><?php echo formatCurrency($totalOutstanding); ?></h4>
                        <p class="mb-0">Outstanding Balance</p>
                    </div>
                    <div class="align-self-center">
                        <i class="icon-dollar-sign fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h5>Filter Tenants</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select name="status" id="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="Active" <?php echo (isset($filters['status']) && $filters['status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo (isset($filters['status']) && $filters['status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                    <option value="Blacklisted" <?php echo (isset($filters['status']) && $filters['status'] == 'Blacklisted') ? 'selected' : ''; ?>>Blacklisted</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="has_active_lease" class="form-label">Has Active Lease</label>
                <select name="has_active_lease" id="has_active_lease" class="form-select">
                    <option value="">All</option>
                    <option value="yes" <?php echo (isset($filters['has_active_lease']) && $filters['has_active_lease'] == 'yes') ? 'selected' : ''; ?>>Yes</option>
                    <option value="no" <?php echo (isset($filters['has_active_lease']) && $filters['has_active_lease'] == 'no') ? 'selected' : ''; ?>>No</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="search" class="form-label">Search</label>
                <input type="text" name="search" id="search" class="form-control" 
                       value="<?php echo isset($filters['search']) ? htmlspecialchars($filters['search']) : ''; ?>" 
                       placeholder="Search by name, email, phone, or tenant code">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <a href="index.php" class="btn btn-secondary">Clear</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Tenants Table -->
<div class="card">
    <div class="card-header">
        <h5>Tenants</h5>
    </div>
    <div class="card-body">
        <?php if (empty($tenants)): ?>
        <div class="text-center py-5">
            <i class="icon-users fa-3x text-muted mb-3"></i>
            <h4>No Tenants Found</h4>
            <p class="text-muted">No tenants match your current filters.</p>
            <?php if (hasPermission('tenants.create')): ?>
            <a href="add.php" class="btn btn-primary">Add First Tenant</a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Tenant Code</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Current Property</th>
                        <th>Lease Expiry</th>
                        <th>Outstanding Balance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tenants as $tenant): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($tenant['tenant_code']); ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <?php if ($tenant['passport_photo']): ?>
                                <img src="<?php echo htmlspecialchars($tenant['passport_photo']); ?>" 
                                     class="rounded-circle me-2" width="32" height="32" alt="Photo">
                                <?php else: ?>
                                <div class="rounded-circle bg-secondary me-2 d-flex align-items-center justify-content-center" 
                                     style="width: 32px; height: 32px;">
                                    <i class="icon-user text-white"></i>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <strong><?php echo htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']); ?></strong>
                                    <?php if ($tenant['company_name']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($tenant['company_name']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($tenant['email']); ?></td>
                        <td><?php echo htmlspecialchars($tenant['phone']); ?></td>
                        <td>
                            <span class="badge <?php echo getTenantStatusBadgeClass($tenant['status']); ?>">
                                <?php echo $tenant['status']; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($tenant['current_property']): ?>
                            <?php echo htmlspecialchars($tenant['current_property']); ?>
                            <?php else: ?>
                            <span class="text-muted">No active lease</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($tenant['lease_expiry']): ?>
                            <?php 
                            $expiryDate = new DateTime($tenant['lease_expiry']);
                            $today = new DateTime();
                            $daysUntilExpiry = $today->diff($expiryDate)->days;
                            if ($expiryDate < $today) {
                                $daysUntilExpiry = -$daysUntilExpiry;
                            }
                            ?>
                            <span class="badge <?php echo $daysUntilExpiry <= 30 ? ($daysUntilExpiry <= 7 ? 'bg-danger' : 'bg-warning') : 'bg-success'; ?>">
                                <?php echo $daysUntilExpiry; ?> days
                            </span>
                            <br><small><?php echo date('M d, Y', strtotime($tenant['lease_expiry'])); ?></small>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($tenant['outstanding_balance'] > 0): ?>
                            <span class="text-danger"><?php echo formatCurrency($tenant['outstanding_balance']); ?></span>
                            <?php else: ?>
                            <span class="text-success"><?php echo formatCurrency(0); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="view.php?id=<?php echo $tenant['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="icon-eye"></i>
                                </a>
                                <?php if (hasPermission('tenants.edit')): ?>
                                <a href="edit.php?id=<?php echo $tenant['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="icon-edit"></i>
                                </a>
                                <?php endif; ?>
                                <?php if ($tenant['status'] !== 'Blacklisted' && hasPermission('tenants.blacklist')): ?>
                                <button type="button" class="btn btn-sm btn-outline-warning" onclick="blacklistTenant(<?php echo $tenant['id']; ?>)">
                                    <i class="icon-ban"></i>
                                </button>
                                <?php endif; ?>
                                <?php if ($tenant['status'] === 'Blacklisted' && hasPermission('tenants.unblacklist')): ?>
                                <button type="button" class="btn btn-sm btn-outline-success" onclick="unblacklistTenant(<?php echo $tenant['id']; ?>)">
                                    <i class="icon-check"></i>
                                </button>
                                <?php endif; ?>
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

<!-- Blacklist Modal -->
<div class="modal fade" id="blacklistModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Blacklist Tenant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="blacklistForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="blacklist">
                    <input type="hidden" name="tenant_id" id="blacklist_tenant_id">
                    
                    <div class="form-group">
                        <label for="blacklist_reason">Reason for Blacklisting</label>
                        <textarea id="blacklist_reason" name="reason" class="form-control" rows="4" 
                                  placeholder="Please provide a reason for blacklisting this tenant..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Blacklist Tenant</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Unblacklist Modal -->
<div class="modal fade" id="unblacklistModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Remove from Blacklist</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="unblacklistForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="unblacklist">
                    <input type="hidden" name="tenant_id" id="unblacklist_tenant_id">
                    
                    <p>Are you sure you want to remove this tenant from the blacklist?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Remove from Blacklist</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Handle blacklist/unblacklist actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $tenantId = (int)($_POST['tenant_id'] ?? 0);
        
        if ($action === 'blacklist' && hasPermission('tenants.blacklist')) {
            $reason = trim($_POST['reason'] ?? '');
            if (empty($reason)) {
                $errors[] = 'Reason for blacklisting is required.';
            } else {
                $stmt = $conn->prepare("UPDATE " . DB_PREFIX . "tenants SET status = 'Blacklisted', notes = CONCAT(IFNULL(notes, ''), '\nBlacklisted: ', ?) WHERE id = ?");
                $stmt->bind_param('si', $reason, $tenantId);
                if ($stmt->execute()) {
                    header('Location: index.php?success=blacklisted');
                    exit;
                } else {
                    $errors[] = 'Error blacklisting tenant.';
                }
            }
        } elseif ($action === 'unblacklist' && hasPermission('tenants.unblacklist')) {
            $stmt = $conn->prepare("UPDATE " . DB_PREFIX . "tenants SET status = 'Active' WHERE id = ?");
            $stmt->bind_param('i', $tenantId);
            if ($stmt->execute()) {
                header('Location: index.php?success=unblacklisted');
                exit;
            } else {
                $errors[] = 'Error removing tenant from blacklist.';
            }
        }
    }
}

// Helper function for tenant status badge class
function getTenantStatusBadgeClass($status) {
    switch ($status) {
        case 'Active': return 'bg-success';
        case 'Inactive': return 'bg-secondary';
        case 'Blacklisted': return 'bg-danger';
        default: return 'bg-secondary';
    }
}
?>

<script>
function blacklistTenant(tenantId) {
    document.getElementById('blacklist_tenant_id').value = tenantId;
    new bootstrap.Modal(document.getElementById('blacklistModal')).show();
}

function unblacklistTenant(tenantId) {
    document.getElementById('unblacklist_tenant_id').value = tenantId;
    new bootstrap.Modal(document.getElementById('unblacklistModal')).show();
}
</script>

<?php include '../../includes/footer.php'; ?>
