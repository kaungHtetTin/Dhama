<?php
/**
 * Check GD Library Status
 * Run this file to check if GD extension is enabled
 */

echo "<h2>PHP GD Library Check</h2>";

if (extension_loaded('gd')) {
    echo "<p style='color: green;'>✓ GD Library is loaded</p>";
    
    $gdInfo = gd_info();
    echo "<h3>GD Information:</h3>";
    echo "<pre>";
    print_r($gdInfo);
    echo "</pre>";
    
    echo "<h3>Available Functions:</h3>";
    $functions = [
        'imagecreatefromjpeg',
        'imagecreatefrompng',
        'imagecreatefromgif',
        'imagecreatefromwebp',
        'imagejpeg',
        'imagepng',
        'imagegif',
        'imagewebp'
    ];
    
    foreach ($functions as $func) {
        $status = function_exists($func) ? '✓' : '✗';
        echo "<p>$status $func</p>";
    }
} else {
    echo "<p style='color: red;'>✗ GD Library is NOT loaded</p>";
    echo "<h3>How to Enable GD Library in XAMPP:</h3>";
    echo "<ol>";
    echo "<li>Open php.ini file (usually in C:\\xampp\\php\\php.ini)</li>";
    echo "<li>Find the line: <code>;extension=gd</code></li>";
    echo "<li>Remove the semicolon to uncomment it: <code>extension=gd</code></li>";
    echo "<li>Save the file</li>";
    echo "<li>Restart Apache in XAMPP Control Panel</li>";
    echo "</ol>";
}
