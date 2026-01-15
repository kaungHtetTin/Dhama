<?php
require_once '../config/config.php';
require_once '../config/upload.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();
$message = '';
$message_type = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $title = trim($_POST['title'] ?? '');
        $artist_id = intval($_POST['artist_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $audio_url = '';
        $cover_image_url = '';
        $duration = intval($_POST['duration'] ?? 0);
        
        // Handle audio file upload (required)
        if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK) {
            $uploaded_url = uploadFile($_FILES['audio_file'], 'audio', 'songs');
            if ($uploaded_url) {
                $audio_url = $uploaded_url;
            } else {
                $message = 'Failed to upload audio file. Please check file type (MP3, WAV, OGG, M4A) and size (max 500MB)';
                $message_type = 'error';
            }
        }
        
        // Handle cover image file upload (optional)
        if (isset($_FILES['cover_image_file']) && $_FILES['cover_image_file']['error'] === UPLOAD_ERR_OK) {
            $uploaded_url = uploadFile($_FILES['cover_image_file'], 'image', 'songs');
            if ($uploaded_url) {
                $cover_image_url = $uploaded_url;
            } else {
                if (empty($message)) {
                    $message = 'Failed to upload cover image. Please check file type (JPG, PNG, GIF, WEBP) and size (max 10MB)';
                    $message_type = 'error';
                }
            }
        }
        
        // Audio file is required
        if (empty($audio_url)) {
            if (empty($message)) {
                $message = 'Audio file is required';
                $message_type = 'error';
            }
        }
        
        if (!empty($title) && $artist_id > 0 && !empty($audio_url) && empty($message)) {
            if ($action === 'create') {
                $stmt = $conn->prepare("INSERT INTO songs (title, artist_id, description, audio_url, cover_image_url, duration) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sisssi", $title, $artist_id, $description, $audio_url, $cover_image_url, $duration);
                if ($stmt->execute()) {
                    $message = 'Song created successfully';
                    $message_type = 'success';
                } else {
                    $message = 'Error creating song';
                    $message_type = 'error';
                }
            } else {
                $id = intval($_POST['id']);
                
                // Get old URLs to delete if new ones are uploaded
                $old_result = $conn->query("SELECT audio_url, cover_image_url FROM songs WHERE id = $id");
                $old_data = $old_result->fetch_assoc();
                $old_audio_url = $old_data['audio_url'] ?? '';
                $old_cover_url = $old_data['cover_image_url'] ?? '';
                
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
                    $message = 'Song updated successfully';
                    $message_type = 'success';
                } else {
                    $message = 'Error updating song';
                    $message_type = 'error';
                }
            }
            $stmt->close();
        } elseif (empty($message)) {
            $message = 'Title, Artist, and Audio file/URL are required';
            $message_type = 'error';
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        
        // Get URLs to delete files
        $result = $conn->query("SELECT audio_url, cover_image_url FROM songs WHERE id = $id");
        if ($result->num_rows === 1) {
            $song = $result->fetch_assoc();
            if (!empty($song['audio_url']) && strpos($song['audio_url'], UPLOAD_URL) === 0) {
                deleteFile($song['audio_url']);
            }
            if (!empty($song['cover_image_url']) && strpos($song['cover_image_url'], UPLOAD_URL) === 0) {
                deleteFile($song['cover_image_url']);
            }
        }
        
        $stmt = $conn->prepare("DELETE FROM songs WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = 'Song deleted successfully';
            $message_type = 'success';
        } else {
            $message = 'Error deleting song';
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// Pagination and Search
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;
$search = trim($_GET['search'] ?? '');

$whereClause = "";
$params = [];
$types = '';

if (!empty($search)) {
    $whereClause = "WHERE s.title LIKE ? OR s.description LIKE ? OR a.name LIKE ?";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types = 'sss';
}

// Get total count for pagination
$countQuery = "
    SELECT COUNT(*) as total 
    FROM songs s 
    LEFT JOIN artists a ON s.artist_id = a.id 
    $whereClause
";
if (!empty($params)) {
    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalSongs = $countResult->fetch_assoc()['total'];
    $countStmt->close();
} else {
    $countResult = $conn->query($countQuery);
    $totalSongs = $countResult->fetch_assoc()['total'];
}
$totalPages = ceil($totalSongs / $perPage);

// Get songs with pagination
$query = "
    SELECT s.*, a.name as artist_name 
    FROM songs s 
    LEFT JOIN artists a ON s.artist_id = a.id 
    $whereClause
    ORDER BY s.created_at DESC 
    LIMIT ? OFFSET ?
";
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$songs = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get artists for dropdown
$artists = $conn->query("SELECT id, name FROM artists ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Get song for editing
$edit_song = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $result = $conn->query("SELECT * FROM songs WHERE id = $id");
    if ($result->num_rows === 1) {
        $edit_song = $result->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Songs - Dhama Podcast Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500&family=Roboto:wght@400;500&display=swap">
    <style>
        .file-upload-wrapper {
            margin-bottom: 20px;
        }
        .file-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        .file-input-label {
            display: block;
            padding: 10px 12px;
            border: 2px dashed var(--border-color);
            border-radius: 4px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            background: var(--bg-color);
            position: relative;
            z-index: 1;
        }
        .file-input-label:hover {
            border-color: var(--primary-color);
            background: rgba(66, 133, 244, 0.05);
        }
        .file-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            top: 0;
            left: 0;
            z-index: 2;
        }
        .file-preview {
            margin-top: 12px;
            text-align: center;
            position: relative;
            z-index: 0;
            pointer-events: auto;
        }
        .file-preview * {
            pointer-events: auto;
        }
        .file-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 4px;
            box-shadow: var(--shadow);
        }
        .file-preview audio {
            width: 100%;
            max-width: 400px;
            margin-top: 8px;
            display: block;
            margin-left: auto;
            margin-right: auto;
            pointer-events: auto;
            position: relative;
            z-index: 10;
        }
        .current-file {
            margin-top: 8px;
            padding: 12px;
            background: var(--bg-color);
            border-radius: 4px;
        }
        .current-file img {
            max-width: 150px;
            max-height: 150px;
            border-radius: 4px;
        }
        .current-file audio {
            width: 100%;
            max-width: 400px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <h1>Dhama Podcast</h1>
            </div>
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item">
                    <span class="nav-item-icon">📊</span>
                    Dashboard
                </a>
                <a href="artists.php" class="nav-item">
                    <span class="nav-item-icon">👤</span>
                    Artists
                </a>
                <a href="songs.php" class="nav-item active">
                    <span class="nav-item-icon">🎵</span>
                    Songs
                </a>
                <a href="bulk_upload.php" class="nav-item">
                    <span class="nav-item-icon">📤</span>
                    Bulk Upload
                </a>
                <a href="categories.php" class="nav-item">
                    <span class="nav-item-icon">📁</span>
                    Categories
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Header -->
            <header class="admin-header">
                <div class="header-title">Songs</div>
                <div class="header-actions">
                    <div class="user-info">
                        <span>👤</span>
                        <span><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                    </div>
                    <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
                </div>
            </header>

            <!-- Content -->
            <div class="admin-content">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <span><?php echo $message_type === 'success' ? '✓' : '✗'; ?></span>
                        <span><?php echo htmlspecialchars($message); ?></span>
                    </div>
                <?php endif; ?>

                <div class="content-header">
                    <div>
                        <h2 class="page-title"><?php echo $edit_song ? 'Edit Song' : 'Songs'; ?></h2>
                        <p class="page-subtitle"><?php echo $edit_song ? 'Update song information' : 'Manage your songs and podcasts'; ?></p>
                    </div>
                    <?php if (!$edit_song): ?>
                        <div style="display: flex; gap: 12px;">
                            <button onclick="showForm()" class="btn btn-primary">+ Add Song</button>
                            <a href="bulk_upload.php" class="btn btn-secondary">📤 Bulk Upload</a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($edit_song): ?>
                    <div class="card">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?php echo $edit_song['id']; ?>">
                            
                            <div class="form-group">
                                <label for="title">Title *</label>
                                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($edit_song['title']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="artist_id">Artist *</label>
                                <select id="artist_id" name="artist_id" required>
                                    <option value="">Select Artist</option>
                                    <?php foreach ($artists as $artist): ?>
                                        <option value="<?php echo $artist['id']; ?>" <?php echo $edit_song['artist_id'] == $artist['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($artist['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($edit_song['description']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Audio File *</label>
                                <?php if ($edit_song['audio_url']): ?>
                                    <div class="current-file">
                                        <strong>Current Audio:</strong><br>
                                        <?php if (strpos($edit_song['audio_url'], UPLOAD_URL) === 0): ?>
                                            <audio controls src="<?php echo htmlspecialchars($edit_song['audio_url']); ?>"></audio>
                                        <?php else: ?>
                                            <a href="<?php echo htmlspecialchars($edit_song['audio_url']); ?>" target="_blank">Listen to current audio</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="file-upload-wrapper">
                                    <div class="file-input-wrapper">
                                        <label for="audio_file" class="file-input-label">
                                            <span id="audio-label-text">🎵 Choose Audio File (MP3, WAV, OGG, M4A - Max 500MB)</span>
                                        </label>
                                        <input type="file" id="audio_file" name="audio_file" accept="audio/mpeg,audio/mp3,audio/wav,audio/ogg,audio/m4a,audio/mp4,audio/x-m4a" class="file-input" onchange="previewAudio(this)">
                                    </div>
                                    <div class="file-preview" id="audio-preview" onclick="event.stopPropagation()"></div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Cover Image</label>
                                <?php if ($edit_song['cover_image_url']): ?>
                                    <div class="current-file">
                                        <strong>Current Cover:</strong><br>
                                        <img src="<?php echo htmlspecialchars($edit_song['cover_image_url']); ?>" alt="Current cover" id="current-cover-preview">
                                    </div>
                                <?php endif; ?>
                                <div class="file-upload-wrapper">
                                    <div class="file-input-wrapper">
                                        <label for="cover_image_file" class="file-input-label">
                                            <span id="cover-label-text">📷 Choose Cover Image (JPG, PNG, GIF, WEBP - Max 10MB)</span>
                                        </label>
                                        <input type="file" id="cover_image_file" name="cover_image_file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" class="file-input" onchange="previewImage(this)">
                                    </div>
                                    <div class="file-preview" id="cover-preview"></div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="duration">Duration (seconds)</label>
                                <input type="number" id="duration" name="duration" value="<?php echo $edit_song['duration']; ?>" min="0">
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Update Song</button>
                                <a href="songs.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div id="song-form" class="card" style="display: none;">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="create">
                            
                            <div class="form-group">
                                <label for="title">Title *</label>
                                <input type="text" id="title" name="title" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="artist_id">Artist *</label>
                                <select id="artist_id" name="artist_id" required>
                                    <option value="">Select Artist</option>
                                    <?php foreach ($artists as $artist): ?>
                                        <option value="<?php echo $artist['id']; ?>"><?php echo htmlspecialchars($artist['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="4"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Audio File *</label>
                                <div class="file-upload-wrapper">
                                    <div class="file-input-wrapper">
                                        <label for="audio_file" class="file-input-label">
                                            <span id="audio-label-text">🎵 Choose Audio File (MP3, WAV, OGG, M4A - Max 500MB)</span>
                                        </label>
                                        <input type="file" id="audio_file" name="audio_file" accept="audio/mpeg,audio/mp3,audio/wav,audio/ogg,audio/m4a,audio/mp4,audio/x-m4a" class="file-input" onchange="previewAudio(this)">
                                    </div>
                                    <div class="file-preview" id="audio-preview" onclick="event.stopPropagation()"></div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Cover Image</label>
                                <div class="file-upload-wrapper">
                                    <div class="file-input-wrapper">
                                        <label for="cover_image_file" class="file-input-label">
                                            <span id="cover-label-text">📷 Choose Cover Image (JPG, PNG, GIF, WEBP - Max 10MB)</span>
                                        </label>
                                        <input type="file" id="cover_image_file" name="cover_image_file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" class="file-input" onchange="previewImage(this)">
                                    </div>
                                    <div class="file-preview" id="cover-preview"></div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="duration">Duration (seconds)</label>
                                <input type="number" id="duration" name="duration" min="0" value="0">
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Create Song</button>
                                <button type="button" onclick="hideForm()" class="btn btn-secondary">Cancel</button>
                            </div>
                        </form>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">All Songs (<?php echo $totalSongs; ?>)</div>
                        </div>
                        
                        <!-- Search Bar -->
                        <div style="margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid var(--border-color);">
                            <form method="GET" action="" style="display: flex; gap: 8px; align-items: center;">
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by title, description, or artist..." style="flex: 1; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 4px; font-size: 14px;">
                                <button type="submit" class="btn btn-primary">Search</button>
                                <?php if (!empty($search)): ?>
                                    <a href="songs.php" class="btn btn-secondary">Clear</a>
                                <?php endif; ?>
                            </form>
                        </div>
                        
                        <?php if (empty($songs)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">🎵</div>
                                <div class="empty-state-text"><?php echo !empty($search) ? 'No songs found matching your search' : 'No songs found'; ?></div>
                                <div class="empty-state-subtext"><?php echo !empty($search) ? 'Try a different search term' : 'Add your first song to get started'; ?></div>
                            </div>
                        <?php else: ?>
                            <div style="margin-bottom: 12px; color: var(--text-secondary); font-size: 14px;">
                                Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalSongs); ?> of <?php echo $totalSongs; ?> songs
                            </div>
                            
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Artist</th>
                                        <th>Plays</th>
                                        <th>Duration</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($songs as $song): ?>
                                        <tr>
                                            <td><?php echo $song['id']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($song['title']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($song['artist_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo number_format($song['play_count']); ?></td>
                                            <td><?php echo gmdate('i:s', $song['duration']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($song['created_at'])); ?></td>
                                            <td>
                                                <?php
                                                $editUrl = "?action=edit&id=" . $song['id'];
                                                if (!empty($search)) {
                                                    $editUrl .= "&search=" . urlencode($search);
                                                }
                                                if ($page > 1) {
                                                    $editUrl .= "&page=" . $page;
                                                }
                                                ?>
                                                <a href="<?php echo $editUrl; ?>" class="btn btn-primary btn-sm">Edit</a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this song?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $song['id']; ?>">
                                                    <?php if (!empty($search)): ?>
                                                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                                                    <?php endif; ?>
                                                    <?php if ($page > 1): ?>
                                                        <input type="hidden" name="page" value="<?php echo $page; ?>">
                                                    <?php endif; ?>
                                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border-color); display: flex; justify-content: center; align-items: center; gap: 8px; flex-wrap: wrap;">
                                    <?php
                                    $queryParams = [];
                                    if (!empty($search)) {
                                        $queryParams['search'] = $search;
                                    }
                                    $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                                    
                                    // Previous button
                                    if ($page > 1): ?>
                                        <a href="?page=<?php echo $page - 1; ?><?php echo $queryString; ?>" class="btn btn-secondary btn-sm">← Previous</a>
                                    <?php else: ?>
                                        <span class="btn btn-secondary btn-sm" style="opacity: 0.5; cursor: not-allowed; pointer-events: none;">← Previous</span>
                                    <?php endif; ?>
                                    
                                    <!-- Page numbers -->
                                    <?php
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    
                                    if ($startPage > 1): ?>
                                        <a href="?page=1<?php echo $queryString; ?>" class="btn btn-secondary btn-sm">1</a>
                                        <?php if ($startPage > 2): ?>
                                            <span style="padding: 0 8px; color: var(--text-secondary);">...</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                        <?php if ($i == $page): ?>
                                            <span class="btn btn-primary btn-sm" style="cursor: default; pointer-events: none;"><?php echo $i; ?></span>
                                        <?php else: ?>
                                            <a href="?page=<?php echo $i; ?><?php echo $queryString; ?>" class="btn btn-secondary btn-sm"><?php echo $i; ?></a>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    
                                    <?php if ($endPage < $totalPages): ?>
                                        <?php if ($endPage < $totalPages - 1): ?>
                                            <span style="padding: 0 8px; color: var(--text-secondary);">...</span>
                                        <?php endif; ?>
                                        <a href="?page=<?php echo $totalPages; ?><?php echo $queryString; ?>" class="btn btn-secondary btn-sm"><?php echo $totalPages; ?></a>
                                    <?php endif; ?>
                                    
                                    <!-- Next button -->
                                    <?php if ($page < $totalPages): ?>
                                        <a href="?page=<?php echo $page + 1; ?><?php echo $queryString; ?>" class="btn btn-secondary btn-sm">Next →</a>
                                    <?php else: ?>
                                        <span class="btn btn-secondary btn-sm" style="opacity: 0.5; cursor: not-allowed; pointer-events: none;">Next →</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function showForm() {
            document.getElementById('song-form').style.display = 'block';
            document.getElementById('song-form').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        function hideForm() {
            document.getElementById('song-form').style.display = 'none';
        }
        
        function previewImage(input) {
            const preview = document.getElementById('cover-preview');
            const labelText = document.getElementById('cover-label-text');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
                }
                
                reader.readAsDataURL(input.files[0]);
                labelText.textContent = '✓ ' + input.files[0].name;
            } else {
                preview.innerHTML = '';
                labelText.textContent = '📷 Choose Cover Image (JPG, PNG, GIF, WEBP - Max 10MB)';
            }
        }
        
        function previewAudio(input) {
            const preview = document.getElementById('audio-preview');
            const labelText = document.getElementById('audio-label-text');
            const durationInput = document.getElementById('duration');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    // Create audio element
                    const audio = document.createElement('audio');
                    audio.controls = true;
                    audio.src = e.target.result;
                    audio.preload = 'metadata';
                    audio.style.width = '100%';
                    audio.style.maxWidth = '400px';
                    audio.style.display = 'block';
                    audio.style.margin = '8px auto';
                    
                    // Prevent file selector from opening when clicking audio
                    audio.addEventListener('click', function(e) {
                        e.stopPropagation();
                    });
                    
                    audio.addEventListener('mousedown', function(e) {
                        e.stopPropagation();
                    });
                    
                    audio.addEventListener('touchstart', function(e) {
                        e.stopPropagation();
                    });
                    
                    // Get duration when metadata is loaded
                    audio.addEventListener('loadedmetadata', function() {
                        if (audio.duration && audio.duration !== Infinity && !isNaN(audio.duration)) {
                            const duration = Math.round(audio.duration);
                            if (durationInput && duration > 0) {
                                durationInput.value = duration;
                            }
                        }
                    });
                    
                    // Also try to get duration on canplay event
                    audio.addEventListener('canplay', function() {
                        if (audio.duration && audio.duration !== Infinity && !isNaN(audio.duration)) {
                            const duration = Math.round(audio.duration);
                            if (durationInput && duration > 0 && (!durationInput.value || durationInput.value == 0)) {
                                durationInput.value = duration;
                            }
                        }
                    });
                    
                    // Handle duration change
                    audio.addEventListener('durationchange', function() {
                        if (audio.duration && audio.duration !== Infinity && !isNaN(audio.duration)) {
                            const duration = Math.round(audio.duration);
                            if (durationInput && duration > 0) {
                                durationInput.value = duration;
                            }
                        }
                    });
                    
                    preview.innerHTML = '';
                    preview.appendChild(audio);
                }
                
                reader.readAsDataURL(file);
                labelText.textContent = '✓ ' + file.name + ' (' + formatFileSize(file.size) + ')';
            } else {
                preview.innerHTML = '';
                labelText.textContent = '🎵 Choose Audio File (MP3, WAV, OGG, M4A - Max 500MB)';
                if (durationInput) {
                    durationInput.value = '';
                }
            }
        }
        
        function formatFileSize(bytes) {
            if (bytes >= 1073741824) {
                return (bytes / 1073741824).toFixed(2) + ' GB';
            } else if (bytes >= 1048576) {
                return (bytes / 1048576).toFixed(2) + ' MB';
            } else if (bytes >= 1024) {
                return (bytes / 1024).toFixed(2) + ' KB';
            } else {
                return bytes + ' bytes';
            }
        }
    </script>
</body>
</html>
