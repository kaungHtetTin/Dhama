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

// Handle single file upload via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_single') {
    header('Content-Type: application/json');
    
    // Error handler to catch and log errors
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        error_log("[BULK UPLOAD PHP] Error: $errstr in $errfile on line $errline");
        echo json_encode([
            'success' => false, 
            'error' => 'Server error: ' . $errstr,
            'debug' => [
                'file' => basename($errfile),
                'line' => $errline,
                'errno' => $errno
            ]
        ]);
        exit;
    });
    
    try {
        $artist_id = intval($_POST['artist_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $duration = intval($_POST['duration'] ?? 0);
        
        error_log("[BULK UPLOAD PHP] Received upload request - Artist ID: $artist_id, Title: $title, Duration: $duration");
        
        if ($artist_id <= 0) {
            error_log("[BULK UPLOAD PHP] Error: Invalid artist ID");
            echo json_encode(['success' => false, 'error' => 'Please select an artist']);
            exit;
        }
        
        // Verify artist exists
        $artist_check = $conn->prepare("SELECT id FROM artists WHERE id = ?");
        if (!$artist_check) {
            error_log("[BULK UPLOAD PHP] Error: Failed to prepare artist check query - " . $conn->error);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
            exit;
        }
        
        $artist_check->bind_param("i", $artist_id);
        $artist_check->execute();
        if ($artist_check->get_result()->num_rows === 0) {
            error_log("[BULK UPLOAD PHP] Error: Artist ID $artist_id not found");
            echo json_encode(['success' => false, 'error' => 'Artist not found']);
            exit;
        }
        $artist_check->close();
        
        if (!isset($_FILES['audio_file'])) {
            error_log("[BULK UPLOAD PHP] Error: No file uploaded");
            // Check if it's a POST size limit issue
            $content_length = $_SERVER['CONTENT_LENGTH'] ?? 0;
            $post_max_size = ini_get('post_max_size');
            if ($content_length > 0) {
                error_log("[BULK UPLOAD PHP] Content-Length: $content_length, post_max_size: $post_max_size");
                echo json_encode([
                    'success' => false, 
                    'error' => 'File too large. POST size limit exceeded. Please increase post_max_size in php.ini to at least 1024M',
                    'debug' => [
                        'content_length' => $content_length,
                        'post_max_size' => $post_max_size,
                        'upload_max_filesize' => ini_get('upload_max_filesize')
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No file uploaded']);
            }
            exit;
        }
        
        $file_error = $_FILES['audio_file']['error'];
        if ($file_error !== UPLOAD_ERR_OK) {
            $error_messages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize (current limit: ' . ini_get('upload_max_filesize') . '). Please increase to 1024M in php.ini',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
            ];
            $error_msg = $error_messages[$file_error] ?? "Upload error code: $file_error";
            error_log("[BULK UPLOAD PHP] Error: File upload error - $error_msg");
            echo json_encode([
                'success' => false, 
                'error' => 'File upload error: ' . $error_msg,
                'debug' => [
                    'error_code' => $file_error,
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'post_max_size' => ini_get('post_max_size')
                ]
            ]);
            exit;
        }
        
        error_log("[BULK UPLOAD PHP] File received: " . $_FILES['audio_file']['name'] . " (" . $_FILES['audio_file']['size'] . " bytes)");
        
        // Upload audio file
        $file_name = $_FILES['audio_file']['name'];
        $file_size = $_FILES['audio_file']['size'];
        $file_type = $_FILES['audio_file']['type'];
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        error_log("[BULK UPLOAD PHP] Attempting to upload file: $file_name");
        error_log("[BULK UPLOAD PHP] File details - Size: $file_size bytes, Type: $file_type, Extension: $file_extension");
        
        $audio_url = uploadFile($_FILES['audio_file'], 'audio', 'songs');
        
        if (!$audio_url) {
            error_log("[BULK UPLOAD PHP] Error: uploadFile() returned false for file: $file_name");
            error_log("[BULK UPLOAD PHP] File extension: $file_extension");
            error_log("[BULK UPLOAD PHP] File size: $file_size bytes (" . round($file_size / 1024 / 1024, 2) . " MB)");
            error_log("[BULK UPLOAD PHP] File MIME type: $file_type");
            
            // Check if it's a size issue
            $maxSize = 1024 * 1024 * 1024; // 1GB
            if ($file_size > $maxSize) {
                $error_msg = "File size ($file_size bytes) exceeds maximum allowed size (1GB)";
            } else {
                $error_msg = "Failed to upload audio file. Please check file type (MP3, WAV, OGG, M4A) and size (max 1GB). Detected extension: $file_extension, MIME type: $file_type";
            }
            
            echo json_encode([
                'success' => false, 
                'error' => $error_msg,
                'debug' => [
                    'filename' => $file_name,
                    'extension' => $file_extension,
                    'size' => $file_size,
                    'mime_type' => $file_type,
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'post_max_size' => ini_get('post_max_size')
                ]
            ]);
            exit;
        }
        
        error_log("[BULK UPLOAD PHP] File uploaded successfully: $audio_url");
        
        // Extract title from filename if not provided
        if (empty($title)) {
            $title = pathinfo($_FILES['audio_file']['name'], PATHINFO_FILENAME);
            $title = preg_replace('/[_-]/', ' ', $title);
            $title = trim($title);
            error_log("[BULK UPLOAD PHP] Extracted title from filename: $title");
        }
        
        // Insert song into database
        $stmt = $conn->prepare("INSERT INTO songs (title, artist_id, description, audio_url, duration) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            error_log("[BULK UPLOAD PHP] Error: Failed to prepare INSERT query - " . $conn->error);
            deleteFile($audio_url);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
            exit;
        }
        
        $description = '';
        $typeString = "s" . "i" . "s" . "s" . "i";
        $stmt->bind_param($typeString, $title, $artist_id, $description, $audio_url, $duration);
        
        if (!$stmt->execute()) {
            error_log("[BULK UPLOAD PHP] Error: Failed to execute INSERT - " . $stmt->error);
            // Delete uploaded file if database insert failed
            deleteFile($audio_url);
            echo json_encode(['success' => false, 'error' => 'Failed to save song: ' . $stmt->error]);
            $stmt->close();
            exit;
        }
        
        $song_id = $conn->insert_id;
        $stmt->close();
        error_log("[BULK UPLOAD PHP] Song inserted successfully with ID: $song_id");
        
        // Get the created song
        $stmt = $conn->prepare("SELECT s.*, a.name as artist_name FROM songs s LEFT JOIN artists a ON s.artist_id = a.id WHERE s.id = ?");
        if (!$stmt) {
            error_log("[BULK UPLOAD PHP] Error: Failed to prepare SELECT query - " . $conn->error);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
            exit;
        }
        
        $stmt->bind_param("i", $song_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $song = $result->fetch_assoc();
        $stmt->close();
        
        error_log("[BULK UPLOAD PHP] Upload completed successfully for song ID: $song_id");
        echo json_encode(['success' => true, 'song' => $song, 'message' => 'Song uploaded successfully']);
        
    } catch (Exception $e) {
        error_log("[BULK UPLOAD PHP] Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        echo json_encode([
            'success' => false, 
            'error' => 'Server error: ' . $e->getMessage(),
            'debug' => [
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ]
        ]);
    } catch (Error $e) {
        error_log("[BULK UPLOAD PHP] Fatal Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        echo json_encode([
            'success' => false, 
            'error' => 'Fatal error: ' . $e->getMessage(),
            'debug' => [
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ]
        ]);
    }
    
    restore_error_handler();
    exit;
}

// Handle bulk upload (legacy - kept for backward compatibility)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_upload') {
    $artist_id = intval($_POST['artist_id'] ?? 0);
    
    if ($artist_id <= 0) {
        $message = 'Please select an artist';
        $message_type = 'error';
    } elseif (!isset($_FILES['audio_files']) || empty($_FILES['audio_files']['name'][0])) {
        $message = 'Please select at least one audio file';
        $message_type = 'error';
    } else {
        $uploaded_count = 0;
        $failed_count = 0;
        $errors = [];
        
        // Verify artist exists
        $artist_check = $conn->prepare("SELECT id FROM artists WHERE id = ?");
        $artist_check->bind_param("i", $artist_id);
        $artist_check->execute();
        if ($artist_check->get_result()->num_rows === 0) {
            $message = 'Artist not found';
            $message_type = 'error';
        } else {
            // Process each uploaded file
            $file_count = count($_FILES['audio_files']['name']);
            
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['audio_files']['error'][$i] === UPLOAD_ERR_OK) {
                    // Create a temporary file array for uploadFile function
                    $file = [
                        'name' => $_FILES['audio_files']['name'][$i],
                        'type' => $_FILES['audio_files']['type'][$i],
                        'tmp_name' => $_FILES['audio_files']['tmp_name'][$i],
                        'error' => $_FILES['audio_files']['error'][$i],
                        'size' => $_FILES['audio_files']['size'][$i]
                    ];
                    
                    // Upload audio file
                    $audio_url = uploadFile($file, 'audio', 'songs');
                    
                    if ($audio_url) {
                        // Extract title from filename (remove extension)
                        $title = pathinfo($_FILES['audio_files']['name'][$i], PATHINFO_FILENAME);
                        // Clean up the title (remove special characters, replace underscores/hyphens with spaces)
                        $title = preg_replace('/[_-]/', ' ', $title);
                        $title = trim($title);
                        
                        // Get duration from POST data (sent by JavaScript)
                        $duration = isset($_POST['durations'][$i]) ? intval($_POST['durations'][$i]) : 0;
                        
                        // Insert song into database
                        $stmt = $conn->prepare("INSERT INTO songs (title, artist_id, description, audio_url, duration) VALUES (?, ?, ?, ?, ?)");
                        $description = '';
                        // Type string: s (title), i (artist_id), s (description), s (audio_url), i (duration) = 5 characters
                        // Parameters: title(string), artist_id(int), description(string), audio_url(string), duration(int)
                        // Fix: type string should be 5 chars: s-i-s-s-i
                        // Correct type string: 5 characters for 5 parameters
                        $typeString = "s" . "i" . "s" . "s" . "i"; // title(s), artist_id(i), description(s), audio_url(s), duration(i)
                        $stmt->bind_param($typeString, $title, $artist_id, $description, $audio_url, $duration);
                        
                        if ($stmt->execute()) {
                            $uploaded_count++;
                        } else {
                            $failed_count++;
                            $errors[] = "Failed to save: " . htmlspecialchars($title);
                            // Delete uploaded file if database insert failed
                            deleteFile($audio_url);
                        }
                        $stmt->close();
                    } else {
                        $failed_count++;
                        $errors[] = "Failed to upload: " . htmlspecialchars($_FILES['audio_files']['name'][$i]);
                    }
                } else {
                    $failed_count++;
                    $errors[] = "Upload error for: " . htmlspecialchars($_FILES['audio_files']['name'][$i]);
                }
            }
            
            if ($uploaded_count > 0) {
                $message = "Successfully uploaded $uploaded_count song(s)";
                if ($failed_count > 0) {
                    $message .= ". $failed_count file(s) failed.";
                }
                $message_type = 'success';
            } else {
                $message = "Failed to upload any songs. " . implode(', ', $errors);
                $message_type = 'error';
            }
        }
    }
}

// Get artists for dropdown
$artists = $conn->query("SELECT id, name FROM artists ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Upload - Dhama Podcast Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500&family=Roboto:wght@400;500&display=swap">
    <style>
        .bulk-upload-container {
            max-width: 800px;
        }
        .file-list {
            margin-top: 20px;
        }
        .file-item {
            display: flex;
            flex-direction: column;
            padding: 12px;
            background: var(--bg-color);
            border-radius: 4px;
            margin-bottom: 8px;
            position: relative;
        }
        .file-item.uploading {
            border: 2px solid var(--primary-color);
        }
        .file-item.success {
            border: 2px solid #4caf50;
            background: rgba(76, 175, 80, 0.1);
        }
        .file-item.error {
            border: 2px solid #dc3545;
            background: rgba(220, 53, 69, 0.1);
        }
        .file-item-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }
        .file-info {
            flex: 1;
        }
        .file-name {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .file-meta {
            font-size: 12px;
            color: var(--text-secondary);
        }
        .file-status {
            margin-left: 12px;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        .file-status.waiting {
            background: #9e9e9e;
            color: white;
        }
        .file-status.uploading {
            background: var(--primary-color);
            color: white;
        }
        .file-status.success {
            background: #4caf50;
            color: white;
        }
        .file-status.error {
            background: #dc3545;
            color: white;
        }
        .file-duration {
            margin-left: 12px;
            padding: 4px 8px;
            background: var(--primary-color);
            color: white;
            border-radius: 4px;
            font-size: 12px;
        }
        .file-progress {
            width: 100%;
            margin-top: 8px;
            display: none;
        }
        .file-progress.active {
            display: block;
        }
        .file-progress-bar {
            width: 100%;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
        }
        .file-progress-fill {
            height: 100%;
            background: var(--primary-color);
            width: 0%;
            transition: width 0.1s;
        }
        .file-progress-text {
            font-size: 11px;
            color: var(--text-secondary);
            margin-top: 4px;
        }
        .overall-progress {
            margin-top: 20px;
            padding: 16px;
            background: var(--bg-color);
            border-radius: 4px;
            display: none;
        }
        .overall-progress.active {
            display: block;
        }
        .overall-progress-bar {
            width: 100%;
            height: 10px;
            background: #e0e0e0;
            border-radius: 5px;
            overflow: hidden;
            margin-top: 8px;
        }
        .overall-progress-fill {
            height: 100%;
            background: var(--primary-color);
            width: 0%;
            transition: width 0.3s;
        }
        .file-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        .file-input-label {
            display: block;
            padding: 16px;
            border: 2px dashed var(--border-color);
            border-radius: 4px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .file-input-label:hover {
            border-color: var(--primary-color);
            background: rgba(66, 133, 244, 0.05);
        }
        .file-input-label input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
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
                <a href="songs.php" class="nav-item">
                    <span class="nav-item-icon">🎵</span>
                    Songs
                </a>
                <a href="bulk_upload.php" class="nav-item active">
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
                <div class="header-title">Bulk Upload Songs</div>
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

                <div class="bulk-upload-container">
                    <div class="content-header">
                        <div>
                            <h2 class="page-title">Bulk Upload Songs</h2>
                            <p class="page-subtitle">Upload multiple songs at once. Song titles will be extracted from filenames.</p>
                        </div>
                    </div>

                    <div class="card">
                            <div class="form-group">
                                <label for="artist_id">Artist *</label>
                                <select id="artist_id" name="artist_id" required>
                                    <option value="">Select an artist</option>
                                    <?php foreach ($artists as $artist): ?>
                                        <option value="<?php echo $artist['id']; ?>">
                                            <?php echo htmlspecialchars($artist['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Audio Files *</label>
                                <div class="file-input-wrapper">
                                    <label class="file-input-label">
                                        <input type="file" id="audio_files" name="audio_files[]" multiple accept="audio/*" required>
                                        <div>
                                            <strong>Click to select files</strong> or drag and drop<br>
                                            <span style="font-size: 12px; color: var(--text-secondary);">Supported: MP3, WAV, OGG, M4A (max 1GB each)</span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div id="fileCount" style="margin-top: 12px; font-size: 14px; color: var(--text-secondary); display: none;">
                                <strong id="fileCountText">0 files selected</strong>
                            </div>

                            <div class="file-list" id="fileList"></div>

                            <div class="overall-progress" id="overallProgress">
                                <div><strong>Overall Progress:</strong> <span id="overallProgressText">0 / 0</span></div>
                                <div class="overall-progress-bar">
                                    <div class="overall-progress-fill" id="overallProgressFill"></div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="button" class="btn btn-primary" id="uploadBtn">Start Upload</button>
                                <button type="button" class="btn btn-secondary" id="cancelBtn" style="display: none;">Cancel</button>
                                <a href="songs.php" class="btn btn-secondary">Back to Songs</a>
                            </div>
                        </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const audioFilesInput = document.getElementById('audio_files');
        const fileList = document.getElementById('fileList');
        const uploadBtn = document.getElementById('uploadBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const artistSelect = document.getElementById('artist_id');
        const fileCount = document.getElementById('fileCount');
        const fileCountText = document.getElementById('fileCountText');
        const overallProgress = document.getElementById('overallProgress');
        const overallProgressText = document.getElementById('overallProgressText');
        const overallProgressFill = document.getElementById('overallProgressFill');
        
        let fileData = [];
        let processingCount = 0;
        let currentUploadIndex = -1;
        let isUploading = false;
        let uploadCancelled = false;

        // Handle file selection
        audioFilesInput.addEventListener('change', function(e) {
            console.log('[BULK UPLOAD] File selection triggered');
            const files = Array.from(e.target.files);
            console.log('[BULK UPLOAD] Files selected:', files.length, files.map(f => f.name));
            
            fileData = [];
            fileList.innerHTML = '';
            
            if (files.length === 0) {
                console.log('[BULK UPLOAD] No files selected');
                fileCount.style.display = 'none';
                uploadBtn.disabled = true;
                return;
            }
            
            fileCount.style.display = 'block';
            fileCountText.textContent = `${files.length} file(s) selected`;
            uploadBtn.disabled = false;

            processingCount = 0;

            files.forEach((file, index) => {
                console.log(`[BULK UPLOAD] Processing file ${index + 1}/${files.length}:`, file.name, `(${formatFileSize(file.size)})`);
                processFile(file, index, files.length);
            });
        });

        function processFile(file, index, total) {
            const fileItem = document.createElement('div');
            fileItem.className = 'file-item';
            fileItem.id = `file-item-${index}`;
            
            const fileHeader = document.createElement('div');
            fileHeader.className = 'file-item-header';
            
            const fileInfo = document.createElement('div');
            fileInfo.className = 'file-info';
            
            const fileName = document.createElement('div');
            fileName.className = 'file-name';
            fileName.textContent = file.name;
            
            const fileMeta = document.createElement('div');
            fileMeta.className = 'file-meta';
            fileMeta.textContent = 'Processing...';
            
            const fileStatus = document.createElement('span');
            fileStatus.className = 'file-status waiting';
            fileStatus.textContent = 'Waiting';
            fileStatus.id = `file-status-${index}`;
            
            fileInfo.appendChild(fileName);
            fileInfo.appendChild(fileMeta);
            fileHeader.appendChild(fileInfo);
            fileHeader.appendChild(fileStatus);
            
            // Progress bar
            const fileProgress = document.createElement('div');
            fileProgress.className = 'file-progress';
            fileProgress.id = `file-progress-${index}`;
            const progressBar = document.createElement('div');
            progressBar.className = 'file-progress-bar';
            const progressFill = document.createElement('div');
            progressFill.className = 'file-progress-fill';
            progressFill.id = `file-progress-fill-${index}`;
            const progressText = document.createElement('div');
            progressText.className = 'file-progress-text';
            progressText.id = `file-progress-text-${index}`;
            progressText.textContent = '0%';
            progressBar.appendChild(progressFill);
            fileProgress.appendChild(progressBar);
            fileProgress.appendChild(progressText);
            
            fileItem.appendChild(fileHeader);
            fileItem.appendChild(fileProgress);
            fileList.appendChild(fileItem);

            // Create audio element to get duration
            const audio = new Audio();
            const objectUrl = URL.createObjectURL(file);
            
            audio.addEventListener('loadedmetadata', function() {
                console.log(`[BULK UPLOAD] Metadata loaded for file ${index + 1}:`, file.name);
                const duration = Math.round(audio.duration);
                const durationText = formatDuration(duration);
                console.log(`[BULK UPLOAD] Duration extracted:`, duration, `seconds (${durationText})`);
                
                // Extract title from filename
                const title = file.name.replace(/\.[^/.]+$/, '').replace(/[_-]/g, ' ').trim();
                console.log(`[BULK UPLOAD] Title extracted:`, title);
                
                // Update UI
                fileMeta.textContent = `Title: ${title} | Size: ${formatFileSize(file.size)} | Duration: ${durationText}`;
                
                // Store file data
                fileData.push({
                    file: file,
                    title: title,
                    duration: duration,
                    index: index
                });
                
                processingCount++;
                console.log(`[BULK UPLOAD] Processing progress: ${processingCount}/${total}`);
                
                // Clean up
                URL.revokeObjectURL(objectUrl);
                
                if (processingCount === total) {
                    console.log(`[BULK UPLOAD] All files processed. Ready to upload:`, fileData.length);
                    fileCountText.textContent = `${total} file(s) ready to upload`;
                }
            });
            
            audio.addEventListener('error', function(e) {
                console.error(`[BULK UPLOAD] Error loading metadata for file ${index + 1}:`, file.name, e);
                fileMeta.textContent = 'Error: Could not read audio file';
                fileMeta.style.color = 'var(--danger-color, #dc3545)';
                
                // Still add file but without duration
                const title = file.name.replace(/\.[^/.]+$/, '').replace(/[_-]/g, ' ').trim();
                fileData.push({
                    file: file,
                    title: title,
                    duration: 0,
                    index: index
                });
                console.log(`[BULK UPLOAD] Added file without duration:`, title);
                
                processingCount++;
                console.log(`[BULK UPLOAD] Processing progress: ${processingCount}/${total}`);
                
                if (processingCount === total) {
                    console.log(`[BULK UPLOAD] All files processed (some with errors). Ready to upload:`, fileData.length);
                    fileCountText.textContent = `${total} file(s) ready to upload`;
                }
                
                URL.revokeObjectURL(objectUrl);
            });
            
            audio.src = objectUrl;
        }

        // Start upload process
        uploadBtn.addEventListener('click', function() {
            console.log('[BULK UPLOAD] Upload button clicked');
            console.log('[BULK UPLOAD] File data:', fileData.length, 'files');
            
            if (fileData.length === 0) {
                console.warn('[BULK UPLOAD] No files to upload');
                alert('Please select at least one audio file');
                return;
            }
            
            const artistId = artistSelect.value;
            console.log('[BULK UPLOAD] Selected artist ID:', artistId);
            
            if (!artistId) {
                console.warn('[BULK UPLOAD] No artist selected');
                alert('Please select an artist');
                return;
            }
            
            console.log('[BULK UPLOAD] Starting upload process...');
            startUpload(artistId);
        });

        // Cancel upload
        cancelBtn.addEventListener('click', function() {
            console.log('[BULK UPLOAD] Upload cancelled by user');
            console.log('[BULK UPLOAD] Current upload index:', currentUploadIndex);
            uploadCancelled = true;
            isUploading = false;
            cancelBtn.style.display = 'none';
            uploadBtn.disabled = false;
            uploadBtn.textContent = 'Start Upload';
        });

        function startUpload(artistId) {
            console.log('[BULK UPLOAD] startUpload() called with artistId:', artistId);
            
            if (isUploading) {
                console.warn('[BULK UPLOAD] Upload already in progress');
                return;
            }
            
            console.log('[BULK UPLOAD] Initializing upload process...');
            isUploading = true;
            uploadCancelled = false;
            currentUploadIndex = -1;
            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Uploading...';
            cancelBtn.style.display = 'inline-block';
            overallProgress.classList.add('active');
            
            console.log('[BULK UPLOAD] Resetting file statuses for', fileData.length, 'files');
            // Reset all file statuses
            fileData.forEach((data) => {
                const fileItem = document.getElementById(`file-item-${data.index}`);
                const fileStatus = document.getElementById(`file-status-${data.index}`);
                const fileProgress = document.getElementById(`file-progress-${data.index}`);
                
                if (!fileItem || !fileStatus || !fileProgress) {
                    console.error(`[BULK UPLOAD] Missing DOM elements for file index ${data.index}`);
                    return;
                }
                
                fileItem.className = 'file-item';
                fileStatus.className = 'file-status waiting';
                fileStatus.textContent = 'Waiting';
                fileProgress.classList.remove('active');
                document.getElementById(`file-progress-fill-${data.index}`).style.width = '0%';
                document.getElementById(`file-progress-text-${data.index}`).textContent = '0%';
            });
            
            console.log('[BULK UPLOAD] Starting first file upload...');
            uploadNext(artistId);
        }

        function uploadNext(artistId) {
            console.log('[BULK UPLOAD] uploadNext() called');
            
            if (uploadCancelled) {
                console.log('[BULK UPLOAD] Upload was cancelled, stopping');
                return;
            }
            
            currentUploadIndex++;
            console.log(`[BULK UPLOAD] Current upload index: ${currentUploadIndex}/${fileData.length}`);
            
            if (currentUploadIndex >= fileData.length) {
                // All files uploaded
                console.log('[BULK UPLOAD] All files processed');
                isUploading = false;
                uploadBtn.disabled = false;
                uploadBtn.textContent = 'Start Upload';
                cancelBtn.style.display = 'none';
                overallProgress.classList.remove('active');
                
                const successCount = fileData.filter(d => {
                    const status = document.getElementById(`file-status-${d.index}`);
                    return status && status.classList.contains('success');
                }).length;
                
                console.log(`[BULK UPLOAD] Upload complete! Success: ${successCount}/${fileData.length}`);
                alert(`Upload complete! ${successCount} of ${fileData.length} file(s) uploaded successfully.`);
                return;
            }
            
            const fileDataItem = fileData[currentUploadIndex];
            console.log(`[BULK UPLOAD] Starting upload for file ${currentUploadIndex + 1}:`, fileDataItem.title, fileDataItem.file.name);
            uploadFile(fileDataItem, artistId);
        }

        function uploadFile(fileDataItem, artistId) {
            console.log(`[BULK UPLOAD] uploadFile() called for file index ${fileDataItem.index}`);
            console.log(`[BULK UPLOAD] File details:`, {
                name: fileDataItem.file.name,
                size: fileDataItem.file.size,
                type: fileDataItem.file.type,
                title: fileDataItem.title,
                duration: fileDataItem.duration,
                artistId: artistId
            });
            
            const fileItem = document.getElementById(`file-item-${fileDataItem.index}`);
            const fileStatus = document.getElementById(`file-status-${fileDataItem.index}`);
            const fileProgress = document.getElementById(`file-progress-${fileDataItem.index}`);
            const progressFill = document.getElementById(`file-progress-fill-${fileDataItem.index}`);
            const progressText = document.getElementById(`file-progress-text-${fileDataItem.index}`);
            
            if (!fileItem || !fileStatus || !fileProgress || !progressFill || !progressText) {
                console.error(`[BULK UPLOAD] Missing DOM elements for file index ${fileDataItem.index}`, {
                    fileItem: !!fileItem,
                    fileStatus: !!fileStatus,
                    fileProgress: !!fileProgress,
                    progressFill: !!progressFill,
                    progressText: !!progressText
                });
                return;
            }
            
            // Update UI to show uploading
            fileItem.className = 'file-item uploading';
            fileStatus.className = 'file-status uploading';
            fileStatus.textContent = 'Uploading...';
            fileProgress.classList.add('active');
            
            // Create FormData
            console.log(`[BULK UPLOAD] Creating FormData...`);
            const formData = new FormData();
            formData.append('action', 'upload_single');
            formData.append('artist_id', artistId);
            formData.append('title', fileDataItem.title);
            formData.append('duration', fileDataItem.duration);
            formData.append('audio_file', fileDataItem.file);
            
            console.log(`[BULK UPLOAD] FormData created with:`, {
                action: 'upload_single',
                artist_id: artistId,
                title: fileDataItem.title,
                duration: fileDataItem.duration,
                audio_file: fileDataItem.file.name + ' (' + formatFileSize(fileDataItem.file.size) + ')'
            });
            
            // Create XMLHttpRequest for progress tracking
            console.log(`[BULK UPLOAD] Creating XMLHttpRequest...`);
            const xhr = new XMLHttpRequest();
            
            // Track upload progress
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    progressFill.style.width = percentComplete + '%';
                    progressText.textContent = Math.round(percentComplete) + '%';
                    console.log(`[BULK UPLOAD] Upload progress for ${fileDataItem.file.name}: ${Math.round(percentComplete)}% (${formatFileSize(e.loaded)}/${formatFileSize(e.total)})`);
                } else {
                    console.log(`[BULK UPLOAD] Upload progress (length not computable): ${e.loaded} bytes`);
                }
            });
            
            // Handle completion
            xhr.addEventListener('load', function() {
                console.log(`[BULK UPLOAD] XHR load event fired for ${fileDataItem.file.name}`);
                console.log(`[BULK UPLOAD] Response status: ${xhr.status} ${xhr.statusText}`);
                console.log(`[BULK UPLOAD] Response headers:`, xhr.getAllResponseHeaders());
                console.log(`[BULK UPLOAD] Response text (first 500 chars):`, xhr.responseText.substring(0, 500));
                
                if (xhr.status === 200) {
                    try {
                        console.log(`[BULK UPLOAD] Attempting to parse JSON response...`);
                        const response = JSON.parse(xhr.responseText);
                        console.log(`[BULK UPLOAD] Parsed response:`, response);
                        
                        if (response.success) {
                            // Success
                            console.log(`[BULK UPLOAD] ✅ Upload successful for ${fileDataItem.file.name}`, response.song);
                            fileItem.className = 'file-item success';
                            fileStatus.className = 'file-status success';
                            fileStatus.textContent = 'Success';
                            progressFill.style.width = '100%';
                            progressText.textContent = '100%';
                            
                            // Update overall progress
                            updateOverallProgress();
                            
                            // Upload next file
                            setTimeout(() => uploadNext(artistId), 500);
                        } else {
                            // Error from server
                            console.error(`[BULK UPLOAD] ❌ Server returned error for ${fileDataItem.file.name}:`, response.error);
                            fileItem.className = 'file-item error';
                            fileStatus.className = 'file-status error';
                            fileStatus.textContent = 'Error';
                            progressText.textContent = response.error || 'Upload failed';
                            
                            updateOverallProgress();
                            setTimeout(() => uploadNext(artistId), 500);
                        }
                    } catch (e) {
                        // JSON parse error
                        console.error(`[BULK UPLOAD] ❌ JSON parse error for ${fileDataItem.file.name}:`, e);
                        console.error(`[BULK UPLOAD] Full response text:`, xhr.responseText);
                        fileItem.className = 'file-item error';
                        fileStatus.className = 'file-status error';
                        fileStatus.textContent = 'Error';
                        progressText.textContent = 'Server error';
                        
                        updateOverallProgress();
                        setTimeout(() => uploadNext(artistId), 500);
                    }
                } else {
                    // HTTP error
                    console.error(`[BULK UPLOAD] ❌ HTTP error ${xhr.status} for ${fileDataItem.file.name}`);
                    console.error(`[BULK UPLOAD] Response text:`, xhr.responseText);
                    fileItem.className = 'file-item error';
                    fileStatus.className = 'file-status error';
                    fileStatus.textContent = 'Error';
                    progressText.textContent = 'HTTP ' + xhr.status;
                    
                    updateOverallProgress();
                    setTimeout(() => uploadNext(artistId), 500);
                }
            });
            
            // Handle errors
            xhr.addEventListener('error', function(e) {
                console.error(`[BULK UPLOAD] ❌ Network error for ${fileDataItem.file.name}:`, e);
                console.error(`[BULK UPLOAD] XHR readyState: ${xhr.readyState}, status: ${xhr.status}`);
                fileItem.className = 'file-item error';
                fileStatus.className = 'file-status error';
                fileStatus.textContent = 'Error';
                progressText.textContent = 'Network error';
                
                updateOverallProgress();
                setTimeout(() => uploadNext(artistId), 500);
            });
            
            // Handle abort
            xhr.addEventListener('abort', function() {
                console.warn(`[BULK UPLOAD] ⚠️ Upload aborted for ${fileDataItem.file.name}`);
            });
            
            // Handle timeout
            xhr.addEventListener('timeout', function() {
                console.error(`[BULK UPLOAD] ❌ Upload timeout for ${fileDataItem.file.name}`);
                fileItem.className = 'file-item error';
                fileStatus.className = 'file-status error';
                fileStatus.textContent = 'Error';
                progressText.textContent = 'Timeout';
                
                updateOverallProgress();
                setTimeout(() => uploadNext(artistId), 500);
            });
            
            // Send request
            const url = 'bulk_upload.php';
            console.log(`[BULK UPLOAD] Opening POST request to: ${url}`);
            xhr.open('POST', url, true);
            
            // Set timeout (30 minutes for large files)
            xhr.timeout = 30 * 60 * 1000;
            console.log(`[BULK UPLOAD] Request timeout set to 30 minutes`);
            
            console.log(`[BULK UPLOAD] Sending request...`);
            xhr.send(formData);
            console.log(`[BULK UPLOAD] Request sent, waiting for response...`);
        }

        function updateOverallProgress() {
            const completed = currentUploadIndex + 1;
            const total = fileData.length;
            const percentage = (completed / total) * 100;
            
            console.log(`[BULK UPLOAD] Overall progress: ${completed}/${total} (${Math.round(percentage)}%)`);
            
            overallProgressText.textContent = `${completed} / ${total}`;
            overallProgressFill.style.width = percentage + '%';
        }

        function formatDuration(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        }

        function formatFileSize(bytes) {
            if (bytes >= 1073741824) {
                return (bytes / 1073741824).toFixed(2) + ' GB';
            } else if (bytes >= 1048576) {
                return (bytes / 1048576).toFixed(2) + ' MB';
            } else if (bytes >= 1024) {
                return (bytes / 1024).toFixed(2) + ' KB';
            }
            return bytes + ' bytes';
        }
    </script>
</body>
</html>
