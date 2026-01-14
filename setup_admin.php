<?php
/**
 * Setup script to create/update admin account
 * Run this file once to set up the admin account
 */

require_once 'config/config.php';

// Generate password hash for admin123
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Password hash for 'admin123': " . $hash . "\n\n";

$conn = getDBConnection();

// Check if admin exists
$result = $conn->query("SELECT id FROM admins WHERE username = 'admin'");

if ($result->num_rows > 0) {
    // Update existing admin
    $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE username = 'admin'");
    $stmt->bind_param("s", $hash);
    
    if ($stmt->execute()) {
        echo "✓ Admin password updated successfully!\n";
        echo "Username: admin\n";
        echo "Password: admin123\n";
    } else {
        echo "✗ Error updating admin: " . $stmt->error . "\n";
    }
    $stmt->close();
} else {
    // Create new admin
    $stmt = $conn->prepare("INSERT INTO admins (username, password, email) VALUES ('admin', ?, 'admin@dhama.com')");
    $stmt->bind_param("s", $hash);
    
    if ($stmt->execute()) {
        echo "✓ Admin account created successfully!\n";
        echo "Username: admin\n";
        echo "Password: admin123\n";
    } else {
        echo "✗ Error creating admin: " . $stmt->error . "\n";
    }
    $stmt->close();
}

$conn->close();
?>
