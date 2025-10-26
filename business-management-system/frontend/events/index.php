<?php
/**
 * Business Management System - Public Event Listing
 * Phase 4: Event Booking System Module
 */

// Define system constant
define('BMS_SYSTEM', true);

// Include required files
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/event-functions.php';

// Get database connection
$conn = getDB();

// Get filter parameters
$categoryId = (int)($_GET['category_id'] ?? 0);
$dateFilter = $_GET['date_filter'] ?? '';
$priceMin = (float)($_GET['price_min'] ?? 0);
$priceMax = (float)($_GET['price_max'] ?? 0);
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'date';

// Build query
$whereConditions = ["e.status = 'Published'", "e.enable_booking = 1"];
$params = [];
$paramTypes = '';

if ($categoryId > 0) {
    $whereConditions[] = 'e.category_id = ?';
    $params[] = $categoryId;
    $paramTypes .= 'i';
}

if (!empty($search)) {
    $whereConditions[] = '(e.event_name LIKE ? OR e.description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $paramTypes .= 'ss';
}

// Date filter
if ($dateFilter == 'upcoming') {
    $whereConditions[] = 'e.start_date >= CURDATE()';
} elseif ($dateFilter == 'this_week') {
    $whereConditions[] = 'e.start_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
} elseif ($dateFilter == 'this_month') {
    $whereConditions[] = 'e.start_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
}

// Price filter
if ($priceMin > 0 || $priceMax > 0) {
    $priceConditions = [];
    if ($priceMin > 0) {
        $priceConditions[] = 'MIN(et.price) >= ?';
        $params[] = $priceMin;
        $paramTypes .= 'd';
    }
    if ($priceMax > 0) {
        $priceConditions[] = 'MIN(et.price) <= ?';
        $params[] = $priceMax;
        $paramTypes .= 'd';
    }
    if (!empty($priceConditions)) {
        $whereConditions[] = '(' . implode(' AND ', $priceConditions) . ')';
    }
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Sort options
$orderBy = 'e.start_date ASC';
switch ($sort) {
    case 'price_low':
        $orderBy = 'min_price ASC';
        break;
    case 'price_high':
        $orderBy = 'min_price DESC';
        break;
    case 'popularity':
        $orderBy = 'tickets_sold DESC';
        break;
    case 'date':
    default:
        $orderBy = 'e.start_date ASC';
        break;
}

// Get events
$query = "
    SELECT e.*, ec.category_name,
           MIN(et.price) as min_price,
           SUM(et.quantity_sold) as tickets_sold,
           SUM(et.quantity_available) as total_capacity,
           COUNT(et.id) as ticket_types_count
    FROM " . DB_PREFIX . "events e
    JOIN " . DB_PREFIX . "event_categories ec ON e.category_id = ec.id
    LEFT JOIN " . DB_PREFIX . "event_tickets et ON e.id = et.event_id AND et.is_active = 1
    {$whereClause}
    GROUP BY e.id
    HAVING (e.start_date >= CURDATE() OR e.start_date = CURDATE())
    ORDER BY {$orderBy}
    LIMIT 20
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get event categories for filter dropdown
$categories = $conn->query("
    SELECT id, category_name 
    FROM " . DB_PREFIX . "event_categories 
    WHERE is_active = 1 
    ORDER BY category_name
")->fetch_all(MYSQLI_ASSOC);

// Get price range
$priceRange = $conn->query("
    SELECT MIN(price) as min_price, MAX(price) as max_price
    FROM " . DB_PREFIX . "event_tickets et
    JOIN " . DB_PREFIX . "events e ON et.event_id = e.id
    WHERE e.status = 'Published' AND e.enable_booking = 1 AND et.is_active = 1
")->fetch_assoc();

$minPrice = $priceRange['min_price'] ?? 0;
$maxPrice = $priceRange['max_price'] ?? 10000;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - <?php echo getSetting('company_name', 'Business Management System'); ?></title>
    <link rel="stylesheet" href="../../public/css/style.css">
    <link rel="stylesheet" href="../../public/css/events.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <h1><?php echo getSetting('company_name', 'Business Management System'); ?></h1>
                </div>
                <nav class="nav">
                    <a href="index.php" class="nav-link active">Events</a>
                    <a href="my-bookings.php" class="nav-link">My Bookings</a>
                    <a href="../../admin/login.php" class="nav-link">Admin Login</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>Discover Amazing Events</h1>
                <p>Find and book tickets for conferences, workshops, concerts, and more</p>
            </div>
        </div>
    </section>

    <!-- Filters -->
    <section class="filters">
        <div class="container">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label for="search">Search Events</label>
                    <input type="text" name="search" id="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by event name or description">
                </div>
                
                <div class="filter-group">
                    <label for="category_id">Category</label>
                    <select name="category_id" id="category_id">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" 
                                <?php echo $categoryId == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['category_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="date_filter">Date</label>
                    <select name="date_filter" id="date_filter">
                        <option value="">All Dates</option>
                        <option value="upcoming" <?php echo $dateFilter == 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                        <option value="this_week" <?php echo $dateFilter == 'this_week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="this_month" <?php echo $dateFilter == 'this_month' ? 'selected' : ''; ?>>This Month</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="price_range">Price Range</label>
                    <div class="price-range">
                        <input type="range" name="price_min" min="<?php echo $minPrice; ?>" 
                               max="<?php echo $maxPrice; ?>" value="<?php echo $priceMin ?: $minPrice; ?>" 
                               class="price-slider" id="price_min">
                        <input type="range" name="price_max" min="<?php echo $minPrice; ?>" 
                               max="<?php echo $maxPrice; ?>" value="<?php echo $priceMax ?: $maxPrice; ?>" 
                               class="price-slider" id="price_max">
                        <div class="price-display">
                            <span id="price_min_display"><?php echo formatCurrency($priceMin ?: $minPrice); ?></span>
                            <span>to</span>
                            <span id="price_max_display"><?php echo formatCurrency($priceMax ?: $maxPrice); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="filter-group">
                    <label for="sort">Sort By</label>
                    <select name="sort" id="sort">
                        <option value="date" <?php echo $sort == 'date' ? 'selected' : ''; ?>>Date (Upcoming First)</option>
                        <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Price (Low to High)</option>
                        <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Price (High to Low)</option>
                        <option value="popularity" <?php echo $sort == 'popularity' ? 'selected' : ''; ?>>Popularity</option>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="index.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>
    </section>

    <!-- Events Grid -->
    <section class="events-section">
        <div class="container">
            <div class="events-header">
                <h2>Available Events</h2>
                <p><?php echo count($events); ?> events found</p>
            </div>
            
            <?php if (empty($events)): ?>
            <div class="no-events">
                <h3>No events found</h3>
                <p>Try adjusting your filters or check back later for new events.</p>
            </div>
            <?php else: ?>
            <div class="events-grid">
                <?php foreach ($events as $event): ?>
                <div class="event-card">
                    <div class="event-image">
                        <?php if ($event['featured_image']): ?>
                            <img src="../../<?php echo htmlspecialchars($event['featured_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($event['event_name']); ?>">
                        <?php else: ?>
                            <div class="placeholder-image">
                                <i class="icon-calendar"></i>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($event['is_featured']): ?>
                            <div class="featured-badge">Featured</div>
                        <?php endif; ?>
                        
                        <div class="category-badge">
                            <?php echo htmlspecialchars($event['category_name']); ?>
                        </div>
                        
                        <?php 
                        $isSoldOut = $event['total_capacity'] > 0 && $event['tickets_sold'] >= $event['total_capacity'];
                        if ($isSoldOut): 
                        ?>
                            <div class="sold-out-overlay">
                                <span>Sold Out</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="event-content">
                        <h3 class="event-title">
                            <a href="view.php?id=<?php echo $event['id']; ?>">
                                <?php echo htmlspecialchars($event['event_name']); ?>
                            </a>
                        </h3>
                        
                        <div class="event-meta">
                            <div class="event-date">
                                <i class="icon-calendar"></i>
                                <?php echo date('M d, Y', strtotime($event['start_date'])); ?>
                            </div>
                            
                            <div class="event-time">
                                <i class="icon-clock"></i>
                                <?php echo date('g:i A', strtotime($event['start_time'])); ?>
                            </div>
                            
                            <?php if ($event['venue_name']): ?>
                            <div class="event-venue">
                                <i class="icon-location"></i>
                                <?php echo htmlspecialchars($event['venue_name']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($event['description']): ?>
                        <p class="event-description">
                            <?php echo substr(strip_tags($event['description']), 0, 120); ?>
                            <?php if (strlen(strip_tags($event['description'])) > 120): ?>...<?php endif; ?>
                        </p>
                        <?php endif; ?>
                        
                        <div class="event-footer">
                            <div class="event-price">
                                <?php if ($event['min_price'] > 0): ?>
                                    <span class="price">From <?php echo formatCurrency($event['min_price']); ?></span>
                                <?php else: ?>
                                    <span class="price">Free</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="event-actions">
                                <?php if ($isSoldOut): ?>
                                    <button class="btn btn-secondary" disabled>Sold Out</button>
                                <?php else: ?>
                                    <a href="view.php?id=<?php echo $event['id']; ?>" class="btn btn-primary">
                                        View Details
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($event['total_capacity'] > 0): ?>
                        <div class="event-stats">
                            <div class="tickets-sold">
                                <?php echo $event['tickets_sold']; ?> / <?php echo $event['total_capacity']; ?> tickets sold
                            </div>
                            <div class="progress-bar">
                                <?php 
                                $percentage = ($event['tickets_sold'] / $event['total_capacity']) * 100;
                                ?>
                                <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <p>&copy; <?php echo date('Y'); ?> <?php echo getSetting('company_name', 'Business Management System'); ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
    // Price range slider functionality
    const priceMinSlider = document.getElementById('price_min');
    const priceMaxSlider = document.getElementById('price_max');
    const priceMinDisplay = document.getElementById('price_min_display');
    const priceMaxDisplay = document.getElementById('price_max_display');

    function updatePriceDisplay() {
        const minValue = parseFloat(priceMinSlider.value);
        const maxValue = parseFloat(priceMaxSlider.value);
        
        priceMinDisplay.textContent = formatCurrency(minValue);
        priceMaxDisplay.textContent = formatCurrency(maxValue);
        
        // Ensure min doesn't exceed max
        if (minValue > maxValue) {
            priceMinSlider.value = maxValue;
            priceMinDisplay.textContent = formatCurrency(maxValue);
        }
    }

    priceMinSlider.addEventListener('input', updatePriceDisplay);
    priceMaxSlider.addEventListener('input', updatePriceDisplay);

    function formatCurrency(amount) {
        return '₦' + parseFloat(amount).toLocaleString('en-NG', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        });
    }
    </script>
</body>
</html>

