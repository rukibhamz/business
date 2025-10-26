-- Business Management System - Complete Database Schema
-- Phases 1-4: Core Foundation, User Management, Accounting, Hall Booking

-- ==============================================
-- PHASE 1: CORE FOUNDATION TABLES
-- ==============================================

-- Users table
CREATE TABLE IF NOT EXISTS `bms_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `role_id` int(11) NOT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_role_id` (`role_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Roles table
CREATE TABLE IF NOT EXISTS `bms_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL UNIQUE,
  `description` text,
  `is_system` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions table
CREATE TABLE IF NOT EXISTS `bms_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL UNIQUE,
  `display_name` varchar(100) NOT NULL,
  `module` varchar(50) NOT NULL,
  `description` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role permissions table
CREATE TABLE IF NOT EXISTS `bms_role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_role_permission` (`role_id`, `permission_id`),
  KEY `idx_role_id` (`role_id`),
  KEY `idx_permission_id` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings table
CREATE TABLE IF NOT EXISTS `bms_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL UNIQUE,
  `setting_value` text,
  `setting_type` enum('text','number','boolean','json') DEFAULT 'text',
  `description` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity logs table
CREATE TABLE IF NOT EXISTS `bms_activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `module` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_module` (`module`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================
-- PHASE 3: ACCOUNTING SYSTEM TABLES
-- ==============================================

-- Chart of Accounts
CREATE TABLE IF NOT EXISTS `bms_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_code` varchar(20) NOT NULL UNIQUE,
  `account_name` varchar(100) NOT NULL,
  `account_type` enum('Asset','Liability','Equity','Income','Expense') NOT NULL,
  `account_subtype` varchar(50) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `description` text,
  `opening_balance` decimal(15,2) DEFAULT 0.00,
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_account_code` (`account_code`),
  KEY `idx_account_type` (`account_type`),
  KEY `idx_parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customers
CREATE TABLE IF NOT EXISTS `bms_customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_code` varchar(20) NOT NULL UNIQUE,
  `company_name` varchar(100) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `outstanding_balance` decimal(15,2) DEFAULT 0.00,
  `credit_limit` decimal(15,2) DEFAULT 0.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customer_code` (`customer_code`),
  KEY `idx_email` (`email`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invoices
CREATE TABLE IF NOT EXISTS `bms_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL UNIQUE,
  `customer_id` int(11) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `discount_percentage` decimal(5,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `tax_percentage` decimal(5,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(15,2) DEFAULT 0.00,
  `balance_due` decimal(15,2) DEFAULT 0.00,
  `status` enum('Draft','Sent','Paid','Overdue','Cancelled') DEFAULT 'Draft',
  `notes` text,
  `terms_conditions` text,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_invoice_number` (`invoice_number`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_invoice_date` (`invoice_date`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invoice Items
CREATE TABLE IF NOT EXISTS `bms_invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `item_name` varchar(200) NOT NULL,
  `description` text,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `line_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_invoice_id` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments
CREATE TABLE IF NOT EXISTS `bms_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_number` varchar(50) NOT NULL UNIQUE,
  `customer_id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `payment_method` enum('Cash','Bank Transfer','Cheque','Card','Online') NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `reference` varchar(100) DEFAULT NULL,
  `notes` text,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_payment_number` (`payment_number`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_invoice_id` (`invoice_id`),
  KEY `idx_payment_date` (`payment_date`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Expense Categories
CREATE TABLE IF NOT EXISTS `bms_expense_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL UNIQUE,
  `description` text,
  `default_account_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category_name` (`category_name`),
  KEY `idx_default_account_id` (`default_account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Expenses
CREATE TABLE IF NOT EXISTS `bms_expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expense_number` varchar(50) NOT NULL UNIQUE,
  `category_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('Cash','Bank Transfer','Cheque','Card') NOT NULL,
  `vendor` varchar(100) DEFAULT NULL,
  `description` text,
  `receipt_file` varchar(255) DEFAULT NULL,
  `expense_date` date NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `is_billable` tinyint(1) DEFAULT 0,
  `customer_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_expense_number` (`expense_number`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_account_id` (`account_id`),
  KEY `idx_expense_date` (`expense_date`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal Entries
CREATE TABLE IF NOT EXISTS `bms_journal_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `journal_number` varchar(50) NOT NULL UNIQUE,
  `entry_date` date NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `description` text NOT NULL,
  `total_debit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_credit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('Draft','Posted','Cancelled') DEFAULT 'Draft',
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_journal_number` (`journal_number`),
  KEY `idx_entry_date` (`entry_date`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal Entry Lines
CREATE TABLE IF NOT EXISTS `bms_journal_entry_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `journal_entry_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `description` text,
  `debit_amount` decimal(15,2) DEFAULT 0.00,
  `credit_amount` decimal(15,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_journal_entry_id` (`journal_entry_id`),
  KEY `idx_account_id` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tax Rates
CREATE TABLE IF NOT EXISTS `bms_tax_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tax_name` varchar(100) NOT NULL,
  `tax_code` varchar(20) NOT NULL UNIQUE,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `tax_type` enum('Percentage','Fixed') DEFAULT 'Percentage',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tax_code` (`tax_code`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================
-- PHASE 4: HALL BOOKING SYSTEM TABLES
-- ==============================================

-- Hall Categories
CREATE TABLE IF NOT EXISTS `bms_hall_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL UNIQUE,
  `description` text,
  `icon` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category_name` (`category_name`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Halls
CREATE TABLE IF NOT EXISTS `bms_halls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hall_code` varchar(20) NOT NULL UNIQUE,
  `hall_name` varchar(100) NOT NULL,
  `category_id` int(11) NOT NULL,
  `description` text,
  `capacity` int(11) NOT NULL DEFAULT 0,
  `area` decimal(10,2) DEFAULT NULL,
  `amenities` text,
  `hourly_rate` decimal(15,2) DEFAULT 0.00,
  `daily_rate` decimal(15,2) DEFAULT 0.00,
  `weekly_rate` decimal(15,2) DEFAULT 0.00,
  `monthly_rate` decimal(15,2) DEFAULT 0.00,
  `featured_image` varchar(255) DEFAULT NULL,
  `gallery_images` text,
  `location` varchar(200) DEFAULT NULL,
  `status` enum('Available','Maintenance','Unavailable') DEFAULT 'Available',
  `is_featured` tinyint(1) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hall_code` (`hall_code`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_status` (`status`),
  KEY `idx_is_featured` (`is_featured`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hall Booking Periods
CREATE TABLE IF NOT EXISTS `bms_hall_booking_periods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hall_id` int(11) NOT NULL,
  `period_name` varchar(50) NOT NULL,
  `period_type` enum('Hourly','Daily','Weekly','Monthly') NOT NULL,
  `duration_hours` int(11) DEFAULT NULL,
  `price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hall_id` (`hall_id`),
  KEY `idx_period_type` (`period_type`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hall Bookings
CREATE TABLE IF NOT EXISTS `bms_hall_bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_number` varchar(50) NOT NULL UNIQUE,
  `hall_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `event_name` varchar(200) NOT NULL,
  `event_type` varchar(100) DEFAULT NULL,
  `start_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_date` date NOT NULL,
  `end_time` time NOT NULL,
  `duration_hours` decimal(5,2) NOT NULL DEFAULT 0.00,
  `check_in_time` datetime DEFAULT NULL,
  `check_out_time` datetime DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `service_fee` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(15,2) DEFAULT 0.00,
  `balance_due` decimal(15,2) DEFAULT 0.00,
  `payment_type` enum('Full Payment','Partial Payment') DEFAULT 'Full Payment',
  `payment_status` enum('Pending','Partial','Paid','Refunded') DEFAULT 'Pending',
  `booking_status` enum('Pending','Confirmed','Cancelled','Completed') DEFAULT 'Pending',
  `special_requirements` text,
  `booking_source` enum('Admin','Online','Phone') DEFAULT 'Online',
  `invoice_id` int(11) DEFAULT NULL,
  `confirmation_sent` tinyint(1) DEFAULT 0,
  `reminder_sent` tinyint(1) DEFAULT 0,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_booking_number` (`booking_number`),
  KEY `idx_hall_id` (`hall_id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_start_date` (`start_date`),
  KEY `idx_booking_status` (`booking_status`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_invoice_id` (`invoice_id`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hall Booking Items
CREATE TABLE IF NOT EXISTS `bms_hall_booking_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `item_name` varchar(200) NOT NULL,
  `description` text,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_booking_id` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hall Booking Payments
CREATE TABLE IF NOT EXISTS `bms_hall_booking_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `payment_number` varchar(50) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('Cash','Bank Transfer','Card','Online') NOT NULL,
  `status` enum('Pending','Completed','Failed') DEFAULT 'Pending',
  `is_deposit` tinyint(1) DEFAULT 0,
  `due_date` date DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_payment_id` (`payment_id`),
  KEY `idx_payment_number` (`payment_number`),
  KEY `idx_payment_date` (`payment_date`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hall Availability
CREATE TABLE IF NOT EXISTS `bms_hall_availability` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hall_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('Available','Booked','Maintenance','Blocked') DEFAULT 'Available',
  `booking_id` int(11) DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hall_id` (`hall_id`),
  KEY `idx_date` (`date`),
  KEY `idx_status` (`status`),
  KEY `idx_booking_id` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hall Promo Codes
CREATE TABLE IF NOT EXISTS `bms_hall_promo_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL UNIQUE,
  `description` text,
  `discount_type` enum('Percentage','Fixed') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `min_amount` decimal(15,2) DEFAULT 0.00,
  `max_discount` decimal(15,2) DEFAULT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `times_used` int(11) DEFAULT 0,
  `valid_from` date NOT NULL,
  `valid_until` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_code` (`code`),
  KEY `idx_valid_from` (`valid_from`),
  KEY `idx_valid_until` (`valid_until`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hall Email Templates
CREATE TABLE IF NOT EXISTS `bms_hall_email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_name` varchar(100) NOT NULL UNIQUE,
  `subject` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_template_name` (`template_name`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hall Settings
CREATE TABLE IF NOT EXISTS `bms_hall_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL UNIQUE,
  `setting_value` text,
  `setting_type` enum('text','number','boolean','json') DEFAULT 'text',
  `description` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================
-- INSERT DEFAULT DATA
-- ==============================================

-- Insert default roles
INSERT INTO `bms_roles` (`name`, `description`, `is_system`) VALUES
('Super Admin', 'Full system access with all permissions', 1),
('Admin', 'Administrative access with most permissions', 1),
('Manager', 'Management access with limited permissions', 0),
('User', 'Basic user access with minimal permissions', 0);

-- Insert default permissions (Phase 1-4)
INSERT INTO `bms_permissions` (`name`, `display_name`, `module`, `description`) VALUES
-- Core permissions
('dashboard.view', 'View Dashboard', 'dashboard', 'Access to the main dashboard'),
('users.view', 'View Users', 'users', 'View user list and details'),
('users.create', 'Create Users', 'users', 'Create new users'),
('users.edit', 'Edit Users', 'users', 'Edit existing users'),
('users.delete', 'Delete Users', 'users', 'Delete users'),
('roles.view', 'View Roles', 'roles', 'View roles and permissions'),
('roles.create', 'Create Roles', 'roles', 'Create new roles'),
('roles.edit', 'Edit Roles', 'roles', 'Edit existing roles'),
('roles.delete', 'Delete Roles', 'roles', 'Delete roles'),
('settings.view', 'View Settings', 'settings', 'View system settings'),
('settings.edit', 'Edit Settings', 'settings', 'Modify system settings'),
('activity.view', 'View Activity Logs', 'activity', 'View system activity logs'),
-- Accounting permissions
('accounts.view', 'View Accounts', 'accounting', 'View chart of accounts'),
('accounts.create', 'Create Accounts', 'accounting', 'Create new accounts'),
('accounts.edit', 'Edit Accounts', 'accounting', 'Edit existing accounts'),
('accounts.delete', 'Delete Accounts', 'accounting', 'Delete accounts'),
('invoices.view', 'View Invoices', 'accounting', 'View invoices'),
('invoices.create', 'Create Invoices', 'accounting', 'Create new invoices'),
('invoices.edit', 'Edit Invoices', 'accounting', 'Edit existing invoices'),
('invoices.delete', 'Delete Invoices', 'accounting', 'Delete invoices'),
('payments.view', 'View Payments', 'accounting', 'View payments'),
('payments.create', 'Create Payments', 'accounting', 'Create new payments'),
('payments.edit', 'Edit Payments', 'accounting', 'Edit existing payments'),
('expenses.view', 'View Expenses', 'accounting', 'View expenses'),
('expenses.create', 'Create Expenses', 'accounting', 'Create new expenses'),
('expenses.edit', 'Edit Expenses', 'accounting', 'Edit existing expenses'),
('expenses.approve', 'Approve Expenses', 'accounting', 'Approve expense requests'),
('reports.view', 'View Reports', 'accounting', 'View financial reports'),
('journal.view', 'View Journal Entries', 'accounting', 'View journal entries'),
('journal.create', 'Create Journal Entries', 'accounting', 'Create journal entries'),
-- Hall booking permissions
('halls.view', 'View Halls', 'halls', 'View halls'),
('halls.create', 'Create Halls', 'halls', 'Create new halls'),
('halls.edit', 'Edit Halls', 'halls', 'Edit existing halls'),
('halls.delete', 'Delete Halls', 'halls', 'Delete halls'),
('halls.bookings.view', 'View Bookings', 'halls', 'View hall bookings'),
('halls.bookings.create', 'Create Bookings', 'halls', 'Create hall bookings'),
('halls.bookings.edit', 'Edit Bookings', 'halls', 'Edit hall bookings'),
('halls.bookings.cancel', 'Cancel Bookings', 'halls', 'Cancel hall bookings'),
('halls.categories.view', 'View Categories', 'halls', 'View hall categories'),
('halls.categories.create', 'Create Categories', 'halls', 'Create hall categories'),
('halls.categories.edit', 'Edit Categories', 'halls', 'Edit hall categories'),
('halls.reports.view', 'View Hall Reports', 'halls', 'View hall reports');

-- Insert default role permissions (Super Admin gets all permissions)
INSERT INTO `bms_role_permissions` (`role_id`, `permission_id`) 
SELECT 1, id FROM `bms_permissions`;

-- Insert default role permissions (Admin gets most permissions except user deletion)
INSERT INTO `bms_role_permissions` (`role_id`, `permission_id`) 
SELECT 2, id FROM `bms_permissions` WHERE name != 'users.delete';

-- Insert default settings
INSERT INTO `bms_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('company_name', 'Business Management System', 'text', 'Company name'),
('company_email', 'admin@example.com', 'text', 'Company email address'),
('company_phone', '+234 000 000 0000', 'text', 'Company phone number'),
('site_url', 'http://localhost', 'text', 'Site URL'),
('timezone', 'Africa/Lagos', 'text', 'System timezone'),
('date_format', 'Y-m-d', 'text', 'Date format'),
('currency', 'NGN', 'text', 'Default currency'),
('records_per_page', '25', 'number', 'Default records per page'),
('session_timeout', '3600', 'number', 'Session timeout in seconds'),
('maintenance_mode', '0', 'boolean', 'Maintenance mode status');

-- Insert default chart of accounts
INSERT INTO `bms_accounts` (`account_code`, `account_name`, `account_type`, `account_subtype`, `description`, `opening_balance`, `current_balance`) VALUES
-- Assets
('1000', 'Cash', 'Asset', 'Current Asset', 'Cash on hand', 0.00, 0.00),
('1100', 'Bank Account', 'Asset', 'Current Asset', 'Main bank account', 0.00, 0.00),
('1200', 'Accounts Receivable', 'Asset', 'Current Asset', 'Money owed by customers', 0.00, 0.00),
('1300', 'Inventory', 'Asset', 'Current Asset', 'Stock inventory', 0.00, 0.00),
('1400', 'Equipment', 'Asset', 'Fixed Asset', 'Office equipment', 0.00, 0.00),
-- Liabilities
('2000', 'Accounts Payable', 'Liability', 'Current Liability', 'Money owed to suppliers', 0.00, 0.00),
('2100', 'Tax Payable', 'Liability', 'Current Liability', 'Taxes owed', 0.00, 0.00),
('2200', 'Loans Payable', 'Liability', 'Long-term Liability', 'Bank loans', 0.00, 0.00),
-- Equity
('3000', 'Owner Equity', 'Equity', 'Capital', 'Owner investment', 0.00, 0.00),
('3100', 'Retained Earnings', 'Equity', 'Retained Earnings', 'Accumulated profits', 0.00, 0.00),
-- Income
('4000', 'Sales Revenue', 'Income', 'Operating Revenue', 'Revenue from sales', 0.00, 0.00),
('4100', 'Hall Rental Revenue', 'Income', 'Operating Revenue', 'Revenue from hall rentals', 0.00, 0.00),
('4200', 'Service Revenue', 'Income', 'Operating Revenue', 'Revenue from services', 0.00, 0.00),
-- Expenses
('5000', 'Cost of Goods Sold', 'Expense', 'Operating Expense', 'Direct costs', 0.00, 0.00),
('5100', 'Rent Expense', 'Expense', 'Operating Expense', 'Office rent', 0.00, 0.00),
('5200', 'Utilities Expense', 'Expense', 'Operating Expense', 'Electricity, water, etc.', 0.00, 0.00),
('5300', 'Salaries Expense', 'Expense', 'Operating Expense', 'Employee salaries', 0.00, 0.00),
('5400', 'Marketing Expense', 'Expense', 'Operating Expense', 'Advertising and marketing', 0.00, 0.00);

-- Insert default tax rates
INSERT INTO `bms_tax_rates` (`tax_name`, `tax_code`, `tax_rate`, `tax_type`) VALUES
('VAT', 'VAT', 7.50, 'Percentage'),
('Withholding Tax', 'WHT', 5.00, 'Percentage'),
('Service Tax', 'ST', 2.50, 'Percentage');

-- Insert default expense categories
INSERT INTO `bms_expense_categories` (`category_name`, `description`, `default_account_id`) VALUES
('Office Supplies', 'Stationery and office materials', 5),
('Travel & Transport', 'Business travel expenses', 5),
('Marketing & Advertising', 'Promotional activities', 5),
('Utilities', 'Electricity, water, internet', 5),
('Professional Services', 'Legal, accounting, consulting', 5),
('Equipment & Maintenance', 'Equipment purchase and repair', 5),
('Training & Development', 'Staff training programs', 5),
('Insurance', 'Business insurance premiums', 5);

-- Insert default hall categories
INSERT INTO `bms_hall_categories` (`category_name`, `description`, `icon`) VALUES
('Conference Hall', 'Large halls for conferences and meetings', 'fas fa-users'),
('Meeting Room', 'Small rooms for team meetings', 'fas fa-handshake'),
('Event Hall', 'Spacious halls for events and parties', 'fas fa-calendar-alt'),
('Training Room', 'Rooms equipped for training sessions', 'fas fa-chalkboard-teacher'),
('Auditorium', 'Large halls with stage and seating', 'fas fa-theater-masks'),
('Boardroom', 'Executive meeting rooms', 'fas fa-building'),
('Seminar Room', 'Medium-sized rooms for seminars', 'fas fa-presentation'),
('Workshop Space', 'Flexible spaces for workshops', 'fas fa-tools');

-- Insert default hall email templates
INSERT INTO `bms_hall_email_templates` (`template_name`, `subject`, `body`) VALUES
('booking_confirmation', 'Booking Confirmed - {{event_name}}', 'Dear {{customer_name}},\n\nYour hall booking has been confirmed!\n\nEvent: {{event_name}}\nHall: {{hall_name}}\nDate: {{booking_date}}\nTime: {{start_time}} - {{end_time}}\nTotal Amount: {{total_amount}}\n\nThank you for choosing our services!\n\nBest regards,\n{{company_name}}'),
('payment_received', 'Payment Received - {{booking_number}}', 'Dear {{customer_name}},\n\nWe have received your payment for booking {{booking_number}}.\n\nPayment Amount: {{payment_amount}}\nPayment Method: {{payment_method}}\nPayment Date: {{payment_date}}\n\nThank you for your payment!\n\nBest regards,\n{{company_name}}'),
('booking_reminder', 'Upcoming Event Reminder - {{event_name}}', 'Dear {{customer_name}},\n\nThis is a reminder about your upcoming event:\n\nEvent: {{event_name}}\nHall: {{hall_name}}\nDate: {{booking_date}}\nTime: {{start_time}} - {{end_time}}\n\nPlease arrive 15 minutes early for check-in.\n\nBest regards,\n{{company_name}}');

-- Insert default hall settings
INSERT INTO `bms_hall_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('booking_advance_days', '30', 'number', 'Maximum days in advance for booking'),
('cancellation_hours', '24', 'number', 'Hours before event for cancellation'),
('service_fee_percentage', '5.00', 'number', 'Service fee percentage'),
('tax_rate', '7.50', 'number', 'Default tax rate for bookings'),
('auto_confirm_bookings', '1', 'boolean', 'Automatically confirm online bookings'),
('require_deposit', '0', 'boolean', 'Require deposit for bookings'),
('deposit_percentage', '30.00', 'number', 'Deposit percentage if required'),
('send_reminder_emails', '1', 'boolean', 'Send reminder emails before events'),
('reminder_hours', '24', 'number', 'Hours before event to send reminder');