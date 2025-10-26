<?php
/**
 * Business Management System - Admin Sidebar
 * Phase 1: Core Foundation
 * 
 * This file is included in header.php but kept separate for organization
 */

// Prevent direct access
if (!defined('BMS_SYSTEM')) {
    die('Direct access not allowed');
}

// Get current user and check permissions
$currentUser = getCurrentUser();
$userPermissions = $auth->getUserPermissions();

// Define menu items with permissions
$menuItems = [
    [
        'id' => 'dashboard',
        'title' => 'Dashboard',
        'icon' => 'icon-dashboard',
        'url' => '../admin/',
        'permission' => 'dashboard.view',
        'active' => basename($_SERVER['PHP_SELF']) == 'index.php'
    ],
    [
        'id' => 'accounting',
        'title' => 'Accounting',
        'icon' => 'icon-accounting',
        'url' => '#',
        'permission' => 'accounting.view',
        'children' => [
            [
                'title' => 'Invoices',
                'url' => '#',
                'permission' => 'accounting.invoices.view',
                'disabled' => true
            ],
            [
                'title' => 'Payments',
                'url' => '#',
                'permission' => 'accounting.payments.view',
                'disabled' => true
            ],
            [
                'title' => 'Expenses',
                'url' => '#',
                'permission' => 'accounting.expenses.view',
                'disabled' => true
            ],
            [
                'title' => 'Reports',
                'url' => '#',
                'permission' => 'accounting.reports.view',
                'disabled' => true
            ]
        ]
    ],
    [
        'id' => 'events',
        'title' => 'Events',
        'icon' => 'icon-events',
        'url' => '#',
        'permission' => 'events.view',
        'disabled' => true,
        'badge' => 'Coming Soon'
    ],
    [
        'id' => 'properties',
        'title' => 'Properties',
        'icon' => 'icon-properties',
        'url' => '#',
        'permission' => 'properties.view',
        'disabled' => true,
        'badge' => 'Coming Soon'
    ],
    [
        'id' => 'inventory',
        'title' => 'Inventory',
        'icon' => 'icon-inventory',
        'url' => '#',
        'permission' => 'inventory.view',
        'disabled' => true,
        'badge' => 'Coming Soon'
    ],
    [
        'id' => 'utilities',
        'title' => 'Utilities',
        'icon' => 'icon-utilities',
        'url' => '#',
        'permission' => 'utilities.view',
        'disabled' => true,
        'badge' => 'Coming Soon'
    ],
    [
        'id' => 'taxes',
        'title' => 'Taxes & Revenue',
        'icon' => 'icon-taxes',
        'url' => '#',
        'permission' => 'taxes.view',
        'disabled' => true,
        'badge' => 'Coming Soon'
    ],
    [
        'id' => 'users',
        'title' => 'Users',
        'icon' => 'icon-users',
        'url' => '#',
        'permission' => 'users.view',
        'children' => [
            [
                'title' => 'All Users',
                'url' => 'users/index.php',
                'permission' => 'users.view'
            ],
            [
                'title' => 'Roles & Permissions',
                'url' => 'roles/index.php',
                'permission' => 'roles.view'
            ],
            [
                'title' => 'Activity Logs',
                'url' => 'activity/index.php',
                'permission' => 'activity.view'
            ]
        ]
    ],
    [
        'id' => 'settings',
        'title' => 'Settings',
        'icon' => 'icon-settings',
        'url' => '#',
        'permission' => 'settings.view',
        'children' => [
            [
                'title' => 'General Settings',
                'url' => 'settings/general.php',
                'permission' => 'settings.edit'
            ],
            [
                'title' => 'Email Settings',
                'url' => 'settings/email.php',
                'permission' => 'settings.edit'
            ],
            [
                'title' => 'System Settings',
                'url' => 'settings/system.php',
                'permission' => 'settings.edit'
            ]
        ]
    ]
];

// Filter menu items based on permissions
$filteredMenuItems = [];
foreach ($menuItems as $item) {
    if (hasPermission($item['permission'])) {
        $filteredItem = $item;
        
        // Filter children if they exist
        if (isset($item['children'])) {
            $filteredChildren = [];
            foreach ($item['children'] as $child) {
                if (hasPermission($child['permission'])) {
                    $filteredChildren[] = $child;
                }
            }
            $filteredItem['children'] = $filteredChildren;
        }
        
        $filteredMenuItems[] = $filteredItem;
    }
}

// Get system status
$systemStatus = getSystemStatus();
$systemStats = getSystemStats();
?>

<!-- Sidebar Navigation -->
<nav class="sidebar-nav">
    <ul class="nav-menu">
        <?php foreach ($filteredMenuItems as $item): ?>
        <li class="nav-item <?php echo isset($item['children']) ? 'nav-dropdown' : ''; ?>">
            <a href="<?php echo $item['url']; ?>" 
               class="nav-link <?php echo $item['active'] ? 'active' : ''; ?> <?php echo $item['disabled'] ? 'disabled' : ''; ?>"
               <?php echo isset($item['children']) ? 'onclick="toggleDropdown(this); return false;"' : ''; ?>>
                <i class="<?php echo $item['icon']; ?>"></i>
                <span><?php echo $item['title']; ?></span>
                <?php if (isset($item['children'])): ?>
                <i class="icon-chevron-down dropdown-icon"></i>
                <?php endif; ?>
                <?php if (isset($item['badge'])): ?>
                <span class="badge"><?php echo $item['badge']; ?></span>
                <?php endif; ?>
            </a>
            
            <?php if (isset($item['children']) && !empty($item['children'])): ?>
            <ul class="dropdown-menu">
                <?php foreach ($item['children'] as $child): ?>
                <li>
                    <a href="<?php echo $child['url']; ?>" 
                       class="<?php echo $child['disabled'] ? 'disabled' : ''; ?>">
                        <?php echo $child['title']; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
</nav>

<!-- Sidebar Footer with System Info -->
<div class="sidebar-footer">
    <div class="system-info">
        <div class="system-status">
            <div class="status-indicator <?php echo $systemStatus['database'] ? 'online' : 'offline'; ?>"></div>
            <span>Database</span>
        </div>
        <div class="system-status">
            <div class="status-indicator <?php echo $systemStatus['cache'] ? 'online' : 'offline'; ?>"></div>
            <span>Cache</span>
        </div>
        <div class="system-status">
            <div class="status-indicator <?php echo $systemStatus['uploads'] ? 'online' : 'offline'; ?>"></div>
            <span>Uploads</span>
        </div>
    </div>
    
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

<script>
// Toggle dropdown menu
function toggleDropdown(element) {
    const dropdown = element.nextElementSibling;
    const icon = element.querySelector('.dropdown-icon');
    
    if (dropdown) {
        dropdown.classList.toggle('show');
        icon.classList.toggle('rotated');
    }
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    const dropdowns = document.querySelectorAll('.dropdown-menu');
    dropdowns.forEach(dropdown => {
        if (!dropdown.parentElement.contains(event.target)) {
            dropdown.classList.remove('show');
            const icon = dropdown.parentElement.querySelector('.dropdown-icon');
            if (icon) {
                icon.classList.remove('rotated');
            }
        }
    });
});

// Toggle sidebar on mobile
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('mobile-open');
}

// Close sidebar on mobile when clicking outside
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.querySelector('.mobile-menu-toggle');
    
    if (sidebar && sidebar.classList.contains('mobile-open')) {
        if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
            sidebar.classList.remove('mobile-open');
        }
    }
});

// Handle window resize
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('sidebar');
    if (window.innerWidth > 768) {
        sidebar.classList.remove('mobile-open');
    }
});
</script>
