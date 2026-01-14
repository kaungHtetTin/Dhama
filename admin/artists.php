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
        $name = trim($_POST['name'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $image_url = '';
        
        // Handle cropped image (base64) - priority
        if (!empty($_POST['cropped_image'])) {
            $uploaded_url = uploadBase64Image($_POST['cropped_image'], 'artists', 500);
            if ($uploaded_url) {
                $image_url = $uploaded_url;
            } else {
                $message = 'Failed to process cropped image';
                $message_type = 'error';
            }
        }
        // Handle file upload (fallback - will be cropped automatically)
        elseif (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $uploaded_url = uploadFile($_FILES['image_file'], 'image', 'artists');
            if ($uploaded_url) {
                // Auto-crop to square
                $filepath = str_replace(UPLOAD_URL, UPLOAD_DIR, $uploaded_url);
                if (file_exists($filepath)) {
                    cropImageToSquare($filepath, 500);
                }
                $image_url = $uploaded_url;
            } else {
                $message = 'Failed to upload image. Please check file type (JPG, PNG, GIF, WEBP) and size (max 10MB)';
                $message_type = 'error';
            }
        }
        
        if (!empty($name) && empty($message)) {
            if ($action === 'create') {
                $stmt = $conn->prepare("INSERT INTO artists (name, bio, image_url) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $name, $bio, $image_url);
                if ($stmt->execute()) {
                    $message = 'Artist created successfully';
                    $message_type = 'success';
                } else {
                    $message = 'Error creating artist';
                    $message_type = 'error';
                }
            } else {
                $id = intval($_POST['id']);
                
                // Get old image URL to delete if new one is uploaded
                $old_result = $conn->query("SELECT image_url FROM artists WHERE id = $id");
                $old_data = $old_result->fetch_assoc();
                $old_image_url = $old_data['image_url'] ?? '';
                
                $stmt = $conn->prepare("UPDATE artists SET name = ?, bio = ?, image_url = ? WHERE id = ?");
                $stmt->bind_param("sssi", $name, $bio, $image_url, $id);
                if ($stmt->execute()) {
                    // Delete old image if new one was uploaded
                    if (!empty($old_image_url) && $image_url !== $old_image_url && strpos($old_image_url, UPLOAD_URL) === 0) {
                        deleteFile($old_image_url);
                    }
                    $message = 'Artist updated successfully';
                    $message_type = 'success';
                } else {
                    $message = 'Error updating artist';
                    $message_type = 'error';
                }
            }
            $stmt->close();
        } elseif (empty($message)) {
            $message = 'Name is required';
            $message_type = 'error';
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        
        // Get image URL to delete file
        $result = $conn->query("SELECT image_url FROM artists WHERE id = $id");
        if ($result->num_rows === 1) {
            $artist = $result->fetch_assoc();
            if (!empty($artist['image_url']) && strpos($artist['image_url'], UPLOAD_URL) === 0) {
                deleteFile($artist['image_url']);
            }
        }
        
        $stmt = $conn->prepare("DELETE FROM artists WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = 'Artist deleted successfully';
            $message_type = 'success';
        } else {
            $message = 'Error deleting artist';
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// Pagination and Search
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$offset = ($page - 1) * $perPage;

// Build query with search
$whereClause = '';
$params = [];
$types = '';

if (!empty($search)) {
    $whereClause = "WHERE name LIKE ? OR bio LIKE ?";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types = 'ss';
}

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM artists $whereClause";
if (!empty($params)) {
    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalArtists = $countResult->fetch_assoc()['total'];
    $countStmt->close();
} else {
    $countResult = $conn->query($countQuery);
    $totalArtists = $countResult->fetch_assoc()['total'];
}
$totalPages = ceil($totalArtists / $perPage);

// Get artists with pagination
$query = "SELECT * FROM artists $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

if (!empty($search)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $artists = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, $perPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $artists = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get artist for editing
$edit_artist = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $result = $conn->query("SELECT * FROM artists WHERE id = $id");
    if ($result->num_rows === 1) {
        $edit_artist = $result->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artists - Dhama Podcast Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500&family=Roboto:wght@400;500&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
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
        }
        .file-preview {
            margin-top: 12px;
            text-align: center;
        }
        .file-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 4px;
            box-shadow: var(--shadow);
        }
        .current-image {
            margin-top: 8px;
            padding: 12px;
            background: var(--bg-color);
            border-radius: 4px;
        }
        .current-image img {
            max-width: 150px;
            max-height: 150px;
            border-radius: 4px;
        }
        .cropper-container {
            margin-top: 12px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        .cropper-container img {
            max-width: 100%;
            max-height: 400px;
        }
        .cropper-wrapper {
            display: none;
            margin-top: 12px;
        }
        .cropper-wrapper.active {
            display: block;
        }
        .cropper-preview {
            width: 200px;
            height: 200px;
            overflow: hidden;
            margin: 12px auto;
            border: 2px solid var(--border-color);
            border-radius: 4px;
        }
        .cropper-actions {
            margin-top: 12px;
            text-align: center;
        }
        .cropper-actions button {
            margin: 0 4px;
        }
        #cropped-preview {
            display: none;
            margin-top: 12px;
            text-align: center;
        }
        #cropped-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 4px;
            box-shadow: var(--shadow);
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
                <a href="artists.php" class="nav-item active">
                    <span class="nav-item-icon">👤</span>
                    Artists
                </a>
                <a href="songs.php" class="nav-item">
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
                <div class="header-title">Artists</div>
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
                        <h2 class="page-title"><?php echo $edit_artist ? 'Edit Artist' : 'Artists'; ?></h2>
                        <p class="page-subtitle"><?php echo $edit_artist ? 'Update artist information' : 'Manage your artists and performers'; ?></p>
                    </div>
                    <?php if (!$edit_artist): ?>
                        <button onclick="showForm()" class="btn btn-primary">+ Add Artist</button>
                    <?php endif; ?>
                </div>

                <?php if ($edit_artist): ?>
                    <div class="card">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?php echo $edit_artist['id']; ?>">
                            
                            <div class="form-group">
                                <label for="name">Name *</label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($edit_artist['name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="bio">Bio</label>
                                <textarea id="bio" name="bio" rows="4"><?php echo htmlspecialchars($edit_artist['bio']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Artist Image (Square - 500x500px)</label>
                                <?php if ($edit_artist['image_url']): ?>
                                    <div class="current-image">
                                        <strong>Current Image:</strong><br>
                                        <img src="<?php echo htmlspecialchars($edit_artist['image_url']); ?>" alt="Current image" id="current-image-preview">
                                    </div>
                                <?php endif; ?>
                                <div class="file-upload-wrapper">
                                    <div class="file-input-wrapper">
                                        <label for="image_file" class="file-input-label">
                                            <span id="file-label-text">📷 Choose Image File (JPG, PNG, GIF, WEBP - Max 10MB)</span>
                                        </label>
                                        <input type="file" id="image_file" name="image_file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" class="file-input" onchange="handleImageSelect(this)">
                                    </div>
                                </div>
                                
                                <!-- Cropper Container -->
                                <div class="cropper-wrapper" id="cropper-wrapper">
                                    <div class="cropper-container">
                                        <img id="cropper-image">
                                    </div>
                                    <div class="cropper-preview" id="cropper-preview"></div>
                                    <div class="cropper-actions">
                                        <button type="button" class="btn btn-primary btn-sm" onclick="cropImage()">✓ Crop to Square</button>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="cancelCrop()">Cancel</button>
                                    </div>
                                </div>
                                
                                <!-- Cropped Preview -->
                                <div id="cropped-preview">
                                    <strong>Cropped Preview:</strong><br>
                                    <img id="cropped-image-preview" alt="Cropped preview">
                                </div>
                                
                                <input type="hidden" id="cropped_image" name="cropped_image">
                                <small style="color: var(--text-secondary); margin-top: 8px; display: block;">Image will be automatically cropped to square (500x500px)</small>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Update Artist</button>
                                <?php
                                $cancelUrl = 'artists.php';
                                $params = [];
                                if (!empty($search)) {
                                    $params['search'] = $search;
                                }
                                if ($page > 1) {
                                    $params['page'] = $page;
                                }
                                if (!empty($params)) {
                                    $cancelUrl .= '?' . http_build_query($params);
                                }
                                ?>
                                <a href="<?php echo $cancelUrl; ?>" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div id="artist-form" class="card" style="display: none;">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="create">
                            
                            <div class="form-group">
                                <label for="name">Name *</label>
                                <input type="text" id="name" name="name" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="bio">Bio</label>
                                <textarea id="bio" name="bio" rows="4"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Artist Image (Square - 500x500px)</label>
                                <div class="file-upload-wrapper">
                                    <div class="file-input-wrapper">
                                        <label for="image_file" class="file-input-label">
                                            <span id="file-label-text">📷 Choose Image File (JPG, PNG, GIF, WEBP - Max 10MB)</span>
                                        </label>
                                        <input type="file" id="image_file" name="image_file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" class="file-input" onchange="handleImageSelect(this)">
                                    </div>
                                </div>
                                
                                <!-- Cropper Container -->
                                <div class="cropper-wrapper" id="cropper-wrapper">
                                    <div class="cropper-container">
                                        <img id="cropper-image">
                                    </div>
                                    <div class="cropper-preview" id="cropper-preview"></div>
                                    <div class="cropper-actions">
                                        <button type="button" class="btn btn-primary btn-sm" onclick="cropImage()">✓ Crop to Square</button>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="cancelCrop()">Cancel</button>
                                    </div>
                                </div>
                                
                                <!-- Cropped Preview -->
                                <div id="cropped-preview">
                                    <strong>Cropped Preview:</strong><br>
                                    <img id="cropped-image-preview" alt="Cropped preview">
                                </div>
                                
                                <input type="hidden" id="cropped_image" name="cropped_image">
                                <small style="color: var(--text-secondary); margin-top: 8px; display: block;">Image will be automatically cropped to square (500x500px)</small>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Create Artist</button>
                                <button type="button" onclick="hideForm()" class="btn btn-secondary">Cancel</button>
                            </div>
                        </form>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">All Artists (<?php echo $totalArtists; ?>)</div>
                        </div>
                        
                        <!-- Search Bar -->
                        <div style="margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid var(--border-color);">
                            <form method="GET" action="" style="display: flex; gap: 8px; align-items: center;">
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name or bio..." style="flex: 1; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 4px; font-size: 14px;">
                                <button type="submit" class="btn btn-primary">Search</button>
                                <?php if (!empty($search)): ?>
                                    <a href="artists.php" class="btn btn-secondary">Clear</a>
                                <?php endif; ?>
                            </form>
                        </div>
                        
                        <?php if (empty($artists)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">👤</div>
                                <div class="empty-state-text"><?php echo !empty($search) ? 'No artists found matching your search' : 'No artists found'; ?></div>
                                <div class="empty-state-subtext"><?php echo !empty($search) ? 'Try a different search term' : 'Add your first artist to get started'; ?></div>
                            </div>
                        <?php else: ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Bio</th>
                                        <th>Image</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($artists as $artist): ?>
                                        <tr>
                                            <td><?php echo $artist['id']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($artist['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars(substr($artist['bio'], 0, 50)) . (strlen($artist['bio']) > 50 ? '...' : ''); ?></td>
                                            <td>
                                                <?php if ($artist['image_url']): ?>
                                                    <img src="<?php echo htmlspecialchars($artist['image_url']); ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($artist['created_at'])); ?></td>
                                            <td>
                                                <?php
                                                $editUrl = "?action=edit&id=" . $artist['id'];
                                                if (!empty($search)) {
                                                    $editUrl .= "&search=" . urlencode($search);
                                                }
                                                if ($page > 1) {
                                                    $editUrl .= "&page=" . $page;
                                                }
                                                ?>
                                                <a href="<?php echo $editUrl; ?>" class="btn btn-primary btn-sm">Edit</a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this artist?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $artist['id']; ?>">
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
                                
                                <div style="text-align: center; margin-top: 12px; color: var(--text-secondary); font-size: 12px;">
                                    Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalArtists); ?> of <?php echo $totalArtists; ?> artist<?php echo $totalArtists != 1 ? 's' : ''; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <script>
        let cropper = null;
        let currentFileInput = null;
        
        function showForm() {
            document.getElementById('artist-form').style.display = 'block';
            document.getElementById('artist-form').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        function hideForm() {
            document.getElementById('artist-form').style.display = 'none';
        }
        
        function handleImageSelect(input) {
            if (input.files && input.files[0]) {
                currentFileInput = input;
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    // Destroy existing cropper if any
                    if (cropper) {
                        cropper.destroy();
                        cropper = null;
                    }
                    
                    // Show cropper
                    const cropperImage = document.getElementById('cropper-image');
                    const cropperWrapper = document.getElementById('cropper-wrapper');
                    const croppedPreview = document.getElementById('cropped-preview');
                    
                    cropperImage.src = e.target.result;
                    cropperWrapper.classList.add('active');
                    croppedPreview.style.display = 'none';
                    document.getElementById('cropped_image').value = '';
                    
                    // Initialize cropper with square aspect ratio
                    cropper = new Cropper(cropperImage, {
                        aspectRatio: 1,
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: 0.8,
                        restore: false,
                        guides: true,
                        center: true,
                        highlight: false,
                        cropBoxMovable: true,
                        cropBoxResizable: true,
                        toggleDragModeOnDblclick: false,
                        preview: '#cropper-preview',
                        zoomable: false,
                        scalable: false,
                        wheelZoomRatio: 0,
                        zoomOnTouch: false,
                        zoomOnWheel: false
                    });
                    
                    document.getElementById('file-label-text').textContent = '✓ ' + input.files[0].name;
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function cropImage() {
            if (!cropper) {
                return;
            }
            
            // Get cropped canvas as base64
            const canvas = cropper.getCroppedCanvas({
                width: 500,
                height: 500,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            });
            
            if (canvas) {
                const base64 = canvas.toDataURL('image/jpeg', 0.9);
                
                // Set hidden input
                document.getElementById('cropped_image').value = base64;
                
                // Show preview
                document.getElementById('cropped-image-preview').src = base64;
                document.getElementById('cropped-preview').style.display = 'block';
                
                // Hide cropper
                document.getElementById('cropper-wrapper').classList.remove('active');
                
                // Destroy cropper
                cropper.destroy();
                cropper = null;
                
                // Clear file input (since we're using base64)
                if (currentFileInput) {
                    currentFileInput.value = '';
                }
            }
        }
        
        function cancelCrop() {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            
            document.getElementById('cropper-wrapper').classList.remove('active');
            document.getElementById('cropped-preview').style.display = 'none';
            document.getElementById('cropped_image').value = '';
            
            if (currentFileInput) {
                currentFileInput.value = '';
                document.getElementById('file-label-text').textContent = '📷 Choose Image File (JPG, PNG, GIF, WEBP - Max 10MB)';
            }
        }
    </script>
</body>
</html>
