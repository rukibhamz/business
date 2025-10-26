<?php
/**
 * Business Management System - Admin Footer
 * Phase 1: Core Foundation
 */

// Prevent direct access
if (!defined('BMS_SYSTEM')) {
    die('Direct access not allowed');
}

// Get system information
$systemStats = getSystemStats();
$systemStatus = getSystemStatus();
$currentUser = getCurrentUser();
?>

            </div> <!-- End page-content -->
        </main> <!-- End main-content -->
    </div> <!-- End admin-container -->

    <!-- Footer -->
    <footer class="admin-footer">
        <div class="footer-content">
            <div class="footer-left">
                <p>&copy; <?php echo date('Y'); ?> <?php echo COMPANY_NAME; ?>. All rights reserved.</p>
                <p>Business Management System v<?php echo BMS_VERSION; ?> - <?php echo BMS_PHASE; ?></p>
            </div>
            <div class="footer-right">
                <div class="system-info">
                    <span class="info-item">
                        <i class="icon-users"></i>
                        <?php echo $systemStats['total_users']; ?> Users
                    </span>
                    <span class="info-item">
                        <i class="icon-database"></i>
                        <?php echo $systemStatus['database'] ? 'Online' : 'Offline'; ?>
                    </span>
                    <span class="info-item">
                        <i class="icon-clock"></i>
                        <?php echo date('H:i:s'); ?>
                    </span>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script src="../public/js/admin.js"></script>
    
    <!-- Page-specific scripts -->
    <?php if (isset($pageScripts) && is_array($pageScripts)): ?>
        <?php foreach ($pageScripts as $script): ?>
            <script src="<?php echo $script; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Inline scripts -->
    <script>
        // Global JavaScript variables
        window.BMS = {
            baseUrl: '<?php echo BMS_URL; ?>',
            adminUrl: '<?php echo BMS_ADMIN_URL; ?>',
            userId: <?php echo $currentUser['id']; ?>,
            userName: '<?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>',
            userRole: '<?php echo htmlspecialchars($currentUser['role_name']); ?>',
            csrfToken: '<?php echo generateCSRFToken(); ?>',
            timezone: '<?php echo TIMEZONE; ?>',
            dateFormat: '<?php echo DATE_FORMAT; ?>',
            currency: '<?php echo CURRENCY; ?>',
            currencySymbol: '<?php echo CURRENCY_SYMBOL; ?>'
        };

        // Initialize admin interface
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips
            initializeTooltips();
            
            // Initialize notifications
            initializeNotifications();
            
            // Initialize user menu
            initializeUserMenu();
            
            // Initialize sidebar
            initializeSidebar();
            
            // Initialize page-specific functionality
            if (typeof initializePage === 'function') {
                initializePage();
            }
        });

        // Toggle sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('sidebar-collapsed');
            
            // Save preference
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        }

        // Toggle notifications dropdown
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('show');
        }

        // Toggle user menu dropdown
        function toggleUserMenu() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('show');
        }

        // Mark all notifications as read
        function markAllNotificationsRead() {
            // This would make an AJAX call to mark all notifications as read
            console.log('Marking all notifications as read...');
            // Implementation would go here
        }

        // Initialize tooltips
        function initializeTooltips() {
            const tooltipElements = document.querySelectorAll('[data-tooltip]');
            tooltipElements.forEach(element => {
                element.addEventListener('mouseenter', showTooltip);
                element.addEventListener('mouseleave', hideTooltip);
            });
        }

        // Show tooltip
        function showTooltip(event) {
            const element = event.target;
            const text = element.getAttribute('data-tooltip');
            
            if (!text) return;
            
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = text;
            tooltip.id = 'tooltip-' + Date.now();
            
            document.body.appendChild(tooltip);
            
            const rect = element.getBoundingClientRect();
            tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
            tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
        }

        // Hide tooltip
        function hideTooltip(event) {
            const tooltips = document.querySelectorAll('.tooltip');
            tooltips.forEach(tooltip => tooltip.remove());
        }

        // Initialize notifications
        function initializeNotifications() {
            // Load notifications via AJAX
            loadNotifications();
            
            // Set up auto-refresh
            setInterval(loadNotifications, 30000); // 30 seconds
        }

        // Load notifications
        function loadNotifications() {
            // This would make an AJAX call to load notifications
            console.log('Loading notifications...');
            // Implementation would go here
        }

        // Initialize user menu
        function initializeUserMenu() {
            // Close dropdowns when clicking outside
            document.addEventListener('click', function(event) {
                const userDropdown = document.getElementById('userDropdown');
                const notificationDropdown = document.getElementById('notificationDropdown');
                
                if (userDropdown && !event.target.closest('.user-menu')) {
                    userDropdown.classList.remove('show');
                }
                
                if (notificationDropdown && !event.target.closest('.notifications')) {
                    notificationDropdown.classList.remove('show');
                }
            });
        }

        // Initialize sidebar
        function initializeSidebar() {
            // Restore sidebar state
            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (sidebarCollapsed) {
                const sidebar = document.getElementById('sidebar');
                const mainContent = document.querySelector('.main-content');
                sidebar.classList.add('collapsed');
                mainContent.classList.add('sidebar-collapsed');
            }
        }

        // Format currency
        function formatCurrency(amount, currency = null) {
            currency = currency || window.BMS.currency;
            const symbol = window.BMS.currencySymbol;
            return symbol + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }

        // Format date
        function formatDate(date, format = null) {
            format = format || window.BMS.dateFormat;
            const d = new Date(date);
            
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            
            switch(format) {
                case 'Y-m-d':
                    return `${year}-${month}-${day}`;
                case 'd/m/Y':
                    return `${day}/${month}/${year}`;
                case 'm/d/Y':
                    return `${month}/${day}/${year}`;
                case 'd-m-Y':
                    return `${day}-${month}-${year}`;
                case 'Y/m/d':
                    return `${year}/${month}/${day}`;
                default:
                    return d.toLocaleDateString();
            }
        }

        // Show loading spinner
        function showLoading(element) {
            if (typeof element === 'string') {
                element = document.querySelector(element);
            }
            
            if (element) {
                element.innerHTML = '<div class="loading-spinner"><i class="icon-spinner"></i> Loading...</div>';
            }
        }

        // Hide loading spinner
        function hideLoading(element) {
            if (typeof element === 'string') {
                element = document.querySelector(element);
            }
            
            if (element) {
                const spinner = element.querySelector('.loading-spinner');
                if (spinner) {
                    spinner.remove();
                }
            }
        }

        // Show alert message
        function showAlert(message, type = 'info', duration = 5000) {
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible`;
            alert.innerHTML = `
                <i class="icon-${type === 'error' ? 'warning' : type}"></i>
                <span>${message}</span>
                <button type="button" class="alert-close" onclick="this.parentElement.remove()">
                    <i class="icon-close"></i>
                </button>
            `;
            
            // Insert at top of page content
            const pageContent = document.querySelector('.page-content');
            if (pageContent) {
                pageContent.insertBefore(alert, pageContent.firstChild);
            }
            
            // Auto-remove after duration
            if (duration > 0) {
                setTimeout(() => {
                    if (alert.parentElement) {
                        alert.remove();
                    }
                }, duration);
            }
        }

        // Confirm dialog
        function confirmDialog(message, callback) {
            if (confirm(message)) {
                if (typeof callback === 'function') {
                    callback();
                }
            }
        }

        // AJAX helper
        function ajaxRequest(url, options = {}) {
            const defaultOptions = {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': window.BMS.csrfToken
                }
            };
            
            const mergedOptions = { ...defaultOptions, ...options };
            
            return fetch(url, mergedOptions)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .catch(error => {
                    console.error('AJAX request failed:', error);
                    showAlert('Request failed: ' + error.message, 'error');
                    throw error;
                });
        }

        // Update CSRF token
        function updateCSRFToken() {
            // This would make an AJAX call to get a new CSRF token
            console.log('Updating CSRF token...');
            // Implementation would go here
        }

        // Auto-update CSRF token every 30 minutes
        setInterval(updateCSRFToken, 30 * 60 * 1000);
    </script>
</body>
</html>
