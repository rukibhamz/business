<?php
/**
 * Business Management System - Public Hall Listing
 * Phase 4: Hall Booking System Module
 */

// Define system constant
define('BMS_SYSTEM', true);

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/hall-functions.php';

// Get database connection
$conn = getDB();

// Get filter parameters
$categoryId = (int)($_GET['category_id'] ?? 0);
$search = $_GET['search'] ?? '';
$minPrice = (float)($_GET['min_price'] ?? 0);
$maxPrice = (float)($_GET['max_price'] ?? 0);
$capacity = (int)($_GET['capacity'] ?? 0);

// Build query
$whereConditions = ["h.status = 'Available'", "h.enable_booking = 1"];
$params = [];
$paramTypes = '';

if ($categoryId > 0) {
    $whereConditions[] = 'h.category_id = ?';
    $params[] = $categoryId;
    $paramTypes .= 'i';
}

if (!empty($search)) {
    $whereConditions[] = '(h.hall_name LIKE ? OR h.location LIKE ? OR h.description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $paramTypes .= 'sss';
}

if ($minPrice > 0) {
    $whereConditions[] = '(h.hourly_rate >= ? OR h.daily_rate >= ? OR h.weekly_rate >= ? OR h.monthly_rate >= ?)';
    $params[] = $minPrice;
    $params[] = $minPrice;
    $params[] = $minPrice;
    $params[] = $minPrice;
    $paramTypes .= 'dddd';
}

if ($maxPrice > 0) {
    $whereConditions[] = '(h.hourly_rate <= ? OR h.daily_rate <= ? OR h.weekly_rate <= ? OR h.monthly_rate <= ?)';
    $params[] = $maxPrice;
    $params[] = $maxPrice;
    $params[] = $maxPrice;
    $params[] = $maxPrice;
    $paramTypes .= 'dddd';
}

if ($capacity > 0) {
    $whereConditions[] = 'h.capacity >= ?';
    $params[] = $capacity;
    $paramTypes .= 'i';
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get halls
$query = "
    SELECT h.*, hc.category_name,
           COUNT(hb.id) as total_bookings,
           AVG(hb.total_amount) as avg_booking_amount
    FROM " . DB_PREFIX . "halls h
    JOIN " . DB_PREFIX . "hall_categories hc ON h.category_id = hc.id
    LEFT JOIN " . DB_PREFIX . "hall_bookings hb ON h.id = hb.hall_id AND hb.booking_status != 'Cancelled'
    {$whereClause}
    GROUP BY h.id
    ORDER BY h.is_featured DESC, h.created_at DESC
    LIMIT 20
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$halls = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get hall categories for filter dropdown
$categories = $conn->query("
    SELECT id, category_name 
    FROM " . DB_PREFIX . "hall_categories 
    WHERE is_active = 1 
    ORDER BY category_name
")->fetch_all(MYSQLI_ASSOC);

// Get company settings for display
$companyName = getSetting('company_name', 'Business Management System');
$companyEmail = getSetting('company_email', 'info@example.com');
$companyPhone = getSetting('company_phone', '+234 000 000 0000');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hall Rentals - <?php echo htmlspecialchars($companyName); ?></title>
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
                    <a href="my-bookings.php" class="btn btn-outline-light">
                        <i class="fas fa-calendar-check"></i> My Bookings
                    </a>
                    <a href="../../admin" class="btn btn-primary">
                        <i class="fas fa-user-shield"></i> Admin Login
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>Find the Perfect Hall for Your Event</h1>
                <p>From intimate meetings to grand celebrations, we have the perfect space for every occasion</p>
                
                <!-- Quick Search -->
                <div class="quick-search">
                    <form method="GET" class="search-form">
                        <div class="search-group">
                            <input type="text" name="search" placeholder="Search halls by name, location..." 
                                   value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Filters -->
    <section class="filters-section">
        <div class="container">
            <div class="filters-card">
                <h3><i class="fas fa-filter"></i> Filter Halls</h3>
                <form method="GET" class="filters-form">
                    <div class="row">
                        <div class="col-md-3">
                            <label for="category_id">Category:</label>
                            <select name="category_id" id="category_id" class="form-control">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                        <?php echo $categoryId == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="capacity">Min Capacity:</label>
                            <input type="number" name="capacity" id="capacity" 
                                   value="<?php echo $capacity; ?>" 
                                   placeholder="50" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label for="min_price">Min Price:</label>
                            <input type="number" name="min_price" id="min_price" 
                                   value="<?php echo $minPrice; ?>" 
                                   placeholder="0" class="form-control" step="0.01">
                        </div>
                        <div class="col-md-2">
                            <label for="max_price">Max Price:</label>
                            <input type="number" name="max_price" id="max_price" 
                                   value="<?php echo $maxPrice; ?>" 
                                   placeholder="10000" class="form-control" step="0.01">
                        </div>
                        <div class="col-md-3">
                            <label>&nbsp;</label>
                            <div class="filter-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Apply Filters
                                </button>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- Halls Grid -->
    <section class="halls-section">
        <div class="container">
            <div class="section-header">
                <h2>Available Halls</h2>
                <p><?php echo count($halls); ?> halls found</p>
            </div>

            <?php if (empty($halls)): ?>
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h3>No halls found</h3>
                <p>Try adjusting your search criteria or browse all available halls.</p>
                <a href="index.php" class="btn btn-primary">View All Halls</a>
            </div>
            <?php else: ?>
            <div class="halls-grid">
                <?php foreach ($halls as $hall): ?>
                <div class="hall-card">
                    <?php if ($hall['is_featured']): ?>
                    <div class="featured-badge">
                        <i class="fas fa-star"></i> Featured
                    </div>
                    <?php endif; ?>
                    
                    <div class="hall-image">
                        <?php if ($hall['featured_image']): ?>
                        <img src="../../uploads/halls/<?php echo htmlspecialchars($hall['featured_image']); ?>" 
                             alt="<?php echo htmlspecialchars($hall['hall_name']); ?>">
                        <?php else: ?>
                        <div class="placeholder-image">
                            <i class="fas fa-building"></i>
                        </div>
                        <?php endif; ?>
                        
                        <div class="hall-category">
                            <?php echo htmlspecialchars($hall['category_name']); ?>
                        </div>
                    </div>
                    
                    <div class="hall-content">
                        <h3><?php echo htmlspecialchars($hall['hall_name']); ?></h3>
                        <p class="hall-code"><?php echo htmlspecialchars($hall['hall_code']); ?></p>
                        
                        <div class="hall-details">
                            <div class="detail-item">
                                <i class="fas fa-users"></i>
                                <span><?php echo number_format($hall['capacity']); ?> people</span>
                            </div>
                            <?php if ($hall['location']): ?>
                            <div class="detail-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($hall['location']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($hall['area_sqft']): ?>
                            <div class="detail-item">
                                <i class="fas fa-expand-arrows-alt"></i>
                                <span><?php echo number_format($hall['area_sqft']); ?> sq ft</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="hall-pricing">
                            <div class="price-options">
                                <?php if ($hall['hourly_rate']): ?>
                                <div class="price-item">
                                    <span class="price-label">Hourly:</span>
                                    <span class="price-value"><?php echo formatCurrency($hall['hourly_rate']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($hall['daily_rate']): ?>
                                <div class="price-item">
                                    <span class="price-label">Daily:</span>
                                    <span class="price-value"><?php echo formatCurrency($hall['daily_rate']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($hall['weekly_rate']): ?>
                                <div class="price-item">
                                    <span class="price-label">Weekly:</span>
                                    <span class="price-value"><?php echo formatCurrency($hall['weekly_rate']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($hall['monthly_rate']): ?>
                                <div class="price-item">
                                    <span class="price-label">Monthly:</span>
                                    <span class="price-value"><?php echo formatCurrency($hall['monthly_rate']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="hall-stats">
                            <div class="stat-item">
                                <i class="fas fa-calendar-check"></i>
                                <span><?php echo $hall['total_bookings']; ?> bookings</span>
                            </div>
                            <?php if ($hall['avg_booking_amount']): ?>
                            <div class="stat-item">
                                <i class="fas fa-chart-line"></i>
                                <span>Avg: <?php echo formatCurrency($hall['avg_booking_amount']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="hall-actions">
                            <a href="view.php?id=<?php echo $hall['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                            <a href="booking.php?hall_id=<?php echo $hall['id']; ?>" class="btn btn-success">
                                <i class="fas fa-calendar-plus"></i> Book Now
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section">
        <div class="container">
            <div class="contact-content">
                <h2>Need Help Finding the Right Hall?</h2>
                <p>Our team is here to help you find the perfect space for your event</p>
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
                    <a href="my-bookings.php">My Bookings</a>
                    <a href="../../admin">Admin Login</a>
                    <a href="mailto:<?php echo htmlspecialchars($companyEmail); ?>">Contact Us</a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($companyName); ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="../../public/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-submit form on filter change
        document.getElementById('category_id').addEventListener('change', function() {
            this.form.submit();
        });
        
        // Price range validation
        document.getElementById('min_price').addEventListener('input', function() {
            const maxPrice = document.getElementById('max_price');
            if (this.value && maxPrice.value && parseFloat(this.value) > parseFloat(maxPrice.value)) {
                maxPrice.value = this.value;
            }
        });
        
        document.getElementById('max_price').addEventListener('input', function() {
            const minPrice = document.getElementById('min_price');
            if (this.value && minPrice.value && parseFloat(this.value) < parseFloat(minPrice.value)) {
                minPrice.value = this.value;
            }
        });
    </script>
</body>
</html>
