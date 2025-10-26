<?php
/**
 * Business Management System - Admin Logout
 * Phase 1: Core Foundation
 */

// Define system constant
define('BMS_SYSTEM', true);

// Start session
session_start();

// Include required files
require_once '../config/config.php';
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Logout user
$auth->logout();

// Redirect to login page
redirect(BMS_ADMIN_URL . '/login.php?logout=1');
