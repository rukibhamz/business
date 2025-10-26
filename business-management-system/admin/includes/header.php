<?php
/**
 * Business Management System - Admin Header
 * Phase 1: Core Foundation
 */

// Prevent direct access
if (!defined('BMS_SYSTEM')) {
    die('Direct access not allowed');
}

// Get current user
$currentUser = getCurrentUser();
$unreadNotifications = getUnreadNotificationCount($currentUser['id']);
$systemStats = getSystemStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo COMPANY_NAME; ?> Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../public/css/admin.css">
    <link rel="icon" type="image/x-icon" href="../public/images/logo.png">
    <meta name="robots" content="noindex, nofollow">
</head>
<body class="admin-body">
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <h2><?php echo COMPANY_NAME; ?></h2>
                    <span class="version">v<?php echo BMS_VERSION; ?></span>
                </div>
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="icon-menu"></i>
                </button>
            </div>

            <nav class="sidebar-nav">
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="../admin/" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                            <i class="icon-dashboard"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>

                    <!-- Accounting Module (Placeholder) -->
                    <li class="nav-item nav-dropdown">
                        <a href="#" class="nav-link dropdown-toggle">
                            <i class="icon-accounting"></i>
                            <span>Accounting</span>
                            <i class="icon-chevron-down"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="#" class="disabled">Invoices</a></li>
                            <li><a href="#" class="disabled">Payments</a></li>
                            <li><a href="#" class="disabled">Expenses</a></li>
                            <li><a href="#" class="disabled">Reports</a></li>
                        </ul>
                    </li>

                    <!-- Events Module (Placeholder) -->
                    <li class="nav-item">
                        <a href="#" class="nav-link disabled">
                            <i class="icon-events"></i>
                            <span>Events</span>
                            <span class="badge">Coming Soon</span>
                        </a>
                    </li>

                    <!-- Properties Module (Placeholder) -->
                    <li class="nav-item">
                        <a href="#" class="nav-link disabled">
                            <i class="icon-properties"></i>
                            <span>Properties</span>
                            <span class="badge">Coming Soon</span>
                        </a>
                    </li>

                    <!-- Inventory Module (Placeholder) -->
                    <li class="nav-item">
                        <a href="#" class="nav-link disabled">
                            <i class="icon-inventory"></i>
                            <span>Inventory</span>
                            <span class="badge">Coming Soon</span>
                        </a>
                    </li>

                    <!-- Utilities Module (Placeholder) -->
                    <li class="nav-item">
                        <a href="#" class="nav-link disabled">
                            <i class="icon-utilities"></i>
                            <span>Utilities</span>
                            <span class="badge">Coming Soon</span>
                        </a>
                    </li>

                    <!-- Taxes & Revenue Module (Placeholder) -->
                    <li class="nav-item">
                        <a href="#" class="nav-link disabled">
                            <i class="icon-taxes"></i>
                            <span>Taxes & Revenue</span>
                            <span class="badge">Coming Soon</span>
                        </a>
                    </li>

                    <!-- Users Module -->
                    <li class="nav-item nav-dropdown">
                        <a href="#" class="nav-link dropdown-toggle">
                            <i class="icon-users"></i>
                            <span>Users</span>
                            <i class="icon-chevron-down"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="#" class="disabled">All Users</a></li>
                            <li><a href="#" class="disabled">Roles & Permissions</a></li>
                            <li><a href="#" class="disabled">Activity Logs</a></li>
                        </ul>
                    </li>

                    <!-- Settings Module -->
                    <li class="nav-item">
                        <a href="#" class="nav-link disabled">
                            <i class="icon-settings"></i>
                            <span>Settings</span>
                            <span class="badge">Coming Soon</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="icon-user"></i>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></div>
                        <div class="user-role"><?php echo htmlspecialchars($currentUser['role_name']); ?></div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Header -->
            <header class="top-header">
                <div class="header-left">
                    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                        <i class="icon-menu"></i>
                    </button>
                    <h1 class="page-title"><?php echo isset($pageTitle) ? $pageTitle : 'Dashboard'; ?></h1>
                </div>

                <div class="header-right">
                    <!-- Notifications -->
                    <div class="header-item notifications">
                        <button class="notification-btn" onclick="toggleNotifications()">
                            <i class="icon-bell"></i>
                            <?php if ($unreadNotifications > 0): ?>
                            <span class="notification-badge"><?php echo $unreadNotifications; ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="notification-dropdown" id="notificationDropdown">
                            <div class="notification-header">
                                <h3>Notifications</h3>
                                <button class="mark-all-read" onclick="markAllNotificationsRead()">
                                    Mark all as read
                                </button>
                            </div>
                            <div class="notification-list">
                                <!-- Notifications will be loaded here -->
                                <div class="notification-item">
                                    <div class="notification-icon">
                                        <i class="icon-info"></i>
                                    </div>
                                    <div class="notification-content">
                                        <div class="notification-title">Welcome to BMS</div>
                                        <div class="notification-message">Your installation is complete!</div>
                                        <div class="notification-time">Just now</div>
                                    </div>
                                </div>
                            </div>
                            <div class="notification-footer">
                                <a href="#" class="view-all">View all notifications</a>
                            </div>
                        </div>
                    </div>

                    <!-- User Menu -->
                    <div class="header-item user-menu">
                        <button class="user-btn" onclick="toggleUserMenu()">
                            <div class="user-avatar">
                                <i class="icon-user"></i>
                            </div>
                            <span class="user-name"><?php echo htmlspecialchars($currentUser['first_name']); ?></span>
                            <i class="icon-chevron-down"></i>
                        </button>
                        <div class="user-dropdown" id="userDropdown">
                            <div class="user-info">
                                <div class="user-avatar">
                                    <i class="icon-user"></i>
                                </div>
                                <div class="user-details">
                                    <div class="user-name"><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></div>
                                    <div class="user-email"><?php echo htmlspecialchars($currentUser['email']); ?></div>
                                </div>
                            </div>
                            <ul class="user-menu-list">
                                <li><a href="#" class="disabled"><i class="icon-profile"></i> Profile</a></li>
                                <li><a href="#" class="disabled"><i class="icon-settings"></i> Settings</a></li>
                                <li><a href="#" class="disabled"><i class="icon-help"></i> Help</a></li>
                                <li class="divider"></li>
                                <li><a href="../admin/logout.php"><i class="icon-logout"></i> Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="page-content">
