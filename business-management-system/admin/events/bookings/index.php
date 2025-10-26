<?php
/**
 * Business Management System - Event Bookings List
 * Phase 4: Event Booking System Module
 */

// Define system constant
define('BMS_SYSTEM', true);

// Start session
session_start();

// Include required files
require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/auth.php';
require_once '../../../../includes/csrf.php';
require_once '../../../../includes/event-functions.php';

// Check authentication and permissions
requireLogin();
requirePermission('events.bookings');

// Get database connection
$conn = getDB();

// Get filter parameters
$eventId = (int)($_GET['event_id'] ?? 0);
$paymentStatus = $_GET['payment_status'] ?? '';
$bookingStatus = $_GET['booking_status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$whereConditions = [];
$params = [];
$paramTypes = '';

if ($eventId > 0) {
    $whereConditions[] = 'eb.event_id = ?';
    $params[] = $eventId;
    $paramTypes .= 'i';
}

if (!empty($paymentStatus)) {
    $whereConditions[] = 'eb.payment_status = ?';
    $params[] = $paymentStatus;
    $paramTypes .= 's';
}

if (!empty($bookingStatus)) {
    $whereConditions[] = 'eb.booking_status = ?';
    $params[] = $bookingStatus;
    $paramTypes .= 's';
}

if (!empty($dateFrom)) {
    $whereConditions[] = 'eb.booking_date >= ?';
    $params[] = $dateFrom . ' 00:00:00';
    $paramTypes .= 's';
}

if (!empty($dateTo)) {
    $whereConditions[] = 'eb.booking_date <= ?';
    $params[] = $dateTo . ' 23:59:59';
    $paramTypes .= 's';
}

if (!empty($search)) {
    $whereConditions[] = '(eb.booking_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.company_name LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $paramTypes .= 'ssss';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get bookings
$query = "
    SELECT eb.*, e.event_name, e.start_date, e.start_time, e.venue_name,
           c.first_name, c.last_name, c.company_name, c.email as customer_email,
           COUNT(ba.id) as attendee_count
    FROM " . DB_PREFIX . "event_bookings eb
    JOIN " . DB_PREFIX . "events e ON eb.event_id = e.id
    LEFT JOIN " . DB_PREFIX . "customers c ON eb.customer_id = c.id
    LEFT JOIN " . DB_PREFIX . "booking_attendees ba ON eb.id = ba.booking_id
    {$whereClause}
    GROUP BY eb.id
    ORDER BY eb.booking_date DESC
    LIMIT 100
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get events for filter dropdown
$events = $conn->query("
    SELECT id, event_name, start_date 
    FROM " . DB_PREFIX . "events 
    ORDER BY start_date DESC
")->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$totalBookings = count($bookings);
$totalRevenue = array_sum(array_column($bookings, 'total_amount'));
$totalPaid = array_sum(array_column($bookings, 'amount_paid'));
$totalOutstanding = array_sum(array_column($bookings, 'balance_due'));

// Set page title
$pageTitle = 'Event Bookings';

include '../../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Event Bookings</h1>
        <p>Manage event bookings and payments</p>
    </div>
    <div class="page-actions">
        <?php if (hasPermission('events.bookings')): ?>
        <a href="add.php" class="btn btn-primary">
            <i class="icon-plus"></i> Create Booking
        </a>
        <?php endif; ?>
        <a href="../index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Events
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
                <label for="event_id">Event:</label>
                <select name="event_id" id="event_id" class="form-control">
                    <option value="">All Events</option>
                    <?php foreach ($events as $event): ?>
                    <option value="<?php echo $event['id']; ?>" 
                            <?php echo $eventId == $event['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($event['event_name']); ?> 
                        (<?php echo date('M d, Y', strtotime($event['start_date'])); ?>)
                    </option>
                    <?php endforeach; ?>
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
                <label for="booking_status">Booking Status:</label>
                <select name="booking_status" id="booking_status" class="form-control">
                    <option value="">All Booking Status</option>
                    <option value="Pending" <?php echo $bookingStatus == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Confirmed" <?php echo $bookingStatus == 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="Cancelled" <?php echo $bookingStatus == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="Attended" <?php echo $bookingStatus == 'Attended' ? 'selected' : ''; ?>>Attended</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="date_from">From:</label>
                <input type="date" name="date_from" id="date_from" 
                       value="<?php echo htmlspecialchars($dateFrom); ?>" class="form-control">
            </div>
            
            <div class="form-group">
                <label for="date_to">To:</label>
                <input type="date" name="date_to" id="date_to" 
                       value="<?php echo htmlspecialchars($dateTo); ?>" class="form-control">
            </div>
            
            <div class="form-group">
                <label for="search">Search:</label>
                <input type="text" name="search" id="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Booking #, Customer name" class="form-control">
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
                        <th>Event</th>
                        <th>Customer</th>
                        <th>Booking Date</th>
                        <th>Attendees</th>
                        <th>Total Amount</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Payment Status</th>
                        <th>Booking Status</th>
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
                                    <strong><?php echo htmlspecialchars($booking['event_name']); ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo date('M d, Y', strtotime($booking['start_date'])); ?>
                                        <?php if ($booking['venue_name']): ?>
                                            • <?php echo htmlspecialchars($booking['venue_name']); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <?php if ($booking['customer_id']): ?>
                                        <strong><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></strong>
                                        <?php if ($booking['company_name']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($booking['company_name']); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Guest Booking</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></td>
                            <td>
                                <span class="badge badge-info"><?php echo $booking['attendee_count']; ?></span>
                            </td>
                            <td><?php echo formatCurrency($booking['total_amount']); ?></td>
                            <td><?php echo formatCurrency($booking['amount_paid']); ?></td>
                            <td>
                                <?php if ($booking['balance_due'] > 0): ?>
                                    <span class="text-danger"><?php echo formatCurrency($booking['balance_due']); ?></span>
                                <?php else: ?>
                                    <span class="text-success"><?php echo formatCurrency($booking['balance_due']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo getPaymentStatusBadgeClass($booking['payment_status']); ?>">
                                    <?php echo $booking['payment_status']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo getBookingStatusBadgeClass($booking['booking_status']); ?>">
                                    <?php echo $booking['booking_status']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="view.php?id=<?php echo $booking['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" title="View">
                                        <i class="icon-eye"></i>
                                    </a>
                                    
                                    <?php if ($booking['booking_status'] == 'Pending'): ?>
                                    <a href="edit.php?id=<?php echo $booking['id']; ?>" 
                                       class="btn btn-sm btn-outline-secondary" title="Edit">
                                        <i class="icon-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($booking['balance_due'] > 0): ?>
                                    <button onclick="recordPayment(<?php echo $booking['id']; ?>)" 
                                            class="btn btn-sm btn-outline-success" title="Record Payment">
                                        <i class="icon-credit-card"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($booking['booking_status'] != 'Cancelled'): ?>
                                    <button onclick="cancelBooking(<?php echo $booking['id']; ?>)" 
                                            class="btn btn-sm btn-outline-danger" title="Cancel">
                                        <i class="icon-x"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <a href="print-tickets.php?id=<?php echo $booking['id']; ?>" 
                                       class="btn btn-sm btn-outline-info" title="Print Tickets">
                                        <i class="icon-printer"></i>
                                    </a>
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

<!-- Record Payment Modal -->
<div id="paymentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Record Payment</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="paymentForm">
                <?php csrfField(); ?>
                <input type="hidden" id="booking_id" name="booking_id">
                
                <div class="form-group">
                    <label>Payment Amount</label>
                    <input type="number" id="payment_amount" name="payment_amount" 
                           step="0.01" min="0.01" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="payment_method" required class="form-control">
                        <option value="">Select Method</option>
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Card Payment">Card Payment</option>
                        <option value="Cheque">Cheque</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Payment Date</label>
                    <input type="date" name="payment_date" 
                           value="<?php echo date('Y-m-d'); ?>" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="3" class="form-control"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Record Payment</button>
                    <button type="button" class="btn btn-secondary" onclick="closePaymentModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function recordPayment(bookingId) {
    document.getElementById('booking_id').value = bookingId;
    document.getElementById('paymentModal').style.display = 'block';
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

function cancelBooking(bookingId) {
    if (confirm('Are you sure you want to cancel this booking? This action cannot be undone.')) {
        fetch('cancel.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'booking_id=' + bookingId + '&csrf_token=' + document.querySelector('meta[name="csrf-token"]').getAttribute('content')
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

// Close modal when clicking X
document.querySelector('.close').addEventListener('click', closePaymentModal);

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('paymentModal');
    if (event.target === modal) {
        closePaymentModal();
    }
});

// Handle payment form submission
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('record-payment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closePaymentModal();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
});
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

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 0;
    border-radius: 5px;
    width: 90%;
    max-width: 500px;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
}

.modal-body {
    padding: 20px;
}

.close {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: #000;
}

.form-actions {
    text-align: right;
    margin-top: 20px;
}

.form-actions .btn {
    margin-left: 10px;
}
</style>

<?php include '../../../includes/footer.php'; ?>
