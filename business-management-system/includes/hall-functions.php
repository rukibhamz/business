<?php
/**
 * Business Management System - Hall Functions
 * Phase 4: Hall Booking System Module
 */

// Prevent direct access
if (!defined('BMS_SYSTEM')) {
    die('Direct access not allowed');
}

// --- Number Generation Functions ---

/**
 * Generate unique hall code
 */
function generateHallCode($conn, $prefix = 'H') {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM " . DB_PREFIX . "halls WHERE hall_code LIKE ?");
    $pattern = $prefix . '%';
    $stmt->bind_param('s', $pattern);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_row()[0];
    
    return $prefix . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
}

/**
 * Generate unique booking number
 */
function generateBookingNumber($conn, $prefix = 'BK') {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM " . DB_PREFIX . "hall_bookings WHERE booking_number LIKE ?");
    $pattern = $prefix . '%';
    $stmt->bind_param('s', $pattern);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_row()[0];
    
    return $prefix . date('Y') . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

// --- Availability Functions ---

/**
 * Check if hall is available for given date/time
 */
function isHallAvailable($hallId, $startDate, $startTime, $endDate, $endTime) {
    $conn = getDB();
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM " . DB_PREFIX . "hall_bookings 
        WHERE hall_id = ? 
        AND booking_status IN ('Pending', 'Confirmed')
        AND (
            (start_date = ? AND start_time < ? AND end_time > ?) OR
            (end_date = ? AND start_time < ? AND end_time > ?) OR
            (start_date <= ? AND end_date >= ?)
        )
    ");
    
    $stmt->bind_param('issssssss', $hallId, $startDate, $endTime, $startTime, $endDate, $endTime, $startTime, $startDate, $endDate);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_row()[0];
    
    return $count == 0;
}

// --- Pricing Functions ---

/**
 * Calculate hall rental cost
 */
function calculateHallRental($hallId, $startDate, $startTime, $endDate, $endTime) {
    $conn = getDB();
    
    $stmt = $conn->prepare("SELECT * FROM " . DB_PREFIX . "halls WHERE id = ?");
    $stmt->bind_param('i', $hallId);
    $stmt->execute();
    $hall = $stmt->get_result()->fetch_assoc();
    
    if (!$hall) return 0;
    
    $startDateTime = new DateTime($startDate . ' ' . $startTime);
    $endDateTime = new DateTime($endDate . ' ' . $endTime);
    $duration = $endDateTime->diff($startDateTime);
    $hours = $duration->days * 24 + $duration->h + ($duration->i / 60);
    
    // Use hourly rate as default
    return $hall['hourly_rate'] * $hours;
}

/**
 * Calculate booking totals
 */
function calculateBookingTotals($hallRental, $serviceFee = 0, $taxRate = 0) {
    $subtotal = $hallRental + $serviceFee;
    $taxAmount = $subtotal * ($taxRate / 100);
    $total = $subtotal + $taxAmount;
    
    return [
        'subtotal' => $subtotal,
        'service_fee' => $serviceFee,
        'tax_amount' => $taxAmount,
        'total' => $total
    ];
}

// --- Booking Functions ---

/**
 * Create hall booking
 */
function createHallBooking($bookingData) {
    $conn = getDB();
    
    try {
        $conn->begin_transaction();
        
        // Generate booking number
        $bookingNumber = generateBookingNumber($conn);
        
        // Calculate duration
        $startDateTime = new DateTime($bookingData['start_date'] . ' ' . $bookingData['start_time']);
        $endDateTime = new DateTime($bookingData['end_date'] . ' ' . $bookingData['end_time']);
        $duration = $endDateTime->diff($startDateTime);
        $durationHours = $duration->days * 24 + $duration->h + ($duration->i / 60);
        
        // Calculate totals
        $totals = calculateBookingTotals(
            $bookingData['hall_rental'],
            $bookingData['service_fee'],
            $bookingData['tax_rate']
        );
        
        // Insert booking
        $stmt = $conn->prepare("
            INSERT INTO " . DB_PREFIX . "hall_bookings 
            (booking_number, hall_id, customer_id, event_name, event_type, 
             start_date, start_time, end_date, end_time, duration_hours, 
             attendee_count, subtotal, service_fee, tax_amount, total_amount, 
             amount_paid, balance_due, payment_type, booking_status, 
             special_requirements, booking_source, created_by, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 'Pending', ?, ?, ?, NOW())
        ");
        
        $stmt->bind_param('siissssssisddddssssi',
            $bookingNumber,
            $bookingData['hall_id'],
            $bookingData['customer_id'],
            $bookingData['event_name'],
            $bookingData['event_type'],
            $bookingData['start_date'],
            $bookingData['start_time'],
            $bookingData['end_date'],
            $bookingData['end_time'],
            $durationHours,
            $bookingData['attendee_count'],
            $totals['subtotal'],
            $totals['service_fee'],
            $totals['tax_amount'],
            $totals['total'],
            $totals['total'],
            $bookingData['payment_type'],
            $bookingData['special_requirements'],
            $bookingData['booking_source'],
            $bookingData['created_by']
        );
        
        $stmt->execute();
        $bookingId = $conn->getConnection()->lastInsertId();
        
        $conn->commit();
        return $bookingId;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error creating hall booking: " . $e->getMessage());
        return false;
    }
}

/**
 * Update booking status
 */
function updateBookingStatus($bookingId, $status) {
    $conn = getDB();
    
    $stmt = $conn->prepare("
        UPDATE " . DB_PREFIX . "hall_bookings 
        SET booking_status = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->bind_param('si', $status, $bookingId);
    
    return $stmt->execute();
}

/**
 * Cancel booking
 */
function cancelBooking($bookingId, $reason) {
    $conn = getDB();
    
    try {
        $conn->begin_transaction();
        
        $stmt = $conn->prepare("
            UPDATE " . DB_PREFIX . "hall_bookings 
            SET booking_status = 'Cancelled', 
                cancelled_at = NOW(), 
                cancellation_reason = ?,
                updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->bind_param('si', $reason, $bookingId);
        $stmt->execute();
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error cancelling booking: " . $e->getMessage());
        return false;
    }
}

/**
 * Complete booking
 */
function completeBooking($bookingId) {
    $conn = getDB();
    
    $stmt = $conn->prepare("
        UPDATE " . DB_PREFIX . "hall_bookings 
        SET booking_status = 'Completed', 
            checked_out_at = NOW(),
            updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->bind_param('i', $bookingId);
    
    return $stmt->execute();
}

/**
 * Record payment for booking
 */
function recordBookingPayment($bookingId, $paymentData) {
    $conn = getDB();
    
    try {
        $conn->begin_transaction();
        
        // Insert payment record
        $stmt = $conn->prepare("
            INSERT INTO " . DB_PREFIX . "hall_booking_payments 
            (booking_id, payment_number, payment_date, amount, payment_method, 
             status, is_deposit, notes, created_at) 
            VALUES (?, ?, ?, ?, ?, 'Completed', ?, ?, NOW())
        ");
        
        $stmt->bind_param('issdsis',
            $bookingId,
            $paymentData['payment_number'],
            $paymentData['payment_date'],
            $paymentData['amount'],
            $paymentData['payment_method'],
            $paymentData['is_deposit'],
            $paymentData['notes']
        );
        
        $stmt->execute();
        
        // Update booking payment status
        $stmt = $conn->prepare("
            UPDATE " . DB_PREFIX . "hall_bookings 
            SET amount_paid = amount_paid + ?, 
                balance_due = total_amount - (amount_paid + ?),
                payment_status = CASE 
                    WHEN (amount_paid + ?) >= total_amount THEN 'Paid'
                    WHEN (amount_paid + ?) > 0 THEN 'Partial'
                    ELSE 'Pending'
                END,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->bind_param('ddddi', 
            $paymentData['amount'],
            $paymentData['amount'],
            $paymentData['amount'],
            $paymentData['amount'],
            $bookingId
        );
        
        $stmt->execute();
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error recording payment: " . $e->getMessage());
        return false;
    }
}

// --- Statistics Functions ---

/**
 * Get hall statistics
 */
function getHallStatistics($hallId, $startDate, $endDate) {
    $conn = getDB();
    
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_bookings,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COALESCE(SUM(duration_hours), 0) as total_hours,
            COALESCE(AVG(total_amount), 0) as avg_booking_value
        FROM " . DB_PREFIX . "hall_bookings 
        WHERE hall_id = ? 
        AND booking_status != 'Cancelled'
        AND start_date BETWEEN ? AND ?
    ");
    
    $stmt->bind_param('iss', $hallId, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    // Calculate occupancy rate (simplified)
    $totalDays = (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24);
    $occupancyRate = $totalDays > 0 ? min(100, ($result['total_hours'] / ($totalDays * 24)) * 100) : 0;
    
    return [
        'total_bookings' => (int)$result['total_bookings'],
        'total_revenue' => (float)$result['total_revenue'],
        'total_hours' => (float)$result['total_hours'],
        'avg_booking_value' => (float)$result['avg_booking_value'],
        'occupancy_rate' => round($occupancyRate, 1)
    ];
}

// --- Settings Functions ---

/**
 * Get hall setting
 */
function getHallSetting($key, $default = null) {
    $conn = getDB();
    
    $stmt = $conn->prepare("SELECT setting_value FROM " . DB_PREFIX . "hall_settings WHERE setting_key = ?");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result ? $result['setting_value'] : $default;
}

// --- Utility Functions ---

/**
 * Format currency
 */
function formatCurrency($amount, $currency = 'NGN') {
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * Format hall booking date/time
 */
function formatHallBookingDateTime($startDate, $startTime, $endDate, $endTime) {
    $start = date('M d, Y g:i A', strtotime($startDate . ' ' . $startTime));
    $end = date('M d, Y g:i A', strtotime($endDate . ' ' . $endTime));
    return $start . ' - ' . $end;
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
        case 'Pending': return 'badge-danger';
        case 'Refunded': return 'badge-info';
        default: return 'badge-secondary';
    }
}