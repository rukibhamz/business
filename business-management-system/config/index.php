<?php
/**
 * Business Management System - Config Directory
 * Prevent direct access to config directory
 */

// Prevent direct access
http_response_code(403);
exit('Direct access not allowed');

