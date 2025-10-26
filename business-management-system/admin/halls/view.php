<?php
/**
 * Business Management System - View Hall
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
requirePermission('halls.view');

// Get database connection
$conn = getDB();

// Get hall ID
$hallId = (int)($_GET['id'] ?? 0);

if ($hallId <= 0) {
    header('Location: index.php');
    exit;
}

// Get hall details
$stmt = $conn->prepare("
    SELECT h.*, hc.category_name, u.first_name as created_by_first, u.last_name as created_by_last
    FROM " . DB_PREFIX . "halls h
    JOIN " . DB_PREFIX . "hall_categories hc ON h.category_id = hc.id
    LEFT JOIN " . DB_PREFIX . "users u ON h.created_by = u.id
    WHERE h.id = ?
");
$stmt->bind_param('i', $hallId);
$stmt->execute();
$hall = $stmt->get_result()->fetch_assoc();

if (!$hall) {
    header('Location: index.php');
    exit;
}

// Get hall statistics
$stats = getHallStatistics($hallId, date('Y-01-01'), date('Y-12-31'));

// Get recent bookings
$stmt = $conn->prepare("
    SELECT hb.*, c.first_name, c.last_name, c.email, c.phone
    FROM " . DB_PREFIX . "hall_bookings hb
    LEFT JOIN " . DB_PREFIX . "customers c ON hb.customer_id = c.id
    WHERE hb.hall_id = ?
    ORDER BY hb.start_date DESC, hb.start_time DESC
    LIMIT 10
");
$stmt->bind_param('i', $hallId);
$stmt->execute();
$recentBookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get booking periods
$stmt = $conn->prepare("
    SELECT * FROM " . DB_PREFIX . "hall_booking_periods 
    WHERE hall_id = ? AND is_active = 1
    ORDER BY sort_order ASC
");
$stmt->bind_param('i', $hallId);
$stmt->execute();
$bookingPeriods = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Parse gallery images
$galleryImages = [];
if (!empty($hall['gallery_images'])) {
    $galleryImages = json_decode($hall['gallery_images'], true) ?: [];
}

// Check for success message
$success = isset($_GET['success']) && $_GET['success'] == '1';

// Set page title
$pageTitle = 'View Hall - ' . $hall['hall_name'];

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1><?php echo htmlspecialchars($hall['hall_name']); ?></h1>
        <p><?php echo htmlspecialchars($hall['category_name']); ?> • <?php echo htmlspecialchars($hall['hall_code']); ?></p>
    </div>
    <div class="page-actions">
        <?php if (hasPermission('halls.edit')): ?>
        <a href="edit.php?id=<?php echo $hall['id']; ?>" class="btn btn-primary">
            <i class="icon-edit"></i> Edit Hall
        </a>
        <?php endif; ?>
        
        <a href="bookings/add.php?hall_id=<?php echo $hall['id']; ?>" class="btn btn-success">
            <i class="icon-plus"></i> New Booking
        </a>
        
        <a href="bookings/index.php?hall_id=<?php echo $hall['id']; ?>" class="btn btn-info">
            <i class="icon-calendar"></i> View Bookings
        </a>
        
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Halls
        </a>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success">
    <i class="icon-check"></i> Hall created successfully!
</div>
<?php endif; ?>

<div class="row">
    <!-- Hall Details -->
    <div class="col-md-8">
        <!-- Basic Information -->
        <div class="card">
            <div class="card-header">
                <h3>Hall Information</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Hall Code:</strong></td>
                                <td><?php echo htmlspecialchars($hall['hall_code']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Category:</strong></td>
                                <td><?php echo htmlspecialchars($hall['category_name']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Capacity:</strong></td>
                                <td><?php echo number_format($hall['capacity']); ?> people</td>
                            </tr>
                            <tr>
                                <td><strong>Area:</strong></td>
                                <td><?php echo $hall['area_sqft'] ? number_format($hall['area_sqft']) . ' sq ft' : 'Not specified'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td>
                                    <span class="badge <?php echo getHallStatusBadgeClass($hall['status']); ?>">
                                        <?php echo $hall['status']; ?>
                                    </span>
                                    <?php if ($hall['is_featured']): ?>
                                        <span class="badge badge-warning ml-2">Featured</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Location:</strong></td>
                                <td><?php echo htmlspecialchars($hall['location'] ?: 'Not specified'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Currency:</strong></td>
                                <td><?php echo htmlspecialchars($hall['currency']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Online Booking:</strong></td>
                                <td>
                                    <?php if ($hall['enable_booking']): ?>
                                        <span class="badge badge-success">Enabled</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Disabled</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Advance Booking:</strong></td>
                                <td><?php echo $hall['booking_advance_days']; ?> days</td>
                            </tr>
                            <tr>
                                <td><strong>Created:</strong></td>
                                <td><?php echo date('M d, Y', strtotime($hall['created_at'])); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php if (!empty($hall['description'])): ?>
                <div class="mt-3">
                    <h5>Description</h5>
                    <p><?php echo nl2br(htmlspecialchars($hall['description'])); ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($hall['address'])): ?>
                <div class="mt-3">
                    <h5>Address</h5>
                    <p><?php echo nl2br(htmlspecialchars($hall['address'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Images -->
        <?php if (!empty($hall['featured_image']) || !empty($galleryImages)): ?>
        <div class="card">
            <div class="card-header">
                <h3>Images</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($hall['featured_image'])): ?>
                <div class="mb-3">
                    <h5>Featured Image</h5>
                    <img src="<?php echo BASE_URL . '/' . $hall['featured_image']; ?>" 
                         alt="Featured Image" class="img-fluid rounded" style="max-height: 300px;">
                </div>
                <?php endif; ?>

                <?php if (!empty($galleryImages)): ?>
                <div>
                    <h5>Gallery</h5>
                    <div class="row">
                        <?php foreach ($galleryImages as $image): ?>
                        <div class="col-md-4 mb-3">
                            <img src="<?php echo BASE_URL . '/' . $image; ?>" 
                                 alt="Gallery Image" class="img-fluid rounded" style="height: 150px; object-fit: cover;">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Pricing -->
        <div class="card">
            <div class="card-header">
                <h3>Pricing</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if ($hall['hourly_rate'] > 0): ?>
                    <div class="col-md-3 text-center mb-3">
                        <div class="pricing-card">
                            <h4>Hourly</h4>
                            <h3><?php echo formatCurrency($hall['hourly_rate'], $hall['currency']); ?></h3>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($hall['daily_rate'] > 0): ?>
                    <div class="col-md-3 text-center mb-3">
                        <div class="pricing-card">
                            <h4>Daily</h4>
                            <h3><?php echo formatCurrency($hall['daily_rate'], $hall['currency']); ?></h3>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($hall['weekly_rate'] > 0): ?>
                    <div class="col-md-3 text-center mb-3">
                        <div class="pricing-card">
                            <h4>Weekly</h4>
                            <h3><?php echo formatCurrency($hall['weekly_rate'], $hall['currency']); ?></h3>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($hall['monthly_rate'] > 0): ?>
                    <div class="col-md-3 text-center mb-3">
                        <div class="pricing-card">
                            <h4>Monthly</h4>
                            <h3><?php echo formatCurrency($hall['monthly_rate'], $hall['currency']); ?></h3>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($bookingPeriods)): ?>
                <div class="mt-4">
                    <h5>Booking Periods</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Period</th>
                                    <th>Type</th>
                                    <th>Price</th>
                                    <th>Min Duration</th>
                                    <th>Max Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookingPeriods as $period): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($period['period_name']); ?></td>
                                    <td><?php echo htmlspecialchars($period['period_type']); ?></td>
                                    <td><?php echo formatCurrency($period['price'], $hall['currency']); ?></td>
                                    <td><?php echo $period['min_duration']; ?> <?php echo strtolower($period['period_type']); ?></td>
                                    <td><?php echo $period['max_duration'] ?: 'No limit'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Policies -->
        <?php if (!empty($hall['cancellation_policy']) || !empty($hall['terms_conditions'])): ?>
        <div class="card">
            <div class="card-header">
                <h3>Policies</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($hall['cancellation_policy'])): ?>
                <div class="mb-3">
                    <h5>Cancellation Policy</h5>
                    <p><?php echo nl2br(htmlspecialchars($hall['cancellation_policy'])); ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($hall['terms_conditions'])): ?>
                <div>
                    <h5>Terms & Conditions</h5>
                    <p><?php echo nl2br(htmlspecialchars($hall['terms_conditions'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Statistics & Recent Bookings -->
    <div class="col-md-4">
        <!-- Statistics -->
        <div class="card">
            <div class="card-header">
                <h3>Statistics (<?php echo date('Y'); ?>)</h3>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="stat-item">
                            <h4><?php echo $stats['total_bookings'] ?? 0; ?></h4>
                            <p>Total Bookings</p>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="stat-item">
                            <h4><?php echo $stats['confirmed_bookings'] ?? 0; ?></h4>
                            <p>Confirmed</p>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="stat-item">
                            <h4><?php echo $stats['completed_bookings'] ?? 0; ?></h4>
                            <p>Completed</p>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="stat-item">
                            <h4><?php echo $stats['cancelled_bookings'] ?? 0; ?></h4>
                            <p>Cancelled</p>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="text-center">
                    <h4><?php echo formatCurrency($stats['total_revenue'] ?? 0, $hall['currency']); ?></h4>
                    <p>Total Revenue</p>
                </div>
                
                <div class="text-center">
                    <h4><?php echo formatCurrency($stats['total_paid'] ?? 0, $hall['currency']); ?></h4>
                    <p>Amount Paid</p>
                </div>
                
                <div class="text-center">
                    <h4><?php echo formatCurrency($stats['avg_booking_value'] ?? 0, $hall['currency']); ?></h4>
                    <p>Avg Booking Value</p>
                </div>
            </div>
        </div>

        <!-- Recent Bookings -->
        <div class="card">
            <div class="card-header">
                <h3>Recent Bookings</h3>
            </div>
            <div class="card-body">
                <?php if (empty($recentBookings)): ?>
                <p class="text-muted">No bookings yet.</p>
                <?php else: ?>
                <div class="booking-list">
                    <?php foreach ($recentBookings as $booking): ?>
                    <div class="booking-item">
                        <div class="booking-header">
                            <strong><?php echo htmlspecialchars($booking['booking_number']); ?></strong>
                            <span class="badge <?php echo getBookingStatusBadgeClass($booking['booking_status']); ?>">
                                <?php echo $booking['booking_status']; ?>
                            </span>
                        </div>
                        <div class="booking-details">
                            <p class="mb-1">
                                <strong><?php echo htmlspecialchars($booking['event_name'] ?: 'No event name'); ?></strong>
                            </p>
                            <p class="mb-1">
                                <i class="icon-calendar"></i> 
                                <?php echo date('M d, Y', strtotime($booking['start_date'])); ?>
                            </p>
                            <p class="mb-1">
                                <i class="icon-clock"></i> 
                                <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - 
                                <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                            </p>
                            <?php if ($booking['first_name']): ?>
                            <p class="mb-1">
                                <i class="icon-user"></i> 
                                <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?>
                            </p>
                            <?php endif; ?>
                            <p class="mb-0">
                                <strong><?php echo formatCurrency($booking['total_amount'], $hall['currency']); ?></strong>
                            </p>
                        </div>
                        <div class="booking-actions">
                            <a href="bookings/view.php?id=<?php echo $booking['id']; ?>" 
                               class="btn btn-sm btn-outline-primary">View</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="text-center mt-3">
                    <a href="bookings/index.php?hall_id=<?php echo $hall['id']; ?>" class="btn btn-sm btn-primary">
                        View All Bookings
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Helper functions for badge classes
function getHallStatusBadgeClass($status) {
    switch ($status) {
        case 'Available':
            return 'badge-success';
        case 'Maintenance':
            return 'badge-warning';
        case 'Unavailable':
            return 'badge-danger';
        default:
            return 'badge-secondary';
    }
}

function getBookingStatusBadgeClass($status) {
    switch ($status) {
        case 'Confirmed':
            return 'badge-success';
        case 'Pending':
            return 'badge-warning';
        case 'Completed':
            return 'badge-info';
        case 'Cancelled':
            return 'badge-danger';
        default:
            return 'badge-secondary';
    }
}
?>

<style>
.pricing-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.pricing-card h4 {
    color: #6c757d;
    font-size: 14px;
    margin-bottom: 10px;
}

.pricing-card h3 {
    color: #007bff;
    font-size: 24px;
    margin: 0;
}

.stat-item h4 {
    color: #007bff;
    font-size: 24px;
    margin: 0;
}

.stat-item p {
    color: #6c757d;
    font-size: 12px;
    margin: 5px 0 0 0;
}

.booking-item {
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 15px;
    background: #f8f9fa;
}

.booking-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.booking-details p {
    font-size: 13px;
    margin-bottom: 5px;
}

.booking-actions {
    margin-top: 10px;
}

.badge-success { background-color: #28a745; color: white; }
.badge-warning { background-color: #ffc107; color: #333; }
.badge-danger { background-color: #dc3545; color: white; }
.badge-info { background-color: #17a2b8; color: white; }
.badge-secondary { background-color: #6c757d; color: white; }
</style>

<?php include '../../includes/footer.php'; ?>
