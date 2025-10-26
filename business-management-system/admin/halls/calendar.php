<?php
/**
 * Business Management System - Hall Calendar
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

// Get parameters
$hallId = (int)($_GET['hall_id'] ?? 0);
$month = (int)($_GET['month'] ?? date('n'));
$year = (int)($_GET['year'] ?? date('Y'));

// Validate month and year
if ($month < 1 || $month > 12) $month = date('n');
if ($year < 2020 || $year > 2030) $year = date('Y');

// Get hall details if specific hall selected
$hall = null;
if ($hallId > 0) {
    $stmt = $conn->prepare("SELECT * FROM " . DB_PREFIX . "halls WHERE id = ?");
    $stmt->bind_param('i', $hallId);
    $stmt->execute();
    $hall = $stmt->get_result()->fetch_assoc();
}

// Get all halls for dropdown
$halls = $conn->query("
    SELECT id, hall_name, hall_code 
    FROM " . DB_PREFIX . "halls 
    WHERE status = 'Available' 
    ORDER BY hall_name
")->fetch_all(MYSQLI_ASSOC);

// Get bookings for the month
$startDate = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
$endDate = date('Y-m-t', strtotime($startDate));

$whereClause = "hb.start_date BETWEEN '$startDate' AND '$endDate'";
if ($hallId > 0) {
    $whereClause .= " AND hb.hall_id = $hallId";
}

$bookings = $conn->query("
    SELECT hb.*, h.hall_name, h.hall_code, c.first_name, c.last_name, c.email, c.phone
    FROM " . DB_PREFIX . "hall_bookings hb
    LEFT JOIN " . DB_PREFIX . "halls h ON hb.hall_id = h.id
    LEFT JOIN " . DB_PREFIX . "customers c ON hb.customer_id = c.id
    WHERE $whereClause
    AND hb.booking_status IN ('Confirmed', 'Pending')
    ORDER BY hb.start_date, hb.start_time
")->fetch_all(MYSQLI_ASSOC);

// Group bookings by date
$bookingsByDate = [];
foreach ($bookings as $booking) {
    $date = $booking['start_date'];
    if (!isset($bookingsByDate[$date])) {
        $bookingsByDate[$date] = [];
    }
    $bookingsByDate[$date][] = $booking;
}

// Calendar data
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$lastDay = mktime(0, 0, 0, $month + 1, 0, $year);
$daysInMonth = date('t', $firstDay);
$firstDayOfWeek = date('w', $firstDay);

// Navigation
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

// Set page title
$pageTitle = 'Hall Calendar';
if ($hall) {
    $pageTitle .= ' - ' . $hall['hall_name'];
}

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Hall Calendar</h1>
        <p><?php echo date('F Y', $firstDay); ?></p>
    </div>
    <div class="page-actions">
        <div class="btn-group">
            <a href="calendar.php?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?><?php echo $hallId ? '&hall_id=' . $hallId : ''; ?>" 
               class="btn btn-secondary">
                <i class="icon-chevron-left"></i> Previous
            </a>
            <a href="calendar.php?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?><?php echo $hallId ? '&hall_id=' . $hallId : ''; ?>" 
               class="btn btn-secondary">
                Today
            </a>
            <a href="calendar.php?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?><?php echo $hallId ? '&hall_id=' . $hallId : ''; ?>" 
               class="btn btn-secondary">
                Next <i class="icon-chevron-right"></i>
            </a>
        </div>
        
        <a href="bookings/add.php<?php echo $hallId ? '?hall_id=' . $hallId : ''; ?>" class="btn btn-primary">
            <i class="icon-plus"></i> New Booking
        </a>
        
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Halls
        </a>
    </div>
</div>

<!-- Hall Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="form-inline">
            <div class="form-group">
                <label for="hall_id">Filter by Hall:</label>
                <select name="hall_id" id="hall_id" class="form-control ml-2">
                    <option value="">All Halls</option>
                    <?php foreach ($halls as $h): ?>
                    <option value="<?php echo $h['id']; ?>" 
                            <?php echo $hallId == $h['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($h['hall_name'] . ' (' . $h['hall_code'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="month" value="<?php echo $month; ?>">
            <input type="hidden" name="year" value="<?php echo $year; ?>">
            <button type="submit" class="btn btn-primary ml-2">Filter</button>
        </form>
    </div>
</div>

<!-- Calendar -->
<div class="card">
    <div class="card-body">
        <div class="calendar-container">
            <div class="calendar-header">
                <div class="calendar-day-header">Sun</div>
                <div class="calendar-day-header">Mon</div>
                <div class="calendar-day-header">Tue</div>
                <div class="calendar-day-header">Wed</div>
                <div class="calendar-day-header">Thu</div>
                <div class="calendar-day-header">Fri</div>
                <div class="calendar-day-header">Sat</div>
            </div>
            
            <div class="calendar-body">
                <?php
                // Empty cells for days before the first day of the month
                for ($i = 0; $i < $firstDayOfWeek; $i++) {
                    echo '<div class="calendar-day empty"></div>';
                }
                
                // Days of the month
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                    $isToday = $date == date('Y-m-d');
                    $dayBookings = $bookingsByDate[$date] ?? [];
                    
                    echo '<div class="calendar-day' . ($isToday ? ' today' : '') . '">';
                    echo '<div class="day-number">' . $day . '</div>';
                    
                    if (!empty($dayBookings)) {
                        echo '<div class="day-bookings">';
                        foreach ($dayBookings as $booking) {
                            $statusClass = strtolower($booking['booking_status']);
                            $timeRange = date('g:i A', strtotime($booking['start_time'])) . ' - ' . date('g:i A', strtotime($booking['end_time']));
                            
                            echo '<div class="booking-item ' . $statusClass . '" ';
                            echo 'title="' . htmlspecialchars($booking['event_name'] . ' (' . $timeRange . ')') . '" ';
                            echo 'onclick="viewBooking(' . $booking['id'] . ')">';
                            echo '<div class="booking-title">' . htmlspecialchars($booking['event_name'] ?: 'No Event Name') . '</div>';
                            echo '<div class="booking-time">' . $timeRange . '</div>';
                            echo '<div class="booking-hall">' . htmlspecialchars($booking['hall_name']) . '</div>';
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                    
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<!-- Legend -->
<div class="card mt-4">
    <div class="card-body">
        <h5>Legend</h5>
        <div class="legend-items">
            <div class="legend-item">
                <span class="legend-color confirmed"></span>
                <span>Confirmed Bookings</span>
            </div>
            <div class="legend-item">
                <span class="legend-color pending"></span>
                <span>Pending Bookings</span>
            </div>
            <div class="legend-item">
                <span class="legend-color today"></span>
                <span>Today</span>
            </div>
        </div>
    </div>
</div>

<script>
function viewBooking(bookingId) {
    window.location.href = 'bookings/view.php?id=' + bookingId;
}

// Calendar interactions
document.addEventListener('DOMContentLoaded', function() {
    // Add click handlers for empty days to create new bookings
    document.querySelectorAll('.calendar-day.empty').forEach(day => {
        day.addEventListener('click', function() {
            // Don't allow booking on empty days (days from previous/next month)
        });
    });
    
    // Add click handlers for days with no bookings
    document.querySelectorAll('.calendar-day:not(.empty)').forEach(day => {
        if (!day.querySelector('.day-bookings')) {
            day.addEventListener('click', function() {
                const dayNumber = this.querySelector('.day-number').textContent;
                const month = <?php echo $month; ?>;
                const year = <?php echo $year; ?>;
                const date = year + '-' + String(month).padStart(2, '0') + '-' + String(dayNumber).padStart(2, '0');
                
                let url = 'bookings/add.php?date=' + date;
                <?php if ($hallId): ?>
                url += '&hall_id=<?php echo $hallId; ?>';
                <?php endif; ?>
                
                window.location.href = url;
            });
        }
    });
});
</script>

<style>
.calendar-container {
    background: white;
    border-radius: 8px;
    overflow: hidden;
}

.calendar-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.calendar-day-header {
    padding: 15px;
    text-align: center;
    font-weight: 600;
    color: #495057;
    border-right: 1px solid #dee2e6;
}

.calendar-day-header:last-child {
    border-right: none;
}

.calendar-body {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
}

.calendar-day {
    min-height: 120px;
    border-right: 1px solid #dee2e6;
    border-bottom: 1px solid #dee2e6;
    padding: 10px;
    position: relative;
    cursor: pointer;
    transition: background-color 0.2s;
}

.calendar-day:hover {
    background-color: #f8f9fa;
}

.calendar-day.empty {
    background-color: #f8f9fa;
    cursor: default;
}

.calendar-day.today {
    background-color: #e3f2fd;
}

.calendar-day.today .day-number {
    background-color: #2196f3;
    color: white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.day-number {
    font-weight: 600;
    margin-bottom: 5px;
    font-size: 14px;
}

.day-bookings {
    position: absolute;
    top: 35px;
    left: 5px;
    right: 5px;
    bottom: 5px;
    overflow-y: auto;
}

.booking-item {
    background: #007bff;
    color: white;
    padding: 2px 6px;
    margin-bottom: 2px;
    border-radius: 3px;
    font-size: 11px;
    cursor: pointer;
    transition: opacity 0.2s;
}

.booking-item:hover {
    opacity: 0.8;
}

.booking-item.confirmed {
    background: #28a745;
}

.booking-item.pending {
    background: #ffc107;
    color: #333;
}

.booking-title {
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.booking-time {
    font-size: 10px;
    opacity: 0.9;
}

.booking-hall {
    font-size: 10px;
    opacity: 0.8;
}

.legend-items {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.legend-color {
    width: 16px;
    height: 16px;
    border-radius: 3px;
    display: inline-block;
}

.legend-color.confirmed {
    background-color: #28a745;
}

.legend-color.pending {
    background-color: #ffc107;
}

.legend-color.today {
    background-color: #2196f3;
}

.form-inline .form-group {
    margin-right: 15px;
    margin-bottom: 10px;
}

.form-inline .form-group label {
    margin-right: 5px;
    font-weight: 500;
}

@media (max-width: 768px) {
    .calendar-day {
        min-height: 80px;
        padding: 5px;
    }
    
    .day-number {
        font-size: 12px;
    }
    
    .booking-item {
        font-size: 10px;
        padding: 1px 4px;
    }
    
    .calendar-day-header {
        padding: 10px 5px;
        font-size: 12px;
    }
}
</style>

<?php include '../../includes/footer.php'; ?>
