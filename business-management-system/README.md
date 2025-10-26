# Business Management System

A comprehensive, modular business management solution built with PHP and MySQL. This system provides a solid foundation for managing various business operations including accounting, events, properties, inventory, utilities, and more.

## 🚀 Phase 1: Core Foundation

This is the first phase of the Business Management System, focusing on the installation wizard and core authentication system. Future phases will add business modules and advanced features.

### ✨ Features

- **Modern Installation Wizard**: Step-by-step installation process with requirements checking
- **Secure Authentication**: Role-based access control with session management
- **Admin Dashboard**: Clean, responsive interface with real-time statistics
- **Database Management**: Comprehensive schema with proper relationships
- **Security Features**: CSRF protection, password hashing, login attempt limiting
- **Activity Logging**: Track user actions and system events
- **Notification System**: Built-in notification management
- **Responsive Design**: Works on desktop, tablet, and mobile devices

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
│   │   ├── step-1.php         # Requirements check
│   │   ├── step-2.php         # Database setup
│   │   ├── step-3.php         # Site configuration
│   │   ├── step-4.php         # Admin account
│   │   └── step-5.php         # Installation complete
│   ├── install-functions.php  # Helper functions
│   ├── database.sql           # Database schema
│   └── assets/                # Installation assets
├── config/                     # Configuration files
│   ├── config.sample.php      # Sample configuration
│   ├── database.php           # Database class
│   └── constants.php          # System constants
├── includes/                   # Core includes
│   ├── functions.php          # Global functions
│   └── auth.php               # Authentication system
├── admin/                      # Admin panel
│   ├── index.php              # Dashboard
│   ├── login.php              # Login page
│   ├── logout.php             # Logout script
│   └── includes/              # Admin includes
├── public/                     # Public assets
│   ├── css/                   # Stylesheets
│   ├── js/                    # JavaScript files
│   └── images/                # Images and icons
├── uploads/                    # File uploads
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

### Core Tables
- `bms_users` - User accounts
- `bms_roles` - User roles
- `bms_settings` - System settings
- `bms_activity_logs` - Activity tracking
- `bms_sessions` - Session management
- `bms_notifications` - User notifications

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

## 🗺️ Roadmap

### Phase 2: User Management & Settings
- Advanced user management
- Role and permission management
- System settings interface
- Profile management

### Phase 3: Business Modules
- Accounting module
- Events management
- Properties management
- Inventory management
- Utilities management

### Phase 4: Advanced Features
- API development
- Mobile app support
- Advanced reporting
- Integration capabilities

### Phase 5: Enterprise Features
- Multi-tenant support
- Advanced security
- Performance optimization
- Scalability improvements

## 📞 Contact

- **Project**: Business Management System
- **Version**: 1.0.0 (Phase 1)
- **Author**: Business Management System Team
- **Website**: [Your Website]
- **Email**: [Your Email]

---

**Thank you for using Business Management System!** 🎉

If you find this project helpful, please consider giving it a star on GitHub and sharing it with others.
