<?php
/**
 * Business Management System - Hall Reports
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
requirePermission('halls.reports');

// Get database connection
$conn = getDB();

// Get filter parameters
$reportType = $_GET['report_type'] ?? 'booking_summary';
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-d'); // Today
$hallId = (int)($_GET['hall_id'] ?? 0);

// Build date filter
$dateFilter = "AND hb.start_date BETWEEN ? AND ?";
$params = [$startDate, $endDate];
$paramTypes = 'ss';

if ($hallId > 0) {
    $dateFilter .= " AND hb.hall_id = ?";
    $params[] = $hallId;
    $paramTypes .= 'i';
}

// Get halls for filter dropdown
$halls = $conn->query("
    SELECT id, hall_name, hall_code 
    FROM " . DB_PREFIX . "halls 
    ORDER BY hall_name
")->fetch_all(MYSQLI_ASSOC);

// Get report data based on type
$reportData = [];

switch ($reportType) {
    case 'booking_summary':
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_bookings,
                COUNT(CASE WHEN hb.booking_status = 'Confirmed' THEN 1 END) as confirmed_bookings,
                COUNT(CASE WHEN hb.booking_status = 'Pending' THEN 1 END) as pending_bookings,
                COUNT(CASE WHEN hb.booking_status = 'Cancelled' THEN 1 END) as cancelled_bookings,
                COUNT(CASE WHEN hb.booking_status = 'Completed' THEN 1 END) as completed_bookings,
                COALESCE(SUM(hb.total_amount), 0) as total_revenue,
                COALESCE(SUM(hb.amount_paid), 0) as total_collected,
                COALESCE(SUM(hb.balance_due), 0) as total_outstanding,
                COALESCE(AVG(hb.total_amount), 0) as avg_booking_value,
                COALESCE(SUM(hb.duration_hours), 0) as total_hours_booked
            FROM " . DB_PREFIX . "hall_bookings hb
            WHERE 1=1 {$dateFilter}
        ");
        $stmt->bind_param($paramTypes, ...$params);
        $stmt->execute();
        $reportData = $stmt->get_result()->fetch_assoc();
        break;
        
    case 'revenue_report':
        $stmt = $conn->prepare("
            SELECT 
                DATE(hb.start_date) as booking_date,
                COUNT(*) as bookings_count,
                SUM(hb.total_amount) as daily_revenue,
                SUM(hb.amount_paid) as daily_collected,
                SUM(hb.balance_due) as daily_outstanding
            FROM " . DB_PREFIX . "hall_bookings hb
            WHERE hb.booking_status != 'Cancelled' {$dateFilter}
            GROUP BY DATE(hb.start_date)
            ORDER BY booking_date DESC
            LIMIT 30
        ");
        $stmt->bind_param($paramTypes, ...$params);
        $stmt->execute();
        $reportData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        break;
        
    case 'hall_utilization':
        $stmt = $conn->prepare("
            SELECT 
                h.hall_name,
                h.hall_code,
                h.capacity,
                COUNT(hb.id) as total_bookings,
                SUM(hb.duration_hours) as total_hours_booked,
                SUM(hb.total_amount) as total_revenue,
                AVG(hb.total_amount) as avg_booking_value,
                CASE 
                    WHEN h.capacity > 0 THEN (COUNT(hb.id) * 100.0 / h.capacity)
                    ELSE 0 
                END as utilization_percentage
            FROM " . DB_PREFIX . "halls h
            LEFT JOIN " . DB_PREFIX . "hall_bookings hb ON h.id = hb.hall_id 
                AND hb.booking_status != 'Cancelled' {$dateFilter}
            GROUP BY h.id
            ORDER BY total_revenue DESC
        ");
        $stmt->bind_param($paramTypes, ...$params);
        $stmt->execute();
        $reportData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        break;
        
    case 'customer_analysis':
        $stmt = $conn->prepare("
            SELECT 
                c.first_name,
                c.last_name,
                c.company_name,
                c.email,
                COUNT(hb.id) as total_bookings,
                SUM(hb.total_amount) as total_spent,
                AVG(hb.total_amount) as avg_booking_value,
                MAX(hb.start_date) as last_booking_date,
                MIN(hb.start_date) as first_booking_date
            FROM " . DB_PREFIX . "customers c
            JOIN " . DB_PREFIX . "hall_bookings hb ON c.id = hb.customer_id
            WHERE hb.booking_status != 'Cancelled' {$dateFilter}
            GROUP BY c.id
            ORDER BY total_spent DESC
            LIMIT 50
        ");
        $stmt->bind_param($paramTypes, ...$params);
        $stmt->execute();
        $reportData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        break;
}

// Set page title
$pageTitle = 'Hall Reports';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Hall Reports</h1>
        <p>Analyze hall booking performance and revenue</p>
    </div>
    <div class="page-actions">
        <a href="../index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Halls
        </a>
        <button onclick="exportReport()" class="btn btn-success">
            <i class="icon-download"></i> Export Report
        </button>
    </div>
</div>

<!-- Report Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="report-filters">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="report_type">Report Type:</label>
                        <select name="report_type" id="report_type" class="form-control">
                            <option value="booking_summary" <?php echo $reportType == 'booking_summary' ? 'selected' : ''; ?>>Booking Summary</option>
                            <option value="revenue_report" <?php echo $reportType == 'revenue_report' ? 'selected' : ''; ?>>Revenue Report</option>
                            <option value="hall_utilization" <?php echo $reportType == 'hall_utilization' ? 'selected' : ''; ?>>Hall Utilization</option>
                            <option value="customer_analysis" <?php echo $reportType == 'customer_analysis' ? 'selected' : ''; ?>>Customer Analysis</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="start_date">Start Date:</label>
                        <input type="date" name="start_date" id="start_date" 
                               value="<?php echo htmlspecialchars($startDate); ?>" class="form-control">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="end_date">End Date:</label>
                        <input type="date" name="end_date" id="end_date" 
                               value="<?php echo htmlspecialchars($endDate); ?>" class="form-control">
                    </div>
                </div>
                <div class="col-md-3">
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
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="icon-search"></i> Generate Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Report Content -->
<div class="card">
    <div class="card-body">
        <?php if ($reportType == 'booking_summary'): ?>
            <!-- Booking Summary Report -->
            <div class="report-header">
                <h3><i class="icon-chart-bar"></i> Booking Summary Report</h3>
                <p>Period: <?php echo date('M d, Y', strtotime($startDate)); ?> - <?php echo date('M d, Y', strtotime($endDate)); ?></p>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="summary-card">
                        <div class="card-body">
                            <h3><?php echo $reportData['total_bookings']; ?></h3>
                            <p>Total Bookings</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-card">
                        <div class="card-body">
                            <h3><?php echo formatCurrency($reportData['total_revenue']); ?></h3>
                            <p>Total Revenue</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-card">
                        <div class="card-body">
                            <h3><?php echo formatCurrency($reportData['total_collected']); ?></h3>
                            <p>Amount Collected</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-card">
                        <div class="card-body">
                            <h3><?php echo formatCurrency($reportData['total_outstanding']); ?></h3>
                            <p>Outstanding</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Booking Status Breakdown</h5>
                        </div>
                        <div class="card-body">
                            <div class="status-breakdown">
                                <div class="status-item">
                                    <span class="status-label">Confirmed:</span>
                                    <span class="status-value"><?php echo $reportData['confirmed_bookings']; ?></span>
                                </div>
                                <div class="status-item">
                                    <span class="status-label">Pending:</span>
                                    <span class="status-value"><?php echo $reportData['pending_bookings']; ?></span>
                                </div>
                                <div class="status-item">
                                    <span class="status-label">Completed:</span>
                                    <span class="status-value"><?php echo $reportData['completed_bookings']; ?></span>
                                </div>
                                <div class="status-item">
                                    <span class="status-label">Cancelled:</span>
                                    <span class="status-value"><?php echo $reportData['cancelled_bookings']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Performance Metrics</h5>
                        </div>
                        <div class="card-body">
                            <div class="metrics">
                                <div class="metric-item">
                                    <span class="metric-label">Average Booking Value:</span>
                                    <span class="metric-value"><?php echo formatCurrency($reportData['avg_booking_value']); ?></span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-label">Total Hours Booked:</span>
                                    <span class="metric-value"><?php echo round($reportData['total_hours_booked'], 1); ?> hrs</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-label">Collection Rate:</span>
                                    <span class="metric-value">
                                        <?php 
                                        $collectionRate = $reportData['total_revenue'] > 0 ? 
                                            ($reportData['total_collected'] / $reportData['total_revenue']) * 100 : 0;
                                        echo round($collectionRate, 1); ?>%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php elseif ($reportType == 'revenue_report'): ?>
            <!-- Revenue Report -->
            <div class="report-header">
                <h3><i class="icon-chart-line"></i> Revenue Report</h3>
                <p>Daily revenue breakdown for the selected period</p>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Bookings</th>
                            <th>Revenue</th>
                            <th>Collected</th>
                            <th>Outstanding</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData as $row): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($row['booking_date'])); ?></td>
                            <td><?php echo $row['bookings_count']; ?></td>
                            <td><?php echo formatCurrency($row['daily_revenue']); ?></td>
                            <td><?php echo formatCurrency($row['daily_collected']); ?></td>
                            <td><?php echo formatCurrency($row['daily_outstanding']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
        <?php elseif ($reportType == 'hall_utilization'): ?>
            <!-- Hall Utilization Report -->
            <div class="report-header">
                <h3><i class="icon-building"></i> Hall Utilization Report</h3>
                <p>Performance analysis by hall</p>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Hall</th>
                            <th>Capacity</th>
                            <th>Bookings</th>
                            <th>Hours Booked</th>
                            <th>Revenue</th>
                            <th>Avg Value</th>
                            <th>Utilization</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData as $row): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($row['hall_name']); ?></strong>
                                <br><small class="text-muted"><?php echo htmlspecialchars($row['hall_code']); ?></small>
                            </td>
                            <td><?php echo number_format($row['capacity']); ?></td>
                            <td><?php echo $row['total_bookings']; ?></td>
                            <td><?php echo round($row['total_hours_booked'], 1); ?> hrs</td>
                            <td><?php echo formatCurrency($row['total_revenue']); ?></td>
                            <td><?php echo formatCurrency($row['avg_booking_value']); ?></td>
                            <td>
                                <div class="utilization-bar">
                                    <div class="utilization-fill" style="width: <?php echo min(100, $row['utilization_percentage']); ?>%"></div>
                                    <span class="utilization-text"><?php echo round($row['utilization_percentage'], 1); ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
        <?php elseif ($reportType == 'customer_analysis'): ?>
            <!-- Customer Analysis Report -->
            <div class="report-header">
                <h3><i class="icon-users"></i> Customer Analysis Report</h3>
                <p>Top customers by spending and booking frequency</p>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Company</th>
                            <th>Bookings</th>
                            <th>Total Spent</th>
                            <th>Avg Value</th>
                            <th>First Booking</th>
                            <th>Last Booking</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData as $row): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></strong>
                                <br><small class="text-muted"><?php echo htmlspecialchars($row['email']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($row['company_name'] ?: 'N/A'); ?></td>
                            <td><?php echo $row['total_bookings']; ?></td>
                            <td><?php echo formatCurrency($row['total_spent']); ?></td>
                            <td><?php echo formatCurrency($row['avg_booking_value']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($row['first_booking_date'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($row['last_booking_date'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function exportReport() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    window.open('export-report.php?' + params.toString(), '_blank');
}
</script>

<style>
.summary-card {
    text-align: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 10px;
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

.report-header {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #eee;
}

.report-header h3 {
    margin: 0 0 10px 0;
    color: #333;
}

.status-breakdown .status-item,
.metrics .metric-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.status-breakdown .status-item:last-child,
.metrics .metric-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.utilization-bar {
    position: relative;
    background-color: #f0f0f0;
    border-radius: 10px;
    height: 20px;
    overflow: hidden;
}

.utilization-fill {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    height: 100%;
    transition: width 0.3s ease;
}

.utilization-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 12px;
    font-weight: bold;
    color: #333;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    font-weight: 500;
    margin-bottom: 5px;
}

.filter-actions {
    display: flex;
    align-items: end;
    height: 100%;
}
</style>

<?php include '../../includes/footer.php'; ?>
