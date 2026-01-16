<?php
/**
 * Dhama Podcast API
 * RESTful API for client-side applications
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

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';
$path_parts = explode('/', trim($path, '/'));

// Response helper
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function sendError($message, $status = 400) {
    sendResponse(['error' => $message], $status);
}

// Route handling
$conn = getDBConnection();

try {
    // Get all artists
    if ($method === 'GET' && ($path === 'artists' || $path_parts[0] === 'artists')) {
        if (isset($path_parts[1]) && is_numeric($path_parts[1])) {
            // Get single artist
            $id = intval($path_parts[1]);
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
            // Get all artists (pinned first, then by name)
            $result = $conn->query("SELECT * FROM artists ORDER BY pin DESC, name");
            $artists = [];
            while ($row = $result->fetch_assoc()) {
                // Get songs count for each artist
                $songs_result = $conn->query("SELECT COUNT(*) as count FROM songs WHERE artist_id = {$row['id']}");
                $row['songs_count'] = $songs_result->fetch_assoc()['count'];
                $artists[] = $row;
            }
            sendResponse(['artists' => $artists, 'count' => count($artists)]);
        }
    }
    
    // Get all songs
    elseif ($method === 'GET' && ($path === 'songs' || $path_parts[0] === 'songs')) {
        if (isset($path_parts[1]) && is_numeric($path_parts[1])) {
            // Get single song
            $id = intval($path_parts[1]);
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
                $query .= " AND (s.title LIKE ? OR s.description LIKE ?)";
                $search_term = "%$search%";
                $params[] = $search_term;
                $params[] = $search_term;
                $types .= 'ss';
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
            $count_query = "SELECT COUNT(*) as total FROM songs s WHERE 1=1";
            if ($artist_id) {
                $count_query .= " AND s.artist_id = " . intval($artist_id);
            }
            if ($search) {
                $count_query .= " AND (s.title LIKE '%$search%' OR s.description LIKE '%$search%')";
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
    }
    
    // Get featured/popular songs
    elseif ($method === 'GET' && ($path === 'featured' || $path_parts[0] === 'featured')) {
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
    }
    
    // Get categories
    elseif ($method === 'GET' && ($path === 'categories' || $path_parts[0] === 'categories')) {
        $result = $conn->query("SELECT * FROM categories ORDER BY name");
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        sendResponse(['categories' => $categories, 'count' => count($categories)]);
    }
    
    // Get statistics
    elseif ($method === 'GET' && ($path === 'stats' || $path_parts[0] === 'stats')) {
        $stats = [];
        
        $result = $conn->query("SELECT COUNT(*) as count FROM artists");
        $stats['total_artists'] = intval($result->fetch_assoc()['count']);
        
        $result = $conn->query("SELECT COUNT(*) as count FROM songs");
        $stats['total_songs'] = intval($result->fetch_assoc()['count']);
        
        $result = $conn->query("SELECT SUM(play_count) as total FROM songs");
        $stats['total_plays'] = intval($result->fetch_assoc()['total'] ?? 0);
        
        sendResponse($stats);
    }
    
    // Default route
    elseif ($method === 'GET' && ($path === '' || $path === 'index.php')) {
        sendResponse([
            'message' => 'Dhama Podcast API',
            'version' => '1.0',
            'endpoints' => [
                'GET /api/?path=artists' => 'Get all artists',
                'GET /api/?path=artists/{id}' => 'Get single artist',
                'GET /api/?path=songs' => 'Get all songs (supports ?artist_id=, ?search=, ?limit=, ?offset=)',
                'GET /api/?path=songs/{id}' => 'Get single song (increments play count)',
                'GET /api/?path=featured' => 'Get featured/popular songs',
                'GET /api/?path=categories' => 'Get all categories',
                'GET /api/?path=stats' => 'Get statistics'
            ]
        ]);
    }
    
    else {
        sendError('Endpoint not found', 404);
    }
    
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), 500);
}
