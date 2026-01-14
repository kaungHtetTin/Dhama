<?php
/**
 * Artists API Endpoint
 * GET /api/artists.php - Get all artists
 * GET /api/artists.php?id=1 - Get single artist
 */

require_once 'common.php';

$conn = getDBConnection();

try {
    // Get single artist by ID
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $conn->prepare("SELECT * FROM artists WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            sendError('Artist not found', 404);
        }
        
        $artist = $result->fetch_assoc();
        
        // Get artist's songs count
        $songs_result = $conn->query("SELECT COUNT(*) as count FROM songs WHERE artist_id = $id");
        $artist['songs_count'] = $songs_result->fetch_assoc()['count'];
        
        sendResponse($artist);
    } else {
        // Get all artists with pagination
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
        $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
        
        // Get total count
        $count_result = $conn->query("SELECT COUNT(*) as total FROM artists");
        $total = $count_result->fetch_assoc()['total'];
        
        // Get paginated artists
        $stmt = $conn->prepare("SELECT * FROM artists ORDER BY name LIMIT ? OFFSET ?");
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $artists = [];
        while ($row = $result->fetch_assoc()) {
            // Get songs count for each artist
            $songs_result = $conn->query("SELECT COUNT(*) as count FROM songs WHERE artist_id = {$row['id']}");
            $row['songs_count'] = $songs_result->fetch_assoc()['count'];
            $artists[] = $row;
        }
        sendResponse(['artists' => $artists, 'count' => count($artists), 'total' => $total]);
    }
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), 500);
}
