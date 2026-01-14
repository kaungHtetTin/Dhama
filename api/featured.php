<?php
/**
 * Featured Songs API Endpoint
 * GET /api/featured.php - Get featured/popular songs
 * GET /api/featured.php?limit=10 - Get featured songs with limit
 */

require_once 'common.php';

$conn = getDBConnection();

try {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $result = $conn->query("
        SELECT s.*, a.name as artist_name, a.image_url as artist_image 
        FROM songs s 
        LEFT JOIN artists a ON s.artist_id = a.id 
        ORDER BY s.play_count DESC, s.created_at DESC 
        LIMIT $limit
    ");
    
    $songs = [];
    while ($row = $result->fetch_assoc()) {
        $songs[] = $row;
    }
    
    sendResponse(['songs' => $songs, 'count' => count($songs)]);
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), 500);
}
