<?php
/**
 * File Upload Helper Functions
 */

/**
 * Upload a file and return the URL
 * @param array $file $_FILES array element
 * @param string $type 'image' or 'audio'
 * @param string $subfolder Optional subfolder (e.g., 'artists', 'songs')
 * @return string|false URL on success, false on failure
 */
function uploadFile($file, $type = 'image', $subfolder = '') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Validate file type
    $allowedTypes = [];
    $allowedExtensions = [];
    
    if ($type === 'image') {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $uploadDir = UPLOAD_DIR . 'images/';
    } elseif ($type === 'audio') {
        $allowedTypes = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/m4a'];
        $allowedExtensions = ['mp3', 'wav', 'ogg', 'm4a'];
        $uploadDir = UPLOAD_DIR . 'audio/';
    } else {
        return false;
    }
    
    // Add subfolder if specified
    if ($subfolder) {
        $uploadDir .= $subfolder . '/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
    }
    
    // Check file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return false;
    }
    
    // Get file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        return false;
    }
    
    // Check file size (10MB for images, 50MB for audio)
    $maxSize = $type === 'image' ? 10 * 1024 * 1024 : 50 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return false;
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $url = UPLOAD_URL;
        if ($type === 'image') {
            $url .= 'images/';
        } else {
            $url .= 'audio/';
        }
        if ($subfolder) {
            $url .= $subfolder . '/';
        }
        $url .= $filename;
        return $url;
    }
    
    return false;
}

/**
 * Delete a file by URL
 * @param string $url File URL
 * @return bool True on success, false on failure
 */
function deleteFile($url) {
    if (empty($url) || strpos($url, UPLOAD_URL) !== 0) {
        return false;
    }
    
    // Convert URL to file path
    $filepath = str_replace(UPLOAD_URL, UPLOAD_DIR, $url);
    
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    
    return false;
}

/**
 * Get file size in human readable format
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Check if GD library is available
 * @return bool True if GD is available, false otherwise
 */
function isGDAvailable() {
    return extension_loaded('gd') && function_exists('imagecreatefromjpeg');
}

/**
 * Crop and resize image to square
 * @param string $imagePath Path to the image file
 * @param int $size Target size (width and height)
 * @return bool True on success, false on failure
 */
function cropImageToSquare($imagePath, $size = 500) {
    // Check if GD library is available
    if (!isGDAvailable()) {
        // GD not available, return false but don't throw error
        return false;
    }
    
    if (!file_exists($imagePath)) {
        return false;
    }
    
    // Get image info
    $imageInfo = getimagesize($imagePath);
    if ($imageInfo === false) {
        return false;
    }
    
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $mimeType = $imageInfo['mime'];
    
    // Create image resource based on type
    $source = false;
    switch ($mimeType) {
        case 'image/jpeg':
            if (function_exists('imagecreatefromjpeg')) {
                $source = @imagecreatefromjpeg($imagePath);
            }
            break;
        case 'image/png':
            if (function_exists('imagecreatefrompng')) {
                $source = @imagecreatefrompng($imagePath);
            }
            break;
        case 'image/gif':
            if (function_exists('imagecreatefromgif')) {
                $source = @imagecreatefromgif($imagePath);
            }
            break;
        case 'image/webp':
            if (function_exists('imagecreatefromwebp')) {
                $source = @imagecreatefromwebp($imagePath);
            }
            break;
        default:
            return false;
    }
    
    if (!$source) {
        return false;
    }
    
    // Calculate crop dimensions (center crop)
    $minDimension = min($width, $height);
    $cropX = ($width - $minDimension) / 2;
    $cropY = ($height - $minDimension) / 2;
    
    // Create square image
    $destination = imagecreatetruecolor($size, $size);
    if (!$destination) {
        imagedestroy($source);
        return false;
    }
    
    // Preserve transparency for PNG and GIF
    if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
        imagealphablending($destination, false);
        imagesavealpha($destination, true);
        $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
        imagefilledrectangle($destination, 0, 0, $size, $size, $transparent);
    }
    
    // Resize and crop
    imagecopyresampled(
        $destination, $source,
        0, 0, $cropX, $cropY,
        $size, $size, $minDimension, $minDimension
    );
    
    // Save the cropped image
    $result = false;
    switch ($mimeType) {
        case 'image/jpeg':
            if (function_exists('imagejpeg')) {
                $result = @imagejpeg($destination, $imagePath, 90);
            }
            break;
        case 'image/png':
            if (function_exists('imagepng')) {
                $result = @imagepng($destination, $imagePath, 9);
            }
            break;
        case 'image/gif':
            if (function_exists('imagegif')) {
                $result = @imagegif($destination, $imagePath);
            }
            break;
        case 'image/webp':
            if (function_exists('imagewebp')) {
                $result = @imagewebp($destination, $imagePath, 90);
            }
            break;
    }
    
    // Clean up
    imagedestroy($source);
    imagedestroy($destination);
    
    return $result;
}

/**
 * Process base64 image data and save as square
 * @param string $base64Data Base64 encoded image data
 * @param string $subfolder Subfolder for upload
 * @return string|false URL on success, false on failure
 */
function uploadBase64Image($base64Data, $subfolder = 'artists', $size = 500) {
    // Remove data URL prefix if present
    if (strpos($base64Data, ',') !== false) {
        $base64Data = explode(',', $base64Data)[1];
    }
    
    // Decode base64
    $imageData = base64_decode($base64Data);
    if ($imageData === false) {
        return false;
    }
    
    // Create temporary file to get image info
    $tempFile = tmpfile();
    $tempPath = stream_get_meta_data($tempFile)['uri'];
    file_put_contents($tempPath, $imageData);
    
    $imageInfo = getimagesize($tempPath);
    if ($imageInfo === false) {
        fclose($tempFile);
        return false;
    }
    
    $mimeType = $imageInfo['mime'];
    $extension = '';
    
    switch ($mimeType) {
        case 'image/jpeg':
            $extension = 'jpg';
            break;
        case 'image/png':
            $extension = 'png';
            break;
        case 'image/gif':
            $extension = 'gif';
            break;
        case 'image/webp':
            $extension = 'webp';
            break;
        default:
            fclose($tempFile);
            return false;
    }
    
    // Generate filename
    $uploadDir = UPLOAD_DIR . 'images/' . $subfolder . '/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Save the image
    if (file_put_contents($filepath, $imageData)) {
        // Crop to square (only if GD is available)
        if (isGDAvailable()) {
            cropImageToSquare($filepath, $size);
        }
        
        fclose($tempFile);
        
        // Return URL
        return UPLOAD_URL . 'images/' . $subfolder . '/' . $filename;
    }
    
    fclose($tempFile);
    return false;
}
