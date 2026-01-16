<?php
/**
 * Artists API Endpoint
 * GET /api/artists.php - Get all artists
 * GET /api/artists.php?id=1 - Get single artist
 * POST /api/artists.php - Create new artist
 * PUT /api/artists.php?id=1 - Update artist
 * DELETE /api/artists.php?id=1 - Delete artist
 */

require_once 'common.php';

$conn = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    // CREATE - POST
    if ($method === 'POST') {
        $data = getRequestData();
        $name = trim($data['name'] ?? '');
        $bio = trim($data['bio'] ?? '');
        $pin = isset($data['pin']) ? (intval($data['pin']) ? 1 : 0) : 0;
        $image_url = '';
        
        // Validate required fields
        if (empty($name)) {
            sendError('Name is required', 400);
        }
        
        // Handle image upload (multipart/form-data)
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploaded_url = uploadFile($_FILES['image'], 'image', 'artists');
            if ($uploaded_url) {
                $filepath = str_replace(UPLOAD_URL, UPLOAD_DIR, $uploaded_url);
                if (file_exists($filepath)) {
                    cropImageToSquare($filepath, 500);
                }
                $image_url = $uploaded_url;
            } else {
                sendError('Failed to upload image. Please check file type (JPG, PNG, GIF, WEBP) and size (max 10MB)', 400);
            }
        }
        // Handle base64 image (JSON)
        elseif (!empty($data['image_base64'])) {
            $uploaded_url = uploadBase64Image($data['image_base64'], 'artists', 500);
            if ($uploaded_url) {
                $image_url = $uploaded_url;
            } else {
                sendError('Failed to process image', 400);
            }
        }
        // Handle image URL (if provided)
        elseif (!empty($data['image_url'])) {
            $image_url = $data['image_url'];
        }
        
        $stmt = $conn->prepare("INSERT INTO artists (name, bio, image_url, pin) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $name, $bio, $image_url, $pin);
        
        if ($stmt->execute()) {
            $id = $conn->insert_id;
            $stmt->close();
            
            // Get the created artist
            $stmt = $conn->prepare("SELECT * FROM artists WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $artist = $result->fetch_assoc();
            
            $songs_result = $conn->query("SELECT COUNT(*) as count FROM songs WHERE artist_id = $id");
            $artist['songs_count'] = $songs_result->fetch_assoc()['count'];
            
            sendResponse($artist, 201);
        } else {
            sendError('Error creating artist: ' . $conn->error, 500);
        }
    }
    
    // UPDATE - PUT
    elseif ($method === 'PUT' || $method === 'PATCH') {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($id <= 0) {
            sendError('Artist ID is required', 400);
        }
        
        // Check if artist exists
        $check_stmt = $conn->prepare("SELECT * FROM artists WHERE id = ?");
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            sendError('Artist not found', 404);
        }
        
        $old_artist = $check_result->fetch_assoc();
        $old_image_url = $old_artist['image_url'] ?? '';
        
        $data = getRequestData();
        $name = trim($data['name'] ?? $old_artist['name']);
        $bio = trim($data['bio'] ?? $old_artist['bio']);
        $pin = isset($data['pin']) ? (intval($data['pin']) ? 1 : 0) : (isset($old_artist['pin']) ? intval($old_artist['pin']) : 0);
        $image_url = $old_image_url; // Keep old image by default
        
        // Handle image upload (multipart/form-data)
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploaded_url = uploadFile($_FILES['image'], 'image', 'artists');
            if ($uploaded_url) {
                $filepath = str_replace(UPLOAD_URL, UPLOAD_DIR, $uploaded_url);
                if (file_exists($filepath)) {
                    cropImageToSquare($filepath, 500);
                }
                $image_url = $uploaded_url;
            } else {
                sendError('Failed to upload image. Please check file type (JPG, PNG, GIF, WEBP) and size (max 10MB)', 400);
            }
        }
        // Handle base64 image (JSON)
        elseif (!empty($data['image_base64'])) {
            $uploaded_url = uploadBase64Image($data['image_base64'], 'artists', 500);
            if ($uploaded_url) {
                $image_url = $uploaded_url;
            } else {
                sendError('Failed to process image', 400);
            }
        }
        // Handle image URL (if provided)
        elseif (isset($data['image_url'])) {
            $image_url = $data['image_url'];
        }
        
        $stmt = $conn->prepare("UPDATE artists SET name = ?, bio = ?, image_url = ?, pin = ? WHERE id = ?");
        $stmt->bind_param("sssii", $name, $bio, $image_url, $pin, $id);
        
        if ($stmt->execute()) {
            // Delete old image if new one was uploaded
            if (!empty($old_image_url) && $image_url !== $old_image_url && strpos($old_image_url, UPLOAD_URL) === 0) {
                deleteFile($old_image_url);
            }
            
            $stmt->close();
            
            // Get the updated artist
            $stmt = $conn->prepare("SELECT * FROM artists WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $artist = $result->fetch_assoc();
            
            $songs_result = $conn->query("SELECT COUNT(*) as count FROM songs WHERE artist_id = $id");
            $artist['songs_count'] = $songs_result->fetch_assoc()['count'];
            
            sendResponse($artist);
        } else {
            sendError('Error updating artist: ' . $conn->error, 500);
        }
    }
    
    // DELETE
    elseif ($method === 'DELETE') {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($id <= 0) {
            sendError('Artist ID is required', 400);
        }
        
        // Check if artist exists
        $check_stmt = $conn->prepare("SELECT image_url FROM artists WHERE id = ?");
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            sendError('Artist not found', 404);
        }
        
        $artist = $check_result->fetch_assoc();
        $image_url = $artist['image_url'] ?? '';
        
        // Delete artist
        $stmt = $conn->prepare("DELETE FROM artists WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            // Delete image file
            if (!empty($image_url) && strpos($image_url, UPLOAD_URL) === 0) {
                deleteFile($image_url);
            }
            
            sendResponse(['message' => 'Artist deleted successfully', 'id' => $id]);
        } else {
            sendError('Error deleting artist: ' . $conn->error, 500);
        }
    }
    
    // READ - GET single artist
    elseif ($method === 'GET' && isset($_GET['id']) && is_numeric($_GET['id'])) {
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
    }
    
    // READ - GET all artists
    elseif ($method === 'GET') {
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
        $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
        
        // Get total count
        $count_result = $conn->query("SELECT COUNT(*) as total FROM artists");
        $total = $count_result->fetch_assoc()['total'];
        
        // Get paginated artists (pinned first, then by name)
        $stmt = $conn->prepare("SELECT * FROM artists ORDER BY pin DESC, name LIMIT ? OFFSET ?");
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
    
    else {
        sendError('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), 500);
}
