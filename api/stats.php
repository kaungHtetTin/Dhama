<?php
/**
 * Statistics API Endpoint
 * GET /api/stats.php - Get statistics
 */

require_once 'common.php';

$conn = getDBConnection();

try {
    $stats = [];
    
    $result = $conn->query("SELECT COUNT(*) as count FROM artists");
    $stats['total_artists'] = intval($result->fetch_assoc()['count']);
    
    $result = $conn->query("SELECT COUNT(*) as count FROM songs");
    $stats['total_songs'] = intval($result->fetch_assoc()['count']);
    
    $result = $conn->query("SELECT SUM(play_count) as total FROM songs");
    $stats['total_plays'] = intval($result->fetch_assoc()['total'] ?? 0);
    
    sendResponse($stats);
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), 500);
}
