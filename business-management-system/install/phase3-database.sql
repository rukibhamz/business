-- Phase 3: Accounting System Database Tables
-- Business Management System

-- 1. Chart of Accounts
CREATE TABLE bms_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  account_code VARCHAR(20) UNIQUE NOT NULL,
  account_name VARCHAR(150) NOT NULL,
  account_type ENUM('Asset', 'Liability', 'Equity', 'Income', 'Expense') NOT NULL,
  account_subtype VARCHAR(50),
  parent_account_id INT NULL,
  description TEXT,
  opening_balance DECIMAL(15,2) DEFAULT 0.00,
  current_balance DECIMAL(15,2) DEFAULT 0.00,
  is_active TINYINT(1) DEFAULT 1,
  is_system TINYINT(1) DEFAULT 0,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_code (account_code),
  INDEX idx_type (account_type),
  INDEX idx_parent (parent_account_id),
  FOREIGN KEY (parent_account_id) REFERENCES bms_accounts(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES bms_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default chart of accounts
INSERT INTO bms_accounts (account_code, account_name, account_type, account_subtype, is_system) VALUES
-- Assets
('1000', 'Assets', 'Asset', 'Header', 1),
('1010', 'Cash', 'Asset', 'Current Asset', 1),
('1020', 'Bank Account', 'Asset', 'Current Asset', 1),
('1030', 'Petty Cash', 'Asset', 'Current Asset', 1),
('1100', 'Accounts Receivable', 'Asset', 'Current Asset', 1),
('1200', 'Inventory', 'Asset', 'Current Asset', 1),
('1500', 'Fixed Assets', 'Asset', 'Non-Current Asset', 1),
('1510', 'Property', 'Asset', 'Non-Current Asset', 1),
('1520', 'Equipment', 'Asset', 'Non-Current Asset', 1),
('1530', 'Vehicles', 'Asset', 'Non-Current Asset', 1),
('1540', 'Accumulated Depreciation', 'Asset', 'Non-Current Asset', 1),

-- Liabilities
('2000', 'Liabilities', 'Liability', 'Header', 1),
('2010', 'Accounts Payable', 'Liability', 'Current Liability', 1),
('2020', 'Credit Card', 'Liability', 'Current Liability', 1),
('2100', 'Loans Payable', 'Liability', 'Long-term Liability', 1),
('2200', 'Tax Payable', 'Liability', 'Current Liability', 1),
('2210', 'VAT Payable', 'Liability', 'Current Liability', 1),
('2220', 'AMAC Tax Payable', 'Liability', 'Current Liability', 1),

-- Equity
('3000', 'Equity', 'Equity', 'Header', 1),
('3010', 'Owner Equity', 'Equity', 'Owner Equity', 1),
('3020', 'Retained Earnings', 'Equity', 'Retained Earnings', 1),
('3030', 'Current Year Earnings', 'Equity', 'Current Earnings', 1),

-- Income
('4000', 'Income', 'Income', 'Header', 1),
('4010', 'Event Revenue', 'Income', 'Operating Income', 1),
('4020', 'Property Rental Income', 'Income', 'Operating Income', 1),
('4030', 'Service Income', 'Income', 'Operating Income', 1),
('4040', 'Product Sales', 'Income', 'Operating Income', 1),
('4100', 'Other Income', 'Income', 'Other Income', 1),

-- Expenses
('5000', 'Expenses', 'Expense', 'Header', 1),
('5010', 'Salaries and Wages', 'Expense', 'Operating Expense', 1),
('5020', 'Rent Expense', 'Expense', 'Operating Expense', 1),
('5030', 'Utilities Expense', 'Expense', 'Operating Expense', 1),
('5040', 'Office Supplies', 'Expense', 'Operating Expense', 1),
('5050', 'Marketing and Advertising', 'Expense', 'Operating Expense', 1),
('5060', 'Insurance', 'Expense', 'Operating Expense', 1),
('5070', 'Repairs and Maintenance', 'Expense', 'Operating Expense', 1),
('5080', 'Bank Charges', 'Expense', 'Operating Expense', 1),
('5090', 'Depreciation', 'Expense', 'Operating Expense', 1),
('5100', 'Tax Expense', 'Expense', 'Operating Expense', 1);

-- 2. Customers
CREATE TABLE bms_customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_code VARCHAR(20) UNIQUE NOT NULL,
  customer_type ENUM('Individual', 'Company') DEFAULT 'Individual',
  first_name VARCHAR(100),
  last_name VARCHAR(100),
  company_name VARCHAR(150),
  email VARCHAR(150),
  phone VARCHAR(20),
  mobile VARCHAR(20),
  address TEXT,
  city VARCHAR(100),
  state VARCHAR(100),
  country VARCHAR(100) DEFAULT 'Nigeria',
  postal_code VARCHAR(20),
  tax_id VARCHAR(50),
  website VARCHAR(150),
  notes TEXT,
  credit_limit DECIMAL(15,2) DEFAULT 0.00,
  outstanding_balance DECIMAL(15,2) DEFAULT 0.00,
  is_active TINYINT(1) DEFAULT 1,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_code (customer_code),
  INDEX idx_email (email),
  INDEX idx_type (customer_type),
  FOREIGN KEY (created_by) REFERENCES bms_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Invoices
CREATE TABLE bms_invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_number VARCHAR(50) UNIQUE NOT NULL,
  customer_id INT NOT NULL,
  invoice_date DATE NOT NULL,
  due_date DATE NOT NULL,
  payment_terms VARCHAR(50) DEFAULT 'Net 30',
  reference VARCHAR(100),
  subject VARCHAR(255),
  notes TEXT,
  terms_conditions TEXT,
  subtotal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  discount_type ENUM('percentage', 'fixed') DEFAULT 'percentage',
  discount_value DECIMAL(10,2) DEFAULT 0.00,
  discount_amount DECIMAL(15,2) DEFAULT 0.00,
  tax_amount DECIMAL(15,2) DEFAULT 0.00,
  total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  amount_paid DECIMAL(15,2) DEFAULT 0.00,
  balance_due DECIMAL(15,2) DEFAULT 0.00,
  status ENUM('Draft', 'Sent', 'Partial', 'Paid', 'Overdue', 'Cancelled') DEFAULT 'Draft',
  sent_date DATETIME NULL,
  paid_date DATETIME NULL,
  currency VARCHAR(10) DEFAULT 'NGN',
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_number (invoice_number),
  INDEX idx_customer (customer_id),
  INDEX idx_status (status),
  INDEX idx_date (invoice_date),
  INDEX idx_due_date (due_date),
  FOREIGN KEY (customer_id) REFERENCES bms_customers(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES bms_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Invoice Items
CREATE TABLE bms_invoice_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  item_order INT DEFAULT 0,
  item_name VARCHAR(255) NOT NULL,
  description TEXT,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  tax_rate DECIMAL(5,2) DEFAULT 0.00,
  tax_amount DECIMAL(15,2) DEFAULT 0.00,
  line_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  account_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_invoice (invoice_id),
  INDEX idx_account (account_id),
  FOREIGN KEY (invoice_id) REFERENCES bms_invoices(id) ON DELETE CASCADE,
  FOREIGN KEY (account_id) REFERENCES bms_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Payments
CREATE TABLE bms_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  payment_number VARCHAR(50) UNIQUE NOT NULL,
  payment_date DATE NOT NULL,
  customer_id INT NOT NULL,
  invoice_id INT,
  amount DECIMAL(15,2) NOT NULL,
  payment_method ENUM('Cash', 'Bank Transfer', 'Credit Card', 'Debit Card', 'Check', 'Mobile Money', 'Other') NOT NULL,
  reference_number VARCHAR(100),
  bank_account_id INT,
  notes TEXT,
  status ENUM('Completed', 'Pending', 'Failed', 'Refunded') DEFAULT 'Completed',
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_number (payment_number),
  INDEX idx_customer (customer_id),
  INDEX idx_invoice (invoice_id),
  INDEX idx_date (payment_date),
  INDEX idx_status (status),
  FOREIGN KEY (customer_id) REFERENCES bms_customers(id) ON DELETE CASCADE,
  FOREIGN KEY (invoice_id) REFERENCES bms_invoices(id) ON DELETE SET NULL,
  FOREIGN KEY (bank_account_id) REFERENCES bms_accounts(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES bms_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Expense Categories
CREATE TABLE bms_expense_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(100) UNIQUE NOT NULL,
  description TEXT,
  default_account_id INT,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_name (category_name),
  FOREIGN KEY (default_account_id) REFERENCES bms_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default expense categories
INSERT INTO bms_expense_categories (category_name, description) VALUES
('Salaries', 'Employee salaries and wages'),
('Rent', 'Office and property rent'),
('Utilities', 'Electricity, water, internet'),
('Office Supplies', 'Stationery and office materials'),
('Marketing', 'Advertising and promotion'),
('Travel', 'Business travel expenses'),
('Insurance', 'Business insurance premiums'),
('Maintenance', 'Repairs and maintenance'),
('Professional Fees', 'Legal, accounting services'),
('Bank Charges', 'Bank fees and charges'),
('Taxes', 'Business taxes and levies'),
('Other', 'Miscellaneous expenses');

-- 7. Expenses
CREATE TABLE bms_expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  expense_number VARCHAR(50) UNIQUE NOT NULL,
  expense_date DATE NOT NULL,
  category_id INT NOT NULL,
  account_id INT,
  amount DECIMAL(15,2) NOT NULL,
  payment_method ENUM('Cash', 'Bank Transfer', 'Credit Card', 'Check', 'Other') NOT NULL,
  reference VARCHAR(100),
  vendor_name VARCHAR(150),
  description TEXT,
  receipt_file VARCHAR(255),
  tax_amount DECIMAL(15,2) DEFAULT 0.00,
  is_billable TINYINT(1) DEFAULT 0,
  customer_id INT,
  status ENUM('Pending', 'Approved', 'Rejected', 'Paid') DEFAULT 'Pending',
  approved_by INT,
  approved_date DATETIME,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_number (expense_number),
  INDEX idx_date (expense_date),
  INDEX idx_category (category_id),
  INDEX idx_status (status),
  FOREIGN KEY (category_id) REFERENCES bms_expense_categories(id) ON DELETE CASCADE,
  FOREIGN KEY (account_id) REFERENCES bms_accounts(id) ON DELETE SET NULL,
  FOREIGN KEY (customer_id) REFERENCES bms_customers(id) ON DELETE SET NULL,
  FOREIGN KEY (approved_by) REFERENCES bms_users(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES bms_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Journal Entries
CREATE TABLE bms_journal_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  journal_number VARCHAR(50) UNIQUE NOT NULL,
  entry_date DATE NOT NULL,
  entry_type ENUM('Manual', 'Invoice', 'Payment', 'Expense', 'System') DEFAULT 'Manual',
  reference_id INT,
  reference_type VARCHAR(50),
  description TEXT,
  total_debit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  total_credit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  status ENUM('Draft', 'Posted', 'Voided') DEFAULT 'Posted',
  posted_by INT,
  posted_date DATETIME,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_number (journal_number),
  INDEX idx_date (entry_date),
  INDEX idx_type (entry_type),
  INDEX idx_status (status),
  FOREIGN KEY (posted_by) REFERENCES bms_users(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES bms_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Journal Entry Lines
CREATE TABLE bms_journal_entry_lines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  journal_entry_id INT NOT NULL,
  line_order INT DEFAULT 0,
  account_id INT NOT NULL,
  description TEXT,
  debit_amount DECIMAL(15,2) DEFAULT 0.00,
  credit_amount DECIMAL(15,2) DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_journal (journal_entry_id),
  INDEX idx_account (account_id),
  FOREIGN KEY (journal_entry_id) REFERENCES bms_journal_entries(id) ON DELETE CASCADE,
  FOREIGN KEY (account_id) REFERENCES bms_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Tax Rates
CREATE TABLE bms_tax_rates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tax_name VARCHAR(100) NOT NULL,
  tax_code VARCHAR(20) UNIQUE NOT NULL,
  tax_rate DECIMAL(5,2) NOT NULL,
  tax_type ENUM('VAT', 'Sales Tax', 'AMAC', 'Withholding', 'Other') DEFAULT 'Other',
  description TEXT,
  is_compound TINYINT(1) DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_code (tax_code),
  INDEX idx_type (tax_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default tax rates
INSERT INTO bms_tax_rates (tax_name, tax_code, tax_rate, tax_type) VALUES
('VAT (7.5%)', 'VAT', 7.50, 'VAT'),
('AMAC Tax (2%)', 'AMAC', 2.00, 'AMAC'),
('Withholding Tax (5%)', 'WHT', 5.00, 'Withholding');

-- Update settings table with accounting prefixes
INSERT INTO bms_settings (setting_key, setting_value, category) VALUES
('invoice_prefix', 'INV-', 'accounting'),
('payment_prefix', 'PAY-', 'accounting'),
('expense_prefix', 'EXP-', 'accounting'),
('customer_prefix', 'CUST-', 'accounting'),
('next_invoice_number', '1', 'accounting'),
('next_payment_number', '1', 'accounting'),
('next_expense_number', '1', 'accounting'),
('next_customer_number', '1', 'accounting'),
('default_tax_rate', '7.5', 'accounting'),
('invoice_footer_text', 'Thank you for your business!', 'accounting'),
('payment_terms_default', 'Net 30', 'accounting'),
('currency_position', 'before', 'accounting'),
('show_tax_on_invoice', '1', 'accounting'),
('auto_generate_numbers', '1', 'accounting');
