-- Phase 2 Database Tables for Business Management System
-- User Management & Settings System

-- 1. Permissions table
CREATE TABLE bms_permissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  display_name VARCHAR(150) NOT NULL,
  module VARCHAR(50) NOT NULL,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_module (module),
  INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert base permissions for all modules
INSERT INTO bms_permissions (name, display_name, module, description) VALUES
-- Dashboard
('dashboard.view', 'View Dashboard', 'dashboard', 'Access admin dashboard'),

-- Users
('users.view', 'View Users', 'users', 'View user list and details'),
('users.create', 'Create Users', 'users', 'Add new users'),
('users.edit', 'Edit Users', 'users', 'Modify existing users'),
('users.delete', 'Delete Users', 'users', 'Remove users from system'),

-- Roles
('roles.view', 'View Roles', 'roles', 'View role list'),
('roles.create', 'Create Roles', 'roles', 'Add new roles'),
('roles.edit', 'Edit Roles', 'roles', 'Modify existing roles'),
('roles.delete', 'Delete Roles', 'roles', 'Remove roles from system'),
('roles.manage_permissions', 'Manage Permissions', 'roles', 'Assign permissions to roles'),

-- Settings
('settings.view', 'View Settings', 'settings', 'View system settings'),
('settings.edit', 'Edit Settings', 'settings', 'Modify system settings'),

-- Activity Logs
('activity.view', 'View Activity Logs', 'activity', 'View system activity logs'),

-- Accounting (for future use)
('accounting.view', 'View Accounting', 'accounting', 'View accounting data'),
('accounting.create', 'Create Transactions', 'accounting', 'Create accounting entries'),
('accounting.edit', 'Edit Transactions', 'accounting', 'Modify accounting entries'),
('accounting.delete', 'Delete Transactions', 'accounting', 'Remove accounting entries'),
('accounting.reports', 'View Reports', 'accounting', 'Access financial reports'),

-- Events (for future use)
('events.view', 'View Events', 'events', 'View event list'),
('events.create', 'Create Events', 'events', 'Add new events'),
('events.edit', 'Edit Events', 'events', 'Modify existing events'),
('events.delete', 'Delete Events', 'events', 'Remove events'),
('events.bookings', 'Manage Bookings', 'events', 'Handle event bookings'),

-- Properties (for future use)
('properties.view', 'View Properties', 'properties', 'View property list'),
('properties.create', 'Create Properties', 'properties', 'Add new properties'),
('properties.edit', 'Edit Properties', 'properties', 'Modify existing properties'),
('properties.delete', 'Delete Properties', 'properties', 'Remove properties'),
('properties.leases', 'Manage Leases', 'properties', 'Handle lease agreements'),

-- Inventory (for future use)
('inventory.view', 'View Inventory', 'inventory', 'View inventory items'),
('inventory.create', 'Create Items', 'inventory', 'Add new inventory items'),
('inventory.edit', 'Edit Items', 'inventory', 'Modify inventory items'),
('inventory.delete', 'Delete Items', 'inventory', 'Remove inventory items'),
('inventory.stock', 'Manage Stock', 'inventory', 'Handle stock movements'),

-- Utilities (for future use)
('utilities.view', 'View Utilities', 'utilities', 'View utility data'),
('utilities.create', 'Create Records', 'utilities', 'Add utility records'),
('utilities.edit', 'Edit Records', 'utilities', 'Modify utility records'),
('utilities.delete', 'Delete Records', 'utilities', 'Remove utility records');

-- 2. Role permissions table
CREATE TABLE bms_role_permissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role_id INT NOT NULL,
  permission_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_role_permission (role_id, permission_id),
  INDEX idx_role (role_id),
  INDEX idx_permission (permission_id),
  FOREIGN KEY (role_id) REFERENCES bms_roles(id) ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES bms_permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Give Super Admin all permissions
INSERT INTO bms_role_permissions (role_id, permission_id)
SELECT 1, id FROM bms_permissions;

-- Give Admin most permissions (exclude some sensitive ones)
INSERT INTO bms_role_permissions (role_id, permission_id)
SELECT 2, id FROM bms_permissions 
WHERE name NOT IN ('roles.delete', 'settings.edit');

-- 3. Update settings table with default values
INSERT INTO bms_settings (setting_key, setting_value, category) VALUES
-- General Settings
('company_name', 'Business Management System', 'general'),
('company_email', 'admin@example.com', 'general'),
('company_phone', '', 'general'),
('company_address', '', 'general'),
('site_url', 'http://localhost', 'general'),
('timezone', 'Africa/Lagos', 'general'),
('date_format', 'Y-m-d', 'general'),
('time_format', 'H:i:s', 'general'),
('currency', 'NGN', 'general'),
('currency_symbol', '₦', 'general'),

-- Email Settings
('email_protocol', 'mail', 'email'),
('smtp_host', '', 'email'),
('smtp_port', '587', 'email'),
('smtp_user', '', 'email'),
('smtp_pass', '', 'email'),
('smtp_encryption', 'tls', 'email'),
('email_from', 'noreply@example.com', 'email'),
('email_from_name', 'Business Management System', 'email'),

-- System Settings
('maintenance_mode', '0', 'system'),
('maintenance_message', 'System under maintenance. Please check back soon.', 'system'),
('session_timeout', '3600', 'system'),
('records_per_page', '25', 'system'),
('enable_registration', '0', 'system'),
('default_user_role', '5', 'system');
