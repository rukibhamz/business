<?php
/**
 * Business Management System - Accounting Dashboard
 * Phase 3: Accounting System Module
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
require_once '../../includes/accounting-functions.php';

// Check authentication and permissions
requireLogin();
requirePermission('accounting.view');

// Get database connection
$conn = getDB();

// Get current month data
$currentMonth = date('Y-m');
$startDate = $currentMonth . '-01';
$endDate = date('Y-m-t');

$revenue = getTotalRevenue($startDate, $endDate);
$expenses = getTotalExpenses($startDate, $endDate);
$profit = $revenue - $expenses;

// Get outstanding invoices
$outstandingInvoices = $conn->query("
    SELECT SUM(balance_due) as total 
    FROM " . DB_PREFIX . "invoices 
    WHERE status IN ('Sent', 'Partial', 'Overdue')
")->fetch_assoc()['total'] ?? 0;

// Get overdue invoices count
$overdueCount = $conn->query("
    SELECT COUNT(*) as count 
    FROM " . DB_PREFIX . "invoices 
    WHERE status = 'Overdue'
")->fetch_assoc()['count'];

// Get recent invoices
$recentInvoices = $conn->query("
    SELECT i.*, c.first_name, c.last_name, c.company_name, c.customer_type
    FROM " . DB_PREFIX . "invoices i
    JOIN " . DB_PREFIX . "customers c ON i.customer_id = c.id
    ORDER BY i.created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Get recent payments
$recentPayments = $conn->query("
    SELECT p.*, c.first_name, c.last_name, c.company_name, c.customer_type
    FROM " . DB_PREFIX . "payments p
    JOIN " . DB_PREFIX . "customers c ON p.customer_id = c.id
    ORDER BY p.created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Get expenses by category for current month
$expensesByCategory = $conn->query("
    SELECT ec.category_name, SUM(e.amount) as total
    FROM " . DB_PREFIX . "expenses e
    JOIN " . DB_PREFIX . "expense_categories ec ON e.category_id = ec.id
    WHERE MONTH(e.expense_date) = MONTH(CURDATE())
    AND YEAR(e.expense_date) = YEAR(CURDATE())
    AND e.status = 'Approved'
    GROUP BY e.category_id
    ORDER BY total DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Get last 6 months data for chart
$months = [];
$revenueData = [];
$expenseData = [];

for ($i = 5; $i >= 0; $i--) {
    $date = date('Y-m', strtotime("-$i months"));
    $months[] = date('M Y', strtotime("-$i months"));
    
    $monthStartDate = $date . '-01';
    $monthEndDate = date('Y-m-t', strtotime($monthStartDate));
    
    $revenueData[] = getTotalRevenue($monthStartDate, $monthEndDate);
    $expenseData[] = getTotalExpenses($monthStartDate, $monthEndDate);
}

// Set page title
$pageTitle = 'Accounting Dashboard';

include 'includes/header.php';
?>

<div class="accounting-dashboard">
    <h1>Accounting Dashboard</h1>
    
    <!-- Summary Cards -->
    <div class="stats-grid">
        <div class="stat-card revenue">
            <div class="stat-icon"><i class="icon-revenue"></i></div>
            <div class="stat-content">
                <h3><?php echo formatCurrency($revenue); ?></h3>
                <p>Revenue (This Month)</p>
            </div>
        </div>
        
        <div class="stat-card expenses">
            <div class="stat-icon"><i class="icon-expenses"></i></div>
            <div class="stat-content">
                <h3><?php echo formatCurrency($expenses); ?></h3>
                <p>Expenses (This Month)</p>
            </div>
        </div>
        
        <div class="stat-card profit">
            <div class="stat-icon"><i class="icon-profit"></i></div>
            <div class="stat-content">
                <h3><?php echo formatCurrency($profit); ?></h3>
                <p>Net Profit (This Month)</p>
            </div>
        </div>
        
        <div class="stat-card outstanding">
            <div class="stat-icon"><i class="icon-invoice"></i></div>
            <div class="stat-content">
                <h3><?php echo formatCurrency($outstandingInvoices); ?></h3>
                <p>Outstanding Invoices</p>
                <?php if ($overdueCount > 0): ?>
                <span class="badge badge-danger"><?php echo $overdueCount; ?> Overdue</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3>Revenue vs Expenses (Last 6 Months)</h3>
                </div>
                <div class="card-body">
                    <canvas id="revenue-chart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3>Expenses by Category</h3>
                </div>
                <div class="card-body">
                    <canvas id="expenses-pie-chart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Transactions -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>Recent Invoices</h3>
                    <?php if (hasPermission('accounting.view')): ?>
                    <a href="invoices/index.php" class="btn btn-sm btn-link">View All</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentInvoices as $inv): ?>
                            <tr>
                                <td>
                                    <?php if (hasPermission('accounting.view')): ?>
                                    <a href="invoices/view.php?id=<?php echo $inv['id']; ?>"><?php echo $inv['invoice_number']; ?></a>
                                    <?php else: ?>
                                    <?php echo $inv['invoice_number']; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo getCustomerDisplayName($inv); ?></td>
                                <td><?php echo formatCurrency($inv['total_amount']); ?></td>
                                <td><span class="badge status-<?php echo strtolower($inv['status']); ?>"><?php echo $inv['status']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>Recent Payments</h3>
                    <?php if (hasPermission('accounting.view')): ?>
                    <a href="payments/index.php" class="btn btn-sm btn-link">View All</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Payment #</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPayments as $pay): ?>
                            <tr>
                                <td>
                                    <?php if (hasPermission('accounting.view')): ?>
                                    <a href="payments/view.php?id=<?php echo $pay['id']; ?>"><?php echo $pay['payment_number']; ?></a>
                                    <?php else: ?>
                                    <?php echo $pay['payment_number']; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo getCustomerDisplayName($pay); ?></td>
                                <td><?php echo formatCurrency($pay['amount']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($pay['payment_date'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="quick-actions">
        <h3>Quick Actions</h3>
        <div class="actions-grid">
            <?php if (hasPermission('accounting.create')): ?>
            <a href="invoices/add.php" class="action-btn">
                <i class="icon-invoice-plus"></i>
                <span>Create Invoice</span>
            </a>
            <a href="payments/add.php" class="action-btn">
                <i class="icon-payment-plus"></i>
                <span>Record Payment</span>
            </a>
            <a href="expenses/add.php" class="action-btn">
                <i class="icon-expense-plus"></i>
                <span>Add Expense</span>
            </a>
            <?php endif; ?>
            <?php if (hasPermission('accounting.reports')): ?>
            <a href="reports/index.php" class="action-btn">
                <i class="icon-reports"></i>
                <span>View Reports</span>
            </a>
            <?php endif; ?>
            <?php if (hasPermission('accounting.view')): ?>
            <a href="accounts/index.php" class="action-btn">
                <i class="icon-chart"></i>
                <span>Chart of Accounts</span>
            </a>
            <a href="journal/index.php" class="action-btn">
                <i class="icon-journal"></i>
                <span>Journal Entries</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart.js for graphs -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Revenue vs Expenses Chart
const revenueCtx = document.getElementById('revenue-chart').getContext('2d');
new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($months); ?>,
        datasets: [
            {
                label: 'Revenue',
                data: <?php echo json_encode($revenueData); ?>,
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4
            },
            {
                label: 'Expenses',
                data: <?php echo json_encode($expenseData); ?>,
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₦' + value.toLocaleString();
                    }
                }
            }
        }
    }
});

// Expenses Pie Chart
const expensesCtx = document.getElementById('expenses-pie-chart').getContext('2d');
new Chart(expensesCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($expensesByCategory, 'category_name')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($expensesByCategory, 'total')); ?>,
            backgroundColor: [
                '#ff6384',
                '#36a2eb',
                '#ffce56',
                '#4bc0c0',
                '#9966ff'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': ₦' + context.parsed.toLocaleString();
                    }
                }
            }
        }
    }
});
</script>

<style>
.accounting-dashboard {
    padding: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-card.revenue {
    border-left: 4px solid #28a745;
}

.stat-card.expenses {
    border-left: 4px solid #dc3545;
}

.stat-card.profit {
    border-left: 4px solid #17a2b8;
}

.stat-card.outstanding {
    border-left: 4px solid #ffc107;
}

.stat-icon {
    font-size: 24px;
    color: #6c757d;
}

.stat-content h3 {
    margin: 0;
    font-size: 24px;
    font-weight: bold;
    color: #333;
}

.stat-content p {
    margin: 5px 0 0 0;
    color: #6c757d;
    font-size: 14px;
}

.badge {
    display: inline-block;
    padding: 4px 8px;
    font-size: 12px;
    font-weight: bold;
    border-radius: 4px;
    margin-top: 5px;
}

.badge-danger {
    background-color: #dc3545;
    color: white;
}

.status-draft { background-color: #6c757d; color: white; }
.status-sent { background-color: #17a2b8; color: white; }
.status-partial { background-color: #ffc107; color: #333; }
.status-paid { background-color: #28a745; color: white; }
.status-overdue { background-color: #dc3545; color: white; }
.status-cancelled { background-color: #343a40; color: white; }

.quick-actions {
    margin-top: 30px;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-decoration: none;
    color: #333;
    transition: transform 0.2s;
}

.action-btn:hover {
    transform: translateY(-2px);
    text-decoration: none;
    color: #333;
}

.action-btn i {
    font-size: 24px;
    margin-bottom: 10px;
    color: #007bff;
}

.action-btn span {
    font-weight: 500;
}
</style>

<?php include 'includes/footer.php'; ?>
