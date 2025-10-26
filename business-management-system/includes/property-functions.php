<?php
/**
 * Business Management System - Property Management Functions
 * Phase 5: Property Management & Rent Expiry System
 */

// Prevent direct access
if (!defined('BMS_SYSTEM')) {
    die('Direct access not allowed');
}

/**
 * Generate unique property code
 */
function generatePropertyCode() {
    global $conn;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM " . DB_PREFIX . "properties");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $nextNumber = $result['count'] + 1;
    return 'PROP-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
}

/**
 * Generate unique tenant code
 */
function generateTenantCode() {
    global $conn;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM " . DB_PREFIX . "tenants");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $nextNumber = $result['count'] + 1;
    return 'TEN-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
}

/**
 * Generate unique lease number
 */
function generateLeaseNumber() {
    global $conn;
    
    $year = date('Y');
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM " . DB_PREFIX . "leases WHERE YEAR(created_at) = ?");
    $stmt->bind_param('s', $year);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $nextNumber = $result['count'] + 1;
    return 'LSE-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
}

/**
 * Generate unique maintenance request number
 */
function generateMaintenanceRequestNumber() {
    global $conn;
    
    $year = date('Y');
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM " . DB_PREFIX . "maintenance_requests WHERE YEAR(created_at) = ?");
    $stmt->bind_param('s', $year);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $nextNumber = $result['count'] + 1;
    return 'MNT-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
}

/**
 * Generate unique inspection number
 */
function generateInspectionNumber() {
    global $conn;
    
    $year = date('Y');
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM " . DB_PREFIX . "property_inspections WHERE YEAR(created_at) = ?");
    $stmt->bind_param('s', $year);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $nextNumber = $result['count'] + 1;
    return 'INS-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
}

/**
 * Get available properties with filters
 */
function getAvailableProperties($filters = []) {
    global $conn;
    
    $sql = "SELECT p.*, pt.type_name, 
                   (SELECT COUNT(*) FROM " . DB_PREFIX . "leases l WHERE l.property_id = p.id AND l.lease_status = 'Active') as active_leases
            FROM " . DB_PREFIX . "properties p
            JOIN " . DB_PREFIX . "property_types pt ON p.property_type_id = pt.id
            WHERE p.property_status = 'Active' AND p.availability_status = 'Available'";
    
    $params = [];
    $types = '';
    
    if (!empty($filters['property_type'])) {
        $sql .= " AND p.property_type_id = ?";
        $params[] = $filters['property_type'];
        $types .= 'i';
    }
    
    if (!empty($filters['min_rent'])) {
        $sql .= " AND p.monthly_rent >= ?";
        $params[] = $filters['min_rent'];
        $types .= 'd';
    }
    
    if (!empty($filters['max_rent'])) {
        $sql .= " AND p.monthly_rent <= ?";
        $params[] = $filters['max_rent'];
        $types .= 'd';
    }
    
    if (!empty($filters['city'])) {
        $sql .= " AND p.city = ?";
        $params[] = $filters['city'];
        $types .= 's';
    }
    
    if (!empty($filters['bedrooms'])) {
        $sql .= " AND p.bedrooms >= ?";
        $params[] = $filters['bedrooms'];
        $types .= 'i';
    }
    
    $sql .= " ORDER BY p.is_featured DESC, p.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get property occupancy rate
 */
function getPropertyOccupancyRate() {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_properties,
            SUM(CASE WHEN availability_status = 'Occupied' THEN 1 ELSE 0 END) as occupied_properties
        FROM " . DB_PREFIX . "properties 
        WHERE property_status = 'Active'
    ");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['total_properties'] > 0) {
        return round(($result['occupied_properties'] / $result['total_properties']) * 100, 2);
    }
    
    return 0;
}

/**
 * Get property revenue for a period
 */
function getPropertyRevenue($propertyId, $startDate, $endDate) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT SUM(total_amount) as total_revenue
        FROM " . DB_PREFIX . "rent_payments 
        WHERE property_id = ? 
        AND payment_date BETWEEN ? AND ?
        AND payment_status = 'Completed'
    ");
    $stmt->bind_param('iss', $propertyId, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['total_revenue'] ?? 0;
}

/**
 * Update property availability status
 */
function updatePropertyAvailability($propertyId, $status) {
    global $conn;
    
    $stmt = $conn->prepare("
        UPDATE " . DB_PREFIX . "properties 
        SET availability_status = ?, updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $stmt->bind_param('si', $status, $propertyId);
    
    return $stmt->execute();
}

/**
 * Get tenant active leases
 */
function getTenantActiveLeases($tenantId) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT l.*, p.property_name, p.property_code, p.address
        FROM " . DB_PREFIX . "leases l
        JOIN " . DB_PREFIX . "properties p ON l.property_id = p.id
        WHERE l.tenant_id = ? AND l.lease_status = 'Active'
        ORDER BY l.start_date DESC
    ");
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get tenant payment history
 */
function getTenantPaymentHistory($tenantId) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT rp.*, p.property_name, l.lease_number
        FROM " . DB_PREFIX . "rent_payments rp
        JOIN " . DB_PREFIX . "properties p ON rp.property_id = p.id
        JOIN " . DB_PREFIX . "leases l ON rp.lease_id = l.id
        WHERE rp.tenant_id = ?
        ORDER BY rp.payment_date DESC
    ");
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get tenant outstanding balance
 */
function getTenantOutstandingBalance($tenantId) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            SUM(l.monthly_rent * l.lease_term_months) as total_expected,
            COALESCE(SUM(rp.total_amount), 0) as total_paid
        FROM " . DB_PREFIX . "leases l
        LEFT JOIN " . DB_PREFIX . "rent_payments rp ON l.id = rp.lease_id AND rp.payment_status = 'Completed'
        WHERE l.tenant_id = ? AND l.lease_status = 'Active'
    ");
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return max(0, $result['total_expected'] - $result['total_paid']);
}

/**
 * Check if tenant is blacklisted
 */
function isTenantBlacklisted($tenantId) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT status FROM " . DB_PREFIX . "tenants WHERE id = ?");
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['status'] === 'Blacklisted';
}

/**
 * Create new lease
 */
function createLease($leaseData) {
    global $conn;
    
    $conn->begin_transaction();
    
    try {
        // Generate lease number
        $leaseNumber = generateLeaseNumber();
        
        // Calculate end date
        $startDate = $leaseData['start_date'];
        $months = $leaseData['lease_term_months'];
        $endDate = date('Y-m-d', strtotime($startDate . " +{$months} months"));
        
        // Calculate total upfront payment
        $totalUpfront = ($leaseData['security_deposit'] ?? 0) + 
                       ($leaseData['agency_fee'] ?? 0) + 
                       ($leaseData['legal_fee'] ?? 0) + 
                       ($leaseData['service_charge'] ?? 0);
        
        $stmt = $conn->prepare("
            INSERT INTO " . DB_PREFIX . "leases (
                lease_number, property_id, tenant_id, lease_type, start_date, end_date,
                lease_term_months, monthly_rent, security_deposit, agency_fee, legal_fee,
                service_charge, total_upfront_payment, rent_payment_day, rent_payment_frequency,
                grace_period_days, late_fee_percentage, payment_method, auto_renewal,
                renewal_notice_days, terms_conditions, special_clauses, lease_status,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param('siisssiddddddiisssissssi',
            $leaseNumber,
            $leaseData['property_id'],
            $leaseData['tenant_id'],
            $leaseData['lease_type'],
            $startDate,
            $endDate,
            $months,
            $leaseData['monthly_rent'],
            $leaseData['security_deposit'],
            $leaseData['agency_fee'],
            $leaseData['legal_fee'],
            $leaseData['service_charge'],
            $totalUpfront,
            $leaseData['rent_payment_day'],
            $leaseData['rent_payment_frequency'],
            $leaseData['grace_period_days'],
            $leaseData['late_fee_percentage'],
            $leaseData['payment_method'],
            $leaseData['auto_renewal'],
            $leaseData['renewal_notice_days'],
            $leaseData['terms_conditions'],
            $leaseData['special_clauses'],
            $leaseData['lease_status'],
            $leaseData['created_by']
        );
        
        $stmt->execute();
        /** @var mysqli $conn */
        $leaseId = $conn->insert_id;
        
        // Log lease creation
        logLeaseHistory($leaseId, 'Created', 'Lease created', null, json_encode($leaseData), $leaseData['created_by']);
        
        $conn->commit();
        return $leaseId;
        
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

/**
 * Activate lease
 */
function activateLease($leaseId) {
    global $conn;
    
    $conn->begin_transaction();
    
    try {
        // Get lease details
        $stmt = $conn->prepare("SELECT * FROM " . DB_PREFIX . "leases WHERE id = ?");
        $stmt->bind_param('i', $leaseId);
        $stmt->execute();
        $lease = $stmt->get_result()->fetch_assoc();
        
        if (!$lease) {
            throw new Exception('Lease not found');
        }
        
        // Update lease status
        $stmt = $conn->prepare("
            UPDATE " . DB_PREFIX . "leases 
            SET lease_status = 'Active', activation_date = CURDATE()
            WHERE id = ?
        ");
        $stmt->bind_param('i', $leaseId);
        $stmt->execute();
        
        // Update property availability
        updatePropertyAvailability($lease['property_id'], 'Occupied');
        
        // Create reminder schedule
        createReminderSchedule($leaseId);
        
        // Log activation
        logLeaseHistory($leaseId, 'Activated', 'Lease activated', 'Draft', 'Active', $_SESSION['user_id']);
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

/**
 * Calculate lease end date
 */
function calculateLeaseEndDate($startDate, $months) {
    return date('Y-m-d', strtotime($startDate . " +{$months} months"));
}

/**
 * Get expiring leases
 */
function getExpiringLeases($days = 30) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT l.*, p.property_name, p.address, t.first_name, t.last_name, t.email, t.phone
        FROM " . DB_PREFIX . "leases l
        JOIN " . DB_PREFIX . "properties p ON l.property_id = p.id
        JOIN " . DB_PREFIX . "tenants t ON l.tenant_id = t.id
        WHERE l.lease_status = 'Active' 
        AND l.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
        ORDER BY l.end_date ASC
    ");
    $stmt->bind_param('i', $days);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Check lease expiry (for cron job)
 */
function checkLeaseExpiry() {
    global $conn;
    
    $expiredLeases = [];
    
    // Find expired leases
    $stmt = $conn->prepare("
        SELECT id FROM " . DB_PREFIX . "leases 
        WHERE lease_status = 'Active' AND end_date < CURDATE()
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        expireLease($row['id']);
        $expiredLeases[] = $row['id'];
    }
    
    return $expiredLeases;
}

/**
 * Expire lease
 */
function expireLease($leaseId) {
    global $conn;
    
    $conn->begin_transaction();
    
    try {
        // Get lease details
        $stmt = $conn->prepare("SELECT * FROM " . DB_PREFIX . "leases WHERE id = ?");
        $stmt->bind_param('i', $leaseId);
        $stmt->execute();
        $lease = $stmt->get_result()->fetch_assoc();
        
        // Update lease status
        $stmt = $conn->prepare("
            UPDATE " . DB_PREFIX . "leases 
            SET lease_status = 'Expired'
            WHERE id = ?
        ");
        $stmt->bind_param('i', $leaseId);
        $stmt->execute();
        
        // Update property availability
        updatePropertyAvailability($lease['property_id'], 'Available');
        
        // Log expiry
        logLeaseHistory($leaseId, 'Expired', 'Lease expired automatically', 'Active', 'Expired', null);
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

/**
 * Record rent payment
 */
function recordRentPayment($paymentData) {
    global $conn;
    
    $conn->begin_transaction();
    
    try {
        // Generate payment number
        $paymentNumber = 'RENT-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $stmt = $conn->prepare("
            INSERT INTO " . DB_PREFIX . "rent_payments (
                payment_number, lease_id, property_id, tenant_id, payment_date,
                payment_type, period_from, period_to, amount, late_fee, total_amount,
                payment_method, reference_number, bank_account_id, receipt_number,
                notes, payment_status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param('siiissssddsssssssi',
            $paymentNumber,
            $paymentData['lease_id'],
            $paymentData['property_id'],
            $paymentData['tenant_id'],
            $paymentData['payment_date'],
            $paymentData['payment_type'],
            $paymentData['period_from'],
            $paymentData['period_to'],
            $paymentData['amount'],
            $paymentData['late_fee'],
            $paymentData['total_amount'],
            $paymentData['payment_method'],
            $paymentData['reference_number'],
            $paymentData['bank_account_id'],
            $paymentData['receipt_number'],
            $paymentData['notes'],
            $paymentData['payment_status'],
            $paymentData['created_by']
        );
        
        $stmt->execute();
        /** @var mysqli $conn */
        $paymentId = $conn->insert_id;
        
        // Create accounting entry
        createRentRevenueEntry($paymentId);
        
        $conn->commit();
        return $paymentId;
        
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

/**
 * Create rent revenue entry in accounting
 */
function createRentRevenueEntry($paymentId) {
    global $conn;
    
    // Get payment details
    $stmt = $conn->prepare("
        SELECT rp.*, p.property_name, l.lease_number
        FROM " . DB_PREFIX . "rent_payments rp
        JOIN " . DB_PREFIX . "properties p ON rp.property_id = p.id
        JOIN " . DB_PREFIX . "leases l ON rp.lease_id = l.id
        WHERE rp.id = ?
    ");
    $stmt->bind_param('i', $paymentId);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    
    if (!$payment) return false;
    
    // Create journal entry
    $description = "Rent payment - {$payment['property_name']} - {$payment['lease_number']}";
    
    // Debit Bank Account
    $stmt = $conn->prepare("
        INSERT INTO " . DB_PREFIX . "journal_entries (
            entry_date, reference, description, account_id, debit_amount, credit_amount, created_by
        ) VALUES (?, ?, ?, ?, ?, 0, ?)
    ");
    $stmt->bind_param('sssdi', 
        $payment['payment_date'], 
        $payment['payment_number'], 
        $description,
        $payment['bank_account_id'],
        $payment['total_amount'],
        $_SESSION['user_id']
    );
    $stmt->execute();
    
    // Credit Rental Income
    $rentalIncomeAccount = getAccountByCode('RENTAL_INCOME'); // You'll need to implement this
    if ($rentalIncomeAccount) {
        $stmt = $conn->prepare("
            INSERT INTO " . DB_PREFIX . "journal_entries (
                entry_date, reference, description, account_id, debit_amount, credit_amount, created_by
            ) VALUES (?, ?, ?, ?, 0, ?, ?)
        ");
        $stmt->bind_param('sssdi', 
            $payment['payment_date'], 
            $payment['payment_number'], 
            $description,
            $rentalIncomeAccount['id'],
            $payment['total_amount'],
            $_SESSION['user_id']
        );
        $stmt->execute();
    }
    
    return true;
}

/**
 * Create reminder schedule for lease
 */
function createReminderSchedule($leaseId) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT end_date FROM " . DB_PREFIX . "leases WHERE id = ?");
    $stmt->bind_param('i', $leaseId);
    $stmt->execute();
    $lease = $stmt->get_result()->fetch_assoc();
    
    if (!$lease) return false;
    
    $endDate = $lease['end_date'];
    $reminderDays = [30, 15, 7, 1];
    
    foreach ($reminderDays as $days) {
        $reminderDate = date('Y-m-d', strtotime($endDate . " -{$days} days"));
        
        $stmt = $conn->prepare("
            INSERT INTO " . DB_PREFIX . "rent_reminders (
                lease_id, tenant_id, reminder_type, reminder_date, days_before, status
            ) VALUES (?, ?, 'Expiry Alert', ?, ?, 'Pending')
        ");
        
        // Get tenant_id from lease
        $tenantStmt = $conn->prepare("SELECT tenant_id FROM " . DB_PREFIX . "leases WHERE id = ?");
        $tenantStmt->bind_param('i', $leaseId);
        $tenantStmt->execute();
        $tenantId = $tenantStmt->get_result()->fetch_assoc()['tenant_id'];
        
        $stmt->bind_param('iisi', $leaseId, $tenantId, $reminderDate, $days);
        $stmt->execute();
    }
    
    return true;
}

/**
 * Process reminders (for cron job)
 */
function processReminders() {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT rr.*, l.lease_number, p.property_name, t.first_name, t.last_name, t.email
        FROM " . DB_PREFIX . "rent_reminders rr
        JOIN " . DB_PREFIX . "leases l ON rr.lease_id = l.id
        JOIN " . DB_PREFIX . "properties p ON l.property_id = p.id
        JOIN " . DB_PREFIX . "tenants t ON rr.tenant_id = t.id
        WHERE rr.status = 'Pending' AND rr.reminder_date <= CURDATE()
    ");
    $stmt->execute();
    $reminders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $sentCount = 0;
    
    foreach ($reminders as $reminder) {
        if (sendExpiryReminder($reminder['lease_id'], $reminder['days_before'])) {
            // Update reminder status
            $updateStmt = $conn->prepare("
                UPDATE " . DB_PREFIX . "rent_reminders 
                SET status = 'Sent', sent_at = NOW() 
                WHERE id = ?
            ");
            $updateStmt->bind_param('i', $reminder['id']);
            $updateStmt->execute();
            
            $sentCount++;
        }
    }
    
    return $sentCount;
}

/**
 * Send expiry reminder email
 */
function sendExpiryReminder($leaseId, $daysBeforeExpiry) {
    global $conn;
    
    // Get lease details
    $stmt = $conn->prepare("
        SELECT l.*, p.property_name, p.address, t.first_name, t.last_name, t.email
        FROM " . DB_PREFIX . "leases l
        JOIN " . DB_PREFIX . "properties p ON l.property_id = p.id
        JOIN " . DB_PREFIX . "tenants t ON l.tenant_id = t.id
        WHERE l.id = ?
    ");
    $stmt->bind_param('i', $leaseId);
    $stmt->execute();
    $lease = $stmt->get_result()->fetch_assoc();
    
    if (!$lease || !$lease['email']) {
        return false;
    }
    
    $subject = "Lease Expiry Reminder - {$daysBeforeExpiry} Days Notice";
    $message = "
        Dear {$lease['first_name']} {$lease['last_name']},
        
        This is a reminder that your lease for {$lease['property_name']} will expire in {$daysBeforeExpiry} days.
        
        Lease Details:
        - Property: {$lease['property_name']}
        - Address: {$lease['address']}
        - Lease End Date: " . date('F d, Y', strtotime($lease['end_date'])) . "
        
        Please contact us to discuss renewal options or arrange for move-out procedures.
        
        Best regards,
        Property Management Team
    ";
    
    // Send email (implement your email sending logic)
    return mail($lease['email'], $subject, $message);
}

/**
 * Log lease history
 */
function logLeaseHistory($leaseId, $action, $description, $oldValue = null, $newValue = null, $performedBy = null) {
    global $conn;
    
    $stmt = $conn->prepare("
        INSERT INTO " . DB_PREFIX . "lease_history (
            lease_id, action, description, old_value, new_value, performed_by
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('issssi', $leaseId, $action, $description, $oldValue, $newValue, $performedBy);
    $stmt->execute();
    /** @var mysqli $conn */
    return $conn->insert_id;
}

/**
 * Create maintenance request
 */
function createMaintenanceRequest($requestData) {
    global $conn;
    
    $conn->begin_transaction();
    
    try {
        $requestNumber = generateMaintenanceRequestNumber();
        
        $stmt = $conn->prepare("
            INSERT INTO " . DB_PREFIX . "maintenance_requests (
                request_number, property_id, lease_id, tenant_id, request_type,
                priority, title, description, location_in_property, reported_date,
                preferred_date, preferred_time, images, is_emergency, requires_entry,
                entry_permission_granted, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param('siiisssssssssiiii',
            $requestNumber,
            $requestData['property_id'],
            $requestData['lease_id'],
            $requestData['tenant_id'],
            $requestData['request_type'],
            $requestData['priority'],
            $requestData['title'],
            $requestData['description'],
            $requestData['location_in_property'],
            $requestData['reported_date'],
            $requestData['preferred_date'],
            $requestData['preferred_time'],
            $requestData['images'],
            $requestData['is_emergency'],
            $requestData['requires_entry'],
            $requestData['entry_permission_granted'],
            $requestData['created_by']
        );
        
        $stmt->execute();
        /** @var mysqli $conn */
        $requestId = $conn->insert_id;
        
        $conn->commit();
        return $requestId;
        
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

/**
 * Get property maintenance history
 */
function getPropertyMaintenanceHistory($propertyId) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT mr.*, t.first_name, t.last_name, l.lease_number
        FROM " . DB_PREFIX . "maintenance_requests mr
        LEFT JOIN " . DB_PREFIX . "tenants t ON mr.tenant_id = t.id
        LEFT JOIN " . DB_PREFIX . "leases l ON mr.lease_id = l.id
        WHERE mr.property_id = ?
        ORDER BY mr.reported_date DESC
    ");
    $stmt->bind_param('i', $propertyId);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Schedule inspection
 */
function scheduleInspection($inspectionData) {
    global $conn;
    
    $conn->begin_transaction();
    
    try {
        $inspectionNumber = generateInspectionNumber();
        
        $stmt = $conn->prepare("
            INSERT INTO " . DB_PREFIX . "property_inspections (
                inspection_number, property_id, lease_id, tenant_id, inspection_type,
                inspection_date, inspection_time, inspector_name, inspector_id,
                tenant_present, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param('siiissssii',
            $inspectionNumber,
            $inspectionData['property_id'],
            $inspectionData['lease_id'],
            $inspectionData['tenant_id'],
            $inspectionData['inspection_type'],
            $inspectionData['inspection_date'],
            $inspectionData['inspection_time'],
            $inspectionData['inspector_name'],
            $inspectionData['inspector_id'],
            $inspectionData['tenant_present'],
            $inspectionData['created_by']
        );
        
        $stmt->execute();
        /** @var mysqli $conn */
        $inspectionId = $conn->insert_id;
        
        $conn->commit();
        return $inspectionId;
        
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

/**
 * Upload property document
 */
function uploadPropertyDocument($file, $documentData) {
    global $conn;
    
    $uploadDir = '../../uploads/property-documents/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileName = time() . '_' . $file['name'];
    $filePath = $uploadDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        $stmt = $conn->prepare("
            INSERT INTO " . DB_PREFIX . "property_documents (
                property_id, lease_id, tenant_id, document_type, document_name,
                file_path, file_size, file_type, description, upload_date, uploaded_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)
        ");
        
        $stmt->bind_param('iiisssissi',
            $documentData['property_id'],
            $documentData['lease_id'],
            $documentData['tenant_id'],
            $documentData['document_type'],
            $documentData['document_name'],
            $filePath,
            $file['size'],
            $file['type'],
            $documentData['description'],
            $documentData['uploaded_by']
        );
        
        $stmt->execute();
        /** @var mysqli $conn */
        return $conn->insert_id;
    }
    
    return false;
}

/**
 * Get property documents
 */
function getPropertyDocuments($propertyId) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT * FROM " . DB_PREFIX . "property_documents 
        WHERE property_id = ? 
        ORDER BY upload_date DESC
    ");
    $stmt->bind_param('i', $propertyId);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Format currency for display
 */
function formatCurrency($amount, $currency = 'NGN') {
    if ($currency === 'NGN') {
        return '₦' . number_format($amount, 2);
    }
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * Get hall booking status badge class
 */
function getHallBookingStatusBadgeClass($status) {
    switch ($status) {
        case 'Pending': return 'badge-warning';
        case 'Confirmed': return 'badge-info';
        case 'Completed': return 'badge-success';
        case 'Cancelled': return 'badge-danger';
        default: return 'badge-secondary';
    }
}

/**
 * Get hall payment status badge class
 */
function getHallPaymentStatusBadgeClass($status) {
    switch ($status) {
        case 'Completed': return 'badge-success';
        case 'Pending': return 'badge-warning';
        case 'Failed': return 'badge-danger';
        case 'Refunded': return 'badge-info';
        default: return 'badge-secondary';
    }
}
