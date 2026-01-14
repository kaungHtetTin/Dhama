<?php
/**
 * Categories API Endpoint
 * GET /api/categories.php - Get all categories
 */

require_once 'common.php';

$conn = getDBConnection();

try {
    $result = $conn->query("SELECT * FROM categories ORDER BY name");
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    sendResponse(['categories' => $categories, 'count' => count($categories)]);
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), 500);
}
