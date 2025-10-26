# Business Management System - cPanel Deployment Guide

## File Structure for cPanel

Upload these files to your cPanel public_html directory:

```
public_html/
├── index.php (redirects to admin)
├── admin/
│   ├── index.php
│   ├── login.php
│   └── [all other admin files]
├── config/
│   ├── config.php
│   ├── constants.php
│   └── database.php
├── includes/
├── uploads/
├── cache/
└── logs/
```

## Database Configuration

Update config/config.php with your cPanel database details:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_cpanel_database_name');
define('DB_USER', 'your_cpanel_db_username');
define('DB_PASS', 'your_cpanel_db_password');
define('SITE_URL', 'https://yourdomain.com');
```

## Common cPanel 403 Error Solutions

1. **File Permissions:**
   - Set directories to 755
   - Set files to 644
   - Set uploads/ to 777

2. **Directory Structure:**
   - Ensure all files are in public_html/
   - Check .htaccess files don't block access

3. **Database Setup:**
   - Create database in cPanel
   - Import the SQL schema
   - Update config.php with correct credentials

4. **PHP Version:**
   - Ensure PHP 7.4+ is selected in cPanel
   - Enable required extensions (mysqli, pdo_mysql)
