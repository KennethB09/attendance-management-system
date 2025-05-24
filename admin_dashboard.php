<?php
// Start session for user management
session_start();

// Database configuration
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'attendance_management_system';

// Establish database connection
$conn = mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

// Fetch admin data from database - ADD THIS CODE HERE
$admin_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

// Function to sanitize input data
function sanitize($data)
{
    global $conn;
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($data)));
}


// Handle AJAX requests
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    // Handle class creation
    if ($action === 'create_class') {
        $class_name = sanitize($_POST['class_name']);
        $class_code = sanitize($_POST['class_code']);
        $description = sanitize($_POST['description']);
        $start_time = sanitize($_POST['start_time']);
        $end_time = sanitize($_POST['end_time']);
        $days = isset($_POST['days']) ? implode(',', $_POST['days']) : '';

        $query = "INSERT INTO classes (name, code, description, start_time, end_time, days, created_by) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ssssssi", $class_name, $class_code, $description, $start_time, $end_time, $days, $admin_id);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['status' => 'success', 'message' => 'Class created successfully', 'class_id' => mysqli_insert_id($conn)]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create class: ' . mysqli_error($conn)]);
        }
        exit;
    }

    // Handle student addition to class
    if ($action === 'add_student_to_class') {
        $student_id = sanitize($_POST['student_id']);
        $class_id = sanitize($_POST['class_id']);

        // Check if student exists
        $check_query = "SELECT id FROM users WHERE student_id = ? AND role = 'student'";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "s", $student_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Student ID not found']);
            exit;
        }

        $user = mysqli_fetch_assoc($check_result);
        $user_id = $user['id'];

        // Check if student is already enrolled
        $enrolled_query = "SELECT id FROM class_enrollments WHERE user_id = ? AND class_id = ?";
        $enrolled_stmt = mysqli_prepare($conn, $enrolled_query);
        mysqli_stmt_bind_param($enrolled_stmt, "ii", $user_id, $class_id);
        mysqli_stmt_execute($enrolled_stmt);
        $enrolled_result = mysqli_stmt_get_result($enrolled_stmt);

        if (mysqli_num_rows($enrolled_result) > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Student is already enrolled in this class']);
            exit;
        }

        // Enroll student
        $query = "INSERT INTO class_enrollments (user_id, class_id, enrolled_date) VALUES (?, ?, NOW())";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $class_id);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['status' => 'success', 'message' => 'Student added to class successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to add student: ' . mysqli_error($conn)]);
        }
        exit;
    }

    // Handle grade addition
    if ($action === 'add_grade') {
        $student_id = sanitize($_POST['student_id']);
        $class_id = sanitize($_POST['class_id']);
        $assessment_type = sanitize($_POST['assessment_type']);
        $assessment_name = sanitize($_POST['assessment_name']);
        $score = sanitize($_POST['score']);
        $max_score = sanitize($_POST['max_score']);
        $weight = sanitize($_POST['weight']);

        // Get user ID from student ID
        $user_query = "SELECT id FROM users WHERE student_id = ? AND role = 'student'";
        $user_stmt = mysqli_prepare($conn, $user_query);
        mysqli_stmt_bind_param($user_stmt, "s", $student_id);
        mysqli_stmt_execute($user_stmt);
        $user_result = mysqli_stmt_get_result($user_stmt);

        if (mysqli_num_rows($user_result) === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Student ID not found']);
            exit;
        }

        $user = mysqli_fetch_assoc($user_result);
        $user_id = $user['id'];

        // Insert grade
        $query = "INSERT INTO grades (user_id, class_id, assessment_type, assessment_name, score, max_score, weight, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "iissddd", $user_id, $class_id, $assessment_type, $assessment_name, $score, $max_score, $weight);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['status' => 'success', 'message' => 'Grade added successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to add grade: ' . mysqli_error($conn)]);
        }
        exit;
    }

    // Handle announcement creation
    if ($action === 'create_announcement') {
        $title = sanitize($_POST['title']);
        $message = sanitize($_POST['message']);
        $class_id = isset($_POST['class_id']) ? sanitize($_POST['class_id']) : null;
        $is_global = ($class_id === null || $class_id === '') ? 1 : 0;

        $query = "INSERT INTO announcements (title, message, class_id, is_global, created_by, created_at) 
                  VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ssiii", $title, $message, $class_id, $is_global, $admin_id);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['status' => 'success', 'message' => 'Announcement created successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create announcement: ' . mysqli_error($conn)]);
        }
        exit;
    }

    // Handle admin profile update
    if ($action === 'update_profile') {
        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);

        $query = "UPDATE users SET name = ?, email = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ssi", $name, $email, $admin_id);

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['user_name'] = $name;
            echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update profile: ' . mysqli_error($conn)]);
        }
        exit;
    }

    // Handle password change
    if ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Get current password hash
        $query = "SELECT password FROM users WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $admin_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect']);
            exit;
        }

        // Check if new passwords match
        if ($new_password !== $confirm_password) {
            echo json_encode(['status' => 'error', 'message' => 'New passwords do not match']);
            exit;
        }

        // Update password
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $query = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "si", $password_hash, $admin_id);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['status' => 'success', 'message' => 'Password changed successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to change password: ' . mysqli_error($conn)]);
        }
        exit;
    }

    // Handle QR code settings
    if ($action === 'update_qr_settings') {
        $class_id = sanitize($_POST['class_id']);
        $late_threshold = sanitize($_POST['late_threshold']);

        $query = "UPDATE classes SET late_threshold = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ii", $late_threshold, $class_id);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['status' => 'success', 'message' => 'QR settings updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update QR settings: ' . mysqli_error($conn)]);
        }
        exit;
    }

    // Handle attendance marking (manual)
    if ($action === 'mark_attendance') {
        $student_id = sanitize($_POST['student_id']);
        $class_id = sanitize($_POST['class_id']);
        $status = sanitize($_POST['status']); // present, late, absent
        $date = sanitize($_POST['date']);

        // Get user ID from student ID
        $user_query = "SELECT id FROM users WHERE student_id = ? AND role = 'student'";
        $user_stmt = mysqli_prepare($conn, $user_query);
        mysqli_stmt_bind_param($user_stmt, "s", $student_id);
        mysqli_stmt_execute($user_stmt);
        $user_result = mysqli_stmt_get_result($user_stmt);

        if (mysqli_num_rows($user_result) === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Student ID not found']);
            exit;
        }

        $user = mysqli_fetch_assoc($user_result);
        $user_id = $user['id'];

        // Check if attendance already exists for this date
        $check_query = "SELECT id FROM attendance WHERE user_id = ? AND class_id = ? AND date = ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "iis", $user_id, $class_id, $date);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) > 0) {
            // Update existing record
            $attendance = mysqli_fetch_assoc($check_result);
            $query = "UPDATE attendance SET status = ?, updated_at = NOW() WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "si", $status, $attendance['id']);
        } else {
            // Insert new record
            $query = "INSERT INTO attendance (user_id, class_id, date, status, created_at) 
                      VALUES (?, ?, ?, ?, NOW())";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "iiss", $user_id, $class_id, $date, $status);
        }

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['status' => 'success', 'message' => 'Attendance marked successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to mark attendance: ' . mysqli_error($conn)]);
        }
        exit;
    }
}

// Get all classes for the admin
/*
$classes_query = "SELECT * FROM classes WHERE created_by = ? ORDER BY name";
$classes_stmt = mysqli_prepare($conn, $classes_query);
mysqli_stmt_bind_param($classes_stmt, "i", $admin_id);
mysqli_stmt_execute($classes_stmt);
$classes_result = mysqli_stmt_get_result($classes_stmt);
$classes = [];
while ($row = mysqli_fetch_assoc($classes_result)) {
    $classes[] = $row;
}*/

// Get recent attendance records


// Get attendance statistics
/*$stats_query = "
    SELECT 
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
        COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count
    FROM attendance a
    JOIN classes c ON a.class_id = c.id
    WHERE c.created_by = ?
    AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "i", $admin_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$attendance_stats = mysqli_fetch_assoc($stats_result);*/

// Get recent announcements


// Get students for select dropdowns


// Current active tab
$active_tab = isset($_GET['tab']) ? sanitize($_GET['tab']) : 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Student Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --primary-dark: #2e59d9;
            --secondary-color: #1cc88a;
            --danger-color: #e74a3b;
            --warning-color: #f6c23e;
            --info-color: #36b9cc;
            --dark-color: #5a5c69;
            --light-color: #f8f9fc;
            --shadow: 0 .15rem 1.75rem 0 rgba(58, 59, 69, .15);
            --border-radius: 0.35rem;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Nunito', 'Segoe UI', Roboto, Arial, sans-serif;
        }

        body {
            background: url('124155623.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #333;
            line-height: 1.6;
            position: relative;
            min-height: 100vh;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(245, 247, 250, 0.92);
            z-index: -1;
        }

        a {
            text-decoration: none;
            color: var(--primary-color);
            transition: all 0.3s ease;
        }

        a:hover {
            color: var(--primary-dark);
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-weight: 700;
            margin-bottom: 1rem;
            color: #333;
        }

        /* === HEADER STYLES === */
        .header {
            background-color: white;
            padding: 1rem 2rem;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            color: var(--primary-color);
            margin: 0;
            font-size: 1.5rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info .name {
            font-weight: 600;
        }

        #profileImage {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
        }

        .logout {
            padding: 0.4rem 1rem;
            background-color: var(--primary-color);
            color: white;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .logout:hover {
            background-color: var(--primary-dark);
            color: white;
        }

        /* === CONTAINER LAYOUT === */
        .container {
            display: flex;
            gap: 1.5rem;
            padding: 1.5rem;
            max-width: 1800px;
            margin: 0 auto;
        }

        /* === SIDEBAR STYLES === */
        .sidebar {
            width: 280px;
            flex-shrink: 0;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            height: fit-content;
            position: sticky;
            top: 85px;
        }

        .qr-container {
            background-color: var(--light-color);
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        #qrcode {
            margin: 1rem auto;
            width: 200px;
            height: 200px;
            background-color: white;
            padding: 1rem;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qr-description {
            font-size: 0.85rem;
            color: var(--dark-color);
        }

        .student-info {
            background-color: var(--light-color);
            padding: 1rem;
            border-radius: var(--border-radius);
        }

        .student-info p {
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        /* === MAIN CONTENT === */
        .main-content {
            flex: 1;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            min-height: calc(100vh - 5rem);
        }

        /* === NAVIGATION TABS === */
        .dashboard-nav {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            padding-bottom: 1rem;
        }

        .dashboard-tab {
            padding: 0.75rem 1.25rem;
            color: var(--dark-color);
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .dashboard-tab:hover {
            background-color: rgba(78, 115, 223, 0.1);
            color: var(--primary-color);
        }

        .dashboard-tab.active {
            background-color: var(--primary-color);
            color: white;
        }

        .dashboard-tab i {
            font-size: 1rem;
        }

        /* === TAB CONTENT === */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* === DASHBOARD TAB === */
        .welcome-card {
            background-color: var(--light-color);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            border-left: 5px solid var(--primary-color);
        }

        .section-title {
            color: var(--dark-color);
            margin: 2rem 0 1rem 0;
            position: relative;
            padding-bottom: 0.5rem;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: var(--primary-color);
        }

        .attendance-stats {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background-color: var(--light-color);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            text-align: center;
            transition: all 0.3s ease;
            border-bottom: 4px solid var(--primary-color);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 1rem;
            color: var(--dark-color);
            font-weight: 600;
        }

        /* Stats card color variants */
        .stat-card:nth-child(1) {
            border-color: var(--primary-color);
        }

        .stat-card:nth-child(1) .stat-value {
            color: var(--primary-color);
        }

        .stat-card:nth-child(2) {
            border-color: var(--warning-color);
        }

        .stat-card:nth-child(2) .stat-value {
            color: var(--warning-color);
        }

        .stat-card:nth-child(3) {
            border-color: var(--danger-color);
        }

        .stat-card:nth-child(3) .stat-value {
            color: var(--danger-color);
        }

        .stat-card:nth-child(4) {
            border-color: var(--info-color);
        }

        .stat-card:nth-child(4) .stat-value {
            color: var(--info-color);
        }

        /* === TABLES === */
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .attendance-table th,
        .attendance-table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid #e3e6f0;
        }

        .attendance-table th {
            background-color: var(--light-color);
            font-weight: 600;
        }

        .attendance-table tr:hover {
            background-color: rgba(78, 115, 223, 0.05);
        }

        .attendance-status {
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-present {
            background-color: rgba(28, 200, 138, 0.1);
            color: var(--secondary-color);
        }

        .status-late {
            background-color: rgba(246, 194, 62, 0.1);
            color: var(--warning-color);
        }

        .status-absent {
            background-color: rgba(231, 74, 59, 0.1);
            color: var(--danger-color);
        }

        .view-all-btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background-color: var(--primary-color);
            color: white;
            border-radius: var(--border-radius);
            font-weight: 600;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .view-all-btn:hover {
            background-color: var(--primary-dark);
            color: white;
        }

        /* === NOTIFICATIONS === */
        .notification-item {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--primary-color);
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .notification-title {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .notification-date {
            color: var(--dark-color);
            font-size: 0.85rem;
        }

        .notification-sender {
            margin-bottom: 0.5rem;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 50px;
            background-color: rgba(78, 115, 223, 0.1);
            color: var(--primary-color);
        }

        .notification-message {
            color: #4e5155;
            margin-bottom: 1rem;
        }

        .notification-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }

        /* === FORMS === */
        .card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .card h3 {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 1.5rem;
            margin: 0;
            background-color: var(--light-color);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            font-size: 1.1rem;
        }

        .card-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
        }

        .card-content {
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d3e2;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }

        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: normal;
            margin-bottom: 0;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn:hover {
            background-color: var(--primary-dark);
        }

        .btn-small {
            padding: 0.4rem 0.75rem;
            font-size: 0.8rem;
        }

        .btn-secondary {
            background-color: var(--dark-color);
        }

        .btn-secondary:hover {
            background-color: #444;
        }

        .btn-danger {
            background-color: var(--danger-color);
        }

        .btn-danger:hover {
            background-color: #c03b30;
        }

        .btn-warning {
            background-color: var(--warning-color);
            color: #212529;
        }

        .btn-warning:hover {
            background-color: #e0af2a;
        }

        .btn-info {
            background-color: var(--info-color);
        }

        .btn-info:hover {
            background-color: #2ca1b3;
        }

        /* === COURSE CARDS === */
        .course-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .course-header {
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--light-color);
            cursor: pointer;
        }

        .course-name {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--primary-color);
        }

        .course-code {
            font-size: 0.9rem;
            color: var(--dark-color);
        }

        .toggle-details-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--primary-color);
            transition: transform 0.3s ease;
        }

        .course-details {
            padding: 1.5rem;
            display: none;
        }

        .course-description {
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .course-instructor {
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .enrolled-students-container {
            margin: 1rem 0;
        }

        .students-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .students-table th,
        .students-table td {
            padding: 0.5rem;
            text-align: left;
            border-bottom: 1px solid #e3e6f0;
        }

        .class-actions {
            margin-top: 1.5rem;
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        /* === SEARCH AND FILTERS === */
        .search-container {
            margin-bottom: 1.5rem;
        }

        .search-bar {
            display: flex;
            gap: 0.5rem;
        }

        .search-bar input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d3e2;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
        }

        .search-bar button {
            padding: 0.75rem 1rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .search-bar button:hover {
            background-color: var(--primary-dark);
        }

        .filter-container {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background-color: var(--light-color);
            border-radius: var(--border-radius);
        }

        /* === MODALS === */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow-y: auto;
            padding: 2rem 1rem;
        }

        .modal-content {
            background-color: white;
            margin: 0 auto;
            max-width: 600px;
            border-radius: var(--border-radius);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease;
        }

        .modal-lg {
            max-width: 800px;
        }

        .modal-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
        }

        .close-modal {
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--dark-color);
            transition: color 0.3s ease;
        }

        .close-modal:hover {
            color: var(--danger-color);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        /* === QR CODE === */
        #qr-code-container {
            margin: 1rem auto;
            background-color: white;
            padding: 1rem;
            display: inline-block;
            border-radius: var(--border-radius);
        }

        #qr-expiry-timer {
            margin-top: 1rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        /* === NO RECORDS === */
        .no-records,
        .no-classes,
        .loading {
            padding: 2rem;
            text-align: center;
            background-color: var(--light-color);
            border-radius: var(--border-radius);
            color: var(--dark-color);
            font-style: italic;
        }

        /* === ANIMATIONS === */
        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* === RESPONSIVE DESIGN === */
        @media (max-width: 1200px) {
            .container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                position: static;
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }

            .dashboard-nav {
                gap: 0.25rem;
            }

            .dashboard-tab {
                padding: 0.5rem 0.75rem;
                font-size: 0.85rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-value {
                font-size: 2rem;
            }

            .attendance-table th,
            .attendance-table td {
                padding: 0.5rem;
            }
        }

        /* === CUSTOM SCROLLBAR === */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Student Management System - Admin Dashboard</h1>
        <div class="user-info">
            <div class="name"><?php echo isset($admin['name']) ? htmlspecialchars($admin['name']) : 'Admin'; ?></div>
            <img src="<?php echo !empty($admin['profile_image']) ? 'uploads/' . $admin['profile_image'] : 'images/default-profile.jpg'; ?>" alt="Profile" id="profileImage">
            <a href="admin_login.php" class="logout">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="sidebar">
            <div class="qr-container">
                <h3>QR Scanner Setup</h3>
                <div id="qrcode"></div>
                <div class="qr-description">
                    Set up QR code scanning for attendance marking
                </div>
            </div>

            <div class="student-info">
                <h3>Admin Information</h3>
                <p><strong>Name:</strong> <?php echo isset($admin['name']) ? htmlspecialchars($admin['name']) : 'N/A'; ?></p>
                <p><strong>Email:</strong> <?php echo isset($admin['email']) ? htmlspecialchars($admin['email']) : 'N/A'; ?></p>
                <p><strong>Role:</strong> Administrator</p>
                <p><strong>Classes:</strong> <?php echo isset($classes) ? count($classes) : 0; ?></p>
            </div>>

            <div class="main-content">
                <div class="dashboard-nav">
                    <a href="?tab=dashboard" class="dashboard-tab <?php echo $active_tab == 'dashboard' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="?tab=classes" class="dashboard-tab <?php echo $active_tab == 'classes' ? 'active' : ''; ?>">
                        <i class="fas fa-chalkboard"></i> Classes
                    </a>
                    <a href="?tab=students" class="dashboard-tab <?php echo $active_tab == 'students' ? 'active' : ''; ?>">
                        <i class="fas fa-user-graduate"></i> Student Management
                    </a>
                    <a href="?tab=attendance" class="dashboard-tab <?php echo $active_tab == 'attendance' ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-check"></i> Attendance
                    </a>
                    <a href="?tab=announcements" class="dashboard-tab <?php echo $active_tab == 'announcements' ? 'active' : ''; ?>">
                        <i class="fas fa-bullhorn"></i> Announcements
                    </a>
                    <a href="?tab=settings" class="dashboard-tab <?php echo $active_tab == 'settings' ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </div>

                <!-- Dashboard Tab -->
                <div class="tab-content <?php echo $active_tab == 'admin_dashboard' ? 'active' : ''; ?>" id="dashboard-tab">
                    <div class="welcome-card">
                        <h2>Welcome, <?php echo htmlspecialchars($admin['name']); ?>!</h2>
                        <p>Here's a quick overview of your classes and recent activities. Use the navigation above to manage your classes, students, and more.</p>
                    </div>
                </div>

                <h2 class="section-title">Attendance Overview</h2>
                <div class="attendance-stats">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $attendance_stats['present_count'] ?? 0; ?></div>
                        <div class="stat-label">Present</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $attendance_stats['late_count'] ?? 0; ?></div>
                        <div class="stat-label">Late</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $attendance_stats['absent_count'] ?? 0; ?></div>
                        <div class="stat-label">Absent</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo count($classes); ?></div>
                        <div class="stat-label">Classes</div>
                    </div>
                </div>

                <h2 class="section-title">Recent Attendance</h2>
                <?php if (isset($recent_attendance) && count($recent_attendance) > 0): ?>
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Student ID</th>
                                <th>Class</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_attendance as $attendance): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($attendance['date']); ?></td>
                                    <td><?php echo htmlspecialchars($attendance['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($attendance['class_name']); ?></td>
                                    <td><?php echo htmlspecialchars($attendance['status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No recent attendance records found.</p>
                <?php endif; ?>
                <a href="?tab=attendance" class="view-all-btn">View All Attendance</a>

                <?php
                // Initialize the $recent_announcements variable before use
                $recent_announcements = [];

                // Database connection (make sure this exists before this code)
                if (isset($conn)) {
                    try {
                        // First, let's check the table structure to determine what columns actually exist
                        $check_table = $conn->query("DESCRIBE announcements");

                        if ($check_table) {
                            $columns = [];
                            while ($column = $check_table->fetch_assoc()) {
                                $columns[] = $column['Field'];
                            }

                            // Determine which content column to use (message, content, description, etc.)
                            $content_column = null;
                            foreach (['message', 'content', 'description', 'announcement_text', 'text'] as $possible_column) {
                                if (in_array($possible_column, $columns)) {
                                    $content_column = $possible_column;
                                    break;
                                }
                            }

                            // Check if class_id column exists for joining
                            $has_class_id = in_array('class_id', $columns);
                            $has_is_global = in_array('is_global', $columns);

                            // Build the query based on existing columns
                            $select_fields = "a.id, a.title, ";

                            // Add the content column if found
                            if ($content_column) {
                                $select_fields .= "a.$content_column AS content, ";
                            } else {
                                $select_fields .= "'' AS content, ";
                            }

                            $select_fields .= "a.created_at";

                            // Add is_global if exists
                            if ($has_is_global) {
                                $select_fields .= ", a.is_global";
                            } else {
                                $select_fields .= ", 0 AS is_global";
                            }

                            // Build the query
                            $query = "SELECT $select_fields FROM announcements a";

                            // Add class join if class_id exists
                            if ($has_class_id) {
                                $query .= " LEFT JOIN classes c ON a.class_id = c.id";
                                $select_fields .= ", COALESCE(c.class_name, 'N/A') as class_name";
                            } else {
                                $select_fields .= ", 'N/A' as class_name";
                            }

                            $query .= " ORDER BY a.created_at DESC LIMIT 5";

                            // Execute the query
                            $result = $conn->query($query);

                            if ($result) {
                                $recent_announcements = $result->fetch_all(MYSQLI_ASSOC);
                            }
                        }
                    } catch (Exception $e) {
                        // Log the error for debugging
                        error_log("Error fetching announcements: " . $e->getMessage());
                    }
                }
                ?>

                <h2 class="section-title">Recent Announcements</h2>
                <?php if (!empty($recent_announcements)): ?>
                    <?php foreach ($recent_announcements as $announcement): ?>
                        <div class="notification-item">
                            <div class="notification-header">
                                <div class="notification-title"><?php echo htmlspecialchars($announcement['title']); ?></div>
                                <div class="notification-date"><?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></div>
                            </div>
                            <div class="notification-sender">
                                <?php if ($announcement['is_global']): ?>
                                    <span class="badge">Global Announcement</span>
                                <?php else: ?>
                                    <span class="badge">Class: <?php echo htmlspecialchars($announcement['class_name']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="notification-message"><?php echo nl2br(htmlspecialchars($announcement['message'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-records">No announcements found.</div>
                <?php endif; ?>
                <a href="?tab=announcements" class="view-all-btn">View All Announcements</a>


                <!-- Classes Tab -->
                <div class="tab-content <?php echo $active_tab == 'classes' ? 'active' : ''; ?>" id="classes-tab">
                    <h2 class="section-title">Manage Classes</h2>

                    <div class="card" id="create-class-card">
                        <h3>
                            <div class="card-icon"><i class="fas fa-plus"></i></div> Create New Class
                        </h3>
                        <div class="card-content">
                            <form id="create-class-form">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="class_name">Class Name</label>
                                        <input type="text" id="class_name" name="class_name" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="class_code">Class Code</label>
                                        <input type="text" id="class_code" name="class_code" class="form-control" required>
                                    </div>
                                    <div class="form-group full-width">
                                        <label for="description">Description</label>
                                        <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="start_time">Start Time</label>
                                        <input type="time" id="start_time" name="start_time" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="end_time">End Time</label>
                                        <input type="time" id="end_time" name="end_time" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Days</label>
                                        <div class="checkbox-group">
                                            <label><input type="checkbox" name="days[]" value="Monday"> Monday</label>
                                            <label><input type="checkbox" name="days[]" value="Tuesday"> Tuesday</label>
                                            <label><input type="checkbox" name="days[]" value="Wednesday"> Wednesday</label>
                                            <label><input type="checkbox" name="days[]" value="Thursday"> Thursday</label>
                                            <label><input type="checkbox" name="days[]" value="Friday"> Friday</label>
                                            <label><input type="checkbox" name="days[]" value="Saturday"> Saturday</label>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="late_threshold">Late Threshold (minutes)</label>
                                        <input type="number" id="late_threshold" name="late_threshold" class="form-control" value="15" min="0" required>
                                    </div>
                                    <div class="form-group full-width">
                                        <button type="submit" class="btn">Create Class</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <h3 class="section-title">Your Classes</h3>
                    <div id="classes-container">
                        <?php if (count($classes) > 0): ?>
                            <?php foreach ($classes as $class): ?>
                                <div class="course-card" data-class-id="<?php echo $class['id']; ?>">
                                    <div class="course-header">
                                        <div>
                                            <div class="course-name"><?php echo htmlspecialchars($class['name']); ?></div>
                                            <div class="course-code"><?php echo htmlspecialchars($class['code']); ?></div>
                                        </div>
                                        <div>
                                            <button class="toggle-details-btn"><i class="fas fa-chevron-down"></i></button>
                                        </div>
                                    </div>
                                    <div class="course-details">
                                        <div class="course-description"><?php echo nl2br(htmlspecialchars($class['description'])); ?></div>
                                        <div class="course-instructor">
                                            <strong>Schedule:</strong> <?php echo htmlspecialchars($class['days']); ?>,
                                            <?php echo date('h:i A', strtotime($class['start_time'])); ?> -
                                            <?php echo date('h:i A', strtotime($class['end_time'])); ?>
                                        </div>
                                        <div class="course-instructor">
                                            <strong>Late Threshold:</strong> <?php echo $class['late_threshold']; ?> minutes
                                        </div>

                                        <h4>Enrolled Students</h4>
                                        <div class="enrolled-students-container">
                                            <?php
                                            $enrolled_query = "
                                        SELECT u.id, u.name, u.student_id, u.email
                                        FROM class_enrollments e
                                        JOIN users u ON e.user_id = u.id
                                        WHERE e.class_id = ?
                                        ORDER BY u.name
                                    ";
                                            $enrolled_stmt = mysqli_prepare($conn, $enrolled_query);
                                            mysqli_stmt_bind_param($enrolled_stmt, "i", $class['id']);
                                            mysqli_stmt_execute($enrolled_stmt);
                                            $enrolled_result = mysqli_stmt_get_result($enrolled_stmt);
                                            $enrolled_students = [];
                                            while ($student = mysqli_fetch_assoc($enrolled_result)) {
                                                $enrolled_students[] = $student;
                                            }

                                            if (count($enrolled_students) > 0):
                                            ?>
                                                <table class="students-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Name</th>
                                                            <th>Student ID</th>
                                                            <th>Email</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($enrolled_students as $student): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                                                <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                                                <td>
                                                                    <button class="btn-small btn-warning view-attendance-btn" data-student-id="<?php echo $student['student_id']; ?>" data-class-id="<?php echo $class['id']; ?>">
                                                                        <i class="fas fa-clipboard-check"></i> Attendance
                                                                    </button>
                                                                    <button class="btn-small btn-info view-grades-btn" data-student-id="<?php echo $student['student_id']; ?>" data-class-id="<?php echo $class['id']; ?>">
                                                                        <i class="fas fa-chart-line"></i> Grades
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            <?php else: ?>
                                                <div class="no-records">No students enrolled in this class yet.</div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="class-actions">
                                            <h4>Add Student to Class</h4>
                                            <form class="add-student-form">
                                                <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                                <div class="form-group">
                                                    <label for="student_id_<?php echo $class['id']; ?>">Student ID</label>
                                                    <input type="text" id="student_id_<?php echo $class['id']; ?>" name="student_id" class="form-control" placeholder="Enter Student ID" required>
                                                </div>
                                                <button type="submit" class="btn btn-primary">Add Student</button>
                                            </form>

                                            <div class="action-buttons">
                                                <button class="btn btn-info qr-settings-btn" data-class-id="<?php echo $class['id']; ?>" data-threshold="<?php echo $class['late_threshold']; ?>">
                                                    <i class="fas fa-qrcode"></i> QR Settings
                                                </button>
                                                <button class="btn btn-primary view-grades-class-btn" data-class-id="<?php echo $class['id']; ?>">
                                                    <i class="fas fa-chart-bar"></i> Class Grades
                                                </button>
                                                <button class="btn btn-warning generate-report-btn" data-class-id="<?php echo $class['id']; ?>">
                                                    <i class="fas fa-file-export"></i> Generate Report
                                                </button>
                                                <button class="btn btn-danger delete-class-btn" data-class-id="<?php echo $class['id']; ?>">
                                                    <i class="fas fa-trash"></i> Delete Class
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-classes">
                                <p>You haven't created any classes yet.</p>
                                <p>Use the form above to create your first class.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Students Tab -->
                <div class="tab-content <?php echo $active_tab == 'students' ? 'active' : ''; ?>" id="students-tab">
                    <h2 class="section-title">Student Management</h2>

                    <div class="search-container">
                        <div class="search-bar">
                            <input type="text" id="search-student" placeholder="Search student by name or ID...">
                            <button id="search-btn"><i class="fas fa-search"></i></button>
                        </div>
                    </div>

                    <div class="card">
                        <h3>
                            <div class="card-icon"><i class="fas fa-user-plus"></i></div> Add New Student
                        </h3>
                        <div class="card-content">
                            <form id="add-student-form">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="student_name">Full Name</label>
                                        <input type="text" id="student_name" name="student_name" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="new_student_id">Student ID</label>
                                        <input type="text" id="new_student_id" name="new_student_id" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="student_email">Email</label>
                                        <input type="email" id="student_email" name="student_email" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="student_password">Password</label>
                                        <input type="password" id="student_password" name="student_password" class="form-control" required>
                                    </div>
                                    <div class="form-group full-width">
                                        <button type="submit" class="btn">Add Student</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <h3 class="section-title">All Students</h3>
                    <div id="students-container">
                        <div class="loading">Loading students...</div>
                    </div>
                </div>

                <!-- Attendance Tab -->
                <div class="tab-content <?php echo $active_tab == 'attendance' ? 'active' : ''; ?>" id="attendance-tab">
                    <h2 class="section-title">Attendance Management</h2>

                    <div class="card">
                        <h3>
                            <div class="card-icon"><i class="fas fa-clipboard-check"></i></div> Mark Attendance
                        </h3>
                        <div class="card-content">
                            <form id="mark-attendance-form">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="attendance_class">Class</label>
                                        <select id="attendance_class" name="class_id" class="form-control" required>
                                            <option value="">Select Class</option>
                                            <?php foreach ($classes as $class): ?>
                                                <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?> (<?php echo htmlspecialchars($class['code']); ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="attendance_student_id">Student ID</label>
                                        <input type="text" id="attendance_student_id" name="student_id" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="attendance_date">Date</label>
                                        <input type="date" id="attendance_date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="attendance_status">Status</label>
                                        <select id="attendance_status" name="status" class="form-control" required>
                                            <option value="present">Present</option>
                                            <option value="late">Late</option>
                                            <option value="absent">Absent</option>
                                        </select>
                                    </div>
                                    <div class="form-group full-width">
                                        <button type="submit" class="btn">Mark Attendance</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <h3>
                            <div class="card-icon"><i class="fas fa-qrcode"></i></div> QR Attendance
                        </h3>
                        <div class="card-content">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="qr_class">Class</label>
                                    <select id="qr_class" class="form-control">
                                        <option value="">Select Class</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?> (<?php echo htmlspecialchars($class['code']); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="qr_expiry">Expiry Time (minutes)</label>
                                    <input type="number" id="qr_expiry" class="form-control" min="1" max="60" value="15">
                                </div>
                                <div class="form-group full-width">
                                    <button id="generate-qr-btn" class="btn">Generate QR Code</button>
                                </div>
                            </div>
                            <div id="qr-code-display" class="text-center" style="display: none;">
                                <div id="qr-code-container"></div>
                                <div id="qr-expiry-timer">Code expires in: <span id="qr-timer">15:00</span></div>
                            </div>
                        </div>
                    </div>

                    <h3 class="section-title">Attendance Records</h3>
                    <div class="filter-container">
                        <div class="form-group">
                            <label for="filter_class">Class</label>
                            <select id="filter_class" class="form-control">
                                <option value="">All Classes</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="filter_date_from">From Date</label>
                            <input type="date" id="filter_date_from" class="form-control" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                        </div>
                        <div class="form-group">
                            <label for="filter_date_to">To Date</label>
                            <input type="date" id="filter_date_to" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="filter_status">Status</label>
                            <select id="filter_status" class="form-control">
                                <option value="">All Status</option>
                                <option value="present">Present</option>
                                <option value="late">Late</option>
                                <option value="absent">Absent</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <button id="filter-attendance-btn" class="btn">Filter</button>
                        </div>
                    </div>

                    <div id="attendance-records-container">
                        <div class="loading">Loading attendance records...</div>
                    </div>
                </div>

                <!-- Announcements Tab -->
                <div class="tab-content <?php echo $active_tab == 'announcements' ? 'active' : ''; ?>" id="announcements-tab">
                    <h2 class="section-title">Announcements</h2>

                    <div class="card">
                        <h3>
                            <div class="card-icon"><i class="fas fa-bullhorn"></i></div> Create Announcement
                        </h3>
                        <div class="card-content">
                            <form id="create-announcement-form">
                                <div class="form-group">
                                    <label for="announcement_title">Title</label>
                                    <input type="text" id="announcement_title" name="title" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="announcement_class">Class (Leave empty for global announcement)</label>
                                    <select id="announcement_class" name="class_id" class="form-control">
                                        <option value="">Global Announcement</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?> (<?php echo htmlspecialchars($class['code']); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="announcement_message">Message</label>
                                    <textarea id="announcement_message" name="message" class="form-control" rows="5" required></textarea>
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="btn">Post Announcement</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <h3 class="section-title">All Announcements</h3>
                    <div id="announcements-container">
                        <?php
                        $all_announcements_query = "
                        SELECT a.*, c.name as class_name
                        FROM announcements a
                        LEFT JOIN classes c ON a.class_id = c.id
                        WHERE a.created_by = ?
                        ORDER BY a.created_at DESC
                    ";
                        $all_announcements_stmt = mysqli_prepare($conn, $all_announcements_query);
                        mysqli_stmt_bind_param($all_announcements_stmt, "i", $admin_id);
                        mysqli_stmt_execute($all_announcements_stmt);
                        $all_announcements_result = mysqli_stmt_get_result($all_announcements_stmt);
                        $all_announcements = [];
                        while ($row = mysqli_fetch_assoc($all_announcements_result)) {
                            $all_announcements[] = $row;
                        }

                        if (count($all_announcements) > 0):
                            foreach ($all_announcements as $announcement):
                        ?>
                                <div class="notification-item">
                                    <div class="notification-header">
                                        <div class="notification-title"><?php echo htmlspecialchars($announcement['title']); ?></div>
                                        <div class="notification-date"><?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></div>
                                    </div>
                                    <div class="notification-sender">
                                        <?php if ($announcement['is_global']): ?>
                                            <span class="badge">Global Announcement</span>
                                        <?php else: ?>
                                            <span class="badge">Class: <?php echo htmlspecialchars($announcement['class_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="notification-message"><?php echo nl2br(htmlspecialchars($announcement['message'])); ?></div>
                                    <div class="notification-actions">
                                        <button class="btn-small btn-danger delete-announcement-btn" data-id="<?php echo $announcement['id']; ?>">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                        <button class="btn-small btn-warning edit-announcement-btn"
                                            data-id="<?php echo $announcement['id']; ?>"
                                            data-title="<?php echo htmlspecialchars($announcement['title']); ?>"
                                            data-message="<?php echo htmlspecialchars($announcement['message']); ?>"
                                            data-class-id="<?php echo $announcement['class_id']; ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    </div>
                                </div>
                            <?php
                            endforeach;
                        else:
                            ?>
                            <div class="no-records">No announcements found.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Settings Tab -->
                <div class="tab-content <?php echo $active_tab == 'settings' ? 'active' : ''; ?>" id="settings-tab">
                    <h2 class="section-title">Account Settings</h2>

                    <div class="card">
                        <h3>
                            <div class="card-icon"><i class="fas fa-user-edit"></i></div> Update Profile
                        </h3>
                        <div class="card-content">
                            <form id="update-profile-form">
                                <div class="form-group">
                                    <label for="profile_name">Full Name</label>
                                    <input type="text" id="profile_name" name="name" class="form-control" value="<?php echo htmlspecialchars($admin['name']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="profile_email">Email</label>
                                    <input type="email" id="profile_email" name="email" class="form-control" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="btn">Update Profile</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <h3>
                            <div class="card-icon"><i class="fas fa-lock"></i></div> Change Password
                        </h3>
                        <div class="card-content">
                            <form id="change-password-form">
                                <div class="form-group">
                                    <label for="current_password">Current Password</label>
                                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="new_password">New Password</label>
                                    <input type="password" id="new_password" name="new_password" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="btn">Change Password</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <h3>
                            <div class="card-icon"><i class="fas fa-image"></i></div> Update Profile Picture
                        </h3>
                        <div class="card-content">
                            <form id="update-picture-form" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="profile_picture">Choose a new profile picture</label>
                                    <input type="file" id="profile_picture" name="profile_picture" class="form-control" accept="image/*" required>
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="btn">Upload Picture</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <h3>
                            <div class="card-icon"><i class="fas fa-cogs"></i></div> System Settings
                        </h3>
                        <div class="card-content">
                            <form id="system-settings-form">
                                <div class="form-group">
                                    <label>Email Notifications</label>
                                    <div class="checkbox-group">
                                        <label><input type="checkbox" name="notifications[]" value="attendance" checked> Attendance Reports</label>
                                        <label><input type="checkbox" name="notifications[]" value="grades" checked> Grade Updates</label>
                                        <label><input type="checkbox" name="notifications[]" value="announcements" checked> New Announcements</label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="session_timeout">Session Timeout (minutes)</label>
                                    <input type="number" id="session_timeout" name="session_timeout" class="form-control" value="30" min="5" max="120">
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="btn">Save Settings</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <div class="modal" id="qr-settings-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>QR Code Settings</h3>
                    <span class="close-modal">&times;</span>
                </div>
                <div class="modal-body">
                    <form id="qr-settings-form">
                        <input type="hidden" id="qr_settings_class_id" name="class_id">
                        <div class="form-group">
                            <label for="late_threshold_input">Late Threshold (minutes)</label>
                            <input type="number" id="late_threshold_input" name="late_threshold" class="form-control" min="0" required>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn">Save Settings</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal" id="student-details-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Student Details</h3>
                    <span class="close-modal">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="student-details-container"></div>
                </div>
            </div>
        </div>

        <div class="modal" id="attendance-details-modal">
            <div class="modal-content modal-lg">
                <div class="modal-header">
                    <h3>Attendance Details</h3>
                    <span class="close-modal">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="attendance-details-container"></div>
                </div>
            </div>
        </div>

        <div class="modal" id="grades-modal">
            <div class="modal-content modal-lg">
                <div class="modal-header">
                    <h3>Grades Management</h3>
                    <span class="close-modal">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="grades-container"></div>

                    <div class="card">
                        <h4>Add Grade</h4>
                        <form id="add-grade-form">
                            <input type="hidden" id="grade_student_id" name="student_id">
                            <input type="hidden" id="grade_class_id" name="class_id">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="assessment_type">Assessment Type</label>
                                    <select id="assessment_type" name="assessment_type" class="form-control" required>
                                        <option value="quiz">Quiz</option>
                                        <option value="assignment">Assignment</option>
                                        <option value="exam">Exam</option>
                                        <option value="project">Project</option>
                                        <option value="participation">Participation</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="assessment_name">Assessment Name</label>
                                    <input type="text" id="assessment_name" name="assessment_name" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="score">Score</label>
                                    <input type="number" id="score" name="score" class="form-control" step="0.01" required>
                                </div>
                                <div class="form-group">
                                    <label for="max_score">Max Score</label>
                                    <input type="number" id="max_score" name="max_score" class="form-control" step="0.01" value="100" required>
                                </div>
                                <div class="form-group">
                                    <label for="weight">Weight (%)</label>
                                    <input type="number" id="weight" name="weight" class="form-control" step="0.01" value="10" required>
                                </div>
                                <div class="form-group full-width">
                                    <button type="submit" class="btn">Add Grade</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal" id="edit-announcement-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Edit Announcement</h3>
                    <span class="close-modal">&times;</span>
                </div>
                <div class="modal-body">
                    <form id="edit-announcement-form">
                        <input type="hidden" id="edit_announcement_id" name="announcement_id">
                        <div class="form-group">
                            <label for="edit_announcement_title">Title</label>
                            <input type="text" id="edit_announcement_title" name="title" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_announcement_class">Class</label>
                            <select id="edit_announcement_class" name="class_id" class="form-control">
                                <option value="">Global Announcement</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_announcement_message">Message</label>
                            <textarea id="edit_announcement_message" name="message" class="form-control" rows="5" required></textarea>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn">Update Announcement</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal" id="confirm-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Confirmation</h3>
                    <span class="close-modal">&times;</span>
                </div>
                <div class="modal-body">
                    <p id="confirm-message"></p>
                    <div class="form-group text-right">
                        <button class="btn btn-secondary" id="cancel-confirm">Cancel</button>
                        <button class="btn btn-danger" id="confirm-action">Confirm</button>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
        <script>
            $(document).ready(function() {
                // Toggle class details
                $(document).on('click', '.toggle-details-btn', function() {
                    var courseCard = $(this).closest('.course-card');
                    courseCard.find('.course-details').slideToggle();
                    $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
                });

                // Add new student to class
                $(document).on('submit', '.add-student-form', function(e) {
                    e.preventDefault();
                    var formData = new FormData(this);
                    var formObject = {};

                    formData.forEach(function(value, key) {
                        formObject[key] = value;
                    });

                    formObject.action = 'add_student_to_class';

                    $.ajax({
                        url: 'admin_dashboard.php',
                        type: 'POST',
                        data: formObject,
                        success: function(response) {
                            try {
                                var data = JSON.parse(response);
                                if (data.status === 'success') {
                                    alert(data.message);
                                    location.reload();
                                } else {
                                    alert(data.message || 'Error adding student to class');
                                }
                            } catch (e) {
                                alert('An error occurred. Please try again.');
                            }
                        },
                        error: function() {
                            alert('An error occurred. Please try again.');
                        }
                    });
                });

                // Create new class
                $('#create-class-form').on('submit', function(e) {
                    e.preventDefault();

                    var formData = new FormData(this);
                    var formObject = {};

                    formData.forEach(function(value, key) {
                        formObject[key] = value;
                    });

                    formObject.action = 'create_class';

                    $.ajax({
                        url: 'admin_dashboard.php',
                        type: 'POST',
                        data: formObject,
                        success: function(response) {
                            try {
                                var data = JSON.parse(response);
                                if (data.status === 'success') {
                                    alert(data.message);
                                    location.reload();
                                } else {
                                    alert(data.message || 'Error creating class');
                                }
                            } catch (e) {
                                alert('An error occurred. Please try again.');
                            }
                        },
                        error: function() {
                            alert('An error occurred. Please try again.');
                        }
                    });
                });

                // Add new student
                $('#add-student-form').on('submit', function(e) {
                    e.preventDefault();

                    var formData = new FormData(this);
                    var formObject = {};

                    formData.forEach(function(value, key) {
                        formObject[key] = value;
                    });

                    formObject.action = 'add_student';

                    $.ajax({
                        url: 'admin_dashboard.php',
                        type: 'POST',
                        data: formObject,
                        success: function(response) {
                            try {
                                var data = JSON.parse(response);
                                if (data.status === 'success') {
                                    alert(data.message);
                                    $('#add-student-form')[0].reset();
                                    loadStudents();
                                } else {
                                    alert(data.message || 'Error adding student');
                                }
                            } catch (e) {
                                alert('An error occurred. Please try again.');
                            }
                        },
                        error: function() {
                            alert('An error occurred. Please try again.');
                        }
                    });
                });

                // Mark attendance
                $('#mark-attendance-form').on('submit', function(e) {
                    e.preventDefault();

                    var formData = new FormData(this);
                    var formObject = {};

                    formData.forEach(function(value, key) {
                        formObject[key] = value;
                    });

                    formObject.action = 'mark_attendance';

                    $.ajax({
                        url: 'admin_dashboard.php',
                        type: 'POST',
                        data: formObject,
                        success: function(response) {
                            try {
                                var data = JSON.parse(response);
                                if (data.status === 'success') {
                                    alert(data.message);
                                    $('#mark-attendance-form')[0].reset();
                                    loadAttendanceRecords();
                                } else {
                                    alert(data.message || 'Error marking attendance');
                                }
                            } catch (e) {
                                alert('An error occurred. Please try again.');
                            }
                        },
                        error: function() {
                            alert('An error occurred. Please try again.');
                        }
                    });
                });

                // Create announcement
                $('#create-announcement-form').on('submit', function(e) {
                    e.preventDefault();

                    var formData = new FormData(this);
                    var formObject = {};

                    formData.forEach(function(value, key) {
                        formObject[key] = value;
                    });

                    formObject.action = 'create_announcement';

                    $.ajax({
                        url: 'admin_dashboard.php',
                        type: 'POST',
                        data: formObject,
                        success: function(response) {
                            try {
                                var data = JSON.parse(response);
                                if (data.status === 'success') {
                                    alert(data.message);
                                    $('#create-announcement-form')[0].reset();
                                    location.reload();
                                } else {
                                    alert(data.message || 'Error creating announcement');
                                }
                            } catch (e) {
                                alert('An error occurred. Please try again.');
                            }
                        },
                        error: function() {
                            alert('An error occurred. Please try again.');
                        }
                    });
                });

                // Update profile
                $('#update-profile-form').on('submit', function(e) {
                    e.preventDefault();

                    var formData = new FormData(this);
                    var formObject = {};

                    formData.forEach(function(value, key) {
                        formObject[key] = value;
                    });

                    formObject.action = 'update_profile';

                    $.ajax({
                        url: 'admin_dashboard.php',
                        type: 'POST',
                        data: formObject,
                        success: function(response) {
                            try {
                                var data = JSON.parse(response);
                                if (data.status === 'success') {
                                    alert(data.message);
                                } else {
                                    alert(data.message || 'Error updating profile');
                                }
                            } catch (e) {
                                alert('An error occurred. Please try again.');
                            }
                        },
                        error: function() {
                            alert('An error occurred. Please try again.');
                        }
                    });
                });

                // Change password
                $('#change-password-form').on('submit', function(e) {
                    e.preventDefault();

                    if ($('#new_password').val() !== $('#confirm_password').val()) {
                        alert('New password and confirm password do not match.');
                        return;
                    }

                    var formData = new FormData(this);
                    var formObject = {};

                    formData.forEach(function(value, key) {
                        formObject[key] = value;
                    });

                    formObject.action = 'change_password';

                    $.ajax({
                        url: 'admin_dashboard.php',
                        type: 'POST',
                        data: formObject,
                        success: function(response) {
                            try {
                                var data = JSON.parse(response);
                                if (data.status === 'success') {
                                    alert(data.message);
                                    $('#change-password-form')[0].reset();
                                } else {
                                    alert(data.message || 'Error changing password');
                                }
                            } catch (e) {
                                alert('An error occurred. Please try again.');
                            }
                        },
                        error: function() {
                            alert('An error occurred. Please try again.');
                        }
                    });
                });

                // Update profile picture
                $('#update-picture-form').on('submit', function(e) {
                    e.preventDefault();

                    var formData = new FormData(this);
                    formData.append('action', 'update_profile_picture');

                    $.ajax({
                        url: 'admin_dashboard.php',
                        type: 'POST',
                        data: formData,
                        contentType: false,
                        processData: false,
                        success: function(response) {
                            try {
                                var data = JSON.parse(response);
                                if (data.status === 'success') {
                                    alert(data.message);
                                    location.reload();
                                } else {
                                    alert(data.message || 'Error updating profile picture');
                                }
                            } catch (e) {
                                alert('An error occurred. Please try again.');
                            }
                        },
                        error: function() {
                            alert('An error occurred. Please try again.');
                        }
                    });
                });

                // Save system settings
                $('#system-settings-form').on('submit', function(e) {
                    e.preventDefault();

                    var formData = new FormData(this);
                    var formObject = {};

                    formData.forEach(function(value, key) {
                        formObject[key] = value;
                    });

                    formObject.action = 'save_system_settings';

                    $.ajax({
                        url: 'admin_dashboard.php',
                        type: 'POST',
                        data: formObject,
                        success: function(response) {
                            try {
                                var data = JSON.parse(response);
                                if (data.status === 'success') {
                                    alert(data.message);
                                } else {
                                    alert(data.message || 'Error saving settings');
                                }
                            } catch (e) {
                                alert('An error occurred. Please try again.');
                            }
                        },
                        error: function() {
                            alert('An error occurred. Please try again.');
                        }
                    });
                });

                // Load students
                function loadStudents() {
                    $.ajax({
                        url: 'admin_dashboard.php',
                        type: 'GET',
                        data: {
                            action: 'get_students',
                            search: $('#search-student').val()
                        },
                        success: function(response) {
                            $('#students-container').html(response);
                            initStudentButtons();
                        },
                        error: function() {
                            $('#students-container').html('<div class="error">Error loading students. Please try again.</div>');
                        }
                    });
                }

                // Load attendance records
                function loadAttendanceRecords() {
                    $.ajax({
                        url: 'admin_dashboard.php',
                        type: 'GET',
                        data: {
                            action: 'get_attendance_records',
                            class_id: $('#filter_class').val(),
                            date_from: $('#filter_date_from').val(),
                            date_to: $('#filter_date_to').val(),
                            status: $('#filter_status').val()
                        },
                        success: function(response) {
                            $('#attendance-records-container').html(response);
                            initAttendanceButtons();
                        },
                        error: function() {
                            $('#attendance-records-container').html('<div class="error">Error loading attendance records. Please try again.</div>');
                        }
                    });
                }

                // Initialize student action buttons
                function initStudentButtons() {
                    $(document).off('click', '.view-student-btn').on('click', '.view-student-btn', function() {
                        var studentId = $(this).data('student-id');

                        $.ajax({
                            url: 'admin_dashboard.php',
                            type: 'GET',
                            data: {
                                action: 'get_student_details',
                                student_id: studentId
                            },
                            success: function(response) {
                                $('#student-details-container').html(response);
                                $('#student-details-modal').show();
                            },
                            error: function() {
                                alert('Error loading student details. Please try again.');
                            }
                        });
                    });

                    $(document).off('click', '.delete-student-btn').on('click', '.delete-student-btn', function() {
                        var studentId = $(this).data('student-id');
                        var studentName = $(this).data('student-name');

                        $('#confirm-message').text(`Are you sure you want to delete student "${studentName}"? This action cannot be undone.`);
                        $('#confirm-action').data('action', 'delete_student');
                        $('#confirm-action').data('id', studentId);
                        $('#confirm-modal').show();
                    });
                }

                // Initialize attendance action buttons
                function initAttendanceButtons() {
                    $(document).off('click', '.edit-attendance-btn').on('click', '.edit-attendance-btn', function() {
                        var attendanceId = $(this).data('id');
                        var studentId = $(this).data('student-id');
                        var classId = $(this).data('class-id');
                        var date = $(this).data('date');
                        var status = $(this).data('status');

                        $('#attendance_id').val(attendanceId);
                        $('#edit_attendance_student_id').val(studentId);
                        $('#edit_attendance_class').val(classId);
                        $('#edit_attendance_date').val(date);
                        $('#edit_attendance_status').val(status);

                        $('#edit-attendance-modal').show();
                    });

                    $(document).off('click', '.delete-attendance-btn').on('click', '.delete-attendance-btn', function() {
                        var attendanceId = $(this).data('id');

                        $('#confirm-message').text('Are you sure you want to delete this attendance record? This action cannot be undone.');
                        $('#confirm-action').data('action', 'delete_attendance');
                        $('#confirm-action').data('id', attendanceId);
                        $('#confirm-modal').show();
                    });
                }

                // Search student
                $('#search-btn').on('click', function() {
                    loadStudents();
                });

                $('#search-student').on('keyup', function(e) {
                    if (e.key === 'Enter') {
                        loadStudents();
                    }
                });

                // Filter attendance records
                $('#filter-attendance-btn').on('click', function() {
                    loadAttendanceRecords();
                });

                // Generate QR Code
                $('#generate-qr-btn').on('click', function() {
                    var classId = $('#qr_class').val();
                    var expiryMinutes = $('#qr_expiry').val();

                    if (!classId) {
                        alert('Please select a class.');
                        return;
                    }

                    $.ajax({
                        url: 'admin_dashboard.php',
                        type: 'POST',
                        data: {
                            action: 'generate_qr_code',
                            class_id: classId,
                            expiry_minutes: expiryMinutes
                        },
                        success: function(response) {
                            try {
                                var data = JSON.parse(response);
                                if (data.status === 'success') {
                                    $('#qr-code-container').empty();
                                    new QRCode(document.getElementById("qr-code-container"), {
                                        text: data.qr_data,
                                        width: 256,
                                        height: 256
                                    });
                                    $('#qr-code-display').show();

                                    // Start countdown timer
                                    var expirySeconds = expiryMinutes * 60;
                                    var timerDisplay = $('#qr-timer');

                                    function updateTimer() {
                                        var minutes = Math.floor(expirySeconds / 60);
                                        var seconds = expirySeconds % 60;
                                        timerDisplay.text(minutes.toString().padStart(2, '0') + ':' + seconds.toString().padStart(2, '0'));

                                        if (expirySeconds <= 0) {
                                            clearInterval(timerInterval);
                                            $('#qr-code-display').hide();
                                            alert('QR code has expired.');
                                        } else {
                                            expirySeconds--;
                                        }
                                    }

                                    updateTimer();
                                    var timerInterval = setInterval(updateTimer, 1000);

                                } else {
                                    alert(data.message || 'Error generating QR code');
                                }
                            } catch (e) {
                                alert('An error occurred. Please try again.');
                            }
                        },
                        error: function() {
                            alert('An error occurred. Please try again.');
                        }
                    });
                });

                // QR Settings
                $(document).on('click', '.qr-settings-btn', function() {
                    var classId = $(this).data('class-id');
                    var lateThreshold = $(this).data('threshold');

                    $('#qr_settings_class_id').val(classId);
                    $('#late_threshold_input').val(lateThreshold);
                    $('#qr-settings-modal').show();
                });

                $('#qr-settings-form').on('submit', function(e) {
                    e.preventDefault();

                    var formData = new FormData(this);
                    var formObject = {};

                    formData.forEach(function(value, key) {
                        formObject[key] = value;
                    });

                    formObject.action = 'save_qr_settings';

                    $.ajax({
                        url: 'admin_dashboard.php',
                        type: 'POST',
                        data: formObject,
                        success: function(response) {
                            try {
                                var data = JSON.parse(response);
                                if (data.status === 'success') {
                                    alert(data.message);
                                    $('#qr-settings-modal').hide();
                                    location.reload();
                                } else {
                                    alert(data.message || 'Error saving QR settings');
                                }
                            } catch (e) {
                                alert('An error occurred. Please try again.');
                            }
                        },
                        error: function() {
                            alert('An error occurred. Please try again.');
                        }
                    });
                });

                // View attendance
                $(document).on('click', '.view-attendance-btn', function() {
                    var studentId = $(this).data('student-id');
                    var classId = $(this).data('class-id');

                    $.ajax({
                        url: 'admin_dashboard.php',
                        type: 'GET',
                        data: {
                            action: 'get_student_attendance',
                            student_id: studentId,
                            class_id: classId
                        },
                        success: function(response) {
                            $('#attendance-details-container').html(response);
                            $('#attendance-details-modal').show();
                        },
                        error: function() {
                            alert('Error loading attendance details. Please try again.');
                        }
                    });
                });

                // View grades
                $(document).on('click', '.view-grades-btn', function() {
                    var studentId = $(this).data('student-id');
                    var classId = $(this).data('class-id');

                    $('#grade_student_id').val(studentId);
                    $('#grade_class_id').val(classId);

                    $.ajax({
                        url: 'admin_dashboard.php',
                        type: 'GET',
                        data: {
                            action: 'get_student_grades',
                            student_id: studentId,
                            class_id: classId
                        },
                        success: function(response) {
                            $('#grades-container').html(response);
                            $('#grades-modal').show();

                            // Initialize delete grade buttons
                            initGradeButtons();

                            // Initialize grade chart if it exists
                            if ($('#grade-chart').length) {
                                var ctx = document.getElementById('grade-chart').getContext('2d');
                                var chartData = JSON.parse($('#grade-chart-data').val());

                                new Chart(ctx, {
                                    type: 'bar',
                                    data: {
                                        labels: chartData.labels,
                                        datasets: [{
                                            label: 'Grade (%)',
                                            data: chartData.data,
                                            backgroundColor: 'rgba(54, 162, 235, 0.5)',
                                            borderColor: 'rgba(54, 162, 235, 1)',
                                            borderWidth: 1
                                        }]
                                    },
                                    options: {
                                        scales: {
                                            y: {
                                                beginAtZero: true,
                                                max: 100
                                            }
                                        }
                                    }
                                });
                            }
                        },
                        error: function() {
                            alert('Error loading grades. Please try again.');
                        }
                    });
                });

                // Initialize grade buttons
                function initGradeButtons() {
                    $(document).off('click', '.delete-grade-btn').on('click', '.delete-grade-btn', function() {
                        var gradeId = $(this).data('id');

                        $('#confirm-message').text('Are you sure you want to delete this grade? This action cannot be undone.');
                        $('#confirm-action').data('action', 'delete_grade');
                        $('#confirm-action').data('id', gradeId);
                        $('#confirm-modal').show();
                    });
                }

                // Add grade
                $('#add-grade-form').on('submit', function(e) {
                    e.preventDefault();

                    var formData = new FormData(this);
                    var formObject = {};

                    formData.forEach(function(value, key) {
                        formObject[key] = value;
                    });

                    formObject.action = 'add_grade';

                    $.ajax({
                        url: 'admin_dashboard.php',
                        type: 'POST',
                        data: formObject,
                        success: function(response) {
                            try {
                                var data = JSON.parse(response);
                                if (data.status === 'success') {
                                    alert(data.message);

                                    // Refresh grades
                                    var studentId = $('#grade_student_id').val();
                                    var classId = $('#grade_class_id').val();

                                    $.ajax({
                                        url: 'admin_dashboard.php',
                                        type: 'GET',
                                        data: {
                                            action: 'get_student_grades',
                                            student_id: studentId,
                                            class_id: classId
                                        },
                                        success: function(response) {
                                            $('#grades-container').html(response);
                                            initGradeButtons();
                                        }
                                    });

                                    $('#add-grade-form')[0].reset();
                                } else {
                                    alert(data.message || 'Error adding grade');
                                }
                            } catch (e) {
                                alert('An error occurred. Please try again.');
                            }
                        },
                        error: function() {
                            alert('An error occurred. Please try again.');
                        }
                    });
                });

                // Class grades
                $(document).on('click', '.view-grades-class-btn', function() {
                    var classId = $(this).data('class-id');

                    $.ajax({
                        url: 'admin_dashboard.php',
                        type: 'GET',
                        data: {
                            action: 'get_class_grades',
                            class_id: classId
                        },
                        success: function(response) {
                            $('#grades-container').html(response);
                            $('#grades-modal').show();
                        },
                        error: function() {
                            alert('Error loading class grades. Please try again.');
                        }
                    });
                });

                // Delete class
                $(document).on('click', '.delete-class-btn', function() {
                    var classId = $(this).data('class-id');

                    $('#confirm-message').text('Are you sure you want to delete this class? All related data including students, grades, and attendance will be deleted. This action cannot be undone.');
                    $('#confirm-action').data('action', 'delete_class');
                    $('#confirm-action').data('id', classId);
                    $('#confirm-modal').show();
                });

                // Edit announcement
                $(document).on('click', '.edit-announcement-btn', function() {
                    var id = $(this).data('id');
                    var title = $(this).data('title');
                    var message = $(this).data('message');
                    var classId = $(this).data('class-id');

                    $('#edit_announcement_id').val(id);
                    $('#edit_announcement_title').val(title);
                    $('#edit_announcement_message').val(message);
                    $('#edit_announcement_class').val(classId);

                    $('#edit-announcement-modal').show();
                });

                $('#edit-announcement-form').on('submit', function(e) {
                    e.preventDefault();

                    var formData = new FormData(this);
                    var formObject = {};

                    formData.forEach(function(value, key) {
                        formObject[key] = value;
                    });

                    formObject.action = 'update_announcement';

                    $.ajax({
                        url: 'admin_dashboard.php',
                        type: 'POST',
                        data: formObject,
                        success: function(response) {
                            try {
                                var data = JSON.parse(response);
                                if (data.status === 'success') {
                                    alert(data.message);
                                    location.reload();
                                } else {
                                    alert(data.message || 'Error updating announcement');
                                }
                            } catch (e) {
                                alert('An error occurred. Please try again.');
                            }
                        },
                        error: function() {
                            alert('An error occurred. Please try again.');
                        }
                    });
                });

                // Delete announcement
                $(document).on('click', '.delete-announcement-btn', function() {
                    var id = $(this).data('id');

                    $('#confirm-message').text('Are you sure you want to delete this announcement? This action cannot be undone.');
                    $('#confirm-action').data('action', 'delete_announcement');
                    $('#confirm-action').data('id', id);
                    $('#confirm-modal').show();
                });

                // Generate report
                $(document).on('click', '.generate-report-btn', function() {
                    var classId = $(this).data('class-id');

                    window.location.href = 'report.php?class_id=' + classId;
                });

                // Edit attendance form submission
                $('#edit-attendance-form').on('submit', function(e) {
                    e.preventDefault();

                    var formData = new FormData(this);
                    var formObject = {};

                    formData.forEach(function(value, key) {
                        formObject[key] = value;
                    });

                    formObject.action = 'update_attendance';

                    $.ajax({
                        url: 'admin_dashboard.php',
                        type: 'POST',
                        data: formObject,
                        success: function(response) {
                            try {
                                var data = JSON.parse(response);
                                if (data.status === 'success') {
                                    alert(data.message);
                                    $('#edit-attendance-modal').hide();
                                    loadAttendanceRecords();
                                } else {
                                    alert(data.message || 'Error updating attendance');
                                }
                            } catch (e) {
                                alert('An error occurred. Please try again.');
                            }
                        },
                        error: function() {
                            alert('An error occurred. Please try again.');
                        }
                    });
                });

                // Confirm action button
                $('#confirm-action').on('click', function() {
                    var action = $(this).data('action');
                    var id = $(this).data('id');

                    $.ajax({
                        url: 'admin_dashboard.php',
                        type: 'POST',
                        data: {
                            action: action,
                            id: id
                        },
                        success: function(response) {
                            try {
                                var data = JSON.parse(response);
                                if (data.status === 'success') {
                                    alert(data.message);
                                    $('#confirm-modal').hide();

                                    if (action === 'delete_student') {
                                        loadStudents();
                                    } else if (action === 'delete_attendance') {
                                        loadAttendanceRecords();
                                    } else if (action === 'delete_grade') {
                                        // Refresh grades
                                        var studentId = $('#grade_student_id').val();
                                        var classId = $('#grade_class_id').val();

                                        $.ajax({
                                            url: 'admin_dashboard.php',
                                            type: 'GET',
                                            data: {
                                                action: 'get_student_grades',
                                                student_id: studentId,
                                                class_id: classId
                                            },
                                            success: function(response) {
                                                $('#grades-container').html(response);
                                                initGradeButtons();
                                            }
                                        });
                                    } else {
                                        location.reload();
                                    }
                                } else {
                                    alert(data.message || 'Error performing action');
                                }
                            } catch (e) {
                                alert('An error occurred. Please try again.');
                            }
                        },
                        error: function() {
                            alert('An error occurred. Please try again.');
                        }
                    });
                });

                // Close modal
                $(document).on('click', '.close-modal, #cancel-confirm', function() {
                    $(this).closest('.modal').hide();
                });

                // Close modal when clicking outside
                $(window).on('click', function(e) {
                    if ($(e.target).hasClass('modal')) {
                        $('.modal').hide();
                    }
                });

                // Load initial data
                loadStudents();
                loadAttendanceRecords();

                // Tab navigation
                $('.nav-link').on('click', function(e) {
                    e.preventDefault();
                    var tab = $(this).attr('href').substring(1);

                    // Update URL with tab
                    var url = new URL(window.location.href);
                    url.searchParams.set('tab', tab);
                    window.history.pushState({}, '', url);

                    $('.nav-link').removeClass('active');
                    $(this).addClass('active');

                    $('.tab-content').removeClass('active');
                    $('#' + tab + '-tab').addClass('active');
                });

                // Check URL for active tab on page load
                function setActiveTabFromUrl() {
                    var url = new URL(window.location.href);
                    var tab = url.searchParams.get('tab');

                    if (tab) {
                        $('.nav-link').removeClass('active');
                        $(`a.nav-link[href="#${tab}"]`).addClass('active');

                        $('.tab-content').removeClass('active');
                        $('#' + tab + '-tab').addClass('active');
                    }
                }

                // Set active tab from URL on page load
                setActiveTabFromUrl();
            });
        </script>
</body>

</html>