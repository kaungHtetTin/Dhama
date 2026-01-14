<?php
/**
 * Songs API Endpoint
 * GET /api/songs.php - Get all songs (supports ?artist_id=, ?search=, ?limit=, ?offset=)
 * GET /api/songs.php?id=1 - Get single song (increments play count)
 */

require_once 'common.php';

$conn = getDBConnection();

try {
    // Get single song by ID
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $conn->prepare("
            SELECT s.*, a.name as artist_name, a.image_url as artist_image 
            FROM songs s 
            LEFT JOIN artists a ON s.artist_id = a.id 
            WHERE s.id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            sendError('Song not found', 404);
        }
        
        $song = $result->fetch_assoc();
        
        // Increment play count
        $conn->query("UPDATE songs SET play_count = play_count + 1 WHERE id = $id");
        $song['play_count'] = intval($song['play_count']) + 1;
        
        sendResponse($song);
    } else {
        // Get all songs with filters
        $artist_id = $_GET['artist_id'] ?? null;
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
        $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
        $search = $_GET['search'] ?? '';
        
        $query = "
            SELECT s.*, a.name as artist_name, a.image_url as artist_image 
            FROM songs s 
            LEFT JOIN artists a ON s.artist_id = a.id 
            WHERE 1=1
        ";
        
        $params = [];
        $types = '';
        
        if ($artist_id) {
            $query .= " AND s.artist_id = ?";
            $params[] = intval($artist_id);
            $types .= 'i';
        }
        
        if ($search) {
            $query .= " AND (s.title LIKE ? OR s.description LIKE ? OR a.name LIKE ?)";
            $search_term = "%$search%";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $types .= 'sss';
        }
        
        $query .= " ORDER BY s.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $songs = [];
        while ($row = $result->fetch_assoc()) {
            $songs[] = $row;
        }
        
        // Get total count
        $count_query = "
            SELECT COUNT(*) as total 
            FROM songs s 
            LEFT JOIN artists a ON s.artist_id = a.id 
            WHERE 1=1
        ";
        if ($artist_id) {
            $count_query .= " AND s.artist_id = " . intval($artist_id);
        }
        if ($search) {
            $escaped_search = $conn->real_escape_string($search);
            $count_query .= " AND (s.title LIKE '%$escaped_search%' OR s.description LIKE '%$escaped_search%' OR a.name LIKE '%$escaped_search%')";
        }
        $total_result = $conn->query($count_query);
        $total = $total_result->fetch_assoc()['total'];
        
        sendResponse([
            'songs' => $songs,
            'count' => count($songs),
            'total' => intval($total),
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), 500);
}
