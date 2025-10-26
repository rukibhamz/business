# Business Management System

A comprehensive, modular business management solution built with PHP and MySQL. This system provides a solid foundation for managing various business operations including accounting, events, properties, inventory, utilities, and more.

## 🎯 Project Status

### ✅ **Phase 1: Core Foundation** - COMPLETED
Installation wizard and core authentication system with role-based access control.

### ✅ **Phase 2: User Management & Settings** - COMPLETED  
Complete user management with roles, permissions, and system settings.

### ✅ **Phase 3: Accounting System** - COMPLETED
Full double-entry accounting system with invoicing, payments, expenses, and financial reports.

### ✅ **Phase 4: Hall Booking System** - COMPLETED
Complete hall management with booking system, payment integration, and customer interface.

### 📋 **Phase 5: Advanced Features** - PLANNED
API development, mobile support, advanced reporting, and integration capabilities.

## ✨ Current Features

### 🔐 **Core System (Phase 1)**
- **Modern Installation Wizard**: Step-by-step installation process with requirements checking
- **Secure Authentication**: Role-based access control with session management
- **Admin Dashboard**: Clean, responsive interface with real-time statistics
- **Database Management**: Comprehensive schema with proper relationships
- **Security Features**: CSRF protection, password hashing, login attempt limiting
- **Activity Logging**: Track user actions and system events
- **Notification System**: Built-in notification management
- **Responsive Design**: Works on desktop, tablet, and mobile devices

### 👥 **User Management (Phase 2)**
- **Complete User Management**: Add, edit, delete users with profile management
- **Role & Permission System**: Granular permissions with role-based access control
- **System Settings**: Comprehensive settings management (general, email, system)
- **Activity Logs**: Detailed activity tracking and audit trails
- **Profile Management**: User profile editing with password change functionality

### 💰 **Accounting System (Phase 3)**
- **Double-Entry Bookkeeping**: Complete accounting system with automatic journal entries
- **Chart of Accounts**: Hierarchical account structure with Nigerian business context
- **Invoice Management**: Complete invoice lifecycle (Draft → Sent → Paid)
- **Payment Processing**: Payment recording with receipt generation
- **Expense Management**: Expense tracking with approval workflow and receipt uploads
- **Financial Reports**: Balance Sheet, P&L, Trial Balance, General Ledger, Cash Flow
- **Journal Entries**: Manual journal entry creation with validation
- **Real-time Analytics**: Interactive charts and financial summaries

### 🏢 **Hall Booking System (Phase 4)**
- **Hall Management**: Complete hall creation, editing, and management system
- **Multiple Pricing**: Hourly, daily, weekly, and monthly rental rates
- **Online Booking**: Customer-facing booking interface with real-time availability
- **Payment Integration**: Support for full payment and installment plans
- **Booking Management**: Admin interface for managing hall bookings and payments
- **Email Notifications**: Automated booking confirmations and reminders
- **Hall Categories**: Organized hall categorization and filtering
- **Public Interface**: Modern, responsive hall listing and booking pages
- **Revenue Integration**: Automatic invoice generation and accounting integration
- **Availability Management**: Real-time availability checking and conflict prevention

## 📋 System Requirements

### Server Requirements
- **PHP**: 7.4 or higher
- **MySQL**: 5.7+ or MariaDB 10.2+
- **Apache**: with mod_rewrite enabled
- **Web Server**: Apache 2.4+ or Nginx 1.18+

### PHP Extensions
- mysqli
- mbstring
- curl
- gd
- json
- openssl
- zip

### Directory Permissions
- `/config` - Writable (755)
- `/uploads` - Writable (755)
- `/cache` - Writable (755)
- `/logs` - Writable (755)

## 🛠️ Installation

### Method 1: Local Development (XAMPP/WAMP)

1. **Download and Extract**
   ```bash
   # Extract the files to your web server directory
   # For XAMPP: C:\xampp\htdocs\bms
   # For WAMP: C:\wamp64\www\bms
   ```

2. **Create Database**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create a new database (e.g., `bms_database`)
   - Note the database credentials

3. **Run Installation**
   - Navigate to `http://localhost/bms/install`
   - Follow the installation wizard
   - Complete all 5 steps

4. **Access Admin Panel**
   - Go to `http://localhost/bms/admin`
   - Login with your admin credentials

### Method 2: cPanel Hosting

1. **Upload Files**
   - Upload all files to your domain's public_html directory
   - Or create a subdirectory like `public_html/bms`

2. **Create Database**
   - Go to cPanel → MySQL Databases
   - Create a new database and user
   - Assign the user to the database with full privileges

3. **Run Installation**
   - Navigate to `https://yourdomain.com/bms/install`
   - Follow the installation wizard
   - Use your cPanel database credentials

4. **Access Admin Panel**
   - Go to `https://yourdomain.com/bms/admin`
   - Login with your admin credentials

### Method 3: VPS/Dedicated Server

1. **Upload Files**
   ```bash
   # Upload files to your web directory
   scp -r business-management-system/ user@your-server:/var/www/html/
   ```

2. **Set Permissions**
   ```bash
   chmod 755 /var/www/html/business-management-system/
   chmod 755 /var/www/html/business-management-system/config/
   chmod 755 /var/www/html/business-management-system/uploads/
   chmod 755 /var/www/html/business-management-system/cache/
   chmod 755 /var/www/html/business-management-system/logs/
   ```

3. **Create Database**
   ```bash
   mysql -u root -p
   CREATE DATABASE bms_database;
   CREATE USER 'bms_user'@'localhost' IDENTIFIED BY 'your_password';
   GRANT ALL PRIVILEGES ON bms_database.* TO 'bms_user'@'localhost';
   FLUSH PRIVILEGES;
   EXIT;
   ```

4. **Run Installation**
   - Navigate to `http://your-server-ip/business-management-system/install`
   - Follow the installation wizard

## 📁 Directory Structure

```
business-management-system/
├── install/                     # Installation wizard
│   ├── index.php               # Main installation file
│   ├── steps/                  # Installation steps
│   ├── install-functions.php  # Helper functions
│   ├── database.sql           # Phase 1 database schema
│   ├── phase2-database.sql    # Phase 2 database schema
│   ├── phase3-database.sql    # Phase 3 database schema
│   ├── phase4-database.sql    # Phase 4 database schema
│   └── assets/                # Installation assets
├── config/                     # Configuration files
│   ├── config.sample.php      # Sample configuration
│   ├── database.php           # Database class
│   └── constants.php          # System constants
├── includes/                   # Core includes
│   ├── functions.php          # Global functions
│   ├── auth.php               # Authentication system
│   ├── csrf.php               # CSRF protection
│   ├── accounting-functions.php # Accounting helper functions
│   └── event-functions.php    # Event management helper functions
├── admin/                      # Admin panel
│   ├── index.php              # Dashboard
│   ├── login.php              # Login page
│   ├── logout.php             # Logout script
│   ├── users/                 # User management (Phase 2)
│   ├── roles/                 # Role management (Phase 2)
│   ├── settings/              # System settings (Phase 2)
│   ├── activity/              # Activity logs (Phase 2)
│   ├── accounting/            # Accounting system (Phase 3)
│   │   ├── index.php          # Accounting dashboard
│   │   ├── accounts/          # Chart of accounts
│   │   ├── invoices/          # Invoice management
│   │   ├── payments/          # Payment processing
│   │   ├── expenses/          # Expense management
│   │   ├── reports/           # Financial reports
│   │   └── journal/           # Journal entries
│   ├── halls/                 # Hall management (Phase 4)
│   │   ├── index.php          # Halls dashboard
│   │   ├── add.php            # Add hall
│   │   ├── edit.php           # Edit hall
│   │   ├── view.php           # View hall
│   │   ├── bookings/          # Booking management
│   │   ├── categories/        # Hall categories
│   │   ├── reports/           # Hall reports
│   │   └── settings/          # Hall settings
│   └── includes/              # Admin includes
├── public/                     # Public assets
│   ├── css/                   # Stylesheets
│   ├── js/                    # JavaScript files
│   └── images/                # Images and icons
├── uploads/                    # File uploads
│   ├── logos/                 # Company logos
│   ├── profiles/              # User profile pictures
│   ├── expenses/              # Expense receipts
│   └── halls/                 # Hall images and galleries
├── frontend/                   # Public-facing pages
│   └── halls/                 # Hall booking interface
│       ├── index.php          # Hall listing
│       ├── view.php           # Hall details
│       ├── booking.php        # Booking form
│       └── my-bookings.php    # Customer bookings
├── cache/                      # System cache
├── logs/                       # System logs
├── .htaccess                   # Apache configuration
├── index.php                   # Main entry point
└── README.md                   # This file
```

## 🔧 Configuration

### Database Configuration
The system automatically creates a `config/config.php` file during installation with your database settings:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_PREFIX', 'bms_');
```

### Site Configuration
```php
define('SITE_URL', 'https://yourdomain.com');
define('COMPANY_NAME', 'Your Company Name');
define('ADMIN_EMAIL', 'admin@yourdomain.com');
define('TIMEZONE', 'UTC');
define('CURRENCY', 'USD');
```

## 👥 User Management

### Default Roles
- **Super Admin**: Full system access
- **Admin**: Administrative access
- **Manager**: Management level access
- **Staff**: Staff level access
- **Customer**: Customer access

### User Permissions
The system uses a role-based permission system. Each role has specific permissions that can be customized.

## 🔒 Security Features

### Authentication Security
- Password hashing using PHP's `password_hash()`
- Session management with timeout
- Login attempt limiting
- IP address validation
- User agent validation

### Data Protection
- CSRF token protection
- SQL injection prevention (prepared statements)
- XSS protection (input sanitization)
- File upload validation
- Directory traversal protection

### Server Security
- Apache .htaccess rules
- File permission restrictions
- Sensitive file protection
- Security headers

## 📊 Database Schema

### Phase 1: Core Tables
- `bms_users` - User accounts
- `bms_roles` - User roles
- `bms_settings` - System settings
- `bms_activity_logs` - Activity tracking
- `bms_sessions` - Session management
- `bms_notifications` - User notifications

### Phase 2: User Management Tables
- `bms_permissions` - System permissions
- `bms_role_permissions` - Role-permission relationships
- `bms_user_profiles` - Extended user profiles

### Phase 3: Accounting Tables
- `bms_accounts` - Chart of accounts
- `bms_customers` - Customer management
- `bms_invoices` - Invoice records
- `bms_invoice_items` - Invoice line items
- `bms_payments` - Payment records
- `bms_expense_categories` - Expense categories
- `bms_expenses` - Expense records
- `bms_journal_entries` - Journal entries
- `bms_journal_entry_lines` - Journal entry lines
- `bms_tax_rates` - Tax rate definitions

### Phase 4: Hall Management Tables
- `bms_hall_categories` - Hall categories
- `bms_halls` - Hall records
- `bms_hall_booking_periods` - Pricing periods
- `bms_hall_bookings` - Customer bookings
- `bms_hall_booking_items` - Additional services
- `bms_hall_booking_payments` - Payment tracking
- `bms_hall_availability` - Availability management
- `bms_hall_promo_codes` - Discount codes
- `bms_hall_email_templates` - Email templates
- `bms_hall_settings` - Hall module settings

### Security Tables
- `bms_login_attempts` - Login attempt tracking
- `bms_system_logs` - System error logs
- `bms_api_tokens` - API token management

## 🎨 Customization

### Themes
The system uses CSS custom properties for easy theming. Main color variables are defined in the CSS files.

### Modules
The system is designed to be modular. New modules can be added by:
1. Creating module files in appropriate directories
2. Adding menu items to the sidebar
3. Implementing proper permission checks

## 🐛 Troubleshooting

### Common Issues

#### Installation Issues
- **"Database connection failed"**: Check your database credentials and ensure MySQL is running
- **"Directory not writable"**: Set proper permissions (755) on required directories
- **"mod_rewrite not enabled"**: Enable mod_rewrite in Apache configuration

#### Login Issues
- **"Invalid credentials"**: Check username/password and ensure account is active
- **"Account locked"**: Too many failed login attempts, wait 15 minutes or clear login_attempts table
- **"Session expired"**: Session timeout, login again

#### Permission Issues
- **"Access denied"**: User doesn't have required permissions
- **"Directory not accessible"**: Check file permissions and .htaccess rules

### Debug Mode
Enable debug mode by setting `ENVIRONMENT` to `development` in `config/config.php`:

```php
define('ENVIRONMENT', 'development');
```

### Log Files
Check log files in the `/logs` directory:
- `database.log` - Database errors
- `installation.log` - Installation process
- `system.log` - General system errors

## 🔄 Updates and Maintenance

### Backup
Always backup your database and files before updating:
```bash
# Database backup
mysqldump -u username -p database_name > backup.sql

# File backup
tar -czf bms_backup.tar.gz /path/to/business-management-system/
```

### Updates
1. Backup your current installation
2. Download the new version
3. Replace files (except config.php and uploads/)
4. Run any database migrations if needed

### Maintenance Mode
Enable maintenance mode by setting:
```php
setSetting('maintenance_mode', '1');
```

## 📈 Performance

### Optimization Tips
- Enable PHP OPcache
- Use a CDN for static assets
- Optimize database queries
- Enable gzip compression
- Use Redis for session storage (future feature)

### Caching
The system includes basic file caching. For better performance, consider:
- Redis for session and data caching
- Memcached for object caching
- Varnish for HTTP caching

## 🤝 Contributing

### Development Setup
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

### Coding Standards
- Follow PSR-12 coding standards
- Use meaningful variable and function names
- Add comments for complex logic
- Write unit tests for new features

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🆘 Support

### Documentation
- Check this README for common issues
- Review the code comments for implementation details
- Check the logs for error messages

### Community
- GitHub Issues for bug reports
- GitHub Discussions for questions
- Pull Requests for contributions

### Professional Support
For professional support and custom development, please contact the development team.

## 🗺️ Development Roadmap

### ✅ Phase 1: Core Foundation (COMPLETED)
- Installation wizard with requirements checking
- Secure authentication system
- Role-based access control
- Admin dashboard with statistics
- Database management and security features

### ✅ Phase 2: User Management & Settings (COMPLETED)
- Complete user management system
- Advanced role and permission management
- Comprehensive system settings interface
- User profile management
- Activity logging and audit trails

### ✅ Phase 3: Accounting System (COMPLETED)
- Double-entry bookkeeping system
- Chart of accounts with Nigerian business context
- Complete invoice management lifecycle
- Payment processing with receipt generation
- Expense management with approval workflow
- Financial reports (Balance Sheet, P&L, Trial Balance, etc.)
- Manual journal entries with validation
- Real-time financial analytics

### ✅ Phase 4: Hall Booking System (COMPLETED)
- Complete hall management system with admin interface
- Multiple pricing tiers (hourly, daily, weekly, monthly)
- Customer-facing booking interface with real-time availability
- Payment integration supporting full payment and installments
- Booking management with payment tracking
- Automated email notifications and confirmations
- Hall categories and filtering system
- Public hall listing and booking pages
- Revenue integration with accounting system
- Availability management and conflict prevention

### 🚧 Phase 5: Additional Business Modules (IN DEVELOPMENT)
- **Properties Management**: Property listings, rentals, and maintenance
- **Inventory Management**: Stock tracking, suppliers, and procurement
- **Utilities Management**: Utility billing and payment tracking

### 📋 Phase 6: Advanced Features (PLANNED)
- **API Development**: RESTful API for third-party integrations
- **Mobile App Support**: Mobile-responsive design and PWA features
- **Advanced Reporting**: Custom report builder and analytics
- **Integration Capabilities**: Third-party service integrations

### 🔮 Phase 7: Enterprise Features (FUTURE)
- **Multi-tenant Support**: Multiple organization management
- **Advanced Security**: Enhanced security features and compliance
- **Performance Optimization**: Caching, optimization, and scalability
- **Cloud Deployment**: Cloud-native deployment options

## 📞 Contact

- **Project**: Business Management System
- **Version**: 4.0.0 (Phase 4 Complete)
- **Author**: Business Management System Team
- **Website**: [Your Website]
- **Email**: [Your Email]

---

**Thank you for using Business Management System!** 🎉

The system now includes a complete accounting module with double-entry bookkeeping, invoice management, payment processing, expense tracking, and comprehensive financial reporting, plus a full hall booking system with customer interface, payment integration, and automated notifications. Perfect for small to medium businesses looking for a comprehensive business management solution.

If you find this project helpful, please consider giving it a star on GitHub and sharing it with others.
