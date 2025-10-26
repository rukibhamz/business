<?php
/**
 * Business Management System - Add Event
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
requirePermission('events.create');

// Get database connection
$conn = getDB();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken($_POST['csrf_token'] ?? '');
    
    $errors = [];
    $success = false;
    
    // Get form data
    $eventCode = generateEventCode();
    $eventName = sanitizeInput($_POST['event_name'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $description = sanitizeInput($_POST['description'] ?? '');
    $eventType = sanitizeInput($_POST['event_type'] ?? 'Single Date');
    $startDate = sanitizeInput($_POST['start_date'] ?? '');
    $startTime = sanitizeInput($_POST['start_time'] ?? '');
    $endDate = sanitizeInput($_POST['end_date'] ?? '');
    $endTime = sanitizeInput($_POST['end_time'] ?? '');
    $venueName = sanitizeInput($_POST['venue_name'] ?? '');
    $venueAddress = sanitizeInput($_POST['venue_address'] ?? '');
    $capacity = !empty($_POST['capacity']) ? (int)$_POST['capacity'] : null;
    $organizerName = sanitizeInput($_POST['organizer_name'] ?? '');
    $organizerEmail = sanitizeInput($_POST['organizer_email'] ?? '');
    $organizerPhone = sanitizeInput($_POST['organizer_phone'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'Draft');
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $enableBooking = isset($_POST['enable_booking']) ? 1 : 0;
    $bookingStarts = !empty($_POST['booking_starts']) ? sanitizeInput($_POST['booking_starts']) : null;
    $bookingEnds = !empty($_POST['booking_ends']) ? sanitizeInput($_POST['booking_ends']) : null;
    $termsConditions = sanitizeInput($_POST['terms_conditions'] ?? '');
    $cancellationPolicy = sanitizeInput($_POST['cancellation_policy'] ?? '');
    
    // Validation
    if (empty($eventName)) {
        $errors[] = 'Event name is required';
    }
    
    if ($categoryId <= 0) {
        $errors[] = 'Please select a category';
    }
    
    if (empty($startDate)) {
        $errors[] = 'Start date is required';
    }
    
    if (empty($startTime)) {
        $errors[] = 'Start time is required';
    }
    
    if (empty($endDate)) {
        $errors[] = 'End date is required';
    }
    
    if (empty($endTime)) {
        $errors[] = 'End time is required';
    }
    
    if ($endDate < $startDate) {
        $errors[] = 'End date must be after start date';
    }
    
    if ($endDate == $startDate && $endTime <= $startTime) {
        $errors[] = 'End time must be after start time';
    }
    
    // Handle featured image upload
    $featuredImage = null;
    if (!empty($_FILES['featured_image']['name'])) {
        $uploadDir = '../../../../uploads/events/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExt = pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION);
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array(strtolower($fileExt), $allowedExts)) {
            $fileName = uniqid() . '.' . $fileExt;
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $filePath)) {
                $featuredImage = 'uploads/events/' . $fileName;
            }
        } else {
            $errors[] = 'Invalid image format. Only JPG, PNG, and GIF files are allowed.';
        }
    }
    
    // Handle gallery images
    $galleryImages = [];
    if (!empty($_FILES['gallery_images']['name'][0])) {
        $uploadDir = '../../../../uploads/events/gallery/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        foreach ($_FILES['gallery_images']['name'] as $key => $name) {
            if (!empty($name)) {
                $fileExt = pathinfo($name, PATHINFO_EXTENSION);
                $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array(strtolower($fileExt), $allowedExts)) {
                    $fileName = uniqid() . '_' . $key . '.' . $fileExt;
                    $filePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$key], $filePath)) {
                        $galleryImages[] = 'uploads/events/gallery/' . $fileName;
                    }
                }
            }
        }
    }
    
    // If no errors, create event
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Insert event
            $stmt = $conn->prepare("
                INSERT INTO " . DB_PREFIX . "events 
                (event_code, event_name, category_id, description, event_type, 
                 start_date, start_time, end_date, end_time, venue_name, venue_address, 
                 capacity, featured_image, gallery_images, organizer_name, organizer_email, 
                 organizer_phone, status, is_featured, enable_booking, booking_starts, 
                 booking_ends, terms_conditions, cancellation_policy, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $userId = $_SESSION['user_id'];
            $galleryJson = !empty($galleryImages) ? json_encode($galleryImages) : null;
            
            $stmt->bind_param('ssissssssssisssssssssssssi',
                $eventCode, $eventName, $categoryId, $description, $eventType,
                $startDate, $startTime, $endDate, $endTime, $venueName, $venueAddress,
                $capacity, $featuredImage, $galleryJson, $organizerName, $organizerEmail,
                $organizerPhone, $status, $isFeatured, $enableBooking, $bookingStarts,
                $bookingEnds, $termsConditions, $cancellationPolicy, $userId
            );
            $stmt->execute();
            $eventId = $conn->getConnection()->lastInsertId();
            
            // Handle ticket types if provided
            if (isset($_POST['ticket_types']) && is_array($_POST['ticket_types'])) {
                foreach ($_POST['ticket_types'] as $ticketData) {
                    if (!empty($ticketData['ticket_name']) && !empty($ticketData['price']) && !empty($ticketData['quantity'])) {
                        $stmt = $conn->prepare("
                            INSERT INTO " . DB_PREFIX . "event_tickets 
                            (event_id, ticket_name, ticket_description, price, quantity_available, 
                             min_purchase, max_purchase, sale_starts, sale_ends, is_active, sort_order) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        $ticketName = sanitizeInput($ticketData['ticket_name']);
                        $ticketDesc = sanitizeInput($ticketData['ticket_description'] ?? '');
                        $price = (float)$ticketData['price'];
                        $quantity = (int)$ticketData['quantity'];
                        $minPurchase = !empty($ticketData['min_purchase']) ? (int)$ticketData['min_purchase'] : 1;
                        $maxPurchase = !empty($ticketData['max_purchase']) ? (int)$ticketData['max_purchase'] : null;
                        $saleStarts = !empty($ticketData['sale_starts']) ? sanitizeInput($ticketData['sale_starts']) : null;
                        $saleEnds = !empty($ticketData['sale_ends']) ? sanitizeInput($ticketData['sale_ends']) : null;
                        $isActive = isset($ticketData['is_active']) ? 1 : 0;
                        $sortOrder = (int)($ticketData['sort_order'] ?? 0);
                        
                        $stmt->bind_param('issdiisssii',
                            $eventId, $ticketName, $ticketDesc, $price, $quantity,
                            $minPurchase, $maxPurchase, $saleStarts, $saleEnds, $isActive, $sortOrder
                        );
                        $stmt->execute();
                    }
                }
            }
            
            // Log activity
            logActivity('events.create', "Event created: {$eventName}", [
                'event_id' => $eventId,
                'event_code' => $eventCode,
                'event_name' => $eventName,
                'category_id' => $categoryId
            ]);
            
            $conn->commit();
            $success = true;
            $_SESSION['success'] = 'Event created successfully!';
            
            header('Location: view.php?id=' . $eventId);
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Error creating event: ' . $e->getMessage();
        }
    }
}

// Get event categories for dropdown
$categories = $conn->query("
    SELECT id, category_name 
    FROM " . DB_PREFIX . "event_categories 
    WHERE is_active = 1 
    ORDER BY category_name
")->fetch_all(MYSQLI_ASSOC);

// Set page title
$pageTitle = 'Add Event';

include '../../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Add Event</h1>
        <p>Create a new event</p>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-secondary">
            <i class="icon-arrow-left"></i> Back to Events
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <h4>Please correct the following errors:</h4>
    <ul>
        <?php foreach ($errors as $error): ?>
        <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>Event Information</h3>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="event-form">
                    <?php csrfField(); ?>
                    
                    <!-- Basic Information -->
                    <div class="form-section">
                        <h4>Basic Information</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Event Code</label>
                                    <input type="text" name="event_code" value="<?php echo generateEventCode(); ?>" readonly class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label class="required">Event Name</label>
                                    <input type="text" name="event_name" required class="form-control" maxlength="200">
                                </div>
                                
                                <div class="form-group">
                                    <label class="required">Category</label>
                                    <select name="category_id" required class="form-control">
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['category_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Event Type</label>
                                    <select name="event_type" class="form-control">
                                        <option value="Single Date">Single Date</option>
                                        <option value="Multiple Dates">Multiple Dates</option>
                                        <option value="Recurring">Recurring</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Status</label>
                                    <div class="form-check">
                                        <input type="radio" name="status" value="Draft" checked class="form-check-input">
                                        <label class="form-check-label">Draft</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" name="status" value="Published" class="form-check-input">
                                        <label class="form-check-label">Published</label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="form-check">
                                        <input type="checkbox" name="is_featured" value="1" class="form-check-input">
                                        <label class="form-check-label">Featured Event</label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="form-check">
                                        <input type="checkbox" name="enable_booking" value="1" checked class="form-check-input">
                                        <label class="form-check-label">Enable Online Booking</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" rows="5" class="form-control" placeholder="Detailed event description"></textarea>
                        </div>
                    </div>
                    
                    <!-- Date & Venue -->
                    <div class="form-section">
                        <h4>Date & Venue</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="required">Start Date</label>
                                    <input type="date" name="start_date" required class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label class="required">Start Time</label>
                                    <input type="time" name="start_time" required class="form-control">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="required">End Date</label>
                                    <input type="date" name="end_date" required class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label class="required">End Time</label>
                                    <input type="time" name="end_time" required class="form-control">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Venue Name</label>
                                    <input type="text" name="venue_name" class="form-control" maxlength="200">
                                </div>
                                
                                <div class="form-group">
                                    <label>Capacity</label>
                                    <input type="number" name="capacity" min="1" class="form-control">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Venue Address</label>
                                    <textarea name="venue_address" rows="3" class="form-control"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Images -->
                    <div class="form-section">
                        <h4>Images</h4>
                        <div class="form-group">
                            <label class="required">Featured Image</label>
                            <input type="file" name="featured_image" accept="image/*" required class="form-control">
                            <small class="form-text text-muted">Main event image (JPG, PNG, GIF - Max 5MB)</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Gallery Images</label>
                            <input type="file" name="gallery_images[]" accept="image/*" multiple class="form-control">
                            <small class="form-text text-muted">Additional images for gallery (Optional)</small>
                        </div>
                    </div>
                    
                    <!-- Organizer Information -->
                    <div class="form-section">
                        <h4>Organizer Information</h4>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Organizer Name</label>
                                    <input type="text" name="organizer_name" class="form-control" maxlength="200">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Organizer Email</label>
                                    <input type="email" name="organizer_email" class="form-control" maxlength="100">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Organizer Phone</label>
                                    <input type="tel" name="organizer_phone" class="form-control" maxlength="20">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Booking Settings -->
                    <div class="form-section">
                        <h4>Booking Settings</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Booking Opens</label>
                                    <input type="datetime-local" name="booking_starts" class="form-control">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Booking Closes</label>
                                    <input type="datetime-local" name="booking_ends" class="form-control">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Terms & Conditions</label>
                            <textarea name="terms_conditions" rows="4" class="form-control" placeholder="Event terms and conditions"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Cancellation Policy</label>
                            <textarea name="cancellation_policy" rows="4" class="form-control" placeholder="Cancellation and refund policy"></textarea>
                        </div>
                    </div>
                    
                    <!-- Ticket Types -->
                    <div class="form-section">
                        <h4>Ticket Types</h4>
                        <div id="ticket-types-container">
                            <!-- Ticket types will be added here by JavaScript -->
                        </div>
                        <button type="button" id="add-ticket-type" class="btn btn-outline-primary">
                            <i class="icon-plus"></i> Add Ticket Type
                        </button>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="action" value="draft" class="btn btn-secondary">
                            <i class="icon-save"></i> Save as Draft
                        </button>
                        <button type="submit" name="action" value="publish" class="btn btn-primary">
                            <i class="icon-check"></i> Save & Publish
                        </button>
                        <a href="index.php" class="btn btn-link">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3>Event Guidelines</h3>
            </div>
            <div class="card-body">
                <div class="guidelines">
                    <h4>Required Information</h4>
                    <ul>
                        <li>Event name</li>
                        <li>Category</li>
                        <li>Start and end date/time</li>
                        <li>Featured image</li>
                    </ul>
                    
                    <h4>Optional Information</h4>
                    <ul>
                        <li>Venue details</li>
                        <li>Organizer information</li>
                        <li>Gallery images</li>
                        <li>Terms and conditions</li>
                        <li>Ticket types</li>
                    </ul>
                    
                    <h4>Publishing</h4>
                    <p>Events can be saved as drafts or published immediately. Published events are visible to customers and allow online bookings.</p>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>Quick Tips</h3>
            </div>
            <div class="card-body">
                <ul class="tips-list">
                    <li>Use descriptive event names</li>
                    <li>Upload high-quality images</li>
                    <li>Set appropriate booking windows</li>
                    <li>Define clear terms and conditions</li>
                    <li>Create multiple ticket types for flexibility</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
let ticketTypeCount = 0;

function addTicketType() {
    ticketTypeCount++;
    const container = document.getElementById('ticket-types-container');
    
    const ticketHtml = `
        <div class="ticket-type-item" data-ticket="${ticketTypeCount}">
            <div class="card mb-3">
                <div class="card-header">
                    <h5>Ticket Type ${ticketTypeCount}</h5>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-ticket" title="Remove Ticket Type">
                        <i class="icon-trash"></i>
                    </button>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Ticket Name</label>
                                <input type="text" name="ticket_types[${ticketTypeCount}][ticket_name]" class="form-control" placeholder="e.g., VIP, Regular, Student">
                            </div>
                            
                            <div class="form-group">
                                <label>Price</label>
                                <input type="number" name="ticket_types[${ticketTypeCount}][price]" step="0.01" min="0" class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label>Quantity Available</label>
                                <input type="number" name="ticket_types[${ticketTypeCount}][quantity]" min="1" class="form-control">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="ticket_types[${ticketTypeCount}][ticket_description]" rows="3" class="form-control"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Min Purchase</label>
                                <input type="number" name="ticket_types[${ticketTypeCount}][min_purchase]" min="1" value="1" class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label>Max Purchase</label>
                                <input type="number" name="ticket_types[${ticketTypeCount}][max_purchase]" min="1" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Sale Starts</label>
                                <input type="datetime-local" name="ticket_types[${ticketTypeCount}][sale_starts]" class="form-control">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Sale Ends</label>
                                <input type="datetime-local" name="ticket_types[${ticketTypeCount}][sale_ends]" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" name="ticket_types[${ticketTypeCount}][is_active]" value="1" checked class="form-check-input">
                            <label class="form-check-label">Active</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', ticketHtml);
}

function removeTicketType(ticketNumber) {
    const ticket = document.querySelector(`[data-ticket="${ticketNumber}"]`);
    if (ticket) {
        ticket.remove();
    }
}

// Event listeners
document.getElementById('add-ticket-type').addEventListener('click', addTicketType);

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-ticket')) {
        const ticketNumber = e.target.closest('.ticket-type-item').dataset.ticket;
        removeTicketType(ticketNumber);
    }
});

// Add initial ticket type
addTicketType();
</script>

<style>
.form-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #dee2e6;
}

.form-section:last-child {
    border-bottom: none;
}

.form-section h4 {
    margin-bottom: 20px;
    color: #333;
    font-weight: 600;
}

.guidelines h4 {
    margin: 15px 0 8px 0;
    font-size: 14px;
    color: #333;
    font-weight: 600;
}

.guidelines ul {
    margin: 0 0 15px 0;
    padding-left: 20px;
}

.guidelines li {
    margin-bottom: 4px;
    font-size: 14px;
    color: #6c757d;
}

.tips-list {
    margin: 0;
    padding-left: 20px;
}

.tips-list li {
    margin-bottom: 8px;
    font-size: 14px;
    color: #6c757d;
}

.required::after {
    content: " *";
    color: #dc3545;
}

.ticket-type-item {
    position: relative;
}

.ticket-type-item .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.form-actions {
    text-align: center;
    padding: 20px 0;
    border-top: 1px solid #dee2e6;
    margin-top: 20px;
}

.form-actions .btn {
    margin: 0 10px;
}

.form-check {
    margin-top: 8px;
}

.form-check-input {
    margin-right: 8px;
}
</style>

<?php include '../../../includes/footer.php'; ?>

