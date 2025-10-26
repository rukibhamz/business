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

// Get parameters
$hallId = (int)($_GET['hall_id'] ?? 0);
$date = $_GET['date'] ?? '';

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
            'start_date' => trim($_POST['start_date'] ?? ''),
            'start_time' => trim($_POST['start_time'] ?? ''),
            'end_date' => trim($_POST['end_date'] ?? ''),
            'end_time' => trim($_POST['end_time'] ?? ''),
            'attendee_count' => (int)($_POST['attendee_count'] ?? 0),
            'special_requirements' => trim($_POST['special_requirements'] ?? ''),
            'payment_type' => trim($_POST['payment_type'] ?? 'Full Payment'),
            'amount_paid' => (float)($_POST['amount_paid'] ?? 0),
            'booking_source' => 'Admin'
        ];

        // Validation
        if ($bookingData['hall_id'] <= 0) {
            $errors[] = 'Please select a hall.';
        }

        if (empty($bookingData['event_name'])) {
            $errors[] = 'Event name is required.';
        }

        if (empty($bookingData['start_date'])) {
            $errors[] = 'Start date is required.';
        }

        if (empty($bookingData['start_time'])) {
            $errors[] = 'Start time is required.';
        }

        if (empty($bookingData['end_date'])) {
            $errors[] = 'End date is required.';
        }

        if (empty($bookingData['end_time'])) {
            $errors[] = 'End time is required.';
        }

        // Validate date/time logic
        if (empty($errors)) {
            $startDateTime = new DateTime($bookingData['start_date'] . ' ' . $bookingData['start_time']);
            $endDateTime = new DateTime($bookingData['end_date'] . ' ' . $bookingData['end_time']);

            if ($endDateTime <= $startDateTime) {
                $errors[] = 'End date/time must be after start date/time.';
            }

            // Calculate duration
            $duration = $endDateTime->diff($startDateTime);
            $durationHours = $duration->days * 24 + $duration->h + ($duration->i / 60);
            $bookingData['duration_hours'] = $durationHours;

            if ($durationHours <= 0) {
                $errors[] = 'Booking duration must be greater than 0.';
            }
        }

        // Check hall availability
        if (empty($errors)) {
            if (!checkHallAvailability($bookingData['hall_id'], $bookingData['start_date'], $bookingData['start_time'], $bookingData['end_date'], $bookingData['end_time'])) {
                $errors[] = 'Hall is not available for the selected date and time.';
            }
        }

        // Calculate pricing
        if (empty($errors)) {
            $hallRental = calculateHallRental($bookingData['hall_id'], $bookingData['start_date'], $bookingData['start_time'], $bookingData['end_date'], $bookingData['end_time']);
            $serviceFee = getHallSetting('service_fee_percentage', 2.5);
            $taxRate = getHallSetting('tax_rate', 7.5);
            
            $totals = calculateBookingTotals($hallRental, $serviceFee, $taxRate);
            
            $bookingData['subtotal'] = $totals['subtotal'];
            $bookingData['service_fee'] = $totals['service_fee'];
            $bookingData['tax_amount'] = $totals['tax_amount'];
            $bookingData['total_amount'] = $totals['total_amount'];
            $bookingData['balance_due'] = $totals['total_amount'] - $bookingData['amount_paid'];
            
            // Set payment status
            if ($bookingData['amount_paid'] >= $totals['total_amount']) {
                $bookingData['payment_status'] = 'Paid';
            } elseif ($bookingData['amount_paid'] > 0) {
                $bookingData['payment_status'] = 'Partial';
            } else {
                $bookingData['payment_status'] = 'Pending';
            }
            
            $bookingData['booking_status'] = 'Pending';
            $bookingData['created_by'] = $_SESSION['user_id'];
        }

        // Create booking if no errors
        if (empty($errors)) {
            try {
                $bookingId = createHallBooking($bookingData);
                
                // Log activity
                logActivity("Hall booking created: {$bookingData['event_name']}", $bookingId, 'hall_booking');
                
                $success = true;
                
                // Redirect to booking view page
                header("Location: view.php?id={$bookingId}&success=1");
                exit;
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Get halls for dropdown
$halls = $conn->query("
    SELECT id, hall_name, hall_code, capacity, hourly_rate, daily_rate, weekly_rate, monthly_rate, currency
    FROM " . DB_PREFIX . "halls 
    WHERE status = 'Available' AND enable_booking = 1
    ORDER BY hall_name
")->fetch_all(MYSQLI_ASSOC);

// Get customers for dropdown
$customers = $conn->query("
    SELECT id, first_name, last_name, company_name, email, phone
    FROM " . DB_PREFIX . "customers 
    WHERE status = 'active'
    ORDER BY company_name, first_name, last_name
")->fetch_all(MYSQLI_ASSOC);

// Set default values
if ($hallId > 0) {
    $bookingData['hall_id'] = $hallId;
}
if (!empty($date)) {
    $bookingData['start_date'] = $date;
    $bookingData['end_date'] = $date;
}

// Set page title
$pageTitle = 'Create Hall Booking';

include '../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Create Hall Booking</h1>
        <p>Book a hall for an event</p>
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
        <!-- Booking Details -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3>Booking Details</h3>
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
                                            <?php echo ($bookingData['hall_id'] ?? '') == $hall['id'] ? 'selected' : ''; ?>
                                            data-hourly-rate="<?php echo $hall['hourly_rate']; ?>"
                                            data-daily-rate="<?php echo $hall['daily_rate']; ?>"
                                            data-weekly-rate="<?php echo $hall['weekly_rate']; ?>"
                                            data-monthly-rate="<?php echo $hall['monthly_rate']; ?>"
                                            data-currency="<?php echo $hall['currency']; ?>">
                                        <?php echo htmlspecialchars($hall['hall_name'] . ' (' . $hall['hall_code'] . ') - Capacity: ' . $hall['capacity']); ?>
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
                                    <option value="">Select Customer (Optional)</option>
                                    <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>" 
                                            <?php echo ($bookingData['customer_id'] ?? '') == $customer['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(($customer['company_name'] ?: $customer['first_name'] . ' ' . $customer['last_name']) . ' (' . $customer['email'] . ')'); ?>
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
                                    <option value="Wedding" <?php echo ($bookingData['event_type'] ?? '') == 'Wedding' ? 'selected' : ''; ?>>Wedding</option>
                                    <option value="Meeting" <?php echo ($bookingData['event_type'] ?? '') == 'Meeting' ? 'selected' : ''; ?>>Meeting</option>
                                    <option value="Banquet" <?php echo ($bookingData['event_type'] ?? '') == 'Banquet' ? 'selected' : ''; ?>>Banquet</option>
                                    <option value="Exhibition" <?php echo ($bookingData['event_type'] ?? '') == 'Exhibition' ? 'selected' : ''; ?>>Exhibition</option>
                                    <option value="Training" <?php echo ($bookingData['event_type'] ?? '') == 'Training' ? 'selected' : ''; ?>>Training</option>
                                    <option value="Party" <?php echo ($bookingData['event_type'] ?? '') == 'Party' ? 'selected' : ''; ?>>Party</option>
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
                        <textarea id="special_requirements" name="special_requirements" class="form-control" rows="3" 
                                  placeholder="Any special requirements, equipment needed, or notes"><?php echo htmlspecialchars($bookingData['special_requirements'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pricing & Payment -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3>Pricing & Payment</h3>
                </div>
                <div class="card-body">
                    <div id="pricing-summary">
                        <div class="pricing-item">
                            <label>Hall Rental:</label>
                            <span id="hall-rental">₦0.00</span>
                        </div>
                        <div class="pricing-item">
                            <label>Service Fee:</label>
                            <span id="service-fee">₦0.00</span>
                        </div>
                        <div class="pricing-item">
                            <label>Tax:</label>
                            <span id="tax-amount">₦0.00</span>
                        </div>
                        <hr>
                        <div class="pricing-item total">
                            <label>Total Amount:</label>
                            <span id="total-amount">₦0.00</span>
                        </div>
                    </div>

                    <div class="form-group mt-3">
                        <label for="amount_paid">Amount Paid</label>
                        <input type="number" id="amount_paid" name="amount_paid" class="form-control" 
                               value="<?php echo htmlspecialchars($bookingData['amount_paid'] ?? '0'); ?>" 
                               step="0.01" min="0">
                        <small class="form-text text-muted">Enter amount paid upfront</small>
                    </div>

                    <div class="pricing-item">
                        <label>Balance Due:</label>
                        <span id="balance-due">₦0.00</span>
                    </div>
                </div>
            </div>

            <!-- Hall Information -->
            <div class="card">
                <div class="card-header">
                    <h3>Hall Information</h3>
                </div>
                <div class="card-body" id="hall-info">
                    <p class="text-muted">Select a hall to view details</p>
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

// Pricing calculation
document.addEventListener('DOMContentLoaded', function() {
    const hallSelect = document.getElementById('hall_id');
    const startDate = document.getElementById('start_date');
    const startTime = document.getElementById('start_time');
    const endDate = document.getElementById('end_date');
    const endTime = document.getElementById('end_time');
    const amountPaid = document.getElementById('amount_paid');

    function calculatePricing() {
        const hallId = hallSelect.value;
        const startDateTime = startDate.value + ' ' + startTime.value;
        const endDateTime = endDate.value + ' ' + endTime.value;

        if (!hallId || !startDateTime || !endDateTime) {
            updatePricingDisplay(0, 0, 0, 0);
            return;
        }

        // Calculate duration
        const start = new Date(startDateTime);
        const end = new Date(endDateTime);
        const durationMs = end - start;
        const durationHours = durationMs / (1000 * 60 * 60);

        if (durationHours <= 0) {
            updatePricingDisplay(0, 0, 0, 0);
            return;
        }

        // Get hall pricing
        const selectedOption = hallSelect.options[hallSelect.selectedIndex];
        const hourlyRate = parseFloat(selectedOption.dataset.hourlyRate) || 0;
        const dailyRate = parseFloat(selectedOption.dataset.dailyRate) || 0;
        const weeklyRate = parseFloat(selectedOption.dataset.weeklyRate) || 0;
        const monthlyRate = parseFloat(selectedOption.dataset.monthlyRate) || 0;
        const currency = selectedOption.dataset.currency || 'NGN';

        // Calculate hall rental based on duration
        let hallRental = 0;
        const days = Math.floor(durationHours / 24);
        const remainingHours = durationHours % 24;

        if (days >= 30) {
            hallRental = monthlyRate * Math.floor(days / 30) + (dailyRate * (days % 30)) + (hourlyRate * remainingHours);
        } else if (days >= 7) {
            hallRental = weeklyRate * Math.floor(days / 7) + (dailyRate * (days % 7)) + (hourlyRate * remainingHours);
        } else if (days >= 1) {
            hallRental = dailyRate * days + (hourlyRate * remainingHours);
        } else {
            hallRental = hourlyRate * durationHours;
        }

        // Calculate service fee and tax
        const serviceFeeRate = 2.5; // Default service fee percentage
        const taxRate = 7.5; // Default tax rate

        const serviceFee = hallRental * (serviceFeeRate / 100);
        const subtotal = hallRental + serviceFee;
        const taxAmount = subtotal * (taxRate / 100);
        const totalAmount = subtotal + taxAmount;

        updatePricingDisplay(hallRental, serviceFee, taxAmount, totalAmount);
        updateHallInfo(selectedOption);
    }

    function updatePricingDisplay(hallRental, serviceFee, taxAmount, totalAmount) {
        document.getElementById('hall-rental').textContent = formatCurrency(hallRental);
        document.getElementById('service-fee').textContent = formatCurrency(serviceFee);
        document.getElementById('tax-amount').textContent = formatCurrency(taxAmount);
        document.getElementById('total-amount').textContent = formatCurrency(totalAmount);

        const amountPaid = parseFloat(document.getElementById('amount_paid').value) || 0;
        const balanceDue = totalAmount - amountPaid;
        document.getElementById('balance-due').textContent = formatCurrency(balanceDue);
    }

    function updateHallInfo(selectedOption) {
        const hallInfo = document.getElementById('hall-info');
        if (selectedOption.value) {
            const hallText = selectedOption.textContent;
            const capacity = hallText.match(/Capacity: (\d+)/);
            const hourlyRate = parseFloat(selectedOption.dataset.hourlyRate) || 0;
            const dailyRate = parseFloat(selectedOption.dataset.dailyRate) || 0;
            const currency = selectedOption.dataset.currency || 'NGN';

            let infoHtml = '<div class="hall-details">';
            if (capacity) {
                infoHtml += '<p><strong>Capacity:</strong> ' + capacity[1] + ' people</p>';
            }
            if (hourlyRate > 0) {
                infoHtml += '<p><strong>Hourly Rate:</strong> ' + formatCurrency(hourlyRate) + '</p>';
            }
            if (dailyRate > 0) {
                infoHtml += '<p><strong>Daily Rate:</strong> ' + formatCurrency(dailyRate) + '</p>';
            }
            infoHtml += '</div>';

            hallInfo.innerHTML = infoHtml;
        } else {
            hallInfo.innerHTML = '<p class="text-muted">Select a hall to view details</p>';
        }
    }

    function formatCurrency(amount) {
        return '₦' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // Event listeners
    hallSelect.addEventListener('change', calculatePricing);
    startDate.addEventListener('change', calculatePricing);
    startTime.addEventListener('change', calculatePricing);
    endDate.addEventListener('change', calculatePricing);
    endTime.addEventListener('change', calculatePricing);
    amountPaid.addEventListener('input', calculatePricing);

    // Auto-set end date to start date if not set
    startDate.addEventListener('change', function() {
        if (!endDate.value) {
            endDate.value = startDate.value;
        }
    });

    // Initial calculation
    calculatePricing();
});
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

.pricing-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.pricing-item.total {
    font-weight: bold;
    font-size: 1.1em;
    border-top: 1px solid #dee2e6;
    padding-top: 10px;
    margin-top: 10px;
}

.hall-details p {
    margin-bottom: 8px;
}

.hall-details p:last-child {
    margin-bottom: 0;
}
</style>

<?php include '../../includes/footer.php'; ?>
