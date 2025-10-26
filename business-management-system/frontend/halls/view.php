<?php
/**
 * Business Management System - Hall Details
 * Phase 4: Hall Booking System Module
 */

// Define system constant
define('BMS_SYSTEM', true);

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/hall-functions.php';

// Get hall ID
$hallId = (int)($_GET['id'] ?? 0);

if (!$hallId) {
    header('Location: index.php');
    exit;
}

// Get database connection
$conn = getDB();

// Get hall details
$stmt = $conn->prepare("
    SELECT h.*, hc.category_name, hc.description as category_description,
           u.first_name as created_by_first, u.last_name as created_by_last
    FROM " . DB_PREFIX . "halls h
    JOIN " . DB_PREFIX . "hall_categories hc ON h.category_id = hc.id
    LEFT JOIN " . DB_PREFIX . "users u ON h.created_by = u.id
    WHERE h.id = ? AND h.status = 'Available' AND h.enable_booking = 1
");
$stmt->bind_param('i', $hallId);
$stmt->execute();
$hall = $stmt->get_result()->fetch_assoc();

if (!$hall) {
    header('Location: index.php');
    exit;
}

// Get hall statistics
$stats = getHallStatistics($hallId);

// Get recent bookings for this hall
$stmt = $conn->prepare("
    SELECT hb.*, c.first_name, c.last_name, c.company_name
    FROM " . DB_PREFIX . "hall_bookings hb
    LEFT JOIN " . DB_PREFIX . "customers c ON hb.customer_id = c.id
    WHERE hb.hall_id = ? AND hb.booking_status != 'Cancelled'
    ORDER BY hb.start_date DESC
    LIMIT 5
");
$stmt->bind_param('i', $hallId);
$stmt->execute();
$recentBookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get hall gallery images
$galleryImages = [];
if ($hall['gallery_images']) {
    $galleryImages = json_decode($hall['gallery_images'], true);
}

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
    <title><?php echo htmlspecialchars($hall['hall_name']); ?> - Hall Details</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../public/css/halls.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="description" content="<?php echo htmlspecialchars(substr($hall['description'], 0, 160)); ?>">
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
                        <i class="fas fa-arrow-left"></i> Back to Halls
                    </a>
                    <a href="my-bookings.php" class="btn btn-outline-light">
                        <i class="fas fa-calendar-check"></i> My Bookings
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Hall Details -->
    <section class="hall-details-section">
        <div class="container">
            <div class="row">
                <!-- Hall Images -->
                <div class="col-lg-8">
                    <div class="hall-gallery">
                        <div class="main-image">
                            <?php if ($hall['featured_image']): ?>
                            <img src="../../uploads/halls/<?php echo htmlspecialchars($hall['featured_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($hall['hall_name']); ?>" 
                                 id="mainImage">
                            <?php else: ?>
                            <div class="placeholder-image">
                                <i class="fas fa-building"></i>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($hall['is_featured']): ?>
                            <div class="featured-badge">
                                <i class="fas fa-star"></i> Featured Hall
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($galleryImages)): ?>
                        <div class="gallery-thumbnails">
                            <?php foreach ($galleryImages as $index => $image): ?>
                            <div class="thumbnail" onclick="changeMainImage('<?php echo htmlspecialchars($image); ?>')">
                                <img src="../../uploads/halls/<?php echo htmlspecialchars($image); ?>" 
                                     alt="Gallery Image <?php echo $index + 1; ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Hall Information -->
                    <div class="hall-info-card">
                        <div class="hall-header">
                            <h1><?php echo htmlspecialchars($hall['hall_name']); ?></h1>
                            <div class="hall-meta">
                                <span class="hall-code"><?php echo htmlspecialchars($hall['hall_code']); ?></span>
                                <span class="hall-category"><?php echo htmlspecialchars($hall['category_name']); ?></span>
                            </div>
                        </div>
                        
                        <div class="hall-description">
                            <?php echo nl2br(htmlspecialchars($hall['description'])); ?>
                        </div>
                        
                        <div class="hall-specifications">
                            <h3><i class="fas fa-info-circle"></i> Specifications</h3>
                            <div class="specs-grid">
                                <div class="spec-item">
                                    <i class="fas fa-users"></i>
                                    <div>
                                        <strong>Capacity</strong>
                                        <span><?php echo number_format($hall['capacity']); ?> people</span>
                                    </div>
                                </div>
                                <?php if ($hall['area_sqft']): ?>
                                <div class="spec-item">
                                    <i class="fas fa-expand-arrows-alt"></i>
                                    <div>
                                        <strong>Area</strong>
                                        <span><?php echo number_format($hall['area_sqft']); ?> sq ft</span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if ($hall['location']): ?>
                                <div class="spec-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <div>
                                        <strong>Location</strong>
                                        <span><?php echo htmlspecialchars($hall['location']); ?></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="spec-item">
                                    <i class="fas fa-calendar-check"></i>
                                    <div>
                                        <strong>Bookings</strong>
                                        <span><?php echo $stats['total_bookings']; ?> completed</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($hall['amenities']): ?>
                        <div class="hall-amenities">
                            <h3><i class="fas fa-star"></i> Amenities</h3>
                            <div class="amenities-list">
                                <?php 
                                $amenities = json_decode($hall['amenities'], true);
                                if ($amenities):
                                    foreach ($amenities as $amenity):
                                ?>
                                <div class="amenity-item">
                                    <i class="fas fa-check"></i>
                                    <span><?php echo htmlspecialchars($amenity); ?></span>
                                </div>
                                <?php 
                                    endforeach;
                                endif;
                                ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($hall['address']): ?>
                        <div class="hall-location">
                            <h3><i class="fas fa-map"></i> Address</h3>
                            <p><?php echo nl2br(htmlspecialchars($hall['address'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Booking Sidebar -->
                <div class="col-lg-4">
                    <div class="booking-sidebar">
                        <div class="pricing-card">
                            <h3><i class="fas fa-tags"></i> Pricing</h3>
                            <div class="pricing-options">
                                <?php if ($hall['hourly_rate']): ?>
                                <div class="price-option">
                                    <div class="price-period">Hourly Rate</div>
                                    <div class="price-amount"><?php echo formatCurrency($hall['hourly_rate']); ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if ($hall['daily_rate']): ?>
                                <div class="price-option">
                                    <div class="price-period">Daily Rate</div>
                                    <div class="price-amount"><?php echo formatCurrency($hall['daily_rate']); ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if ($hall['weekly_rate']): ?>
                                <div class="price-option">
                                    <div class="price-period">Weekly Rate</div>
                                    <div class="price-amount"><?php echo formatCurrency($hall['weekly_rate']); ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if ($hall['monthly_rate']): ?>
                                <div class="price-option">
                                    <div class="price-period">Monthly Rate</div>
                                    <div class="price-amount"><?php echo formatCurrency($hall['monthly_rate']); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="booking-card">
                            <h3><i class="fas fa-calendar-plus"></i> Book This Hall</h3>
                            <p>Ready to book this hall for your event?</p>
                            <a href="booking.php?hall_id=<?php echo $hall['id']; ?>" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-calendar-plus"></i> Book Now
                            </a>
                            <div class="booking-info">
                                <div class="info-item">
                                    <i class="fas fa-shield-alt"></i>
                                    <span>Secure booking process</span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-envelope"></i>
                                    <span>Instant confirmation</span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-credit-card"></i>
                                    <span>Multiple payment options</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stats-card">
                            <h3><i class="fas fa-chart-bar"></i> Hall Statistics</h3>
                            <div class="stats-grid">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['total_bookings']; ?></div>
                                    <div class="stat-label">Total Bookings</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo formatCurrency($stats['total_revenue']); ?></div>
                                    <div class="stat-label">Total Revenue</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $stats['occupancy_rate']; ?>%</div>
                                    <div class="stat-label">Occupancy Rate</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="contact-card">
                            <h3><i class="fas fa-phone"></i> Contact Us</h3>
                            <div class="contact-info">
                                <div class="contact-item">
                                    <i class="fas fa-phone"></i>
                                    <span><?php echo htmlspecialchars($companyPhone); ?></span>
                                </div>
                                <div class="contact-item">
                                    <i class="fas fa-envelope"></i>
                                    <span><?php echo htmlspecialchars($companyEmail); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Recent Bookings -->
    <?php if (!empty($recentBookings)): ?>
    <section class="recent-bookings">
        <div class="container">
            <h2><i class="fas fa-history"></i> Recent Bookings</h2>
            <div class="bookings-list">
                <?php foreach ($recentBookings as $booking): ?>
                <div class="booking-item">
                    <div class="booking-date">
                        <div class="date-day"><?php echo date('d', strtotime($booking['start_date'])); ?></div>
                        <div class="date-month"><?php echo date('M', strtotime($booking['start_date'])); ?></div>
                    </div>
                    <div class="booking-details">
                        <h4><?php echo htmlspecialchars($booking['event_name'] ?: 'Event'); ?></h4>
                        <p>
                            <i class="fas fa-clock"></i>
                            <?php echo formatHallBookingDateTime($booking['start_date'], $booking['start_time'], $booking['end_date'], $booking['end_time']); ?>
                        </p>
                        <p>
                            <i class="fas fa-user"></i>
                            <?php echo htmlspecialchars($booking['company_name'] ?: $booking['first_name'] . ' ' . $booking['last_name']); ?>
                        </p>
                    </div>
                    <div class="booking-status">
                        <span class="badge <?php echo getHallBookingStatusBadgeClass($booking['booking_status']); ?>">
                            <?php echo $booking['booking_status']; ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Terms & Conditions -->
    <?php if ($hall['terms_conditions']): ?>
    <section class="terms-section">
        <div class="container">
            <h2><i class="fas fa-file-contract"></i> Terms & Conditions</h2>
            <div class="terms-content">
                <?php echo nl2br(htmlspecialchars($hall['terms_conditions'])); ?>
            </div>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Cancellation Policy -->
    <?php if ($hall['cancellation_policy']): ?>
    <section class="cancellation-section">
        <div class="container">
            <h2><i class="fas fa-ban"></i> Cancellation Policy</h2>
            <div class="cancellation-content">
                <?php echo nl2br(htmlspecialchars($hall['cancellation_policy'])); ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-info">
                    <h3><?php echo htmlspecialchars($companyName); ?></h3>
                    <p>Professional hall rentals and event spaces</p>
                </div>
                <div class="footer-links">
                    <a href="index.php">All Halls</a>
                    <a href="my-bookings.php">My Bookings</a>
                    <a href="mailto:<?php echo htmlspecialchars($companyEmail); ?>">Contact Us</a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($companyName); ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        function changeMainImage(imageSrc) {
            const mainImage = document.getElementById('mainImage');
            if (mainImage) {
                mainImage.src = '../../uploads/halls/' + imageSrc;
            }
        }
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>
