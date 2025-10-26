-- Phase 4: Hall Booking System Database Schema
-- Business Management System
-- This file contains all database tables for the hall booking system

-- Hall Categories Table
CREATE TABLE `bms_hall_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `description` text,
  `icon` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_name` (`category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default hall categories
INSERT INTO `bms_hall_categories` (`category_name`, `description`, `icon`) VALUES
('Conference Hall', 'Large halls for conferences and seminars', 'icon-conference'),
('Wedding Hall', 'Elegant halls for weddings and celebrations', 'icon-wedding'),
('Meeting Room', 'Small to medium meeting rooms', 'icon-meeting'),
('Banquet Hall', 'Halls for banquets and formal dinners', 'icon-banquet'),
('Exhibition Hall', 'Spaces for exhibitions and trade shows', 'icon-exhibition'),
('Training Hall', 'Halls for training and workshops', 'icon-training'),
('Party Hall', 'Halls for parties and social events', 'icon-party'),
('Other', 'Other types of halls', 'icon-other');

-- Halls Table (Properties)
CREATE TABLE `bms_halls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hall_code` varchar(50) NOT NULL,
  `hall_name` varchar(200) NOT NULL,
  `category_id` int(11) NOT NULL,
  `description` longtext,
  `capacity` int(11) NOT NULL,
  `area_sqft` decimal(10,2) DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `address` text,
  `amenities` json DEFAULT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `gallery_images` json DEFAULT NULL,
  `hourly_rate` decimal(10,2) DEFAULT NULL,
  `daily_rate` decimal(10,2) DEFAULT NULL,
  `weekly_rate` decimal(10,2) DEFAULT NULL,
  `monthly_rate` decimal(10,2) DEFAULT NULL,
  `currency` varchar(3) DEFAULT 'NGN',
  `status` enum('Available','Maintenance','Unavailable') DEFAULT 'Available',
  `is_featured` tinyint(1) DEFAULT 0,
  `enable_booking` tinyint(1) DEFAULT 1,
  `booking_advance_days` int(11) DEFAULT 30,
  `cancellation_policy` text,
  `terms_conditions` text,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hall_code` (`hall_code`),
  KEY `category_id` (`category_id`),
  KEY `created_by` (`created_by`),
  KEY `status` (`status`),
  CONSTRAINT `bms_halls_category_fk` FOREIGN KEY (`category_id`) REFERENCES `bms_hall_categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `bms_halls_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `bms_users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hall Booking Periods Table (for different pricing periods)
CREATE TABLE `bms_hall_booking_periods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hall_id` int(11) NOT NULL,
  `period_name` varchar(100) NOT NULL,
  `period_type` enum('Hourly','Daily','Weekly','Monthly') NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'NGN',
  `min_duration` int(11) DEFAULT 1,
  `max_duration` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `hall_id` (`hall_id`),
  KEY `is_active` (`is_active`),
  CONSTRAINT `bms_hall_booking_periods_hall_fk` FOREIGN KEY (`hall_id`) REFERENCES `bms_halls` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hall Bookings Table
CREATE TABLE `bms_hall_bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_number` varchar(50) NOT NULL,
  `hall_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `booking_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `event_name` varchar(200) DEFAULT NULL,
  `event_type` varchar(100) DEFAULT NULL,
  `start_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_date` date NOT NULL,
  `end_time` time NOT NULL,
  `duration_hours` decimal(5,2) NOT NULL,
  `attendee_count` int(11) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `service_fee` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `amount_paid` decimal(10,2) DEFAULT 0.00,
  `balance_due` decimal(10,2) NOT NULL,
  `payment_type` enum('Full Payment','Partial Payment') DEFAULT 'Full Payment',
  `payment_status` enum('Pending','Partial','Paid','Refunded') DEFAULT 'Pending',
  `booking_status` enum('Pending','Confirmed','Cancelled','Completed') DEFAULT 'Pending',
  `payment_schedule` json DEFAULT NULL,
  `special_requirements` text,
  `booking_source` enum('Admin','Online','Phone') DEFAULT 'Online',
  `invoice_id` int(11) DEFAULT NULL,
  `confirmation_sent` tinyint(1) DEFAULT 0,
  `reminder_sent` tinyint(1) DEFAULT 0,
  `checked_in_at` datetime DEFAULT NULL,
  `checked_out_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_number` (`booking_number`),
  KEY `hall_id` (`hall_id`),
  KEY `customer_id` (`customer_id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `created_by` (`created_by`),
  KEY `booking_status` (`booking_status`),
  KEY `payment_status` (`payment_status`),
  KEY `start_date` (`start_date`),
  CONSTRAINT `bms_hall_bookings_hall_fk` FOREIGN KEY (`hall_id`) REFERENCES `bms_halls` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `bms_hall_bookings_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `bms_customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bms_hall_bookings_invoice_fk` FOREIGN KEY (`invoice_id`) REFERENCES `bms_invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bms_hall_bookings_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `bms_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hall Booking Items Table (for additional services)
CREATE TABLE `bms_hall_booking_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `item_name` varchar(200) NOT NULL,
  `item_description` text,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `line_total` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  CONSTRAINT `bms_hall_booking_items_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bms_hall_bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hall Booking Payments Table
CREATE TABLE `bms_hall_booking_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `payment_number` varchar(50) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `status` enum('Pending','Completed','Failed') DEFAULT 'Pending',
  `is_deposit` tinyint(1) DEFAULT 0,
  `due_date` date DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `payment_id` (`payment_id`),
  KEY `status` (`status`),
  CONSTRAINT `bms_hall_booking_payments_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bms_hall_bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bms_hall_booking_payments_payment_fk` FOREIGN KEY (`payment_id`) REFERENCES `bms_payments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hall Availability Table (for checking conflicts)
CREATE TABLE `bms_hall_availability` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hall_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('Available','Booked','Maintenance','Blocked') DEFAULT 'Available',
  `booking_id` int(11) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `hall_id` (`hall_id`),
  KEY `date` (`date`),
  KEY `booking_id` (`booking_id`),
  CONSTRAINT `bms_hall_availability_hall_fk` FOREIGN KEY (`hall_id`) REFERENCES `bms_halls` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bms_hall_availability_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bms_hall_bookings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hall Promo Codes Table (for discounts)
CREATE TABLE `bms_hall_promo_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `description` text,
  `discount_type` enum('Percentage','Fixed Amount') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `valid_from` datetime NOT NULL,
  `valid_to` datetime NOT NULL,
  `max_uses` int(11) DEFAULT NULL,
  `times_used` int(11) DEFAULT 0,
  `applicable_halls` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `is_active` (`is_active`),
  KEY `valid_from` (`valid_from`),
  KEY `valid_to` (`valid_to`),
  CONSTRAINT `bms_hall_promo_codes_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `bms_users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hall Email Templates Table
CREATE TABLE `bms_hall_email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_name` varchar(100) NOT NULL,
  `template_type` enum('Booking Confirmation','Payment Received','Payment Reminder','Booking Reminder','Booking Cancelled','Booking Update') NOT NULL,
  `subject` varchar(200) NOT NULL,
  `body` longtext NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `template_name` (`template_name`),
  KEY `template_type` (`template_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default email templates
INSERT INTO `bms_hall_email_templates` (`template_name`, `template_type`, `subject`, `body`) VALUES
('Hall Booking Confirmation', 'Booking Confirmation', 'Hall Booking Confirmed - {{hall_name}}', '<h2>Hall Booking Confirmed!</h2><p>Dear {{customer_name}},</p><p>Your hall booking for <strong>{{hall_name}}</strong> has been confirmed.</p><p><strong>Booking Details:</strong></p><ul><li>Booking Number: {{booking_number}}</li><li>Hall: {{hall_name}}</li><li>Event: {{event_name}}</li><li>Date: {{booking_date}}</li><li>Time: {{start_time}} - {{end_time}}</li><li>Total Amount: {{total_amount}}</li></ul><p>Thank you for your booking!</p>'),
('Hall Payment Received', 'Payment Received', 'Payment Received - {{booking_number}}', '<h2>Payment Received</h2><p>Dear {{customer_name}},</p><p>We have received your payment for hall booking {{booking_number}}.</p><p><strong>Payment Details:</strong></p><ul><li>Amount: {{payment_amount}}</li><li>Payment Method: {{payment_method}}</li><li>Date: {{payment_date}}</li></ul><p>Thank you!</p>'),
('Hall Booking Reminder', 'Booking Reminder', 'Upcoming Hall Booking Reminder - {{hall_name}}', '<h2>Booking Reminder</h2><p>Dear {{customer_name}},</p><p>This is a reminder that you have an upcoming hall booking:</p><p><strong>{{hall_name}}</strong></p><p>Event: {{event_name}}<br>Date: {{booking_date}}<br>Time: {{start_time}} - {{end_time}}</p><p>We look forward to hosting your event!</p>');

-- Hall Settings Table
CREATE TABLE `bms_hall_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` enum('text','number','boolean','json') DEFAULT 'text',
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default hall settings
INSERT INTO `bms_hall_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('service_fee_percentage', '2.5', 'number', 'Default service fee percentage for hall bookings'),
('service_fee_fixed', '0', 'number', 'Fixed service fee amount'),
('tax_rate', '7.5', 'number', 'Default tax rate (VAT) for hall bookings'),
('min_deposit_percentage', '30', 'number', 'Minimum deposit percentage for partial payments'),
('max_installments', '3', 'number', 'Maximum number of installments allowed'),
('booking_confirmation_email', '1', 'boolean', 'Send booking confirmation emails'),
('payment_reminder_email', '1', 'boolean', 'Send payment reminder emails'),
('booking_reminder_email', '1', 'boolean', 'Send booking reminder emails'),
('auto_generate_invoice', '1', 'boolean', 'Automatically generate invoices for hall bookings'),
('auto_create_journal_entry', '1', 'boolean', 'Automatically create journal entries for hall bookings'),
('default_currency', 'NGN', 'text', 'Default currency for hall bookings'),
('booking_timeout_minutes', '15', 'number', 'Booking timeout in minutes for online payments'),
('enable_promo_codes', '1', 'boolean', 'Enable promo codes and discounts'),
('default_booking_advance_days', '30', 'number', 'Default advance booking days'),
('enable_online_booking', '1', 'boolean', 'Enable online hall booking'),
('require_customer_registration', '0', 'boolean', 'Require customer registration for bookings');

-- Add permissions for hall management
INSERT INTO `bms_permissions` (`name`, `display_name`, `module`, `description`) VALUES
('halls.view', 'View Halls', 'halls', 'View halls and hall details'),
('halls.create', 'Create Halls', 'halls', 'Create new halls'),
('halls.edit', 'Edit Halls', 'halls', 'Edit existing halls'),
('halls.delete', 'Delete Halls', 'halls', 'Delete halls'),
('halls.bookings', 'Manage Hall Bookings', 'halls', 'View and manage hall bookings'),
('halls.reports', 'Hall Reports', 'halls', 'View hall reports and analytics'),
('halls.settings', 'Hall Settings', 'halls', 'Manage hall module settings');

-- Grant permissions to Super Admin and Admin roles
INSERT INTO `bms_role_permissions` (`role_id`, `permission_id`) VALUES
(1, (SELECT id FROM `bms_permissions` WHERE name = 'halls.view')),
(1, (SELECT id FROM `bms_permissions` WHERE name = 'halls.create')),
(1, (SELECT id FROM `bms_permissions` WHERE name = 'halls.edit')),
(1, (SELECT id FROM `bms_permissions` WHERE name = 'halls.delete')),
(1, (SELECT id FROM `bms_permissions` WHERE name = 'halls.bookings')),
(1, (SELECT id FROM `bms_permissions` WHERE name = 'halls.reports')),
(1, (SELECT id FROM `bms_permissions` WHERE name = 'halls.settings')),
(2, (SELECT id FROM `bms_permissions` WHERE name = 'halls.view')),
(2, (SELECT id FROM `bms_permissions` WHERE name = 'halls.create')),
(2, (SELECT id FROM `bms_permissions` WHERE name = 'halls.edit')),
(2, (SELECT id FROM `bms_permissions` WHERE name = 'halls.bookings')),
(2, (SELECT id FROM `bms_permissions` WHERE name = 'halls.reports'));

-- Add Hall Revenue account to chart of accounts
INSERT INTO `bms_accounts` (`account_code`, `account_name`, `account_type`, `account_subtype`, `parent_id`, `description`, `opening_balance`, `current_balance`, `is_active`) VALUES
('4001', 'Hall Rental Revenue', 'Income', 'Operating Income', NULL, 'Revenue from hall rentals and bookings', 0.00, 0.00, 1);

-- Add Hall Expenses account
INSERT INTO `bms_accounts` (`account_code`, `account_name`, `account_type`, `account_subtype`, `parent_id`, `description`, `opening_balance`, `current_balance`, `is_active`) VALUES
('5001', 'Hall Maintenance Expenses', 'Expense', 'Operating Expense', NULL, 'Expenses related to hall maintenance and operations', 0.00, 0.00, 1);
