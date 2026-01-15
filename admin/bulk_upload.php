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

// Handle bulk upload
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
            align-items: center;
            justify-content: space-between;
            padding: 12px;
            background: var(--bg-color);
            border-radius: 4px;
            margin-bottom: 8px;
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
        .file-duration {
            margin-left: 12px;
            padding: 4px 8px;
            background: var(--primary-color);
            color: white;
            border-radius: 4px;
            font-size: 12px;
        }
        .remove-file {
            margin-left: 12px;
            padding: 4px 12px;
            background: var(--danger-color, #dc3545);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .remove-file:hover {
            opacity: 0.9;
        }
        .upload-progress {
            display: none;
            margin-top: 20px;
            padding: 16px;
            background: var(--bg-color);
            border-radius: 4px;
        }
        .upload-progress.active {
            display: block;
        }
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }
        .progress-fill {
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

                    <form method="POST" action="" enctype="multipart/form-data" id="bulkUploadForm">
                        <input type="hidden" name="action" value="bulk_upload">
                        
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
                                            <span style="font-size: 12px; color: var(--text-secondary);">Supported: MP3, WAV, OGG, M4A (max 500MB each)</span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div id="fileCount" style="margin-top: 12px; font-size: 14px; color: var(--text-secondary); display: none;">
                                <strong id="fileCountText">0 files selected</strong>
                            </div>

                            <div class="file-list" id="fileList"></div>

                            <div class="upload-progress" id="uploadProgress">
                                <div>Processing files and extracting metadata...</div>
                                <div class="progress-bar">
                                    <div class="progress-fill" id="progressFill"></div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary" id="submitBtn">Upload Songs</button>
                                <a href="songs.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        const audioFilesInput = document.getElementById('audio_files');
        const fileList = document.getElementById('fileList');
        const uploadProgress = document.getElementById('uploadProgress');
        const progressFill = document.getElementById('progressFill');
        const submitBtn = document.getElementById('submitBtn');
        const form = document.getElementById('bulkUploadForm');
        const fileCount = document.getElementById('fileCount');
        const fileCountText = document.getElementById('fileCountText');
        
        let fileData = [];
        let processingCount = 0;

        // Handle file selection
        audioFilesInput.addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            fileData = [];
            fileList.innerHTML = '';
            
            if (files.length === 0) {
                fileCount.style.display = 'none';
                return;
            }
            
            fileCount.style.display = 'block';
            fileCountText.textContent = `${files.length} file(s) selected`;

            uploadProgress.classList.add('active');
            submitBtn.disabled = true;
            processingCount = 0;

            files.forEach((file, index) => {
                processFile(file, index, files.length);
            });
        });

        function processFile(file, index, total) {
            const fileItem = document.createElement('div');
            fileItem.className = 'file-item';
            fileItem.id = `file-item-${index}`;
            
            const fileInfo = document.createElement('div');
            fileInfo.className = 'file-info';
            
            const fileName = document.createElement('div');
            fileName.className = 'file-name';
            fileName.textContent = file.name;
            
            const fileMeta = document.createElement('div');
            fileMeta.className = 'file-meta';
            fileMeta.textContent = 'Processing...';
            
            fileInfo.appendChild(fileName);
            fileInfo.appendChild(fileMeta);
            fileItem.appendChild(fileInfo);
            fileList.appendChild(fileItem);

            // Create audio element to get duration
            const audio = new Audio();
            const objectUrl = URL.createObjectURL(file);
            
            audio.addEventListener('loadedmetadata', function() {
                const duration = Math.round(audio.duration);
                const durationText = formatDuration(duration);
                
                // Extract title from filename
                const title = file.name.replace(/\.[^/.]+$/, '').replace(/[_-]/g, ' ').trim();
                
                // Update UI
                fileMeta.textContent = `Title: ${title} | Size: ${formatFileSize(file.size)}`;
                
                const durationBadge = document.createElement('span');
                durationBadge.className = 'file-duration';
                durationBadge.textContent = durationText;
                fileItem.appendChild(durationBadge);
                
                // Store file data
                fileData.push({
                    file: file,
                    title: title,
                    duration: duration
                });
                
                processingCount++;
                updateProgress(processingCount, total);
                
                // Clean up
                URL.revokeObjectURL(objectUrl);
                
                if (processingCount === total) {
                    uploadProgress.classList.remove('active');
                    submitBtn.disabled = false;
                    fileCountText.textContent = `${total} file(s) ready to upload`;
                }
            });
            
            audio.addEventListener('error', function() {
                fileMeta.textContent = 'Error: Could not read audio file';
                fileMeta.style.color = 'var(--danger-color, #dc3545)';
                
                // Still add file but without duration
                fileData.push({
                    file: file,
                    title: file.name.replace(/\.[^/.]+$/, '').replace(/[_-]/g, ' ').trim(),
                    duration: 0
                });
                
                processingCount++;
                updateProgress(processingCount, total);
                
                if (processingCount === total) {
                    uploadProgress.classList.remove('active');
                    submitBtn.disabled = false;
                    fileCountText.textContent = `${total} file(s) ready to upload`;
                }
                
                URL.revokeObjectURL(objectUrl);
            });
            
            audio.src = objectUrl;
        }

        function updateProgress(current, total) {
            const percentage = (current / total) * 100;
            progressFill.style.width = percentage + '%';
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

        // Handle form submission - add duration data
        form.addEventListener('submit', function(e) {
            if (fileData.length === 0) {
                e.preventDefault();
                alert('Please select at least one audio file');
                return false;
            }

            // Create hidden inputs for durations
            fileData.forEach((data, index) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `durations[${index}]`;
                input.value = data.duration;
                form.appendChild(input);
            });

            submitBtn.disabled = true;
            submitBtn.textContent = 'Uploading...';
        });
    </script>
</body>
</html>
