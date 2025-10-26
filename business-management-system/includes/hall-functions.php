<?php
/**
 * Business Management System - Hall Management Functions
 * Phase 4: Hall Booking System Module
 */

if (!defined('BMS_SYSTEM')) {
    die('Direct access not allowed');
}

/**
 * Generate unique hall code
 * Format: HALL-YYYY-NNNN
 */
function generateHallCode() {
    $year = date('Y');
    $prefix = "HALL-{$year}-";
    
    $conn = getDB();
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM " . DB_PREFIX . "halls 
        WHERE hall_code LIKE ?
    ");
    $stmt->bind_param('s', $prefix . '%');
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $nextNumber = str_pad($result['count'] + 1, 4, '0', STR_PAD_LEFT);
    return $prefix . $nextNumber;
}

/**
 * Generate unique hall booking number
 * Format: HBK-YYYY-NNNN
 */
function generateHallBookingNumber() {
    $year = date('Y');
    $prefix = "HBK-{$year}-";
    
    $conn = getDB();
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM " . DB_PREFIX . "hall_bookings 
        WHERE booking_number LIKE ?
    ");
    $stmt->bind_param('s', $prefix . '%');
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $nextNumber = str_pad($result['count'] + 1, 4, '0', STR_PAD_LEFT);
    return $prefix . $nextNumber;
}

/**
 * Calculate hall booking totals
 */
function calculateHallBookingTotals($hallId, $startDate, $startTime, $endDate, $endTime, $additionalItems = [], $serviceFeePercentage = 0, $taxRate = 0) {
    $conn = getDB();
    
    // Get hall pricing
    $stmt = $conn->prepare("
        SELECT hourly_rate, daily_rate, weekly_rate, monthly_rate, currency
        FROM " . DB_PREFIX . "halls 
        WHERE id = ?
    ");
    $stmt->bind_param('i', $hallId);
    $stmt->execute();
    $hall = $stmt->get_result()->fetch_assoc();
    
    if (!$hall) {
        return false;
    }
    
    // Calculate duration in hours
    $startDateTime = $startDate . ' ' . $startTime;
    $endDateTime = $endDate . ' ' . $endTime;
    $durationHours = (strtotime($endDateTime) - strtotime($startDateTime)) / 3600;
    
    // Calculate base price based on duration
    $basePrice = 0;
    if ($durationHours <= 24) {
        // Hourly or daily rate
        if ($durationHours <= 8) {
            $basePrice = $hall['hourly_rate'] * $durationHours;
        } else {
            $basePrice = $hall['daily_rate'];
        }
    } elseif ($durationHours <= 168) { // 7 days
        $basePrice = $hall['weekly_rate'];
    } else {
        $basePrice = $hall['monthly_rate'];
    }
    
    // Add additional items
    $subtotal = $basePrice;
    foreach ($additionalItems as $item) {
        $subtotal += $item['quantity'] * $item['price'];
    }
    
    $serviceFee = $subtotal * ($serviceFeePercentage / 100);
    $taxAmount = ($subtotal + $serviceFee) * ($taxRate / 100);
    $total = $subtotal + $serviceFee + $taxAmount;
    
    return [
        'duration_hours' => $durationHours,
        'base_price' => $basePrice,
        'subtotal' => $subtotal,
        'service_fee' => $serviceFee,
        'tax_amount' => $taxAmount,
        'total' => $total,
        'currency' => $hall['currency']
    ];
}

/**
 * Check if hall is available for booking
 */
function isHallAvailable($hallId, $startDate, $startTime, $endDate, $endTime, $excludeBookingId = null) {
    $conn = getDB();
    
    // Check hall status
    $stmt = $conn->prepare("
        SELECT status, enable_booking 
        FROM " . DB_PREFIX . "halls 
        WHERE id = ?
    ");
    $stmt->bind_param('i', $hallId);
    $stmt->execute();
    $hall = $stmt->get_result()->fetch_assoc();
    
    if (!$hall || $hall['status'] != 'Available' || !$hall['enable_booking']) {
        return false;
    }
    
    // Check for conflicting bookings
    $whereClause = "hall_id = ? AND booking_status != 'Cancelled' AND (
        (start_date = ? AND start_time < ? AND end_time > ?) OR
        (end_date = ? AND start_time < ? AND end_time > ?) OR
        (start_date <= ? AND end_date >= ?)
    )";
    
    $params = [$hallId, $startDate, $endTime, $startTime, $endDate, $endTime, $startTime, $startDate, $endDate];
    $paramTypes = 'isssisssss';
    
    if ($excludeBookingId) {
        $whereClause .= " AND id != ?";
        $params[] = $excludeBookingId;
        $paramTypes .= 'i';
    }
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as conflicts 
        FROM " . DB_PREFIX . "hall_bookings 
        WHERE {$whereClause}
    ");
    $stmt->bind_param($paramTypes, ...$params);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['conflicts'] == 0;
}

/**
 * Create hall booking
 */
function createHallBooking($hallId, $customerId, $eventName, $eventType, $startDate, $startTime, $endDate, $endTime, $attendeeCount = null, $additionalItems = [], $paymentType = 'Full Payment', $specialRequirements = '', $bookingSource = 'Online', $createdBy = null) {
    $conn = getDB();
    
    // Get hall details
    $stmt = $conn->prepare("
        SELECT hall_name, location, address, currency
        FROM " . DB_PREFIX . "halls 
        WHERE id = ?
    ");
    $stmt->bind_param('i', $hallId);
    $stmt->execute();
    $hall = $stmt->get_result()->fetch_assoc();
    
    if (!$hall) {
        return false;
    }
    
    // Check availability
    if (!isHallAvailable($hallId, $startDate, $startTime, $endDate, $endTime)) {
        return false;
    }
    
    // Get hall settings
    $serviceFeePercentage = (float)getHallSetting('service_fee_percentage', 2.5);
    $taxRate = (float)getHallSetting('tax_rate', 7.5);
    
    // Calculate totals
    $totals = calculateHallBookingTotals($hallId, $startDate, $startTime, $endDate, $endTime, $additionalItems, $serviceFeePercentage, $taxRate);
    
    if (!$totals) {
        return false;
    }
    
    // Generate booking number
    $bookingNumber = generateHallBookingNumber();
    
    $conn->begin_transaction();
    
    try {
        // Create booking record
        $stmt = $conn->prepare("
            INSERT INTO " . DB_PREFIX . "hall_bookings 
            (booking_number, hall_id, customer_id, event_name, event_type, 
             start_date, start_time, end_date, end_time, duration_hours, 
             attendee_count, subtotal, service_fee, tax_amount, total_amount, 
             balance_due, payment_type, special_requirements, booking_source, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $balanceDue = $totals['total'];
        
        $stmt->bind_param('siissssssdiddddddsssi', 
            $bookingNumber, $hallId, $customerId, $eventName, $eventType,
            $startDate, $startTime, $endDate, $endTime, $totals['duration_hours'],
            $attendeeCount, $totals['subtotal'], $totals['service_fee'], $totals['tax_amount'], 
            $totals['total'], $balanceDue, $paymentType, $specialRequirements, $bookingSource, $createdBy
        );
        $stmt->execute();
        $bookingId = $conn->getConnection()->lastInsertId();
        
        // Create booking items for additional services
        foreach ($additionalItems as $item) {
            $stmt = $conn->prepare("
                INSERT INTO " . DB_PREFIX . "hall_booking_items 
                (booking_id, item_name, item_description, quantity, unit_price, line_total) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $lineTotal = $item['quantity'] * $item['price'];
            $stmt->bind_param('issidd', 
                $bookingId, $item['name'], $item['description'], 
                $item['quantity'], $item['price'], $lineTotal
            );
            $stmt->execute();
        }
        
        // Generate invoice if enabled
        $invoiceId = null;
        if (getHallSetting('auto_generate_invoice', true)) {
            $invoiceId = createHallBookingInvoice($bookingId, $hall, $eventName, $totals);
        }
        
        // Update booking with invoice ID
        if ($invoiceId) {
            $stmt = $conn->prepare("
                UPDATE " . DB_PREFIX . "hall_bookings 
                SET invoice_id = ? 
                WHERE id = ?
            ");
            $stmt->bind_param('ii', $invoiceId, $bookingId);
            $stmt->execute();
        }
        
        $conn->commit();
        
        // Send confirmation email
        if (getHallSetting('booking_confirmation_email', true)) {
            sendHallBookingConfirmationEmail($bookingId);
        }
        
        return $bookingId;
        
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

/**
 * Create invoice for hall booking
 */
function createHallBookingInvoice($bookingId, $hall, $eventName, $totals) {
    $conn = getDB();
    
    // Get booking details
    $stmt = $conn->prepare("
        SELECT booking_number, customer_id, attendee_count, start_date, start_time, end_date, end_time
        FROM " . DB_PREFIX . "hall_bookings 
        WHERE id = ?
    ");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    // Get customer details
    $stmt = $conn->prepare("
        SELECT first_name, last_name, company_name, customer_type, email, phone, address
        FROM " . DB_PREFIX . "customers 
        WHERE id = ?
    ");
    $stmt->bind_param('i', $booking['customer_id']);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();
    
    // Generate invoice number
    $invoiceNumber = generateInvoiceNumber();
    
    // Create invoice
    $stmt = $conn->prepare("
        INSERT INTO " . DB_PREFIX . "invoices 
        (invoice_number, customer_id, invoice_date, due_date, subtotal, 
         tax_amount, total_amount, status, notes, created_by) 
        VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), ?, ?, ?, 'Sent', ?, ?)
    ");
    
    $notes = "Hall Booking: {$hall['hall_name']} - {$booking['booking_number']}";
    $userId = $_SESSION['user_id'] ?? 1;
    
    $stmt->bind_param('siidddsi', 
        $invoiceNumber, $booking['customer_id'], $totals['subtotal'], 
        $totals['tax_amount'], $totals['total'], $notes, $userId
    );
    $stmt->execute();
    $invoiceId = $conn->getConnection()->lastInsertId();
    
    // Create invoice items
    $stmt = $conn->prepare("
        INSERT INTO " . DB_PREFIX . "invoice_items 
        (invoice_id, item_name, description, quantity, unit_price, line_total) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $itemName = "Hall Rental - {$hall['hall_name']}";
    $description = "Hall rental for {$eventName} from {$booking['start_date']} {$booking['start_time']} to {$booking['end_date']} {$booking['end_time']}";
    $quantity = 1;
    $unitPrice = $totals['total'];
    $lineTotal = $totals['total'];
    
    $stmt->bind_param('issidd', 
        $invoiceId, $itemName, $description, $quantity, $unitPrice, $lineTotal
    );
    $stmt->execute();
    
    // Create journal entry if enabled
    if (getHallSetting('auto_create_journal_entry', true)) {
        $lines = [
            [
                'account_id' => 1100, // Accounts Receivable
                'debit' => $totals['total'],
                'credit' => 0,
                'description' => 'Hall booking - ' . $booking['booking_number']
            ],
            [
                'account_id' => 4001, // Hall Rental Revenue
                'debit' => 0,
                'credit' => $totals['total'],
                'description' => 'Hall rental revenue - ' . $hall['hall_name']
            ]
        ];
        
        createJournalEntry(
            date('Y-m-d'),
            'Hall Booking ' . $booking['booking_number'] . ' - ' . $hall['hall_name'],
            $lines,
            'Invoice',
            $invoiceId,
            'invoice'
        );
    }
    
    return $invoiceId;
}

/**
 * Get hall setting value
 */
function getHallSetting($key, $default = null) {
    $conn = getDB();
    $stmt = $conn->prepare("
        SELECT setting_value 
        FROM " . DB_PREFIX . "hall_settings 
        WHERE setting_key = ?
    ");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result ? $result['setting_value'] : $default;
}

/**
 * Update hall setting value
 */
function updateHallSetting($key, $value) {
    $conn = getDB();
    $stmt = $conn->prepare("
        INSERT INTO " . DB_PREFIX . "hall_settings (setting_key, setting_value) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    $stmt->bind_param('ss', $key, $value);
    return $stmt->execute();
}

/**
 * Send hall booking confirmation email
 */
function sendHallBookingConfirmationEmail($bookingId) {
    // Get booking details
    $conn = getDB();
    $stmt = $conn->prepare("
        SELECT hb.*, h.hall_name, h.location, h.address,
               c.first_name, c.last_name, c.email as customer_email
        FROM " . DB_PREFIX . "hall_bookings hb
        JOIN " . DB_PREFIX . "halls h ON hb.hall_id = h.id
        LEFT JOIN " . DB_PREFIX . "customers c ON hb.customer_id = c.id
        WHERE hb.id = ?
    ");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    if (!$booking) {
        return false;
    }
    
    // Get email template
    $stmt = $conn->prepare("
        SELECT subject, body 
        FROM " . DB_PREFIX . "hall_email_templates 
        WHERE template_type = 'Booking Confirmation' AND is_active = 1
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute();
    $template = $stmt->get_result()->fetch_assoc();
    
    if (!$template) {
        return false;
    }
    
    // Replace template variables
    $subject = str_replace([
        '{{hall_name}}',
        '{{booking_number}}',
        '{{customer_name}}'
    ], [
        $booking['hall_name'],
        $booking['booking_number'],
        $booking['first_name'] . ' ' . $booking['last_name']
    ], $template['subject']);
    
    $body = str_replace([
        '{{hall_name}}',
        '{{booking_number}}',
        '{{customer_name}}',
        '{{event_name}}',
        '{{booking_date}}',
        '{{start_time}}',
        '{{end_time}}',
        '{{total_amount}}'
    ], [
        $booking['hall_name'],
        $booking['booking_number'],
        $booking['first_name'] . ' ' . $booking['last_name'],
        $booking['event_name'],
        date('M d, Y', strtotime($booking['start_date'])),
        date('g:i A', strtotime($booking['start_time'])),
        date('g:i A', strtotime($booking['end_time'])),
        formatCurrency($booking['total_amount'])
    ], $template['body']);
    
    // Send email
    $to = $booking['customer_email'];
    $headers = [
        'From: ' . getSetting('company_email', 'noreply@example.com'),
        'Content-Type: text/html; charset=UTF-8'
    ];
    
    $sent = mail($to, $subject, $body, implode("\r\n", $headers));
    
    if ($sent) {
        // Mark confirmation as sent
        $stmt = $conn->prepare("
            UPDATE " . DB_PREFIX . "hall_bookings 
            SET confirmation_sent = 1 
            WHERE id = ?
        ");
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
    }
    
    return $sent;
}

/**
 * Format hall booking date and time
 */
function formatHallBookingDateTime($startDate, $startTime, $endDate, $endTime) {
    $start = date('M d, Y', strtotime($startDate)) . ' at ' . date('g:i A', strtotime($startTime));
    $end = date('M d, Y', strtotime($endDate)) . ' at ' . date('g:i A', strtotime($endTime));
    
    if ($startDate == $endDate) {
        return $start . ' - ' . date('g:i A', strtotime($endTime));
    }
    
    return $start . ' to ' . $end;
}

/**
 * Get hall status badge class
 */
function getHallStatusBadgeClass($status) {
    switch ($status) {
        case 'Available': return 'badge-success';
        case 'Maintenance': return 'badge-warning';
        case 'Unavailable': return 'badge-danger';
        default: return 'badge-secondary';
    }
}

/**
 * Get hall booking status badge class
 */
function getHallBookingStatusBadgeClass($status) {
    switch ($status) {
        case 'Confirmed': return 'badge-success';
        case 'Pending': return 'badge-warning';
        case 'Cancelled': return 'badge-danger';
        case 'Completed': return 'badge-info';
        default: return 'badge-secondary';
    }
}

/**
 * Get hall payment status badge class
 */
function getHallPaymentStatusBadgeClass($status) {
    switch ($status) {
        case 'Paid': return 'badge-success';
        case 'Partial': return 'badge-warning';
        case 'Pending': return 'badge-secondary';
        case 'Refunded': return 'badge-danger';
        default: return 'badge-secondary';
    }
}

/**
 * Get hall statistics
 */
function getHallStatistics($hallId) {
    $conn = getDB();
    
    // Get total bookings and revenue
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_bookings,
            SUM(total_amount) as total_revenue,
            SUM(amount_paid) as total_paid,
            SUM(balance_due) as total_outstanding
        FROM " . DB_PREFIX . "hall_bookings 
        WHERE hall_id = ? AND booking_status != 'Cancelled'
    ");
    $stmt->bind_param('i', $hallId);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    // Get occupancy rate (hours booked vs available)
    $stmt = $conn->prepare("
        SELECT SUM(duration_hours) as total_hours_booked
        FROM " . DB_PREFIX . "hall_bookings 
        WHERE hall_id = ? AND booking_status != 'Cancelled' 
        AND start_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->bind_param('i', $hallId);
    $stmt->execute();
    $occupancy = $stmt->get_result()->fetch_assoc();
    
    $totalHoursAvailable = 30 * 24; // 30 days * 24 hours
    $occupancyRate = 0;
    if ($totalHoursAvailable > 0) {
        $occupancyRate = ($occupancy['total_hours_booked'] / $totalHoursAvailable) * 100;
    }
    
    return [
        'total_bookings' => $stats['total_bookings'] ?? 0,
        'total_revenue' => $stats['total_revenue'] ?? 0,
        'total_paid' => $stats['total_paid'] ?? 0,
        'total_outstanding' => $stats['total_outstanding'] ?? 0,
        'occupancy_rate' => round($occupancyRate, 1)
    ];
}

/**
 * Check hall availability for a date range
 */
function getHallAvailability($hallId, $startDate, $endDate) {
    $conn = getDB();
    
    $stmt = $conn->prepare("
        SELECT start_date, start_time, end_date, end_time, booking_status, event_name
        FROM " . DB_PREFIX . "hall_bookings 
        WHERE hall_id = ? 
        AND booking_status != 'Cancelled'
        AND (
            (start_date BETWEEN ? AND ?) OR 
            (end_date BETWEEN ? AND ?) OR 
            (start_date <= ? AND end_date >= ?)
        )
        ORDER BY start_date, start_time
    ");
    $stmt->bind_param('issssss', $hallId, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

