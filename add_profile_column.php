<?php
// Include your database configuration
require_once 'config.php';

try {
    // SQL to add the profile_picture column to the students table
    $sql = "ALTER TABLE students ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL";
    
    // Execute the query
    $pdo->exec($sql);
    
    echo "Profile picture column added successfully!";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>