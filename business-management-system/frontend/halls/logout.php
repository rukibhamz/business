<?php
/**
 * Business Management System - Customer Logout
 * Phase 4: Hall Booking System Module
 */

// Define system constant
define('BMS_SYSTEM', true);

// Start session
session_start();

// Clear customer session
unset($_SESSION['customer_email']);

// Redirect to login page
header('Location: login.php');
exit;
