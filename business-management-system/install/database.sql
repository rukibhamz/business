-- Business Management System - Database Schema
-- Phase 1: Core Foundation

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

-- Insert default roles
INSERT INTO `bms_roles` (`name`, `description`, `is_system`) VALUES
('Super Admin', 'Full system access with all permissions', 1),
('Admin', 'Administrative access with most permissions', 1),
('Manager', 'Management access with limited permissions', 0),
('User', 'Basic user access with minimal permissions', 0);

-- Insert default permissions
INSERT INTO `bms_permissions` (`name`, `display_name`, `module`, `description`) VALUES
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
('activity.view', 'View Activity Logs', 'activity', 'View system activity logs');

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