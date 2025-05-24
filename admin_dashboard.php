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

if (isset($_POST["create_class"])) {
    echo "run";
    $class_name = sanitize($_POST['class_name']);
    $class_code = sanitize($_POST['class_code']);
    $description = sanitize($_POST['description']);
    $start_time = sanitize($_POST['start_time']);
    $end_time = sanitize($_POST['end_time']);
    $days = isset($_POST['days']) ? implode(',', $_POST['days']) : '';

    $query = "INSERT INTO classes (class_name, code, description, start_time, end_time, days, created_by) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ssssssi", $class_name, $class_code, $description, $start_time, $end_time, $days, $admin_id);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['status' => 'success', 'message' => 'Class created successfully', 'class_id' => mysqli_insert_id($conn)]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create class: ' . mysqli_error($conn)]);
    }
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    // Handle class creation

    // Handle student addition to class
    if ($action === 'add_student_to_class') {
        $student_id = sanitize($_POST['student_id']);
        $class_id = sanitize($_POST['class_id']);

        // Check if student exists
        $check_query = "SELECT id FROM students WHERE student_id = ? AND role = 'student'";
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
        $user_query = "SELECT id FROM students WHERE student_id = ? AND role = 'student'";
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

        $query = "UPDATE students SET name = ?, email = ? WHERE id = ?";
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
        $query = "SELECT password FROM students WHERE id = ?";
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
        $query = "UPDATE students SET password = ? WHERE id = ?";
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
        $user_query = "SELECT id FROM students WHERE student_id = ? AND role = 'student'";
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

$classes_query = "SELECT * FROM classes WHERE created_by = ? ORDER BY class_name";
$classes_stmt = mysqli_prepare($conn, $classes_query);
mysqli_stmt_bind_param($classes_stmt, "i", $admin_id);
mysqli_stmt_execute($classes_stmt);
$classes_result = mysqli_stmt_get_result($classes_stmt);
$classes = [];
while ($row = mysqli_fetch_assoc($classes_result)) {
    $classes[] = $row;
}

// Get recent attendance records


// Get attendance statistics

$stats_query = "
    SELECT 
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
        COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count
    FROM attendance a
    JOIN classes c ON a.id = c.id
    WHERE c.created_by = ?
    AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "i", $admin_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$attendance_stats = mysqli_fetch_assoc($stats_result);

// Get recent announcements


// Get students for select dropdowns


// Current active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Student Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="./styles/admin-dashboard.css">
</head>

<body>
    <div class="header">
        <h1>Student Management System - Admin Dashboard</h1>
        <div class="user-info">
            <div class="name"><?php echo isset($admin['name']) ? htmlspecialchars($admin['name']) : 'Admin'; ?></div>
            <img src="<?php echo !empty($admin['profile_image']) ? 'uploads/' . $admin['profile_image'] : 'uploads/profile_pictures/69_1747031134.jpg'; ?>" alt="Profile" id="profileImage">
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
            </div>

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
                <div class="tab-content <?php echo $active_tab == 'dashboard' ? 'active' : ''; ?>" id="dashboard-tab">
                    <div class="welcome-card">
                        <h2>Welcome, <?php echo htmlspecialchars($admin['name']); ?>!</h2>
                        <p>Here's a quick overview of your classes and recent activities. Use the navigation above to manage your classes, students, and more.</p>
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
                </div>

                <!-- Classes Tab -->
                <div class="tab-content <?php echo $active_tab == 'classes' ? 'active' : ''; ?>" id="classes-tab">
                    <h2 class="section-title">Manage Classes</h2>

                    <div class="card" id="create-class-card">
                        <h3>
                            <div class="card-icon"><i class="fas fa-plus"></i></div> Create New Class
                        </h3>
                        <div class="card-content">
                            <!-- id="create-class-form" -->
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) ?>" method="post">
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
                                        <button type="submit" name="create_class" class="btn">Create Class</button>
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
                                            <div class="course-name"><?php echo htmlspecialchars($class['class_name']); ?></div>
                                            <div class="course-code"><?php echo htmlspecialchars($class['code']); ?></div>
                                        </div>
                                        <div>
                                            <button class="toggle-details-btn"><i class="fas fa-chevron-down"></i></button>
                                        </div>
                                    </div>
                                    <div class="course-details">
                                        <div class="course-description"><?php echo htmlspecialchars($class['description']); ?></div>
                                        <div class="course-instructor">
                                            <strong>Schedule:</strong> <?php echo htmlspecialchars($class['days']); ?>,
                                            <?php echo date('h:i A', strtotime($class['start_time'])); ?> -
                                            <?php echo date('h:i A', strtotime($class['end_time'])); ?>
                                        </div>
                                        <div class="course-instructor">
                                            <strong>Late Threshold:</strong> <?php echo $class['late_threshold']; ?> minutes
                                        </div>
                                        
                                        <!-- ITO YUNG PROBLEMA NA KANINA KO PA HIANAHANAP!!!! -->
                                        <h4>Enrolled Students</h4>
                                        <div class="enrolled-students-container">
                                            <?php
                                                $enrolled_query = "
                                                    SELECT u.id, u.name, u.student_id, u.email
                                                    FROM class_enrollments e
                                                    JOIN students u ON e.user_id = u.id
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
                                        <!-- END DITO, DAPAT MA FIX TO -->
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

                    <div class="card" id="add-new-student-card">
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

        <script defer src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
        <script defer src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
        <script defer src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
        <script defer src="./js/admin-dashboard.js"></script>
</body>

</html>