<?php
/**
 * Business Management System - Main Entry Point
 * Phase 1: Core Foundation
 */

// Define system constant
define('BMS_SYSTEM', true);

// Check if system is installed
if (!file_exists('config/config.php')) {
    // Redirect to installation
    header('Location: install/');
    exit;
}

// Include configuration
require_once 'config/config.php';

// Check if installation is complete
if (!defined('INSTALLED') || !INSTALLED) {
    // Redirect to installation
    header('Location: install/');
    exit;
}

// Check if install directory should be blocked
if (file_exists('installed.lock') && strpos($_SERVER['REQUEST_URI'], '/install/') !== false) {
    // Redirect to admin panel
    header('Location: admin/');
    exit;
}

// Redirect to admin panel
header('Location: admin/');
exit;
