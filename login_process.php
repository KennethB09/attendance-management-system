<?php
// Start session to enable session variables
session_start();

// Include database configuration
require_once 'config.php';

// Initialize error message variable
$error_message = '';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $student_id = $_POST['student_id'];
    $password = $_POST['password'];
    
    try {
        // Prepare SQL statement to prevent SQL injection
        $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check if student exists and password is correct
        if ($student && password_verify($password, $student['password'])) {
            // Login successful - store student information in session
            $_SESSION['student_id'] = $student['student_id'];
            $_SESSION['student_name'] = $student['name'];
            
            // Redirect to dashboard
            header("Location: dashboard.php");
            exit();
        } else {
            // Login failed - redirect back to login page with error
            $_SESSION['login_error'] = "Invalid student ID or password";
            header("Location: login.php");
            exit();
        }
    } catch(PDOException $e) {
        // Database error occurred
        $_SESSION['login_error'] = "Database error: " . $e->getMessage();
        header("Location: login.php");
        exit();
    }
} else {
    // If someone tries to access this page directly without submitting the form
    $_SESSION['login_error'] = "Invalid access method";
    header("Location: login.php");
    exit();
}
?>