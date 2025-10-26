<?php
/**
 * Business Management System - Email Functions
 * Phase 4: Hall Booking System Module
 */

// Prevent direct access
if (!defined('BMS_SYSTEM')) {
    die('Direct access not allowed');
}

/**
 * Send hall booking confirmation email
 */
function sendHallBookingConfirmation($bookingId) {
    $conn = getDB();
    
    // Get booking details
    $stmt = $conn->prepare("
        SELECT hb.*, h.hall_name, h.location, h.address,
               c.first_name, c.last_name, c.email as customer_email, c.company_name
        FROM " . DB_PREFIX . "hall_bookings hb
        JOIN " . DB_PREFIX . "halls h ON hb.hall_id = h.id
        JOIN " . DB_PREFIX . "customers c ON hb.customer_id = c.id
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
        SELECT subject, body FROM " . DB_PREFIX . "hall_email_templates 
        WHERE template_type = 'Booking Confirmation' AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute();
    $template = $stmt->get_result()->fetch_assoc();
    
    if (!$template) {
        return false;
    }
    
    // Replace variables in template
    $variables = [
        '{{customer_name}}' => $booking['first_name'] . ' ' . $booking['last_name'],
        '{{hall_name}}' => $booking['hall_name'],
        '{{booking_number}}' => $booking['booking_number'],
        '{{event_name}}' => $booking['event_name'],
        '{{booking_date}}' => date('M d, Y', strtotime($booking['start_date'])),
        '{{start_time}}' => date('g:i A', strtotime($booking['start_time'])),
        '{{end_time}}' => date('g:i A', strtotime($booking['end_time'])),
        '{{total_amount}}' => formatCurrency($booking['total_amount']),
        '{{hall_location}}' => $booking['location'],
        '{{hall_address}}' => $booking['address']
    ];
    
    $subject = str_replace(array_keys($variables), array_values($variables), $template['subject']);
    $body = str_replace(array_keys($variables), array_values($variables), $template['body']);
    
    // Send email
    return sendEmail($booking['customer_email'], $subject, $body);
}

/**
 * Send payment received email
 */
function sendPaymentReceivedEmail($bookingId, $paymentAmount, $paymentMethod) {
    $conn = getDB();
    
    // Get booking details
    $stmt = $conn->prepare("
        SELECT hb.*, h.hall_name,
               c.first_name, c.last_name, c.email as customer_email
        FROM " . DB_PREFIX . "hall_bookings hb
        JOIN " . DB_PREFIX . "halls h ON hb.hall_id = h.id
        JOIN " . DB_PREFIX . "customers c ON hb.customer_id = c.id
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
        SELECT subject, body FROM " . DB_PREFIX . "hall_email_templates 
        WHERE template_type = 'Payment Received' AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute();
    $template = $stmt->get_result()->fetch_assoc();
    
    if (!$template) {
        return false;
    }
    
    // Replace variables in template
    $variables = [
        '{{customer_name}}' => $booking['first_name'] . ' ' . $booking['last_name'],
        '{{hall_name}}' => $booking['hall_name'],
        '{{booking_number}}' => $booking['booking_number'],
        '{{payment_amount}}' => formatCurrency($paymentAmount),
        '{{payment_method}}' => $paymentMethod,
        '{{payment_date}}' => date('M d, Y'),
        '{{total_amount}}' => formatCurrency($booking['total_amount']),
        '{{balance_due}}' => formatCurrency($booking['balance_due'])
    ];
    
    $subject = str_replace(array_keys($variables), array_values($variables), $template['subject']);
    $body = str_replace(array_keys($variables), array_values($variables), $template['body']);
    
    // Send email
    return sendEmail($booking['customer_email'], $subject, $body);
}

/**
 * Send booking reminder email
 */
function sendBookingReminderEmail($bookingId) {
    $conn = getDB();
    
    // Get booking details
    $stmt = $conn->prepare("
        SELECT hb.*, h.hall_name, h.location,
               c.first_name, c.last_name, c.email as customer_email
        FROM " . DB_PREFIX . "hall_bookings hb
        JOIN " . DB_PREFIX . "halls h ON hb.hall_id = h.id
        JOIN " . DB_PREFIX . "customers c ON hb.customer_id = c.id
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
        SELECT subject, body FROM " . DB_PREFIX . "hall_email_templates 
        WHERE template_type = 'Booking Reminder' AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute();
    $template = $stmt->get_result()->fetch_assoc();
    
    if (!$template) {
        return false;
    }
    
    // Replace variables in template
    $variables = [
        '{{customer_name}}' => $booking['first_name'] . ' ' . $booking['last_name'],
        '{{hall_name}}' => $booking['hall_name'],
        '{{booking_number}}' => $booking['booking_number'],
        '{{event_name}}' => $booking['event_name'],
        '{{booking_date}}' => date('M d, Y', strtotime($booking['start_date'])),
        '{{start_time}}' => date('g:i A', strtotime($booking['start_time'])),
        '{{end_time}}' => date('g:i A', strtotime($booking['end_time'])),
        '{{hall_location}}' => $booking['location']
    ];
    
    $subject = str_replace(array_keys($variables), array_values($variables), $template['subject']);
    $body = str_replace(array_keys($variables), array_values($variables), $template['body']);
    
    // Send email
    return sendEmail($booking['customer_email'], $subject, $body);
}

/**
 * Send booking cancellation email
 */
function sendBookingCancellationEmail($bookingId, $cancellationReason = '') {
    $conn = getDB();
    
    // Get booking details
    $stmt = $conn->prepare("
        SELECT hb.*, h.hall_name,
               c.first_name, c.last_name, c.email as customer_email
        FROM " . DB_PREFIX . "hall_bookings hb
        JOIN " . DB_PREFIX . "halls h ON hb.hall_id = h.id
        JOIN " . DB_PREFIX . "customers c ON hb.customer_id = c.id
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
        SELECT subject, body FROM " . DB_PREFIX . "hall_email_templates 
        WHERE template_type = 'Booking Cancelled' AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute();
    $template = $stmt->get_result()->fetch_assoc();
    
    if (!$template) {
        return false;
    }
    
    // Replace variables in template
    $variables = [
        '{{customer_name}}' => $booking['first_name'] . ' ' . $booking['last_name'],
        '{{hall_name}}' => $booking['hall_name'],
        '{{booking_number}}' => $booking['booking_number'],
        '{{event_name}}' => $booking['event_name'],
        '{{booking_date}}' => date('M d, Y', strtotime($booking['start_date'])),
        '{{start_time}}' => date('g:i A', strtotime($booking['start_time'])),
        '{{end_time}}' => date('g:i A', strtotime($booking['end_time'])),
        '{{cancellation_reason}}' => $cancellationReason,
        '{{total_amount}}' => formatCurrency($booking['total_amount']),
        '{{refund_amount}}' => formatCurrency($booking['amount_paid'])
    ];
    
    $subject = str_replace(array_keys($variables), array_values($variables), $template['subject']);
    $body = str_replace(array_keys($variables), array_values($variables), $template['body']);
    
    // Send email
    return sendEmail($booking['customer_email'], $subject, $body);
}

/**
 * Send payment reminder email
 */
function sendPaymentReminderEmail($bookingId) {
    $conn = getDB();
    
    // Get booking details
    $stmt = $conn->prepare("
        SELECT hb.*, h.hall_name,
               c.first_name, c.last_name, c.email as customer_email
        FROM " . DB_PREFIX . "hall_bookings hb
        JOIN " . DB_PREFIX . "halls h ON hb.hall_id = h.id
        JOIN " . DB_PREFIX . "customers c ON hb.customer_id = c.id
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
        SELECT subject, body FROM " . DB_PREFIX . "hall_email_templates 
        WHERE template_type = 'Payment Reminder' AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute();
    $template = $stmt->get_result()->fetch_assoc();
    
    if (!$template) {
        return false;
    }
    
    // Replace variables in template
    $variables = [
        '{{customer_name}}' => $booking['first_name'] . ' ' . $booking['last_name'],
        '{{hall_name}}' => $booking['hall_name'],
        '{{booking_number}}' => $booking['booking_number'],
        '{{event_name}}' => $booking['event_name'],
        '{{booking_date}}' => date('M d, Y', strtotime($booking['start_date'])),
        '{{balance_due}}' => formatCurrency($booking['balance_due']),
        '{{total_amount}}' => formatCurrency($booking['total_amount'])
    ];
    
    $subject = str_replace(array_keys($variables), array_values($variables), $template['subject']);
    $body = str_replace(array_keys($variables), array_values($variables), $template['body']);
    
    // Send email
    return sendEmail($booking['customer_email'], $subject, $body);
}

/**
 * Generic email sending function
 */
function sendEmail($to, $subject, $body) {
    $companyName = getSetting('company_name', 'Business Management System');
    $companyEmail = getSetting('company_email', 'noreply@example.com');
    
    // Set headers
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . $companyName . ' <' . $companyEmail . '>',
        'Reply-To: ' . $companyEmail,
        'X-Mailer: PHP/' . phpversion()
    ];
    
    // Create HTML email
    $htmlBody = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($subject) . '</title>
        <style>
            body { font-family: "Poppins", Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: white; padding: 30px; border: 1px solid #eee; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; font-size: 14px; color: #666; }
            h1 { margin: 0; font-size: 24px; }
            h2 { color: #333; margin-top: 0; }
            .btn { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            .btn:hover { background: #5a6fd8; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . htmlspecialchars($companyName) . '</h1>
                <p>Hall Rentals & Event Spaces</p>
            </div>
            <div class="content">
                ' . $body . '
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' ' . htmlspecialchars($companyName) . '. All rights reserved.</p>
                <p>This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Send email
    return mail($to, $subject, $htmlBody, implode("\r\n", $headers));
}

/**
 * Send bulk reminder emails for upcoming bookings
 */
function sendBulkBookingReminders() {
    $conn = getDB();
    
    // Get bookings that need reminders (24 hours before event)
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $stmt = $conn->prepare("
        SELECT id FROM " . DB_PREFIX . "hall_bookings 
        WHERE start_date = ? 
        AND booking_status = 'Confirmed' 
        AND reminder_sent = 0
    ");
    $stmt->bind_param('s', $tomorrow);
    $stmt->execute();
    $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $sentCount = 0;
    foreach ($bookings as $booking) {
        if (sendBookingReminderEmail($booking['id'])) {
            // Mark reminder as sent
            $updateStmt = $conn->prepare("
                UPDATE " . DB_PREFIX . "hall_bookings 
                SET reminder_sent = 1 
                WHERE id = ?
            ");
            $updateStmt->bind_param('i', $booking['id']);
            $updateStmt->execute();
            $sentCount++;
        }
    }
    
    return $sentCount;
}

/**
 * Send bulk payment reminder emails
 */
function sendBulkPaymentReminders() {
    $conn = getDB();
    
    // Get bookings with outstanding payments
    $stmt = $conn->prepare("
        SELECT id FROM " . DB_PREFIX . "hall_bookings 
        WHERE balance_due > 0 
        AND booking_status IN ('Confirmed', 'Pending')
        AND payment_status IN ('Pending', 'Partial')
    ");
    $stmt->execute();
    $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $sentCount = 0;
    foreach ($bookings as $booking) {
        if (sendPaymentReminderEmail($booking['id'])) {
            $sentCount++;
        }
    }
    
    return $sentCount;
}

