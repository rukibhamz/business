-- Business Management System Database Schema
-- Phase 1: Core Foundation Tables

-- Set charset and collation
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================
-- 1. ROLES TABLE
-- =============================================
CREATE TABLE `bms_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text,
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default roles
INSERT INTO `bms_roles` (`id`, `name`, `description`, `is_system`) VALUES
(1, 'Super Admin', 'Full system access with all permissions', 1),
(2, 'Admin', 'Administrative access with most permissions', 1),
(3, 'Manager', 'Management level access for specific modules', 1),
(4, 'Staff', 'Staff level access for daily operations', 1),
(5, 'Customer', 'Customer access for self-service features', 1);

-- =============================================
-- 2. USERS TABLE
-- =============================================
CREATE TABLE `bms_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_role` (`role_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `bms_roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 3. SETTINGS TABLE
-- =============================================
CREATE TABLE `bms_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` varchar(50) NOT NULL DEFAULT 'text',
  `category` varchar(50) NOT NULL DEFAULT 'general',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_category` (`category`),
  KEY `idx_type` (`setting_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO `bms_settings` (`setting_key`, `setting_value`, `setting_type`, `category`) VALUES
('site_name', 'Business Management System', 'text', 'general'),
('site_url', '', 'text', 'general'),
('admin_email', '', 'email', 'general'),
('default_currency', 'USD', 'text', 'general'),
('timezone', 'UTC', 'text', 'general'),
('date_format', 'Y-m-d', 'text', 'general'),
('time_format', 'H:i:s', 'text', 'general'),
('items_per_page', '25', 'number', 'general'),
('maintenance_mode', '0', 'boolean', 'general'),
('registration_enabled', '0', 'boolean', 'general'),
('email_notifications', '1', 'boolean', 'general'),
('session_timeout', '3600', 'number', 'security'),
('max_login_attempts', '5', 'number', 'security'),
('password_min_length', '8', 'number', 'security'),
('require_strong_password', '1', 'boolean', 'security'),
('two_factor_auth', '0', 'boolean', 'security'),
('backup_enabled', '1', 'boolean', 'system'),
('backup_frequency', 'daily', 'text', 'system'),
('log_level', 'info', 'text', 'system'),
('cache_enabled', '1', 'boolean', 'system');

-- =============================================
-- 4. ACTIVITY LOGS TABLE
-- =============================================
CREATE TABLE `bms_activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`),
  KEY `idx_ip` (`ip_address`),
  CONSTRAINT `fk_activity_logs_user` FOREIGN KEY (`user_id`) REFERENCES `bms_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 5. SESSIONS TABLE (for better session management)
-- =============================================
CREATE TABLE `bms_sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `data` text,
  `last_activity` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_last_activity` (`last_activity`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `bms_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 6. LOGIN ATTEMPTS TABLE (for security)
-- =============================================
CREATE TABLE `bms_login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `attempts` int(11) NOT NULL DEFAULT '1',
  `last_attempt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `blocked_until` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_username` (`username`),
  KEY `idx_blocked` (`blocked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 7. SYSTEM LOGS TABLE (for error tracking)
-- =============================================
CREATE TABLE `bms_system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level` varchar(20) NOT NULL DEFAULT 'info',
  `message` text NOT NULL,
  `context` text,
  `file` varchar(255) DEFAULT NULL,
  `line` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_level` (`level`),
  KEY `idx_created` (`created_at`),
  KEY `idx_file` (`file`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 8. MODULES TABLE (for future module management)
-- =============================================
CREATE TABLE `bms_modules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text,
  `version` varchar(20) NOT NULL DEFAULT '1.0.0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_core` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_active` (`is_active`),
  KEY `idx_core` (`is_core`),
  KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert core modules
INSERT INTO `bms_modules` (`name`, `title`, `description`, `version`, `is_active`, `is_core`, `sort_order`) VALUES
('core', 'Core System', 'Core system functionality and authentication', '1.0.0', 1, 1, 1),
('dashboard', 'Dashboard', 'Main dashboard and overview', '1.0.0', 1, 1, 2),
('users', 'User Management', 'User and role management system', '1.0.0', 1, 1, 3),
('settings', 'System Settings', 'System configuration and settings', '1.0.0', 1, 1, 4);

-- =============================================
-- 9. PERMISSIONS TABLE (for role-based access)
-- =============================================
CREATE TABLE `bms_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `module` varchar(50) NOT NULL DEFAULT 'core',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default permissions
INSERT INTO `bms_permissions` (`name`, `description`, `module`) VALUES
('dashboard.view', 'View dashboard', 'dashboard'),
('users.view', 'View users', 'users'),
('users.create', 'Create users', 'users'),
('users.edit', 'Edit users', 'users'),
('users.delete', 'Delete users', 'users'),
('settings.view', 'View settings', 'settings'),
('settings.edit', 'Edit settings', 'settings'),
('logs.view', 'View system logs', 'core'),
('backup.create', 'Create backups', 'core'),
('backup.restore', 'Restore backups', 'core');

-- =============================================
-- 10. ROLE PERMISSIONS TABLE
-- =============================================
CREATE TABLE `bms_role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_permission` (`role_id`, `permission_id`),
  KEY `idx_role` (`role_id`),
  KEY `idx_permission` (`permission_id`),
  CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `bms_roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `bms_permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Assign permissions to Super Admin role (all permissions)
INSERT INTO `bms_role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `bms_permissions`;

-- Assign basic permissions to Admin role
INSERT INTO `bms_role_permissions` (`role_id`, `permission_id`)
SELECT 2, id FROM `bms_permissions` WHERE name IN ('dashboard.view', 'users.view', 'users.create', 'users.edit', 'settings.view', 'settings.edit');

-- Assign limited permissions to Manager role
INSERT INTO `bms_role_permissions` (`role_id`, `permission_id`)
SELECT 3, id FROM `bms_permissions` WHERE name IN ('dashboard.view', 'users.view', 'settings.view');

-- Assign minimal permissions to Staff role
INSERT INTO `bms_role_permissions` (`role_id`, `permission_id`)
SELECT 4, id FROM `bms_permissions` WHERE name IN ('dashboard.view');

-- =============================================
-- 11. NOTIFICATIONS TABLE (for system notifications)
-- =============================================
CREATE TABLE `bms_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_read` (`is_read`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `bms_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 12. API TOKENS TABLE (for future API functionality)
-- =============================================
CREATE TABLE `bms_api_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `token` varchar(64) NOT NULL,
  `permissions` text,
  `last_used` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_user` (`user_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `fk_api_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `bms_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- FINAL SETUP
-- =============================================

-- Reset auto increment values
ALTER TABLE `bms_roles` AUTO_INCREMENT = 6;
ALTER TABLE `bms_users` AUTO_INCREMENT = 1;
ALTER TABLE `bms_settings` AUTO_INCREMENT = 21;
ALTER TABLE `bms_activity_logs` AUTO_INCREMENT = 1;
ALTER TABLE `bms_sessions` AUTO_INCREMENT = 1;
ALTER TABLE `bms_login_attempts` AUTO_INCREMENT = 1;
ALTER TABLE `bms_system_logs` AUTO_INCREMENT = 1;
ALTER TABLE `bms_modules` AUTO_INCREMENT = 5;
ALTER TABLE `bms_permissions` AUTO_INCREMENT = 11;
ALTER TABLE `bms_role_permissions` AUTO_INCREMENT = 1;
ALTER TABLE `bms_notifications` AUTO_INCREMENT = 1;
ALTER TABLE `bms_api_tokens` AUTO_INCREMENT = 1;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Create indexes for better performance
CREATE INDEX `idx_users_email_active` ON `bms_users` (`email`, `is_active`);
CREATE INDEX `idx_users_username_active` ON `bms_users` (`username`, `is_active`);
CREATE INDEX `idx_activity_logs_user_action` ON `bms_activity_logs` (`user_id`, `action`);
CREATE INDEX `idx_activity_logs_created_action` ON `bms_activity_logs` (`created_at`, `action`);
CREATE INDEX `idx_sessions_user_activity` ON `bms_sessions` (`user_id`, `last_activity`);
CREATE INDEX `idx_login_attempts_ip_blocked` ON `bms_login_attempts` (`ip_address`, `blocked_until`);
CREATE INDEX `idx_system_logs_level_created` ON `bms_system_logs` (`level`, `created_at`);
CREATE INDEX `idx_notifications_user_read` ON `bms_notifications` (`user_id`, `is_read`);

-- Insert welcome notification for first admin user (will be created during installation)
-- This will be handled by the installation script

-- =============================================
-- INSTALLATION COMPLETE
-- =============================================
-- Database schema created successfully!
-- Ready for Phase 1 installation.
