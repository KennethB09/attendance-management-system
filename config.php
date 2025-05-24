<?php
// Start a PHP session for storing user data across multiple pages

// Database credentials
$db_host = 'localhost';    // Database host (usually localhost for XAMPP)
$db_name = 'attendance_management_system';  // Database name as defined in the SQL
$db_user = 'root';         // Default XAMPP MySQL username
$db_pass = '';             // Default XAMPP MySQL password (blank)

// Set timezone (modify to match your location)
date_default_timezone_set('Asia/Manila');

// Error reporting settings
// Comment these out in production for security
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Try to connect to database with PDO

try {
    // Create a new PDO instance for database connection
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    
    // Set PDO error mode to exception for better error handling
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Prevent emulated prepared statements for better security
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    // Use UTF-8 character encoding
    $pdo->exec("SET NAMES utf8");
    
} catch(PDOException $e) {
    // If connection fails, terminate and display error message
    die("Database Connection Failed: " . $e->getMessage());
}

// Some helpful general functions
function redirect($location) {
    header("Location: $location");
    exit();
}

// Function to securely hash passwords
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

// Function to validate passwords
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Function to sanitize user input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Function to display date in a readable format
function formatDate($date, $format = 'M d, Y h:i A') {
    return date($format, strtotime($date));
}

// Define the site URL (modify based on your setup)
$site_url = 'http://localhost/attendance_management_system';

// Define color codes for attendance status
$status_colors = [
    'present' => 'green',
    'absent' => 'red',
    'late' => 'orange'
];
?>