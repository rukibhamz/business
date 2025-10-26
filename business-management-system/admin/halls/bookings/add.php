<?php
/**
 * Business Management System - Add Hall Booking
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
requirePermission('halls.bookings');

// Get database connection
$conn = getDB();

// Initialize variables
$errors = [];
$success = false;
$bookingData = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        // Sanitize and validate input
        $bookingData = [
            'hall_id' => (int)($_POST['hall_id'] ?? 0),
            'customer_id' => (int)($_POST['customer_id'] ?? 0),
            'event_name' => trim($_POST['event_name'] ?? ''),
            'event_type' => trim($_POST['event_type'] ?? ''),
            'start_date' => $_POST['start_date'] ?? '',
            'start_time' => $_POST['start_time'] ?? '',
            'end_date' => $_POST['end_date'] ?? '',
            'end_time' => $_POST['end_time'] ?? '',
            'attendee_count' => (int)($_POST['attendee_count'] ?? 0),
            'payment_type' => $_POST['payment_type'] ?? 'Full Payment',
            'special_requirements' => trim($_POST['special_requirements'] ?? ''),
            'booking_source' => 'Admin',
            'created_by' => $_SESSION['user_id']
        ];

        // Validation
        if ($bookingData['hall_id'] <= 0) {
            $errors[] = 'Please select a hall.';
        }

        if (empty($bookingData['event_name'])) {
            $errors[] = 'Event name is required.';
        }

        if (empty($bookingData['start_date']) || empty($bookingData['start_time'])) {
            $errors[] = 'Start date and time are required.';
        }

        if (empty($bookingData['end_date']) || empty($bookingData['end_time'])) {
            $errors[] = 'End date and time are required.';
        }

        if ($bookingData['start_date'] && $bookingData['end_date'] && $bookingData['start_time'] && $bookingData['end_time']) {
            $startDateTime = $bookingData['start_date'] . ' ' . $bookingData['start_time'];
            $endDateTime = $bookingData['end_date'] . ' ' . $bookingData['end_time'];
            
            if (strtotime($endDateTime) <= strtotime($startDateTime)) {
                $errors[] = 'End date and time must be after start date and time.';
            }
        }

        // Check availability
        if (empty($errors)) {
            $isAvailable = checkHallAvailability(
                $bookingData['hall_id'],
                $bookingData['start_date'],
                $bookingData['start_time'],
                $bookingData['end_date'],
                $bookingData['end_time']
            );
            
            if (!$isAvailable) {
                $errors[] = 'Hall is not available for the selected date and time.';
            }
        }

        // Calculate duration and pricing
        if (empty($errors)) {
            $startDateTime = new DateTime($bookingData['start_date'] . ' ' . $bookingData['start_time']);
            $endDateTime = new DateTime($bookingData['end_date'] . ' ' . $bookingData['end_time']);
            $duration = $endDateTime->diff($startDateTime);
            $bookingData['duration_hours'] = $duration->days * 24 + $duration->h + ($duration->i / 60);
            
            $bookingData['hall_rental'] = calculateHallRental(
                $bookingData['hall_id'],
                $bookingData['start_date'],
                $bookingData['start_time'],
                $bookingData['end_date'],
                $bookingData['end_time']
            );
            
            $bookingData['service_fee'] = 0;
            $bookingData['tax_rate'] = (float)getHallSetting('tax_rate', 7.5);
        }

        // Create booking if no errors
        if (empty($errors)) {
            try {
                $bookingId = createHallBooking($bookingData);
                
                if ($bookingId) {
                    $success = true;
                    // Redirect to booking view page
                    header("Location: view.php?id={$bookingId}&success=1");
                    exit;
                } else {
                    $errors[] = 'Failed to create booking. Please try again.';
                }
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Get halls for dropdown
$halls = $conn->query("
    SELECT id, hall_name, hall_code, capacity 
    FROM " . DB_PREFIX . "halls 
    WHERE status = 'Available' AND enable_booking = 1 
    ORDER BY hall_name
")->fetch_all(MYSQLI_ASSOC);

// Get customers for dropdown
$customers = $conn->query("
    SELECT id, CONCAT(first_name, ' ', last_name) as full_name, company_name, email 
    FROM " . DB_PREFIX . "customers 
    ORDER BY first_name, last_name
")->fetch_all(MYSQLI_ASSOC);

// Set page title
$pageTitle = 'Add Hall Booking';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Add Hall Booking</h1>
        <p>Create a new hall booking</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Bookings
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <h4>Please correct the following errors:</h4>
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
        <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="POST" class="needs-validation" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    
    <div class="row">
        <!-- Booking Information -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3>Booking Information</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="hall_id">Hall <span class="text-danger">*</span></label>
                                <select id="hall_id" name="hall_id" class="form-control" required>
                                    <option value="">Select Hall</option>
                                    <?php foreach ($halls as $hall): ?>
                                    <option value="<?php echo $hall['id']; ?>" 
                                            <?php echo ($bookingData['hall_id'] ?? '') == $hall['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($hall['hall_name'] . ' (' . $hall['hall_code'] . ')'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">
                                    Please select a hall.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="customer_id">Customer</label>
                                <select id="customer_id" name="customer_id" class="form-control">
                                    <option value="">Select Customer</option>
                                    <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>" 
                                            <?php echo ($bookingData['customer_id'] ?? '') == $customer['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer['full_name'] . ($customer['company_name'] ? ' (' . $customer['company_name'] . ')' : '')); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="event_name">Event Name <span class="text-danger">*</span></label>
                                <input type="text" id="event_name" name="event_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($bookingData['event_name'] ?? ''); ?>" 
                                       required>
                                <div class="invalid-feedback">
                                    Please provide an event name.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="event_type">Event Type</label>
                                <select id="event_type" name="event_type" class="form-control">
                                    <option value="">Select Event Type</option>
                                    <option value="Conference" <?php echo ($bookingData['event_type'] ?? '') == 'Conference' ? 'selected' : ''; ?>>Conference</option>
                                    <option value="Meeting" <?php echo ($bookingData['event_type'] ?? '') == 'Meeting' ? 'selected' : ''; ?>>Meeting</option>
                                    <option value="Wedding" <?php echo ($bookingData['event_type'] ?? '') == 'Wedding' ? 'selected' : ''; ?>>Wedding</option>
                                    <option value="Birthday Party" <?php echo ($bookingData['event_type'] ?? '') == 'Birthday Party' ? 'selected' : ''; ?>>Birthday Party</option>
                                    <option value="Corporate Event" <?php echo ($bookingData['event_type'] ?? '') == 'Corporate Event' ? 'selected' : ''; ?>>Corporate Event</option>
                                    <option value="Training" <?php echo ($bookingData['event_type'] ?? '') == 'Training' ? 'selected' : ''; ?>>Training</option>
                                    <option value="Other" <?php echo ($bookingData['event_type'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="start_date">Start Date <span class="text-danger">*</span></label>
                                <input type="date" id="start_date" name="start_date" class="form-control" 
                                       value="<?php echo htmlspecialchars($bookingData['start_date'] ?? ''); ?>" 
                                       required>
                                <div class="invalid-feedback">
                                    Please provide a start date.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="start_time">Start Time <span class="text-danger">*</span></label>
                                <input type="time" id="start_time" name="start_time" class="form-control" 
                                       value="<?php echo htmlspecialchars($bookingData['start_time'] ?? ''); ?>" 
                                       required>
                                <div class="invalid-feedback">
                                    Please provide a start time.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="end_date">End Date <span class="text-danger">*</span></label>
                                <input type="date" id="end_date" name="end_date" class="form-control" 
                                       value="<?php echo htmlspecialchars($bookingData['end_date'] ?? ''); ?>" 
                                       required>
                                <div class="invalid-feedback">
                                    Please provide an end date.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="end_time">End Time <span class="text-danger">*</span></label>
                                <input type="time" id="end_time" name="end_time" class="form-control" 
                                       value="<?php echo htmlspecialchars($bookingData['end_time'] ?? ''); ?>" 
                                       required>
                                <div class="invalid-feedback">
                                    Please provide an end time.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="attendee_count">Expected Attendees</label>
                                <input type="number" id="attendee_count" name="attendee_count" class="form-control" 
                                       value="<?php echo htmlspecialchars($bookingData['attendee_count'] ?? ''); ?>" 
                                       min="1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="payment_type">Payment Type</label>
                                <select id="payment_type" name="payment_type" class="form-control">
                                    <option value="Full Payment" <?php echo ($bookingData['payment_type'] ?? 'Full Payment') == 'Full Payment' ? 'selected' : ''; ?>>Full Payment</option>
                                    <option value="Partial Payment" <?php echo ($bookingData['payment_type'] ?? '') == 'Partial Payment' ? 'selected' : ''; ?>>Partial Payment</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="special_requirements">Special Requirements</label>
                        <textarea id="special_requirements" name="special_requirements" class="form-control" rows="4" 
                                  placeholder="Any special requirements or notes..."><?php echo htmlspecialchars($bookingData['special_requirements'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Booking Summary -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3>Booking Summary</h3>
                </div>
                <div class="card-body">
                    <div id="booking-summary">
                        <p class="text-muted">Select a hall and date/time to see pricing details.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <i class="icon-save"></i> Create Booking
        </button>
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-times"></i> Cancel
        </a>
    </div>
</form>

<script>
// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Auto-set end date when start date changes
document.getElementById('start_date').addEventListener('change', function() {
    const endDateInput = document.getElementById('end_date');
    if (!endDateInput.value) {
        endDateInput.value = this.value;
    }
});

// Auto-set end time when start time changes
document.getElementById('start_time').addEventListener('change', function() {
    const endTimeInput = document.getElementById('end_time');
    if (!endTimeInput.value) {
        const startTime = new Date('2000-01-01 ' + this.value);
        const endTime = new Date(startTime.getTime() + 2 * 60 * 60 * 1000); // Add 2 hours
        endTimeInput.value = endTime.toTimeString().slice(0, 5);
    }
});

// Set minimum date to today
const today = new Date().toISOString().split('T')[0];
document.getElementById('start_date').setAttribute('min', today);
document.getElementById('end_date').setAttribute('min', today);
</script>

<style>
.form-actions {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #dee2e6;
}

.form-actions .btn {
    margin-right: 10px;
}

.card {
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    font-weight: 500;
    margin-bottom: 5px;
}

.text-danger {
    color: #dc3545 !important;
}

.invalid-feedback {
    display: none;
    width: 100%;
    margin-top: 0.25rem;
    font-size: 0.875em;
    color: #dc3545;
}

.was-validated .form-control:invalid ~ .invalid-feedback {
    display: block;
}
</style>

<?php include '../../includes/footer.php'; ?>

