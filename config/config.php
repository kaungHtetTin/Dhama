<?php
/**
 * Application Configuration
 */

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// Timezone
date_default_timezone_set('UTC');

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set unlimited execution time for long-running operations (file uploads, etc.)
ini_set('max_execution_time', 0);
set_time_limit(0);

// Set unlimited memory limit for large file operations
ini_set('memory_limit', '-1');

// Set upload limits for large files (up to 1GB)
ini_set('post_max_size', '1024M');
ini_set('upload_max_filesize', '1024M');
ini_set('max_input_time', 0);

// Base URL
define('BASE_URL', 'https://www.calamuseducation.com/dhama/');

// Paths
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', BASE_URL . 'uploads/');

// Create upload directories if they don't exist
if (!file_exists(UPLOAD_DIR . 'audio/')) {
    mkdir(UPLOAD_DIR . 'audio/', 0777, true);
}
if (!file_exists(UPLOAD_DIR . 'audio/songs/')) {
    mkdir(UPLOAD_DIR . 'audio/songs/', 0777, true);
}
if (!file_exists(UPLOAD_DIR . 'images/')) {
    mkdir(UPLOAD_DIR . 'images/', 0777, true);
}
if (!file_exists(UPLOAD_DIR . 'images/artists/')) {
    mkdir(UPLOAD_DIR . 'images/artists/', 0777, true);
}
if (!file_exists(UPLOAD_DIR . 'images/songs/')) {
    mkdir(UPLOAD_DIR . 'images/songs/', 0777, true);
}

// Include database config
require_once __DIR__ . '/database.php';
