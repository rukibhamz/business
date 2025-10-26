<?php
// includes/hall-functions.php
// Hall Booking System Helper Functions
// Business Management System - Phase 4

// --- Number Generation Functions ---

/**
 * Generate unique hall code
 */
function generateHallCode() {
    $db = new Database();
    $prefix = 'H';
    $year = date('Y');
    
    // Get the last hall code for this year
    $stmt = $db->prepare("SELECT hall_code FROM " . DB_PREFIX . "halls WHERE hall_code LIKE ? ORDER BY hall_code DESC LIMIT 1");
    $stmt->execute([$prefix . $year . '%']);
    $lastCode = $stmt->fetchColumn();
    
    if ($lastCode) {
        $lastNumber = (int)substr($lastCode, -4);
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    return $prefix . $year . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
}

/**
 * Generate unique booking number
 */
function generateBookingNumber() {
    $db = new Database();
    $prefix = 'HB';
    $year = date('Y');
    $month = date('m');
    
    // Get the last booking number for this month
    $stmt = $db->prepare("SELECT booking_number FROM " . DB_PREFIX . "hall_bookings WHERE booking_number LIKE ? ORDER BY booking_number DESC LIMIT 1");
    $stmt->execute([$prefix . $year . $month . '%']);
    $lastNumber = $stmt->fetchColumn();
    
    if ($lastNumber) {
        $lastSequence = (int)substr($lastNumber, -4);
        $newSequence = $lastSequence + 1;
    } else {
        $newSequence = 1;
    }
    
    return $prefix . $year . $month . str_pad($newSequence, 4, '0', STR_PAD_LEFT);
}

/**
 * Generate unique payment number
 */
function generatePaymentNumber() {
    $db = new Database();
    $prefix = 'HP';
    $year = date('Y');
    $month = date('m');
    
    $stmt = $db->prepare("SELECT payment_number FROM " . DB_PREFIX . "hall_booking_payments WHERE payment_number LIKE ? ORDER BY payment_number DESC LIMIT 1");
    $stmt->execute([$prefix . $year . $month . '%']);
    $lastNumber = $stmt->fetchColumn();
    
    if ($lastNumber) {
        $lastSequence = (int)substr($lastNumber, -4);
        $newSequence = $lastSequence + 1;
    } else {
        $newSequence = 1;
    }
    
    return $prefix . $year . $month . str_pad($newSequence, 4, '0', STR_PAD_LEFT);
}

// --- Availability Functions ---

/**
 * Check hall availability for a specific date and time
 */
function checkHallAvailability($hallId, $startDate, $startTime, $endDate, $endTime, $excludeBookingId = null) {
    $db = new Database();
    
    $sql = "SELECT COUNT(*) FROM " . DB_PREFIX . "hall_bookings 
            WHERE hall_id = ? 
            AND booking_status IN ('Confirmed', 'Pending')
            AND (
                (start_date = ? AND start_time < ? AND end_time > ?) OR
                (end_date = ? AND start_time < ? AND end_time > ?) OR
                (start_date <= ? AND end_date >= ?)
            )";
    
    $params = [$hallId, $startDate, $endTime, $startTime, $endDate, $endTime, $startTime, $startDate, $endDate];
    
    if ($excludeBookingId) {
        $sql .= " AND id != ?";
        $params[] = $excludeBookingId;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchColumn() == 0;
}

/**
 * Get conflicting bookings for a hall
 */
function getHallConflicts($hallId, $startDate, $startTime, $endDate, $endTime, $excludeBookingId = null) {
    $db = new Database();
    
    $sql = "SELECT * FROM " . DB_PREFIX . "hall_bookings 
            WHERE hall_id = ? 
            AND booking_status IN ('Confirmed', 'Pending')
            AND (
                (start_date = ? AND start_time < ? AND end_time > ?) OR
                (end_date = ? AND start_time < ? AND end_time > ?) OR
                (start_date <= ? AND end_date >= ?)
            )";
    
    $params = [$hallId, $startDate, $endTime, $startTime, $endDate, $endTime, $startTime, $startDate, $endDate];
    
    if ($excludeBookingId) {
        $sql .= " AND id != ?";
        $params[] = $excludeBookingId;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Check if hall is under maintenance
 */
function isHallUnderMaintenance($hallId, $date) {
    $db = new Database();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM " . DB_PREFIX . "hall_availability 
                          WHERE hall_id = ? AND date = ? AND status = 'Maintenance'");
    $stmt->execute([$hallId, $date]);
    
    return $stmt->fetchColumn() > 0;
}

// --- Pricing Functions ---

/**
 * Calculate hall rental cost based on duration and pricing
 */
function calculateHallRental($hallId, $startDate, $startTime, $endDate, $endTime) {
    $db = new Database();
    
    // Get hall pricing
    $stmt = $db->prepare("SELECT * FROM " . DB_PREFIX . "halls WHERE id = ?");
    $stmt->execute([$hallId]);
    $hall = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$hall) {
        return 0;
    }
    
    // Calculate duration in hours
    $startDateTime = new DateTime($startDate . ' ' . $startTime);
    $endDateTime = new DateTime($endDate . ' ' . $endTime);
    $duration = $endDateTime->diff($startDateTime);
    $totalHours = $duration->days * 24 + $duration->h + ($duration->i / 60);
    
    // Calculate days
    $totalDays = $duration->days + ($duration->h > 0 ? 1 : 0);
    
    // Determine pricing based on duration
    if ($totalDays >= 30) {
        return $hall['monthly_rate'] * floor($totalDays / 30) + ($hall['daily_rate'] * ($totalDays % 30));
    } elseif ($totalDays >= 7) {
        return $hall['weekly_rate'] * floor($totalDays / 7) + ($hall['daily_rate'] * ($totalDays % 7));
    } elseif ($totalDays >= 1) {
        return $hall['daily_rate'] * $totalDays;
    } else {
        return $hall['hourly_rate'] * $totalHours;
    }
}

/**
 * Get applicable pricing for a hall
 */
function getApplicablePricing($hallId, $startDate, $startTime, $endDate, $endTime) {
    $db = new Database();
    
    $stmt = $db->prepare("SELECT * FROM " . DB_PREFIX . "hall_booking_periods 
                          WHERE hall_id = ? AND is_active = 1 
                          ORDER BY sort_order ASC");
    $stmt->execute([$hallId]);
    $pricing = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate duration
    $startDateTime = new DateTime($startDate . ' ' . $startTime);
    $endDateTime = new DateTime($endDate . ' ' . $endTime);
    $duration = $endDateTime->diff($startDateTime);
    $totalHours = $duration->days * 24 + $duration->h + ($duration->i / 60);
    $totalDays = $duration->days + ($duration->h > 0 ? 1 : 0);
    
    // Find best pricing
    $bestPrice = null;
    $bestPricing = null;
    
    foreach ($pricing as $price) {
        $calculatedPrice = 0;
        
        switch ($price['period_type']) {
            case 'Hourly':
                $calculatedPrice = $price['price'] * $totalHours;
                break;
            case 'Daily':
                $calculatedPrice = $price['price'] * $totalDays;
                break;
            case 'Weekly':
                $calculatedPrice = $price['price'] * ceil($totalDays / 7);
                break;
            case 'Monthly':
                $calculatedPrice = $price['price'] * ceil($totalDays / 30);
                break;
        }
        
        if ($bestPrice === null || $calculatedPrice < $bestPrice) {
            $bestPrice = $calculatedPrice;
            $bestPricing = $price;
        }
    }
    
    return $bestPricing;
}

/**
 * Calculate booking totals
 */
function calculateBookingTotals($hallRental, $serviceFee = 0, $taxRate = 7.5, $discount = 0) {
    $subtotal = $hallRental + $serviceFee;
    $discountAmount = $discount;
    $taxableAmount = $subtotal - $discountAmount;
    $taxAmount = $taxableAmount * ($taxRate / 100);
    $totalAmount = $taxableAmount + $taxAmount;
    
    return [
        'subtotal' => $subtotal,
        'service_fee' => $serviceFee,
        'discount_amount' => $discountAmount,
        'tax_amount' => $taxAmount,
        'total_amount' => $totalAmount
    ];
}

// --- Booking Functions ---

/**
 * Create a new hall booking
 */
function createHallBooking($bookingData) {
    $db = new Database();
    
    try {
        $db->beginTransaction();
        
        // Generate booking number
        $bookingNumber = generateBookingNumber();
        
        // Calculate totals
        $totals = calculateBookingTotals(
            $bookingData['hall_rental'],
            $bookingData['service_fee'] ?? 0,
            $bookingData['tax_rate'] ?? 7.5,
            $bookingData['discount'] ?? 0
        );
        
        // Insert booking
        $stmt = $db->prepare("
            INSERT INTO " . DB_PREFIX . "hall_bookings (
                booking_number, hall_id, customer_id, event_name, event_type,
                start_date, start_time, end_date, end_time, duration_hours,
                attendee_count, subtotal, service_fee, tax_amount, total_amount,
                amount_paid, balance_due, payment_type, payment_status, booking_status,
                special_requirements, booking_source, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $bookingNumber,
            $bookingData['hall_id'],
            $bookingData['customer_id'] ?? null,
            $bookingData['event_name'] ?? null,
            $bookingData['event_type'] ?? null,
            $bookingData['start_date'],
            $bookingData['start_time'],
            $bookingData['end_date'],
            $bookingData['end_time'],
            $bookingData['duration_hours'],
            $bookingData['attendee_count'] ?? null,
            $totals['subtotal'],
            $totals['service_fee'],
            $totals['tax_amount'],
            $totals['total_amount'],
            $bookingData['amount_paid'] ?? 0,
            $totals['total_amount'] - ($bookingData['amount_paid'] ?? 0),
            $bookingData['payment_type'] ?? 'Full Payment',
            $bookingData['payment_status'] ?? 'Pending',
            $bookingData['booking_status'] ?? 'Pending',
            $bookingData['special_requirements'] ?? null,
            $bookingData['booking_source'] ?? 'Online',
            $bookingData['created_by'] ?? null
        ]);
        
        $bookingId = $db->getConnection()->lastInsertId();
        
        // Insert booking items if any
        if (isset($bookingData['items']) && is_array($bookingData['items'])) {
            foreach ($bookingData['items'] as $item) {
                $stmt = $db->prepare("
                    INSERT INTO " . DB_PREFIX . "hall_booking_items (
                        booking_id, item_name, item_description, quantity, unit_price, line_total
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $lineTotal = $item['quantity'] * $item['unit_price'];
                $stmt->execute([
                    $bookingId,
                    $item['item_name'],
                    $item['item_description'] ?? null,
                    $item['quantity'],
                    $item['unit_price'],
                    $lineTotal
                ]);
            }
        }
        
        // Create journal entry for accounting
        createHallBookingJournalEntry($bookingId);
        
        $db->commit();
        return $bookingId;
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Update booking status
 */
function updateBookingStatus($bookingId, $newStatus, $userId = null) {
    $db = new Database();
    
    $stmt = $db->prepare("UPDATE " . DB_PREFIX . "hall_bookings SET booking_status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$newStatus, $bookingId]);
    
    // Log activity
    if ($userId) {
        logActivity("Booking status updated to {$newStatus}", $bookingId, 'hall_booking');
    }
    
    return true;
}

/**
 * Cancel a booking
 */
function cancelBooking($bookingId, $reason, $refundAmount = 0) {
    $db = new Database();
    
    try {
        $db->beginTransaction();
        
        // Update booking status
        $stmt = $db->prepare("
            UPDATE " . DB_PREFIX . "hall_bookings 
            SET booking_status = 'Cancelled', cancelled_at = NOW(), cancellation_reason = ?
            WHERE id = ?
        ");
        $stmt->execute([$reason, $bookingId]);
        
        // Process refund if applicable
        if ($refundAmount > 0) {
            $stmt = $db->prepare("
                INSERT INTO " . DB_PREFIX . "hall_booking_payments (
                    booking_id, payment_date, amount, payment_method, status, notes
                ) VALUES (?, NOW(), ?, 'Refund', 'Completed', ?)
            ");
            $stmt->execute([$bookingId, -$refundAmount, "Refund for cancelled booking: {$reason}"]);
        }
        
        // Reverse journal entry
        reverseHallBookingEntry($bookingId);
        
        $db->commit();
        return true;
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Confirm a booking
 */
function confirmBooking($bookingId) {
    $db = new Database();
    
    $stmt = $db->prepare("UPDATE " . DB_PREFIX . "hall_bookings SET booking_status = 'Confirmed' WHERE id = ?");
    $stmt->execute([$bookingId]);
    
    // Send confirmation email
    sendBookingConfirmation($bookingId);
    
    return true;
}

/**
 * Complete a booking
 */
function completeBooking($bookingId) {
    $db = new Database();
    
    $stmt = $db->prepare("UPDATE " . DB_PREFIX . "hall_bookings SET booking_status = 'Completed', checked_out_at = NOW() WHERE id = ?");
    $stmt->execute([$bookingId]);
    
    return true;
}

// --- Payment Functions ---

/**
 * Record a payment for a booking
 */
function recordBookingPayment($bookingId, $amount, $method, $reference = null, $isDeposit = false) {
    $db = new Database();
    
    try {
        $db->beginTransaction();
        
        // Generate payment number
        $paymentNumber = generatePaymentNumber();
        
        // Insert payment record
        $stmt = $db->prepare("
            INSERT INTO " . DB_PREFIX . "hall_booking_payments (
                booking_id, payment_number, payment_date, amount, payment_method, 
                status, is_deposit, notes
            ) VALUES (?, ?, NOW(), ?, ?, 'Completed', ?, ?)
        ");
        
        $notes = $isDeposit ? 'Deposit payment' : 'Payment received';
        if ($reference) {
            $notes .= " - Reference: {$reference}";
        }
        
        $stmt->execute([$bookingId, $paymentNumber, $amount, $method, $isDeposit ? 1 : 0, $notes]);
        $paymentId = $db->getConnection()->lastInsertId();
        
        // Update booking payment status
        $stmt = $db->prepare("
            SELECT SUM(amount) FROM " . DB_PREFIX . "hall_booking_payments 
            WHERE booking_id = ? AND status = 'Completed'
        ");
        $stmt->execute([$bookingId]);
        $totalPaid = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT total_amount FROM " . DB_PREFIX . "hall_bookings WHERE id = ?");
        $stmt->execute([$bookingId]);
        $totalAmount = $stmt->fetchColumn();
        
        $balanceDue = $totalAmount - $totalPaid;
        $paymentStatus = $balanceDue <= 0 ? 'Paid' : ($totalPaid > 0 ? 'Partial' : 'Pending');
        
        $stmt = $db->prepare("
            UPDATE " . DB_PREFIX . "hall_bookings 
            SET amount_paid = ?, balance_due = ?, payment_status = ?
            WHERE id = ?
        ");
        $stmt->execute([$totalPaid, $balanceDue, $paymentStatus, $bookingId]);
        
        // Create accounting journal entry
        createPaymentJournalEntry($paymentId);
        
        $db->commit();
        return $paymentId;
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Calculate installment schedule
 */
function calculateInstallmentSchedule($totalAmount, $depositPercent, $numInstallments, $firstDate) {
    $depositAmount = ($totalAmount * $depositPercent) / 100;
    $remainingAmount = $totalAmount - $depositAmount;
    $installmentAmount = $remainingAmount / $numInstallments;
    
    $schedule = [];
    $schedule[] = [
        'type' => 'Deposit',
        'amount' => $depositAmount,
        'due_date' => $firstDate,
        'status' => 'Pending'
    ];
    
    $currentDate = new DateTime($firstDate);
    for ($i = 1; $i <= $numInstallments; $i++) {
        $currentDate->add(new DateInterval('P30D')); // Add 30 days
        $schedule[] = [
            'type' => 'Installment ' . $i,
            'amount' => $installmentAmount,
            'due_date' => $currentDate->format('Y-m-d'),
            'status' => 'Pending'
        ];
    }
    
    return $schedule;
}

/**
 * Check payment status
 */
function checkPaymentStatus($bookingId) {
    $db = new Database();
    
    $stmt = $db->prepare("
        SELECT 
            total_amount,
            amount_paid,
            balance_due,
            payment_status,
            payment_type
        FROM " . DB_PREFIX . "hall_bookings 
        WHERE id = ?
    ");
    $stmt->execute([$bookingId]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// --- Email Functions ---

/**
 * Send booking confirmation email
 */
function sendBookingConfirmation($bookingId) {
    $db = new Database();
    
    // Get booking details
    $stmt = $db->prepare("
        SELECT hb.*, h.hall_name, h.location, c.first_name, c.last_name, c.email
        FROM " . DB_PREFIX . "hall_bookings hb
        LEFT JOIN " . DB_PREFIX . "halls h ON hb.hall_id = h.id
        LEFT JOIN " . DB_PREFIX . "customers c ON hb.customer_id = c.id
        WHERE hb.id = ?
    ");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking || !$booking['email']) {
        return false;
    }
    
    // Get email template
    $stmt = $db->prepare("SELECT * FROM " . DB_PREFIX . "hall_email_templates WHERE template_type = 'Booking Confirmation' AND is_active = 1");
    $stmt->execute();
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$template) {
        return false;
    }
    
    // Replace template variables
    $subject = $template['subject'];
    $body = $template['body'];
    
    $replacements = [
        '{{customer_name}}' => $booking['first_name'] . ' ' . $booking['last_name'],
        '{{hall_name}}' => $booking['hall_name'],
        '{{booking_number}}' => $booking['booking_number'],
        '{{event_name}}' => $booking['event_name'],
        '{{booking_date}}' => date('M d, Y', strtotime($booking['start_date'])),
        '{{start_time}}' => date('g:i A', strtotime($booking['start_time'])),
        '{{end_time}}' => date('g:i A', strtotime($booking['end_time'])),
        '{{total_amount}}' => CURRENCY . ' ' . number_format($booking['total_amount'], 2)
    ];
    
    foreach ($replacements as $placeholder => $value) {
        $subject = str_replace($placeholder, $value, $subject);
        $body = str_replace($placeholder, $value, $body);
    }
    
    // Send email
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . ADMIN_EMAIL . "\r\n";
    
    return mail($booking['email'], $subject, $body, $headers);
}

/**
 * Send payment receipt email
 */
function sendPaymentReceipt($bookingId, $paymentId) {
    // Implementation similar to sendBookingConfirmation
    // Get payment details and send receipt email
    return true;
}

/**
 * Send booking reminder email
 */
function sendBookingReminder($bookingId) {
    // Implementation for booking reminder emails
    return true;
}

/**
 * Send payment reminder email
 */
function sendPaymentReminder($bookingId, $paymentDue) {
    // Implementation for payment reminder emails
    return true;
}

/**
 * Send cancellation email
 */
function sendCancellationEmail($bookingId) {
    // Implementation for cancellation emails
    return true;
}

// --- Calendar Functions ---

/**
 * Get hall calendar for a specific month
 */
function getHallCalendar($hallId, $month, $year) {
    $db = new Database();
    
    $startDate = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));
    
    $stmt = $db->prepare("
        SELECT 
            start_date, start_time, end_time, booking_status, event_name,
            customer_id, booking_number
        FROM " . DB_PREFIX . "hall_bookings 
        WHERE hall_id = ? 
        AND start_date BETWEEN ? AND ?
        AND booking_status IN ('Confirmed', 'Pending')
        ORDER BY start_date, start_time
    ");
    $stmt->execute([$hallId, $startDate, $endDate]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get all halls calendar for a specific month
 */
function getAllHallsCalendar($month, $year) {
    $db = new Database();
    
    $startDate = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));
    
    $stmt = $db->prepare("
        SELECT 
            hb.hall_id, h.hall_name, hb.start_date, hb.start_time, hb.end_time, 
            hb.booking_status, hb.event_name, hb.booking_number
        FROM " . DB_PREFIX . "hall_bookings hb
        LEFT JOIN " . DB_PREFIX . "halls h ON hb.hall_id = h.id
        WHERE hb.start_date BETWEEN ? AND ?
        AND hb.booking_status IN ('Confirmed', 'Pending')
        ORDER BY hb.start_date, hb.start_time
    ");
    $stmt->execute([$startDate, $endDate]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get upcoming bookings
 */
function getUpcomingBookings($hallId = null, $days = 30) {
    $db = new Database();
    
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime("+{$days} days"));
    
    $sql = "
        SELECT hb.*, h.hall_name, c.first_name, c.last_name, c.email
        FROM " . DB_PREFIX . "hall_bookings hb
        LEFT JOIN " . DB_PREFIX . "halls h ON hb.hall_id = h.id
        LEFT JOIN " . DB_PREFIX . "customers c ON hb.customer_id = c.id
        WHERE hb.start_date BETWEEN ? AND ?
        AND hb.booking_status IN ('Confirmed', 'Pending')
    ";
    
    $params = [$startDate, $endDate];
    
    if ($hallId) {
        $sql .= " AND hb.hall_id = ?";
        $params[] = $hallId;
    }
    
    $sql .= " ORDER BY hb.start_date, hb.start_time";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Document Generation ---

/**
 * Generate booking contract
 */
function generateBookingContract($bookingId) {
    // Implementation for generating booking contracts
    return true;
}

/**
 * Generate booking invoice
 */
function generateBookingInvoice($bookingId) {
    // Implementation for generating booking invoices
    return true;
}

// --- Accounting Integration ---

/**
 * Create journal entry for hall booking
 */
function createHallBookingJournalEntry($bookingId) {
    $db = new Database();
    
    try {
        // Get booking details
        $stmt = $db->prepare("SELECT * FROM " . DB_PREFIX . "hall_bookings WHERE id = ?");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            return false;
        }
        
        // Create journal entry
        $stmt = $db->prepare("
            INSERT INTO " . DB_PREFIX . "journal_entries (
                entry_number, entry_date, description, reference_type, reference_id, created_by
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $entryNumber = generateJournalNumber();
        $description = "Hall booking: " . $booking['booking_number'];
        
        $stmt->execute([
            $entryNumber,
            $booking['start_date'],
            $description,
            'hall_booking',
            $bookingId,
            $booking['created_by']
        ]);
        
        $journalEntryId = $db->getConnection()->lastInsertId();
        
        // Create journal entry lines
        // Debit: Accounts Receivable
        $stmt = $db->prepare("
            INSERT INTO " . DB_PREFIX . "journal_entry_lines (
                journal_entry_id, account_id, description, debit_amount, credit_amount
            ) VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $journalEntryId,
            2, // Accounts Receivable account
            $description,
            $booking['total_amount'],
            0
        ]);
        
        // Credit: Hall Rental Revenue
        $stmt = $db->prepare("
            SELECT id FROM " . DB_PREFIX . "accounts WHERE account_name = 'Hall Rental Revenue'
        ");
        $stmt->execute();
        $revenueAccountId = $stmt->fetchColumn();
        
        if ($revenueAccountId) {
            $stmt = $db->prepare("
                INSERT INTO " . DB_PREFIX . "journal_entry_lines (
                    journal_entry_id, account_id, description, debit_amount, credit_amount
                ) VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $journalEntryId,
                $revenueAccountId,
                $description,
                0,
                $booking['total_amount']
            ]);
        }
        
        return $journalEntryId;
        
    } catch (Exception $e) {
        error_log("Error creating hall booking journal entry: " . $e->getMessage());
        return false;
    }
}

/**
 * Reverse hall booking journal entry
 */
function reverseHallBookingEntry($bookingId) {
    // Implementation for reversing journal entries when booking is cancelled
    return true;
}

/**
 * Create payment journal entry
 */
function createPaymentJournalEntry($paymentId) {
    // Implementation for creating payment journal entries
    return true;
}

// --- Statistics Functions ---

/**
 * Get hall statistics
 */
function getHallStatistics($hallId, $startDate, $endDate) {
    $db = new Database();
    
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN booking_status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
            SUM(CASE WHEN booking_status = 'Completed' THEN 1 ELSE 0 END) as completed_bookings,
            SUM(CASE WHEN booking_status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
            SUM(total_amount) as total_revenue,
            SUM(amount_paid) as total_paid,
            AVG(total_amount) as avg_booking_value
        FROM " . DB_PREFIX . "hall_bookings 
        WHERE hall_id = ? 
        AND start_date BETWEEN ? AND ?
    ");
    $stmt->execute([$hallId, $startDate, $endDate]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get booking revenue
 */
function getBookingRevenue($startDate, $endDate, $hallId = null) {
    $db = new Database();
    
    $sql = "
        SELECT 
            SUM(total_amount) as total_revenue,
            SUM(amount_paid) as total_paid,
            COUNT(*) as total_bookings
        FROM " . DB_PREFIX . "hall_bookings 
        WHERE start_date BETWEEN ? AND ?
        AND booking_status IN ('Confirmed', 'Completed')
    ";
    
    $params = [$startDate, $endDate];
    
    if ($hallId) {
        $sql .= " AND hall_id = ?";
        $params[] = $hallId;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get hall utilization
 */
function getHallUtilization($hallId, $month, $year) {
    $db = new Database();
    
    $startDate = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));
    
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as booking_count,
            SUM(duration_hours) as total_hours_booked
        FROM " . DB_PREFIX . "hall_bookings 
        WHERE hall_id = ? 
        AND start_date BETWEEN ? AND ?
        AND booking_status IN ('Confirmed', 'Completed')
    ");
    $stmt->execute([$hallId, $startDate, $endDate]);
    
    $bookings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate total available hours in the month
    $daysInMonth = date('t', strtotime($startDate));
    $totalAvailableHours = $daysInMonth * 24;
    
    $utilization = $totalAvailableHours > 0 ? ($bookings['total_hours_booked'] / $totalAvailableHours) * 100 : 0;
    
    return [
        'booking_count' => $bookings['booking_count'],
        'total_hours_booked' => $bookings['total_hours_booked'],
        'total_available_hours' => $totalAvailableHours,
        'utilization_percentage' => round($utilization, 2)
    ];
}

// --- Utility Functions ---

/**
 * Format currency
 */
function formatCurrency($amount, $currency = 'NGN') {
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * Get customer display name
 */
function getCustomerDisplayName($customerId) {
    $db = new Database();
    
    $stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name FROM " . DB_PREFIX . "customers WHERE id = ?");
    $stmt->execute([$customerId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['full_name'] : 'Unknown Customer';
}

/**
 * Get hall display name
 */
function getHallDisplayName($hallId) {
    $db = new Database();
    
    $stmt = $db->prepare("SELECT hall_name FROM " . DB_PREFIX . "halls WHERE id = ?");
    $stmt->execute([$hallId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['hall_name'] : 'Unknown Hall';
}

/**
 * Get hall setting
 */
function getHallSetting($key, $default = null) {
    $db = new Database();
    
    $stmt = $db->prepare("SELECT setting_value FROM " . DB_PREFIX . "hall_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['setting_value'] : $default;
}

/**
 * Update hall setting
 */
function updateHallSetting($key, $value) {
    $db = new Database();
    
    $stmt = $db->prepare("
        INSERT INTO " . DB_PREFIX . "hall_settings (setting_key, setting_value) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    
    return $stmt->execute([$key, $value]);
}
?>
