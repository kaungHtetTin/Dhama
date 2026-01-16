<?php
/**
 * Migration: Add pin column to artists table
 * Run this file once to add the pin feature to existing databases
 */

require_once '../config/database.php';

$conn = getDBConnection();

// Check if pin column already exists
$result = $conn->query("SHOW COLUMNS FROM artists LIKE 'pin'");

if ($result->num_rows > 0) {
    echo "Pin column already exists. Migration not needed.\n";
    exit;
}

// Add pin column
$sql = "ALTER TABLE artists ADD COLUMN pin TINYINT(1) DEFAULT 0 AFTER image_url";
if ($conn->query($sql)) {
    echo "✓ Pin column added successfully.\n";
} else {
    echo "✗ Error adding pin column: " . $conn->error . "\n";
    exit;
}

// Add index for faster sorting
$sql = "ALTER TABLE artists ADD INDEX idx_pin (pin)";
if ($conn->query($sql)) {
    echo "✓ Index added successfully.\n";
} else {
    echo "⚠ Warning: Could not add index (may already exist): " . $conn->error . "\n";
}

echo "\nMigration completed successfully!\n";
echo "You can now use the pin feature in the admin panel.\n";
