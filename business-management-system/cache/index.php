<?php
/**
 * Business Management System - Cache Directory
 * Prevent direct access to cache directory
 */

// Prevent direct access
http_response_code(403);
exit('Direct access not allowed');
