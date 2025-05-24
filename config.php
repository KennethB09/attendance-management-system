<?php
session_start();

// Database credentials
$db_host = 'localhost';
$db_name = 'attendance_management_system';
$db_user = 'root';
$db_pass = '';

// Set timezone
date_default_timezone_set('Asia/Manila');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Try to connect to database with PDO
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $pdo->exec("SET NAMES utf8");
} catch(PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// Helper functions
function redirect($location) {
    header("Location: $location");
    exit();
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function formatDate($date, $format = 'M d, Y h:i A') {
    return date($format, strtotime($date));
}

// Site configuration
$site_url = 'http://localhost/attendance_management_system';
?>