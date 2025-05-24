<?php
require_once 'config.php';

// Initialize variables
$error = "";

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data and trim whitespace
    $admin_id = trim($_POST['admin_id']);
    $password = $_POST['password'];
    
    // Basic validation
    if (empty($admin_id) || empty($password)) {
        $error = "All fields are required";
        header("Location: admin_login.php?error=" . urlencode($error));
        exit();
    }
    
    try {
        // DEBUGGING: Log the admin ID we're searching for
        error_log("Attempting login with admin_id: " . $admin_id);
        
        // Let's first check what columns exist in the admins table
        $check_columns = $pdo->query("SHOW COLUMNS FROM admins");
        $columns = $check_columns->fetchAll(PDO::FETCH_COLUMN);
        error_log("Available columns in admins table: " . implode(", ", $columns));
        
        // Adapt our query based on column existence
        if (in_array('admin_id', $columns)) {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE admin_id = ?");
        } else {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
        }
        
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // DEBUGGING: Log whether we found a matching admin
        if ($admin) {
            error_log("Admin found with ID: " . $admin_id);
        } else {
            error_log("No admin found with ID: " . $admin_id);
        }
        
        // Verify admin credentials
        if ($admin && password_verify($password, $admin['password'])) {
            // Set session variables - use the appropriate ID field
            $_SESSION['admin_id'] = isset($admin['admin_id']) ? $admin['admin_id'] : $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['is_logged_in'] = true;
            
            // DEBUGGING: Log successful login
            error_log("Admin login successful for: " . $admin_id);
            
            // Redirect to admin dashboard
            header("Location: admin_dashboard.php");
            exit();
        } else {
            // For debugging, let's check if the password verification is failing
            if ($admin) {
                error_log("Password verification failed for admin: " . $admin_id);
            }
            
            // Invalid credentials
            $error = "Invalid admin ID or password";
            header("Location: admin_login.php?error=" . urlencode($error));
            exit();
        }
    } catch (PDOException $e) {
        // Database error
        error_log("Login failed with error: " . $e->getMessage());
        $error = "Login failed: " . $e->getMessage();
        header("Location: admin_login.php?error=" . urlencode($error));
        exit();
    }
} else {
    // If accessed directly without POST
    header("Location: admin_login.php");
    exit();
}