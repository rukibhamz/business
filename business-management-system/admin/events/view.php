<?php
/**
 * Business Management System - View Event
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
requirePermission('events.view');

// Get database connection
$conn = getDB();

// Get event ID
$eventId = (int)($_GET['id'] ?? 0);

if ($eventId <= 0) {
    header('Location: index.php');
    exit;
}

// Get event details
$stmt = $conn->prepare("
    SELECT e.*, ec.category_name, u.first_name as created_by_first, u.last_name as created_by_last
    FROM " . DB_PREFIX . "events e
    JOIN " . DB_PREFIX . "event_categories ec ON e.category_id = ec.id
    LEFT JOIN " . DB_PREFIX . "users u ON e.created_by = u.id
    WHERE e.id = ?
");
$stmt->bind_param('i', $eventId);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

if (!$event) {
    header('Location: index.php');
    exit;
}

// Get event statistics
$stats = getEventStatistics($eventId);

// Get ticket types
$ticketTypes = $conn->prepare("
    SELECT * FROM " . DB_PREFIX . "event_tickets 
    WHERE event_id = ? 
    ORDER BY sort_order, id
");
$ticketTypes->bind_param('i', $eventId);
$ticketTypes->execute();
$tickets = $ticketTypes->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent bookings
$recentBookings = $conn->prepare("
    SELECT eb.*, c.first_name, c.last_name, c.company_name
    FROM " . DB_PREFIX . "event_bookings eb
    LEFT JOIN " . DB_PREFIX . "customers c ON eb.customer_id = c.id
    WHERE eb.event_id = ? AND eb.booking_status != 'Cancelled'
    ORDER BY eb.created_at DESC
    LIMIT 10
");
$recentBookings->bind_param('i', $eventId);
$recentBookings->execute();
$bookings = $recentBookings->get_result()->fetch_all(MYSQLI_ASSOC);

// Parse gallery images
$galleryImages = [];
if ($event['gallery_images']) {
    $galleryImages = json_decode($event['gallery_images'], true);
}

// Set page title
$pageTitle = $event['event_name'];

include '../../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1><?php echo htmlspecialchars($event['event_name']); ?></h1>
        <p><?php echo htmlspecialchars($event['event_code']); ?> • <?php echo htmlspecialchars($event['category_name']); ?></p>
    </div>
    <div class="page-actions">
        <?php if (hasPermission('events.edit')): ?>
        <a href="edit.php?id=<?php echo $event['id']; ?>" class="btn btn-primary">
            <i class="icon-edit"></i> Edit Event
        </a>
        <?php endif; ?>
        
        <a href="bookings/index.php?event_id=<?php echo $event['id']; ?>" class="btn btn-secondary">
            <i class="icon-ticket"></i> Manage Bookings
        </a>
        
        <?php if (hasPermission('events.create')): ?>
        <a href="duplicate.php?id=<?php echo $event['id']; ?>" class="btn btn-outline-secondary">
            <i class="icon-copy"></i> Duplicate
        </a>
        <?php endif; ?>
        
        <a href="index.php" class="btn btn-link">
            <i class="icon-arrow-left"></i> Back to Events
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <!-- Event Information -->
        <div class="card">
            <div class="card-header">
                <h3>Event Information</h3>
                <div class="status-badge">
                    <span class="badge <?php echo getEventStatusBadgeClass($event['status']); ?>">
                        <?php echo $event['status']; ?>
                    </span>
                    <?php if ($event['is_featured']): ?>
                        <span class="badge badge-warning">Featured</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-item">
                            <label>Event Code:</label>
                            <span><?php echo htmlspecialchars($event['event_code']); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <label>Category:</label>
                            <span><?php echo htmlspecialchars($event['category_name']); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <label>Event Type:</label>
                            <span><?php echo htmlspecialchars($event['event_type']); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <label>Start Date & Time:</label>
                            <span><?php echo formatEventDateTime($event['start_date'], $event['start_time']); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <label>End Date & Time:</label>
                            <span><?php echo formatEventDateTime($event['end_date'], $event['end_time']); ?></span>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="info-item">
                            <label>Venue:</label>
                            <span><?php echo htmlspecialchars($event['venue_name'] ?: 'Not specified'); ?></span>
                        </div>
                        
                        <?php if ($event['venue_address']): ?>
                        <div class="info-item">
                            <label>Address:</label>
                            <span><?php echo nl2br(htmlspecialchars($event['venue_address'])); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($event['capacity']): ?>
                        <div class="info-item">
                            <label>Capacity:</label>
                            <span><?php echo number_format($event['capacity']); ?> attendees</span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="info-item">
                            <label>Online Booking:</label>
                            <span class="badge <?php echo $event['enable_booking'] ? 'badge-success' : 'badge-secondary'; ?>">
                                <?php echo $event['enable_booking'] ? 'Enabled' : 'Disabled'; ?>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <label>Created By:</label>
                            <span><?php echo htmlspecialchars($event['created_by_first'] . ' ' . $event['created_by_last']); ?></span>
                        </div>
                    </div>
                </div>
                
                <?php if ($event['description']): ?>
                <div class="description-section">
                    <h4>Description</h4>
                    <div class="description-content">
                        <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($event['organizer_name'] || $event['organizer_email'] || $event['organizer_phone']): ?>
                <div class="organizer-section">
                    <h4>Organizer Information</h4>
                    <div class="row">
                        <?php if ($event['organizer_name']): ?>
                        <div class="col-md-4">
                            <div class="info-item">
                                <label>Name:</label>
                                <span><?php echo htmlspecialchars($event['organizer_name']); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($event['organizer_email']): ?>
                        <div class="col-md-4">
                            <div class="info-item">
                                <label>Email:</label>
                                <span><a href="mailto:<?php echo htmlspecialchars($event['organizer_email']); ?>">
                                    <?php echo htmlspecialchars($event['organizer_email']); ?>
                                </a></span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($event['organizer_phone']): ?>
                        <div class="col-md-4">
                            <div class="info-item">
                                <label>Phone:</label>
                                <span><a href="tel:<?php echo htmlspecialchars($event['organizer_phone']); ?>">
                                    <?php echo htmlspecialchars($event['organizer_phone']); ?>
                                </a></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Featured Image -->
        <?php if ($event['featured_image']): ?>
        <div class="card">
            <div class="card-header">
                <h3>Featured Image</h3>
            </div>
            <div class="card-body">
                <img src="../../../../<?php echo htmlspecialchars($event['featured_image']); ?>" 
                     alt="<?php echo htmlspecialchars($event['event_name']); ?>" 
                     class="featured-image">
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Gallery Images -->
        <?php if (!empty($galleryImages)): ?>
        <div class="card">
            <div class="card-header">
                <h3>Gallery</h3>
            </div>
            <div class="card-body">
                <div class="gallery-grid">
                    <?php foreach ($galleryImages as $image): ?>
                    <div class="gallery-item">
                        <img src="../../../../<?php echo htmlspecialchars($image); ?>" 
                             alt="Gallery Image" 
                             class="gallery-image"
                             onclick="openImageModal('<?php echo htmlspecialchars($image); ?>')">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Terms & Conditions -->
        <?php if ($event['terms_conditions'] || $event['cancellation_policy']): ?>
        <div class="card">
            <div class="card-header">
                <h3>Terms & Policies</h3>
            </div>
            <div class="card-body">
                <?php if ($event['terms_conditions']): ?>
                <div class="terms-section">
                    <h4>Terms & Conditions</h4>
                    <div class="terms-content">
                        <?php echo nl2br(htmlspecialchars($event['terms_conditions'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($event['cancellation_policy']): ?>
                <div class="policy-section">
                    <h4>Cancellation Policy</h4>
                    <div class="policy-content">
                        <?php echo nl2br(htmlspecialchars($event['cancellation_policy'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-md-4">
        <!-- Statistics -->
        <div class="card">
            <div class="card-header">
                <h3>Event Statistics</h3>
            </div>
            <div class="card-body">
                <div class="stat-item">
                    <div class="stat-value"><?php echo formatCurrency($stats['total_revenue']); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['total_bookings']; ?></div>
                    <div class="stat-label">Total Bookings</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['tickets_sold']; ?></div>
                    <div class="stat-label">Tickets Sold</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['checkin_rate']; ?>%</div>
                    <div class="stat-label">Check-in Rate</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-value"><?php echo formatCurrency($stats['total_outstanding']); ?></div>
                    <div class="stat-label">Outstanding Balance</div>
                </div>
            </div>
        </div>
        
        <!-- Ticket Types -->
        <div class="card">
            <div class="card-header">
                <h3>Ticket Types</h3>
            </div>
            <div class="card-body">
                <?php if (empty($tickets)): ?>
                <p class="text-muted">No ticket types defined</p>
                <?php else: ?>
                    <?php foreach ($tickets as $ticket): ?>
                    <div class="ticket-type">
                        <div class="ticket-header">
                            <h5><?php echo htmlspecialchars($ticket['ticket_name']); ?></h5>
                            <span class="ticket-price"><?php echo formatCurrency($ticket['price']); ?></span>
                        </div>
                        
                        <?php if ($ticket['ticket_description']): ?>
                        <p class="ticket-description"><?php echo htmlspecialchars($ticket['ticket_description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="ticket-stats">
                            <div class="progress">
                                <?php 
                                $percentage = $ticket['quantity_available'] > 0 ? 
                                    ($ticket['quantity_sold'] / $ticket['quantity_available']) * 100 : 0;
                                ?>
                                <div class="progress-bar" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                            <div class="ticket-count">
                                <?php echo $ticket['quantity_sold']; ?> / <?php echo $ticket['quantity_available']; ?> sold
                            </div>
                        </div>
                        
                        <div class="ticket-status">
                            <span class="badge <?php echo $ticket['is_active'] ? 'badge-success' : 'badge-secondary'; ?>">
                                <?php echo $ticket['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Bookings -->
        <div class="card">
            <div class="card-header">
                <h3>Recent Bookings</h3>
                <a href="bookings/index.php?event_id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-primary">
                    View All
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($bookings)): ?>
                <p class="text-muted">No bookings yet</p>
                <?php else: ?>
                    <?php foreach ($bookings as $booking): ?>
                    <div class="booking-item">
                        <div class="booking-header">
                            <strong><?php echo htmlspecialchars($booking['booking_number']); ?></strong>
                            <span class="badge <?php echo getBookingStatusBadgeClass($booking['booking_status']); ?>">
                                <?php echo $booking['booking_status']; ?>
                            </span>
                        </div>
                        
                        <div class="booking-details">
                            <div><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></div>
                            <div><?php echo formatCurrency($booking['total_amount']); ?></div>
                            <div class="text-muted"><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <img id="modalImage" src="" alt="Gallery Image">
    </div>
</div>

<script>
function openImageModal(imageSrc) {
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    modal.style.display = 'block';
    modalImg.src = '../../../../' + imageSrc;
}

// Close modal when clicking X
document.querySelector('.close').addEventListener('click', function() {
    document.getElementById('imageModal').style.display = 'none';
});

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('imageModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
});
</script>

<style>
.status-badge {
    display: flex;
    gap: 10px;
    align-items: center;
}

.info-item {
    margin-bottom: 15px;
}

.info-item label {
    font-weight: 600;
    color: #333;
    display: block;
    margin-bottom: 5px;
}

.info-item span {
    color: #6c757d;
}

.description-section, .organizer-section, .terms-section, .policy-section {
    margin-top: 20px;
}

.description-section h4, .organizer-section h4, .terms-section h4, .policy-section h4 {
    margin-bottom: 10px;
    color: #333;
    font-weight: 600;
}

.description-content, .terms-content, .policy-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    border-left: 4px solid #007bff;
}

.featured-image {
    width: 100%;
    max-height: 400px;
    object-fit: cover;
    border-radius: 5px;
}

.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
}

.gallery-item {
    position: relative;
    overflow: hidden;
    border-radius: 5px;
    cursor: pointer;
}

.gallery-image {
    width: 100%;
    height: 150px;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.gallery-image:hover {
    transform: scale(1.05);
}

.stat-item {
    text-align: center;
    margin-bottom: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 5px;
}

.stat-value {
    font-size: 24px;
    font-weight: bold;
    color: #007bff;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 14px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.ticket-type {
    margin-bottom: 20px;
    padding: 15px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    background: #f8f9fa;
}

.ticket-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.ticket-header h5 {
    margin: 0;
    color: #333;
}

.ticket-price {
    font-weight: bold;
    color: #28a745;
    font-size: 16px;
}

.ticket-description {
    margin-bottom: 10px;
    color: #6c757d;
    font-size: 14px;
}

.ticket-stats {
    margin-bottom: 10px;
}

.progress {
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 5px;
}

.progress-bar {
    height: 100%;
    background: #28a745;
    transition: width 0.3s ease;
}

.ticket-count {
    font-size: 12px;
    color: #6c757d;
    text-align: center;
}

.booking-item {
    margin-bottom: 15px;
    padding: 10px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    background: #f8f9fa;
}

.booking-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.booking-details {
    font-size: 14px;
    color: #6c757d;
}

.booking-details div {
    margin-bottom: 2px;
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
    background-color: rgba(0,0,0,0.8);
}

.modal-content {
    position: relative;
    margin: auto;
    padding: 0;
    width: 90%;
    max-width: 800px;
    top: 50%;
    transform: translateY(-50%);
}

.modal-content img {
    width: 100%;
    height: auto;
    border-radius: 5px;
}

.close {
    position: absolute;
    top: 15px;
    right: 35px;
    color: #fff;
    font-size: 40px;
    font-weight: bold;
    cursor: pointer;
    z-index: 1001;
}

.close:hover {
    opacity: 0.7;
}
</style>

<?php include '../../../includes/footer.php'; ?>
