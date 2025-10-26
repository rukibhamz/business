<?php
/**
 * Business Management System - Installation Entry Point
 * Redirects to the main installation wizard
 */

// Check if already installed
if (file_exists('../installed.lock')) {
    header('Location: ../admin/');
    exit;
}

// Redirect to installation wizard
header('Location: install.php');
exit;
