<?php
/**
 * Business Management System - Uploads Directory
 * Prevent direct access to uploads directory
 */

// Prevent direct access
http_response_code(403);
exit('Direct access not allowed');

