<?php
/**
 * Business Management System - Customer Portal
 * Phase 4: Hall Booking System Module
 */

// Define system constant
define('BMS_SYSTEM', true);

// Start session
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/hall-functions.php';

// Get database connection
$conn = getDB();

// Get customer email from session or URL parameter
$customerEmail = $_SESSION['customer_email'] ?? $_GET['email'] ?? '';

if (empty($customerEmail)) {
    // Redirect to login form
    header('Location: login.php');
    exit;
}

// Get customer details
$stmt = $conn->prepare("SELECT * FROM " . DB_PREFIX . "customers WHERE email = ?");
$stmt->bind_param('s', $customerEmail);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();

if (!$customer) {
    // Redirect to login form
    header('Location: login.php');
    exit;
}

// Get customer bookings
$stmt = $conn->prepare("
    SELECT hb.*, h.hall_name, h.hall_code, h.location,
           hc.category_name
    FROM " . DB_PREFIX . "hall_bookings hb
    JOIN " . DB_PREFIX . "halls h ON hb.hall_id = h.id
    JOIN " . DB_PREFIX . "hall_categories hc ON h.category_id = hc.id
    WHERE hb.customer_id = ?
    ORDER BY hb.start_date DESC, hb.created_at DESC
");
$stmt->bind_param('i', $customer['id']);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$totalBookings = count($bookings);
$totalSpent = array_sum(array_column($bookings, 'total_amount'));
$upcomingBookings = count(array_filter($bookings, function($b) { 
    return $b['booking_status'] == 'Confirmed' && strtotime($b['start_date']) > time(); 
}));

// Get company settings
$companyName = getSetting('company_name', 'Business Management System');
$companyEmail = getSetting('company_email', 'info@example.com');
$companyPhone = getSetting('company_phone', '+234 000 000 0000');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - <?php echo htmlspecialchars($companyName); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../public/css/halls.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <h2><?php echo htmlspecialchars($companyName); ?></h2>
                    <p>Hall Rentals & Event Spaces</p>
                </div>
                <div class="header-actions">
                    <a href="index.php" class="btn btn-outline-light">
                        <i class="fas fa-home"></i> Browse Halls
                    </a>
                    <a href="logout.php" class="btn btn-outline-light">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Customer Dashboard -->
    <section class="customer-dashboard">
        <div class="container">
            <div class="dashboard-header">
                <h1>Welcome, <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>!</h1>
                <p>Manage your hall bookings and view your booking history</p>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card summary-card">
                        <div class="card-body">
                            <h3><?php echo $totalBookings; ?></h3>
                            <p>Total Bookings</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card summary-card">
                        <div class="card-body">
                            <h3><?php echo formatCurrency($totalSpent); ?></h3>
                            <p>Total Spent</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card summary-card">
                        <div class="card-body">
                            <h3><?php echo $upcomingBookings; ?></h3>
                            <p>Upcoming Bookings</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Customer Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-user"></i> Customer Information</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-item">
                                <strong>Name:</strong>
                                <span><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></span>
                            </div>
                            <div class="info-item">
                                <strong>Email:</strong>
                                <span><?php echo htmlspecialchars($customer['email']); ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <strong>Phone:</strong>
                                <span><?php echo htmlspecialchars($customer['phone']); ?></span>
                            </div>
                            <div class="info-item">
                                <strong>Company:</strong>
                                <span><?php echo htmlspecialchars($customer['company_name'] ?: 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bookings Table -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-check"></i> My Bookings</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($bookings)): ?>
                    <div class="no-bookings">
                        <i class="fas fa-calendar-times"></i>
                        <h4>No bookings found</h4>
                        <p>You haven't made any hall bookings yet.</p>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-search"></i> Browse Available Halls
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Booking #</th>
                                    <th>Hall</th>
                                    <th>Event</th>
                                    <th>Date & Time</th>
                                    <th>Duration</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($booking['booking_number']); ?></strong>
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
                                        <div>
                                            <strong><?php echo formatCurrency($booking['total_amount']); ?></strong>
                                            <?php if ($booking['balance_due'] > 0): ?>
                                                <br><small class="text-danger">Balance: <?php echo formatCurrency($booking['balance_due']); ?></small>
                                            <?php endif; ?>
                                        </div>
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
                                            <a href="booking-details.php?id=<?php echo $booking['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if ($booking['booking_status'] == 'Pending'): ?>
                                            <a href="cancel-booking.php?id=<?php echo $booking['id']; ?>" 
                                               class="btn btn-sm btn-outline-warning" title="Cancel Booking">
                                                <i class="fas fa-times"></i>
                                            </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($booking['balance_due'] > 0): ?>
                                            <a href="make-payment.php?id=<?php echo $booking['id']; ?>" 
                                               class="btn btn-sm btn-outline-success" title="Make Payment">
                                                <i class="fas fa-credit-card"></i>
                                            </a>
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
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-info">
                    <h3><?php echo htmlspecialchars($companyName); ?></h3>
                    <p>Professional hall rentals and event spaces</p>
                </div>
                <div class="footer-links">
                    <a href="index.php">Browse Halls</a>
                    <a href="mailto:<?php echo htmlspecialchars($companyEmail); ?>">Contact Us</a>
                    <a href="logout.php">Logout</a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($companyName); ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="../../public/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<style>
.customer-dashboard {
    padding: 40px 0;
    background-color: #f8f9fa;
    min-height: calc(100vh - 200px);
}

.dashboard-header {
    text-align: center;
    margin-bottom: 40px;
}

.dashboard-header h1 {
    color: #333;
    margin-bottom: 10px;
}

.dashboard-header p {
    color: #666;
    font-size: 18px;
}

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

.info-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.info-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.no-bookings {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.no-bookings i {
    font-size: 64px;
    margin-bottom: 20px;
    color: #ccc;
}

.no-bookings h4 {
    margin-bottom: 10px;
    color: #333;
}

.badge-success { background-color: #28a745; color: white; }
.badge-secondary { background-color: #6c757d; color: white; }
.badge-danger { background-color: #dc3545; color: white; }
.badge-info { background-color: #17a2b8; color: white; }
.badge-warning { background-color: #ffc107; color: #333; }
.badge-primary { background-color: #007bff; color: white; }

.status-badges {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.status-badges .badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.btn-group .btn {
    margin-right: 2px;
}

.btn-group .btn:last-child {
    margin-right: 0;
}
</style>
