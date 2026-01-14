<?php
/**
 * Common API functions
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/config.php';
require_once '../config/upload.php';

// Response helper
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function sendError($message, $status = 400) {
    sendResponse(['error' => $message], $status);
}

// Get JSON input
function getJsonInput() {
    $json = file_get_contents('php://input');
    return json_decode($json, true);
}

// Get request data (supports both JSON and form data)
function getRequestData() {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        // Check if JSON
        if (strpos($contentType, 'application/json') !== false) {
            return getJsonInput();
        }
        
        // Otherwise return POST data
        return $_POST;
    }
    
    return [];
}
