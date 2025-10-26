<?php
/**
 * Business Management System - Logs Directory
 * Prevent direct access to logs directory
 */

// Prevent direct access
http_response_code(403);
exit('Direct access not allowed');

