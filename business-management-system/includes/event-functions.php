<?php
/**
 * Business Management System - Event Functions
 * Phase 4: Event Booking System Module
 */

if (!defined('BMS_SYSTEM')) {
    die('Direct access not allowed');
}

/**
 * Generate unique event code
 * Format: EVT-YYYY-NNNN
 */
function generateEventCode() {
    $year = date('Y');
    $prefix = "EVT-{$year}-";
    
    $conn = getDB();
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM " . DB_PREFIX . "events 
        WHERE event_code LIKE ?
    ");
    $stmt->bind_param('s', $prefix . '%');
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $nextNumber = str_pad($result['count'] + 1, 4, '0', STR_PAD_LEFT);
    return $prefix . $nextNumber;
}

/**
 * Generate unique booking number
 * Format: BKG-YYYY-NNNN
 */
function generateBookingNumber() {
    $year = date('Y');
    $prefix = "BKG-{$year}-";
    
    $conn = getDB();
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM " . DB_PREFIX . "event_bookings 
        WHERE booking_number LIKE ?
    ");
    $stmt->bind_param('s', $prefix . '%');
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $nextNumber = str_pad($result['count'] + 1, 4, '0', STR_PAD_LEFT);
    return $prefix . $nextNumber;
}

/**
 * Generate unique ticket code for attendee
 * Format: TKT-XXXXXX
 */
function generateTicketCode() {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    
    do {
        $code = 'TKT-' . substr(str_shuffle($characters), 0, 6);
        
        $conn = getDB();
        $stmt = $conn->prepare("SELECT id FROM " . DB_PREFIX . "booking_attendees WHERE ticket_code = ?");
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
    } while ($exists);
    
    return $code;
}

/**
 * Calculate booking totals
 */
function calculateBookingTotals($ticketItems, $serviceFeePercentage = 0, $taxRate = 0) {
    $subtotal = 0;
    
    foreach ($ticketItems as $item) {
        $subtotal += $item['quantity'] * $item['price'];
    }
    
    $serviceFee = $subtotal * ($serviceFeePercentage / 100);
    $taxAmount = ($subtotal + $serviceFee) * ($taxRate / 100);
    $total = $subtotal + $serviceFee + $taxAmount;
    
    return [
        'subtotal' => $subtotal,
        'service_fee' => $serviceFee,
        'tax_amount' => $taxAmount,
        'total' => $total
    ];
}

/**
 * Check if event booking is allowed
 */
function isEventBookingAllowed($eventId) {
    $conn = getDB();
    $stmt = $conn->prepare("
        SELECT enable_booking, booking_starts, booking_ends, status, start_date, start_time
        FROM " . DB_PREFIX . "events 
        WHERE id = ?
    ");
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    
    if (!$event || $event['status'] != 'Published' || !$event['enable_booking']) {
        return false;
    }
    
    $now = date('Y-m-d H:i:s');
    $eventStart = $event['start_date'] . ' ' . $event['start_time'];
    
    // Check if event has already started
    if ($now >= $eventStart) {
        return false;
    }
    
    // Check booking window
    if ($event['booking_starts'] && $now < $event['booking_starts']) {
        return false;
    }
    
    if ($event['booking_ends'] && $now > $event['booking_ends']) {
        return false;
    }
    
    return true;
}

/**
 * Check ticket availability
 */
function checkTicketAvailability($ticketId, $quantity) {
    $conn = getDB();
    $stmt = $conn->prepare("
        SELECT quantity_available, quantity_sold, sale_starts, sale_ends, is_active
        FROM " . DB_PREFIX . "event_tickets 
        WHERE id = ?
    ");
    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    
    if (!$ticket || !$ticket['is_active']) {
        return false;
    }
    
    $now = date('Y-m-d H:i:s');
    
    // Check sale period
    if ($ticket['sale_starts'] && $now < $ticket['sale_starts']) {
        return false;
    }
    
    if ($ticket['sale_ends'] && $now > $ticket['sale_ends']) {
        return false;
    }
    
    $available = $ticket['quantity_available'] - $ticket['quantity_sold'];
    
    return $available >= $quantity;
}

/**
 * Create event booking
 */
function createEventBooking($eventId, $customerId, $ticketItems, $paymentType = 'Full Payment', $specialRequirements = '', $bookingSource = 'Online', $createdBy = null) {
    $conn = getDB();
    
    // Get event details
    $stmt = $conn->prepare("
        SELECT event_name, start_date, start_time, venue_name, organizer_name, organizer_email
        FROM " . DB_PREFIX . "events 
        WHERE id = ?
    ");
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    
    if (!$event) {
        return false;
    }
    
    // Get event settings
    $serviceFeePercentage = (float)getEventSetting('service_fee_percentage', 2.5);
    $taxRate = (float)getEventSetting('tax_rate', 7.5);
    
    // Calculate totals
    $totals = calculateBookingTotals($ticketItems, $serviceFeePercentage, $taxRate);
    
    // Generate booking number
    $bookingNumber = generateBookingNumber();
    
    $conn->begin_transaction();
    
    try {
        // Create booking record
        $stmt = $conn->prepare("
            INSERT INTO " . DB_PREFIX . "event_bookings 
            (booking_number, event_id, customer_id, attendee_count, subtotal, service_fee, 
             tax_amount, total_amount, balance_due, payment_type, special_requirements, 
             booking_source, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $attendeeCount = array_sum(array_column($ticketItems, 'quantity'));
        $balanceDue = $totals['total'];
        
        $stmt->bind_param('siiddddddsssi', 
            $bookingNumber, $eventId, $customerId, $attendeeCount, 
            $totals['subtotal'], $totals['service_fee'], $totals['tax_amount'], 
            $totals['total'], $balanceDue, $paymentType, $specialRequirements, 
            $bookingSource, $createdBy
        );
        $stmt->execute();
        $bookingId = $conn->getConnection()->lastInsertId();
        
        // Create booking items
        foreach ($ticketItems as $item) {
            $stmt = $conn->prepare("
                INSERT INTO " . DB_PREFIX . "booking_items 
                (booking_id, ticket_id, ticket_name, quantity, unit_price, line_total) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $lineTotal = $item['quantity'] * $item['price'];
            $stmt->bind_param('iisidd', 
                $bookingId, $item['ticket_id'], $item['ticket_name'], 
                $item['quantity'], $item['price'], $lineTotal
            );
            $stmt->execute();
            
            // Update ticket sold count
            $stmt = $conn->prepare("
                UPDATE " . DB_PREFIX . "event_tickets 
                SET quantity_sold = quantity_sold + ? 
                WHERE id = ?
            ");
            $stmt->bind_param('ii', $item['quantity'], $item['ticket_id']);
            $stmt->execute();
        }
        
        // Generate invoice if enabled
        $invoiceId = null;
        if (getEventSetting('auto_generate_invoice', true)) {
            $invoiceId = createEventInvoice($bookingId, $event, $totals);
        }
        
        // Update booking with invoice ID
        if ($invoiceId) {
            $stmt = $conn->prepare("
                UPDATE " . DB_PREFIX . "event_bookings 
                SET invoice_id = ? 
                WHERE id = ?
            ");
            $stmt->bind_param('ii', $invoiceId, $bookingId);
            $stmt->execute();
        }
        
        $conn->commit();
        
        // Send confirmation email
        if (getEventSetting('booking_confirmation_email', true)) {
            sendBookingConfirmationEmail($bookingId);
        }
        
        return $bookingId;
        
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

/**
 * Create invoice for event booking
 */
function createEventInvoice($bookingId, $event, $totals) {
    $conn = getDB();
    
    // Get booking details
    $stmt = $conn->prepare("
        SELECT booking_number, customer_id, attendee_count
        FROM " . DB_PREFIX . "event_bookings 
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
    
    $notes = "Event Booking: {$event['event_name']} - {$booking['booking_number']}";
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
    
    $itemName = "Event Booking - {$event['event_name']}";
    $description = "Event booking for {$booking['attendee_count']} attendee(s)";
    $quantity = 1;
    $unitPrice = $totals['total'];
    $lineTotal = $totals['total'];
    
    $stmt->bind_param('issidd', 
        $invoiceId, $itemName, $description, $quantity, $unitPrice, $lineTotal
    );
    $stmt->execute();
    
    // Create journal entry if enabled
    if (getEventSetting('auto_create_journal_entry', true)) {
        $lines = [
            [
                'account_id' => 1100, // Accounts Receivable
                'debit' => $totals['total'],
                'credit' => 0,
                'description' => 'Event booking - ' . $booking['booking_number']
            ],
            [
                'account_id' => 4001, // Event Revenue
                'debit' => 0,
                'credit' => $totals['total'],
                'description' => 'Event revenue - ' . $event['event_name']
            ]
        ];
        
        createJournalEntry(
            date('Y-m-d'),
            'Event Booking ' . $booking['booking_number'] . ' - ' . $event['event_name'],
            $lines,
            'Invoice',
            $invoiceId,
            'invoice'
        );
    }
    
    return $invoiceId;
}

/**
 * Get event setting value
 */
function getEventSetting($key, $default = null) {
    $conn = getDB();
    $stmt = $conn->prepare("
        SELECT setting_value 
        FROM " . DB_PREFIX . "event_settings 
        WHERE setting_key = ?
    ");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result ? $result['setting_value'] : $default;
}

/**
 * Update event setting value
 */
function updateEventSetting($key, $value) {
    $conn = getDB();
    $stmt = $conn->prepare("
        INSERT INTO " . DB_PREFIX . "event_settings (setting_key, setting_value) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    $stmt->bind_param('ss', $key, $value);
    return $stmt->execute();
}

/**
 * Send booking confirmation email
 */
function sendBookingConfirmationEmail($bookingId) {
    // Get booking details
    $conn = getDB();
    $stmt = $conn->prepare("
        SELECT eb.*, e.event_name, e.start_date, e.start_time, e.venue_name, e.venue_address,
               c.first_name, c.last_name, c.email as customer_email
        FROM " . DB_PREFIX . "event_bookings eb
        JOIN " . DB_PREFIX . "events e ON eb.event_id = e.id
        LEFT JOIN " . DB_PREFIX . "customers c ON eb.customer_id = c.id
        WHERE eb.id = ?
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
        FROM " . DB_PREFIX . "email_templates 
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
        '{{event_name}}',
        '{{booking_number}}',
        '{{customer_name}}'
    ], [
        $booking['event_name'],
        $booking['booking_number'],
        $booking['first_name'] . ' ' . $booking['last_name']
    ], $template['subject']);
    
    $body = str_replace([
        '{{event_name}}',
        '{{booking_number}}',
        '{{customer_name}}',
        '{{event_date}}',
        '{{venue_name}}',
        '{{total_amount}}'
    ], [
        $booking['event_name'],
        $booking['booking_number'],
        $booking['first_name'] . ' ' . $booking['last_name'],
        date('M d, Y', strtotime($booking['start_date'])),
        $booking['venue_name'],
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
            UPDATE " . DB_PREFIX . "event_bookings 
            SET confirmation_sent = 1 
            WHERE id = ?
        ");
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
    }
    
    return $sent;
}

/**
 * Format event date and time
 */
function formatEventDateTime($date, $time) {
    return date('M d, Y', strtotime($date)) . ' at ' . date('g:i A', strtotime($time));
}

/**
 * Get event status badge class
 */
function getEventStatusBadgeClass($status) {
    switch ($status) {
        case 'Published': return 'badge-success';
        case 'Draft': return 'badge-secondary';
        case 'Cancelled': return 'badge-danger';
        case 'Completed': return 'badge-info';
        default: return 'badge-secondary';
    }
}

/**
 * Get booking status badge class
 */
function getBookingStatusBadgeClass($status) {
    switch ($status) {
        case 'Confirmed': return 'badge-success';
        case 'Pending': return 'badge-warning';
        case 'Cancelled': return 'badge-danger';
        case 'Attended': return 'badge-info';
        default: return 'badge-secondary';
    }
}

/**
 * Get payment status badge class
 */
function getPaymentStatusBadgeClass($status) {
    switch ($status) {
        case 'Paid': return 'badge-success';
        case 'Partial': return 'badge-warning';
        case 'Pending': return 'badge-secondary';
        case 'Refunded': return 'badge-danger';
        default: return 'badge-secondary';
    }
}

/**
 * Check if event is sold out
 */
function isEventSoldOut($eventId) {
    $conn = getDB();
    $stmt = $conn->prepare("
        SELECT SUM(quantity_available - quantity_sold) as available_tickets
        FROM " . DB_PREFIX . "event_tickets 
        WHERE event_id = ? AND is_active = 1
    ");
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['available_tickets'] <= 0;
}

/**
 * Get event statistics
 */
function getEventStatistics($eventId) {
    $conn = getDB();
    
    // Get total bookings and revenue
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_bookings,
            SUM(total_amount) as total_revenue,
            SUM(amount_paid) as total_paid,
            SUM(balance_due) as total_outstanding
        FROM " . DB_PREFIX . "event_bookings 
        WHERE event_id = ? AND booking_status != 'Cancelled'
    ");
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    // Get tickets sold
    $stmt = $conn->prepare("
        SELECT SUM(quantity_sold) as tickets_sold
        FROM " . DB_PREFIX . "event_tickets 
        WHERE event_id = ?
    ");
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $tickets = $stmt->get_result()->fetch_assoc();
    
    // Get check-in rate
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_attendees,
            SUM(checked_in) as checked_in_count
        FROM " . DB_PREFIX . "booking_attendees ba
        JOIN " . DB_PREFIX . "event_bookings eb ON ba.booking_id = eb.id
        WHERE eb.event_id = ? AND eb.booking_status = 'Confirmed'
    ");
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $checkin = $stmt->get_result()->fetch_assoc();
    
    $checkinRate = 0;
    if ($checkin['total_attendees'] > 0) {
        $checkinRate = ($checkin['checked_in_count'] / $checkin['total_attendees']) * 100;
    }
    
    return [
        'total_bookings' => $stats['total_bookings'] ?? 0,
        'total_revenue' => $stats['total_revenue'] ?? 0,
        'total_paid' => $stats['total_paid'] ?? 0,
        'total_outstanding' => $stats['total_outstanding'] ?? 0,
        'tickets_sold' => $tickets['tickets_sold'] ?? 0,
        'checkin_rate' => round($checkinRate, 1)
    ];
}
