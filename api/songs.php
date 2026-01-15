<?php
/**
 * Songs API Endpoint
 * GET /api/songs.php - Get all songs (supports ?artist_id=, ?search=, ?limit=, ?offset=)
 * GET /api/songs.php?id=1 - Get single song (increments play count)
 * POST /api/songs.php - Create new song
 * PUT /api/songs.php?id=1 - Update song
 * DELETE /api/songs.php?id=1 - Delete song
 */

require_once 'common.php';

$conn = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    // CREATE - POST
    if ($method === 'POST') {
        $data = getRequestData();
        $title = trim($data['title'] ?? '');
        $artist_id = intval($data['artist_id'] ?? 0);
        $description = trim($data['description'] ?? '');
        $audio_url = '';
        $cover_image_url = '';
        $duration = intval($data['duration'] ?? 0);
        
        // Validate required fields
        if (empty($title)) {
            sendError('Title is required', 400);
        }
        if ($artist_id <= 0) {
            sendError('Valid artist_id is required', 400);
        }
        
        // Verify artist exists
        $artist_check = $conn->prepare("SELECT id FROM artists WHERE id = ?");
        $artist_check->bind_param("i", $artist_id);
        $artist_check->execute();
        if ($artist_check->get_result()->num_rows === 0) {
            sendError('Artist not found', 404);
        }
        
        // Handle audio file upload (multipart/form-data)
        if (isset($_FILES['audio']) && $_FILES['audio']['error'] === UPLOAD_ERR_OK) {
            $uploaded_url = uploadFile($_FILES['audio'], 'audio', 'songs');
            if ($uploaded_url) {
                $audio_url = $uploaded_url;
            } else {
                sendError('Failed to upload audio file. Please check file type (MP3, WAV, OGG, M4A) and size (max 500MB)', 400);
            }
        }
        // Handle audio URL (if provided)
        elseif (!empty($data['audio_url'])) {
            $audio_url = $data['audio_url'];
        }
        else {
            sendError('Audio file or audio_url is required', 400);
        }
        
        // Handle cover image upload (optional)
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $uploaded_url = uploadFile($_FILES['cover_image'], 'image', 'songs');
            if ($uploaded_url) {
                $cover_image_url = $uploaded_url;
            }
        }
        // Handle base64 cover image (JSON)
        elseif (!empty($data['cover_image_base64'])) {
            $uploaded_url = uploadBase64Image($data['cover_image_base64'], 'songs', 500);
            if ($uploaded_url) {
                $cover_image_url = $uploaded_url;
            }
        }
        // Handle cover image URL (if provided)
        elseif (!empty($data['cover_image_url'])) {
            $cover_image_url = $data['cover_image_url'];
        }
        
        $stmt = $conn->prepare("INSERT INTO songs (title, artist_id, description, audio_url, cover_image_url, duration) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sisssi", $title, $artist_id, $description, $audio_url, $cover_image_url, $duration);
        
        if ($stmt->execute()) {
            $id = $conn->insert_id;
            $stmt->close();
            
            // Get the created song
            $stmt = $conn->prepare("
                SELECT s.*, a.name as artist_name, a.image_url as artist_image 
                FROM songs s 
                LEFT JOIN artists a ON s.artist_id = a.id 
                WHERE s.id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $song = $result->fetch_assoc();
            
            sendResponse($song, 201);
        } else {
            sendError('Error creating song: ' . $conn->error, 500);
        }
    }
    
    // UPDATE - PUT
    elseif ($method === 'PUT' || $method === 'PATCH') {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($id <= 0) {
            sendError('Song ID is required', 400);
        }
        
        // Check if song exists
        $check_stmt = $conn->prepare("SELECT * FROM songs WHERE id = ?");
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            sendError('Song not found', 404);
        }
        
        $old_song = $check_result->fetch_assoc();
        $old_audio_url = $old_song['audio_url'] ?? '';
        $old_cover_url = $old_song['cover_image_url'] ?? '';
        
        $data = getRequestData();
        $title = trim($data['title'] ?? $old_song['title']);
        $artist_id = isset($data['artist_id']) ? intval($data['artist_id']) : $old_song['artist_id'];
        $description = trim($data['description'] ?? $old_song['description']);
        $audio_url = $old_audio_url;
        $cover_image_url = $old_cover_url;
        $duration = isset($data['duration']) ? intval($data['duration']) : $old_song['duration'];
        
        // Verify artist exists if changed
        if ($artist_id != $old_song['artist_id']) {
            $artist_check = $conn->prepare("SELECT id FROM artists WHERE id = ?");
            $artist_check->bind_param("i", $artist_id);
            $artist_check->execute();
            if ($artist_check->get_result()->num_rows === 0) {
                sendError('Artist not found', 404);
            }
        }
        
        // Handle audio file upload (multipart/form-data)
        if (isset($_FILES['audio']) && $_FILES['audio']['error'] === UPLOAD_ERR_OK) {
            $uploaded_url = uploadFile($_FILES['audio'], 'audio', 'songs');
            if ($uploaded_url) {
                $audio_url = $uploaded_url;
            } else {
                sendError('Failed to upload audio file. Please check file type (MP3, WAV, OGG, M4A) and size (max 500MB)', 400);
            }
        }
        // Handle audio URL (if provided)
        elseif (isset($data['audio_url'])) {
            $audio_url = $data['audio_url'];
        }
        
        // Handle cover image upload (optional)
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $uploaded_url = uploadFile($_FILES['cover_image'], 'image', 'songs');
            if ($uploaded_url) {
                $cover_image_url = $uploaded_url;
            }
        }
        // Handle base64 cover image (JSON)
        elseif (!empty($data['cover_image_base64'])) {
            $uploaded_url = uploadBase64Image($data['cover_image_base64'], 'songs', 500);
            if ($uploaded_url) {
                $cover_image_url = $uploaded_url;
            }
        }
        // Handle cover image URL (if provided)
        elseif (isset($data['cover_image_url'])) {
            $cover_image_url = $data['cover_image_url'];
        }
        
        $stmt = $conn->prepare("UPDATE songs SET title = ?, artist_id = ?, description = ?, audio_url = ?, cover_image_url = ?, duration = ? WHERE id = ?");
        $stmt->bind_param("sisssii", $title, $artist_id, $description, $audio_url, $cover_image_url, $duration, $id);
        
        if ($stmt->execute()) {
            // Delete old files if new ones were uploaded
            if (!empty($old_audio_url) && $audio_url !== $old_audio_url && strpos($old_audio_url, UPLOAD_URL) === 0) {
                deleteFile($old_audio_url);
            }
            if (!empty($old_cover_url) && $cover_image_url !== $old_cover_url && strpos($old_cover_url, UPLOAD_URL) === 0) {
                deleteFile($old_cover_url);
            }
            
            $stmt->close();
            
            // Get the updated song
            $stmt = $conn->prepare("
                SELECT s.*, a.name as artist_name, a.image_url as artist_image 
                FROM songs s 
                LEFT JOIN artists a ON s.artist_id = a.id 
                WHERE s.id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $song = $result->fetch_assoc();
            
            sendResponse($song);
        } else {
            sendError('Error updating song: ' . $conn->error, 500);
        }
    }
    
    // DELETE
    elseif ($method === 'DELETE') {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($id <= 0) {
            sendError('Song ID is required', 400);
        }
        
        // Check if song exists
        $check_stmt = $conn->prepare("SELECT audio_url, cover_image_url FROM songs WHERE id = ?");
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            sendError('Song not found', 404);
        }
        
        $song = $check_result->fetch_assoc();
        $audio_url = $song['audio_url'] ?? '';
        $cover_image_url = $song['cover_image_url'] ?? '';
        
        // Delete song
        $stmt = $conn->prepare("DELETE FROM songs WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            // Delete files
            if (!empty($audio_url) && strpos($audio_url, UPLOAD_URL) === 0) {
                deleteFile($audio_url);
            }
            if (!empty($cover_image_url) && strpos($cover_image_url, UPLOAD_URL) === 0) {
                deleteFile($cover_image_url);
            }
            
            sendResponse(['message' => 'Song deleted successfully', 'id' => $id]);
        } else {
            sendError('Error deleting song: ' . $conn->error, 500);
        }
    }
    
    // READ - GET single song
    elseif ($method === 'GET' && isset($_GET['id']) && is_numeric($_GET['id'])) {
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
    }
    
    // READ - GET all songs
    elseif ($method === 'GET') {
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
    
    else {
        sendError('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), 500);
}
