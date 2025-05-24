<?php
require_once 'config.php';
// Check if user is logged in
if (!isset($_SESSION['student_id'])) {
    // Redirect to login page if not logged in
    header("Location: login.php");
    exit();
}

// Get student information from session
$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'];

// Handle profile picture upload
$upload_message = '';
if (isset($_POST['upload_profile_pic']) && isset($_FILES['photo'])) {
    $target_dir = "uploads/profile_pictures/";
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION));
    $new_filename = $student_id . "_" . time() . "." . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    // Check if image file is an actual image
    $check = getimagesize($_FILES["photo"]["tmp_name"]);
    if ($check !== false) {
        // Check file size (limit to 5MB)
        if ($_FILES["photo"]["size"] > 5000000) {
            $upload_message = "Sorry, your file is too large. Max size is 5MB.";
        } else {
            // Allow only certain file formats
            if ($file_extension != "jpg" && $file_extension != "png" && $file_extension != "jpeg") {
                $upload_message = "Sorry, only JPG, JPEG & PNG files are allowed.";
            } else {
                // Upload file and update database
                if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
                    try {
                        $stmt = $pdo->prepare("UPDATE students SET photo = ? WHERE student_id = ?");
                        $stmt->execute([$target_file, $student_id]);
                        $upload_message = "Your profile picture has been updated successfully!";
                        $_SESSION['photo'] = $target_file;
                    } catch(PDOException $e) {
                        $upload_message = "Database error: " . $e->getMessage();
                    }
                } else {
                    $upload_message = "Sorry, there was an error uploading your file.";
                }
            }
        }
    } else {
        $upload_message = "File is not an image.";
    }
}

// You can fetch additional student data here if needed
try {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $student_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get profile picture path from database or use placeholder
    $profile_picture = isset($student_data['photo']) && !empty($student_data['profile_picture']) 
        ? $student_data['profile_picture'] 
        : "/api/placeholder/40/40";
        
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Fetch attendance records for the student
$attendance_records = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.date, a.time, a.status, c.course_name, c.course_code 
        FROM attendance a 
        JOIN courses c ON a.course_id = c.course_id 
        WHERE a.student_id = ? 
        ORDER BY a.date DESC, a.time DESC
        LIMIT 10
    ");
    $stmt->execute([$student_id]);
    $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $attendance_error = "Database error: " . $e->getMessage();
}

// Calculate attendance statistics
$attendance_stats = [
    'total' => 0,
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'percentage' => 0
];

try {
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM attendance 
        WHERE student_id = ? 
        GROUP BY status
    ");
    $stmt->execute([$student_id]);
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($stats as $stat) {
        if ($stat['status'] == 'present') {
            $attendance_stats['present'] = $stat['count'];
        } elseif ($stat['status'] == 'absent') {
            $attendance_stats['absent'] = $stat['count'];
        } elseif ($stat['status'] == 'late') {
            $attendance_stats['late'] = $stat['count'];
        }
    }
    
    $attendance_stats['total'] = $attendance_stats['present'] + $attendance_stats['absent'] + $attendance_stats['late'];
    
    if ($attendance_stats['total'] > 0) {
        $attendance_stats['percentage'] = round(($attendance_stats['present'] + $attendance_stats['late']) / $attendance_stats['total'] * 100);
    }
} catch(PDOException $e) {
    $stats_error = "Database error: " . $e->getMessage();
}

// Fetch enrolled courses for the student
$enrolled_courses = [];
try {
    $stmt = $pdo->prepare("
        SELECT c.course_id, c.course_code, c.course_name, c.description, t.teacher_name
        FROM enrolled_courses e
        JOIN courses c ON e.course_id = c.course_id
        LEFT JOIN teachers t ON c.teacher_id = t.teacher_id
        WHERE e.student_id = ?
        ORDER BY c.course_code
    ");
    $stmt->execute([$student_id]);
    $enrolled_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $courses_error = "Database error: " . $e->getMessage();
}

// Fetch notifications
$notifications = [];
try {
    $stmt = $pdo->prepare("
        SELECT n.notification_id, n.title, n.message, n.created_at, n.sender_id, n.is_read, 
               t.teacher_name as sender_name
        FROM notifications n
        LEFT JOIN teachers t ON n.sender_id = t.teacher_id
        WHERE n.recipient_id = ? OR n.recipient_id = 0
        ORDER BY n.created_at DESC
        LIMIT 15
    ");
    $stmt->execute([$student_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $notification_error = "Database error: " . $e->getMessage();
}

// Count unread notifications
$unread_count = 0;
foreach ($notifications as $notification) {
    if ($notification['is_read'] == 0) {
        $unread_count++;
    }
}

// Fetch student grades and scores
$grades = [];
try {
    $stmt = $pdo->prepare("
        SELECT g.grade_id, g.course_id, g.assessment_type, g.score, g.max_score, g.date,
               c.course_code, c.course_name
        FROM grades g
        JOIN courses c ON g.course_id = c.course_id
        WHERE g.student_id = ?
        ORDER BY g.date DESC
    ");
    $stmt->execute([$student_id]);
    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $grades_error = "Database error: " . $e->getMessage();
}

// Process grades by course
$course_grades = [];
$overall_gpa = 0;
$total_courses = 0;

foreach ($grades as $grade) {
    $course_id = $grade['course_id'];
    $course_code = $grade['course_code'];
    $course_name = $grade['course_name'];
    
    if (!isset($course_grades[$course_id])) {
        $course_grades[$course_id] = [
            'course_code' => $course_code,
            'course_name' => $course_name,
            'assessments' => [],
            'total_score' => 0,
            'max_score' => 0,
            'percentage' => 0
        ];
    }
    
    $course_grades[$course_id]['assessments'][] = $grade;
    $course_grades[$course_id]['total_score'] += $grade['score'];
    $course_grades[$course_id]['max_score'] += $grade['max_score'];
}

// Calculate percentages and letter grades
foreach ($course_grades as $course_id => &$course) {
    if ($course['max_score'] > 0) {
        $course['percentage'] = round(($course['total_score'] / $course['max_score']) * 100);
        
        // Assign letter grade
        if ($course['percentage'] >= 90) {
            $course['letter_grade'] = 'A';
            $course['grade_point'] = 4.0;
        } elseif ($course['percentage'] >= 80) {
            $course['letter_grade'] = 'B';
            $course['grade_point'] = 3.0;
        } elseif ($course['percentage'] >= 70) {
            $course['letter_grade'] = 'C';
            $course['grade_point'] = 2.0;
        } elseif ($course['percentage'] >= 60) {
            $course['letter_grade'] = 'D';
            $course['grade_point'] = 1.0;
        } else {
            $course['letter_grade'] = 'F';
            $course['grade_point'] = 0.0;
        }
        
        $overall_gpa += $course['grade_point'];
        $total_courses++;
    }
}

// Calculate overall GPA
if ($total_courses > 0) {
    $overall_gpa = round($overall_gpa / $total_courses, 2);
}

// Handle notification reply submission
if (isset($_POST['submit_reply']) && isset($_POST['notification_id']) && isset($_POST['reply_message'])) {
    $notification_id = $_POST['notification_id'];
    $reply_message = $_POST['reply_message'];
    $sender_id = $_POST['sender_id'];
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notification_replies (notification_id, student_id, message, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$notification_id, $student_id, $reply_message]);
        
        // Mark notification as read
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?");
        $stmt->execute([$notification_id]);
        
        $reply_success = "Your reply has been sent successfully!";
        
        // Refresh notifications list
        $stmt = $pdo->prepare("
            SELECT n.notification_id, n.title, n.message, n.created_at, n.sender_id, n.is_read,
                   t.teacher_name as sender_name
            FROM notifications n
            LEFT JOIN teachers t ON n.sender_id = t.teacher_id
            WHERE n.recipient_id = ? OR n.recipient_id = 0
            ORDER BY n.created_at DESC
            LIMIT 15
        ");
        $stmt->execute([$student_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Recount unread
        $unread_count = 0;
        foreach ($notifications as $notification) {
            if ($notification['is_read'] == 0) {
                $unread_count++;
            }
        }
    } catch(PDOException $e) {
        $reply_error = "Failed to send reply: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Student Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.0/main.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.0/main.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="dashboard.css">
</head>

<body>
    <div class="header">
        <h1>Student Dashboard</h1>
        <div class="user-info">
            <img id="profilePic" src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile Picture"
                title="Click to change your profile picture">
            <div class="name"><?php echo htmlspecialchars($student_name); ?></div>
            <a href="logout.php" class="logout">Logout</a>
        </div>
    </div>

    <div class="container">
        <!-- Left Sidebar with QR Code -->
        <div class="sidebar">
            <div class="qr-container">
                <h3>Attendance QR Code</h3>
                <div id="qrcode"></div>
                <p class="qr-description">Scan this QR code to mark your attendance</p>
            </div>

            <div class="student-info">
                <h3>Student Information</h3>
                <p><strong>ID:</strong> <?php echo htmlspecialchars($student_id); ?></p>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($student_name); ?></p>
                <?php if(isset($student_data['program'])): ?>
                <p><strong>Program:</strong> <?php echo htmlspecialchars($student_data['program']); ?></p>
                <?php endif; ?>
                <?php if(isset($student_data['semester'])): ?>
                <p><strong>Semester:</strong> <?php echo htmlspecialchars($student_data['semester']); ?></p>
                <?php endif; ?>

                <div class="gpa-indicator">
                    <div>GPA</div>
                    <div><?php echo $overall_gpa; ?></div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="main-content">
            <?php if(isset($_SESSION['login_success'])): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($_SESSION['login_success']); ?>
                <?php unset($_SESSION['login_success']); ?>
            </div>
            <?php endif; ?>

            <?php if(!empty($upload_message)): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($upload_message); ?>
            </div>
            <?php endif; ?>

            <?php if(isset($reply_success)): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($reply_success); ?>
            </div>
            <?php endif; ?>

            <?php if(isset($reply_error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($reply_error); ?>
            </div>
            <?php endif; ?>

            <?php if(isset($schedule_success)): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($schedule_success); ?>
            </div>
            <?php endif; ?>

            <?php if(isset($schedule_error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($schedule_error); ?>
            </div>
            <?php endif; ?>

            <div class="welcome-card">
                <h2>Welcome back, <?php echo htmlspecialchars($student_name); ?>!</h2>
                <p>Track your attendance, check your schedule, courses, and more.</p>
            </div>

            <div class="attendance-section">
                <h2 class="section-title">Attendance Overview</h2>

                <div class="attendance-stats">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #10b981;"><?php echo $attendance_stats['percentage']; ?>%
                        </div>
                        <div class="stat-label">Attendance Rate</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-value"><?php echo $attendance_stats['present']; ?></div>
                        <div class="stat-label">Present</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-value"><?php echo $attendance_stats['late']; ?></div>
                        <div class="stat-label">Late</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-value"><?php echo $attendance_stats['absent']; ?></div>
                        <div class="stat-label">Absent</div>
                    </div>
                </div>

                <h3 class="section-title">Recent Attendance Records</h3>

                <?php if (!empty($attendance_records)): ?>
                <div class="table-responsive">
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Course</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_records as $record): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($record['time'])); ?></td>
                                <td><?php echo htmlspecialchars($record['course_code'] . ' - ' . $record['course_name']); ?>
                                </td>
                                <td>
                                    <span class="attendance-status status-<?php echo strtolower($record['status']); ?>">
                                        <?php echo ucfirst($record['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <a href="attendance_history.php" class="view-all-btn">View All Attendance Records</a>
                <?php else: ?>
                <div class="no-records">No attendance records found. Your attendance history will appear here after
                    classes.</div>
                <?php endif; ?>
            </div>

            <!-- Dashboard Cards for Navigation -->
            <div class="dashboard-cards">
                <div class="card" data-tab="schedule-tab">
                    <h3>
                        <div class="card-icon">📅</div>
                        Schedule
                    </h3>
                    <p>Manage your class schedule and personal events.</p>
                </div>

                <div class="card" data-tab="courses-tab">
                    <h3>
                        <div class="card-icon">📚</div>
                        Courses
                    </h3>
                    <p>View your enrolled courses and details.</p>
                </div>

                <div class="card" data-tab="status-tab">
                    <h3>
                        <div class="card-icon">📊</div>
                        Academic Status
                    </h3>
                    <p>Check your grades, scores, and academic progress.</p>
                </div>

                <div class="card" data-tab="notifications-tab">
                    <h3>
                        <div class="card-icon">🔔</div>
                        Notifications
                        <?php if($unread_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </h3>
                    <p>Stay updated with announcements and messages.</p>
                </div>
            </div>

            <div id="courses-tab" class="tab-content">
                <h2 class="section-title">My Courses</h2>

                <?php if (!empty($enrolled_courses)): ?>
                <?php foreach ($enrolled_courses as $course): ?>
                <div class="course-card" data-course-id="<?php echo $course['course_id']; ?>">
                    <div class="course-header">
                        <div>
                            <div class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></div>
                            <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                        </div>
                        <div class="toggle-icon">▼</div>
                    </div>

                    <div class="course-details">
                        <div class="course-description">
                            <?php echo htmlspecialchars($course['description'] ?? 'No description available.'); ?>
                        </div>

                        <div class="course-instructor">
                            <strong>Instructor:</strong>
                            <?php echo htmlspecialchars($course['teacher_name'] ?? 'Not assigned'); ?>
                        </div>

                        <a href="course_details.php?id=<?php echo $course['course_id']; ?>" class="view-all-btn">View
                            Course Details</a>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div class="no-records">No courses found. Your enrolled courses will appear here.</div>
                <?php endif; ?>
            </div>

            <div id="status-tab" class="tab-content">
                <h2 class="section-title">Academic Status</h2>

                <div class="grades-overview">
                    <div class="grades-summary">
                        <div class="grade-card">
                            <div class="grade-value"><?php echo $overall_gpa; ?></div>
                            <div class="grade-label">Overall GPA</div>
                        </div>

                        <div class="grade-card">
                            <div class="grade-value"><?php echo count($course_grades); ?></div>
                            <div class="grade-label">Courses</div>
                        </div>

                        <div class="grade-card">
                            <div class="grade-value"><?php echo count($grades); ?></div>
                            <div class="grade-label">Assessments</div>
                        </div>
                    </div>

                    <h3 class="section-title">Course Grades</h3>

                    <?php if (!empty($course_grades)): ?>
                    <div class="course-grades">
                        <?php foreach ($course_grades as $course_id => $course): ?>
                        <div class="grade-course-card" data-course-id="<?php echo $course_id; ?>">
                            <div class="grade-course-header">
                                <div class="grade-course-title">
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                </div>
                                <div>
                                    <span class="grade-course-percentage"><?php echo $course['percentage']; ?>%</span>
                                    <span
                                        class="grade-course-letter grade-<?php echo strtolower($course['letter_grade']); ?>">
                                        <?php echo $course['letter_grade']; ?>
                                    </span>
                                </div>
                            </div>

                            <div class="grade-assessments">
                                <?php if (!empty($course['assessments'])): ?>
                                <?php foreach ($course['assessments'] as $assessment): ?>
                                <div class="assessment-item">
                                    <div>
                                        <div class="assessment-type">
                                            <?php echo htmlspecialchars($assessment['assessment_type']); ?></div>
                                        <div class="assessment-date">
                                            <?php echo date('M d, Y', strtotime($assessment['date'])); ?></div>
                                    </div>
                                    <div class="assessment-score">
                                        <?php echo $assessment['score']; ?> / <?php echo $assessment['max_score']; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <div class="no-records">No assessments found for this course.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="no-records">No grade records found. Your grades will appear here once assessments are
                        graded.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="notifications-tab" class="tab-content">
                <h2 class="section-title">Notifications</h2>

                <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $notification): ?>
                <div class="notification-item <?php echo ($notification['is_read'] == 0) ? 'unread' : ''; ?>"
                    data-notification-id="<?php echo $notification['notification_id']; ?>">
                    <div class="notification-header">
                        <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                        <div class="notification-date">
                            <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?></div>
                    </div>

                    <div class="notification-sender">
                        From: <?php echo htmlspecialchars($notification['sender_name'] ?? 'System'); ?>
                    </div>

                    <div class="notification-message">
                        <?php echo htmlspecialchars($notification['message']); ?>
                    </div>

                    <?php if ($notification['sender_id'] > 0): ?>
                    <button class="reply-button"
                        data-notification-id="<?php echo $notification['notification_id']; ?>">Reply</button>

                    <div class="reply-form">
                        <form action="" method="POST">
                            <input type="hidden" name="notification_id"
                                value="<?php echo $notification['notification_id']; ?>">
                            <input type="hidden" name="sender_id" value="<?php echo $notification['sender_id']; ?>">

                            <div class="form-group">
                                <textarea name="reply_message" class="reply-textarea"
                                    placeholder="Type your reply here..." required></textarea>
                            </div>

                            <button type="submit" name="submit_reply" class="btn">Send Reply</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div class="no-records">No notifications found. New announcements and messages will appear here.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Profile Picture Modal -->
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Update Profile Picture</h2>
            <form action="" method="POST" enctype="multipart/form-data" class="upload-form">
                <div class="form-group">
                    <label for="profile_picture">Select a new profile picture:</label>
                    <input type="file" id="profile_picture" name="profile_picture" class="form-control"
                        accept="image/jpeg, image/png" required>
                </div>
                <div class="upload-preview">
                    <img id="preview" class="preview-image" src="" alt="Preview">
                </div>
                <button type="submit" name="upload_profile_pic" class="btn">Upload Picture</button>
            </form>
        </div>
    </div>

    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Schedule</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div id="schedule-tab" class="tab-content">
        <h2 class="section-title">My Schedule</h2>

        <div class="schedule-controls">
            <button id="prev-week" class="schedule-nav-btn">◀ Previous Week</button>
            <div id="current-week-display" class="current-week">May 12 - May 18, 2025</div>
            <button id="next-week" class="schedule-nav-btn">Next Week ▶</button>
        </div>

        <div class="schedule-container">
            <div class="schedule-sidebar">
                <div class="time-labels">
                    <div class="time-slot">6:00 AM</div>
                    <div class="time-slot">7:00 AM</div>
                    <div class="time-slot">8:00 AM</div>
                    <div class="time-slot">9:00 AM</div>
                    <div class="time-slot">10:00 AM</div>
                    <div class="time-slot">11:00 AM</div>
                    <div class="time-slot">12:00 PM</div>
                    <div class="time-slot">1:00 PM</div>
                    <div class="time-slot">2:00 PM</div>
                    <div class="time-slot">3:00 PM</div>
                    <div class="time-slot">4:00 PM</div>
                    <div class="time-slot">5:00 PM</div>
                    <div class="time-slot">6:00 PM</div>
                    <div class="time-slot">7:00 PM</div>
                    <div class="time-slot">8:00 PM</div>
                    <div class="time-slot">9:00 PM</div>
                    <div class="time-slot">10:00 PM</div>
                </div>
            </div>

            <div class="schedule-grid">
                <div class="day-headers">
                    <div class="day-header">Monday</div>
                    <div class="day-header">Tuesday</div>
                    <div class="day-header">Wednesday</div>
                    <div class="day-header">Thursday</div>
                    <div class="day-header">Friday</div>
                    <div class="day-header">Saturday</div>
                </div>

                <div class="schedule-body">
                    <!-- Schedule grid will be populated by JavaScript -->
                    <div id="schedule-grid-container" class="week-grid"></div>
                </div>
            </div>
        </div>

        <div class="schedule-legend">
            <h3>Type of Schedule</h3>
            <div class="legend-items">
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #4ade80;">
                        <span class="legend-text">Lecture</span>
                    </div>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #60a5fa;">
                        <span class="legend-text">Laboratory</span>
                    </div>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #f97316;">
                        <span class="legend-text">Exam</span>
                    </div>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #a78bfa;">
                        <span class="legend-text">Activity</span>
                    </div>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #ec4899;">
                        <span class="legend-text">Personal</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add personal event form -->
        <div class="personal-events-section">
            <h3>Add Personal Schedule</h3>
            <div id="message-container"></div>
            <form id="add-personal-event-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="event_title">Event Title:</label>
                        <input type="text" id="event_title" name="event_title" required>
                    </div>

                    <div class="form-group">
                        <label for="event_type">Event Type:</label>
                        <select id="event_type" name="event_type">
                            <option value="lecture">Lecture</option>
                            <option value="lab">Laboratory</option>
                            <option value="exam">Exam</option>
                            <option value="other">Activity</option>
                            <option value="personal">Personal</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="event_date">Date:</label>
                        <input type="date" id="event_date" name="event_date" required>
                    </div>

                    <div class="form-group">
                        <label for="event_start_time">Start Time:</label>
                        <input type="time" id="event_start_time" name="event_start_time" required>
                    </div>

                    <div class="form-group">
                        <label for="event_end_time">End Time:</label>
                        <input type="time" id="event_end_time" name="event_end_time" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="event_description">Description (Optional):</label>
                    <textarea id="event_description" name="event_description" rows="2"></textarea>
                </div>

                <button type="submit" id="add-event-btn" class="btn">Add Schedule</button>
            </form>
        </div>
    </div>

    <script src="schedule.js"></script>
</body>
</html>


    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log("DOM fully loaded");

        // Generate QR code
        var qrcodeDiv = document.getElementById('qrcode');
        if (qrcodeDiv) {
            console.log("QR code div found, generating QR code");
            // Make sure your PHP variable is defined and not empty
            var studentId = "<?php echo $student_id; ?>";
            console.log("Student ID for QR code:", studentId);

            try {
                new QRCode(qrcodeDiv, {
                    text: studentId || "fallback-id-if-empty",
                    width: 128,
                    height: 128
                });
            } catch (e) {
                console.error("Error generating QR code:", e);
                qrcodeDiv.innerHTML = "<p>Error generating QR code. Please refresh the page.</p>";
            }
        } else {
            console.error("QR code container not found");
        }

        // Profile Picture Modal
        var modal = document.getElementById("profileModal");
        var profilePic = document.getElementById("profilePic");
        var span = document.getElementsByClassName("close")[0];

        if (profilePic && modal && span) {
            // Open modal when clicking on profile picture
            profilePic.onclick = function() {
                console.log("Profile picture clicked");
                modal.style.display = "block";
            }

            // Close modal when clicking on X
            span.onclick = function() {
                modal.style.display = "none";
            }

            // Close modal when clicking outside of it
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            }
        }

        // Image preview before upload
        var profilePictureInput = document.getElementById('profile_picture');
        if (profilePictureInput) {
            profilePictureInput.addEventListener('change', function(event) {
                var reader = new FileReader();
                var preview = document.getElementById('preview');

                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }

                if (event.target.files[0]) {
                    reader.readAsDataURL(event.target.files[0]);
                }
            });
        }

        // Tab navigation
        const tabLinks = document.querySelectorAll('.card');
        const tabContents = document.querySelectorAll('.tab-content');

        console.log("Found tab links:", tabLinks.length);
        console.log("Found tab contents:", tabContents.length);

        // First, hide all tab contents
        tabContents.forEach(content => {
            content.style.display = 'none';
        });

        // Then set up click handlers
        tabLinks.forEach(tabLink => {
            tabLink.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent default behavior

                console.log("Tab clicked:", this.getAttribute('data-tab'));
                const tabId = this.getAttribute('data-tab');

                // Hide all tab contents
                tabContents.forEach(content => {
                    content.style.display = 'none';
                });

                // Show selected tab content
                const selectedTab = document.getElementById(tabId);
                if (selectedTab) {
                    selectedTab.style.display = 'block';
                    console.log("Displayed tab:", tabId);
                } else {
                    console.error("Tab content not found:", tabId);
                }

                // Active state for tab links
                tabLinks.forEach(link => {
                    link.classList.remove('active');
                });

                this.classList.add('active');
            });
        });

        // Course card expansion
        const courseCards = document.querySelectorAll('.course-card');
        courseCards.forEach(card => {
            const header = card.querySelector('.course-header');
            if (header) {
                header.addEventListener('click', function() {
                    card.classList.toggle('expanded');
                    const icon = card.querySelector('.toggle-icon');
                    if (icon) {
                        if (card.classList.contains('expanded')) {
                            icon.textContent = '▲';
                        } else {
                            icon.textContent = '▼';
                        }
                    }
                });
            }
        });

        // Grade course card expansion
        const gradeCourseCards = document.querySelectorAll('.grade-course-card');
        gradeCourseCards.forEach(card => {
            const header = card.querySelector('.grade-course-header');
            if (header) {
                header.addEventListener('click', function() {
                    card.classList.toggle('expanded');
                });
            }
        });

        // Notification reply functionality
        const replyButtons = document.querySelectorAll('.reply-button');
        replyButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent event bubbling to notification item
                const notificationId = this.getAttribute('data-notification-id');
                const notificationItem = document.querySelector(
                    `.notification-item[data-notification-id="${notificationId}"]`);

                if (notificationItem) {
                    // Toggle replying class
                    notificationItem.classList.toggle('replying');

                    // Update button text
                    if (notificationItem.classList.contains('replying')) {
                        this.textContent = 'Cancel';
                    } else {
                        this.textContent = 'Reply';
                    }
                }
            });
        });

        // Mark notifications as read when clicked
        const notifications = document.querySelectorAll('.notification-item');
        notifications.forEach(notification => {
            notification.addEventListener('click', function(e) {
                // Don't mark as read if clicking reply button or within reply form
                if (e.target.classList.contains('reply-button') || e.target.closest(
                        '.reply-form')) {
                    return;
                }

                const notificationId = this.getAttribute('data-notification-id');

                // Only send AJAX request if notification is unread
                if (this.classList.contains('unread')) {
                    // Remove unread class
                    this.classList.remove('unread');

                    // Send AJAX request to mark as read
                    fetch('mark_notification_read.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'notification_id=' + notificationId
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Update notification badge count
                                const badge = document.querySelector('.notification-badge');
                                if (badge) {
                                    const currentCount = parseInt(badge.textContent);
                                    if (currentCount > 1) {
                                        badge.textContent = currentCount - 1;
                                    } else {
                                        badge.remove();
                                    }
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error marking notification as read:', error);
                        });
                }
            });
        });
    });

    // Schedule handling functions
    // Schedule handling functions
// Complete replacement for your schedule JavaScript

document.addEventListener('DOMContentLoaded', function() {
    console.log("DOM fully loaded with updated schedule code");
    
    // Initialize student schedule if not exists
    if (typeof window.studentSchedule === 'undefined') {
        window.studentSchedule = [];
    }
    
    // Variables to track current week
    let currentDate = new Date();
    let currentWeekStart = getWeekStart(currentDate);
    
    // Initialize schedule display
    updateWeekDisplay(currentWeekStart);
    renderScheduleGrid(currentWeekStart);
    
    // Add event listeners for week navigation
    const prevWeekBtn = document.getElementById('prev-week');
    const nextWeekBtn = document.getElementById('next-week');
    
    if (prevWeekBtn && nextWeekBtn) {
        prevWeekBtn.addEventListener('click', function() {
            // Go to previous week
            currentWeekStart.setDate(currentWeekStart.getDate() - 7);
            updateWeekDisplay(currentWeekStart);
            renderScheduleGrid(currentWeekStart);
        });
        
        nextWeekBtn.addEventListener('click', function() {
            // Go to next week
            currentWeekStart.setDate(currentWeekStart.getDate() + 7);
            updateWeekDisplay(currentWeekStart);
            renderScheduleGrid(currentWeekStart);
        });
    }
    
    // Initialize personal event form
    const personalEventForm = document.getElementById('add-personal-event-form');
    if (personalEventForm) {
        // Set default date to today
        const eventDateInput = document.getElementById('event_date');
        if (eventDateInput) {
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            eventDateInput.value = `${year}-${month}-${day}`;
        }
        
        // Form submission handling - LOCAL VERSION
        personalEventForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form values directly
            const eventTitle = document.getElementById('event_title').value;
            const eventType = document.getElementById('event_type').value;
            const eventDate = document.getElementById('event_date').value;
            const eventStartTime = document.getElementById('event_start_time').value;
            const eventEndTime = document.getElementById('event_end_time').value;
            const eventDescription = document.getElementById('event_description').value;
            
            // Validate time inputs (make sure end time is after start time)
            if (eventStartTime >= eventEndTime) {
                // Show error message
                const errorMessage = document.createElement('div');
                errorMessage.className = 'error-message';
                errorMessage.textContent = 'End time must be after start time.';
                personalEventForm.parentNode.insertBefore(errorMessage, personalEventForm);
                
                // Remove error message after 3 seconds
                setTimeout(() => {
                    errorMessage.remove();
                }, 3000);
                
                return; // Stop the form submission
            }
            
            // Generate a unique ID
            const eventId = 'local_' + Date.now();
            
            // Map event type values to display labels
            let displayType = 'Other';
            switch(eventType) {
                case 'study': displayType = 'Lecture'; break;
                case 'meeting': displayType = 'Laboratory'; break;
                case 'activity': displayType = 'Exam'; break;
                case 'other': displayType = 'Other'; break;
            }
            
            // Create the new event object
            const newEvent = {
                id: eventId,
                title: eventTitle,
                type: displayType,
                date: eventDate,
                start_time: eventStartTime,
                end_time: eventEndTime,
                description: eventDescription,
                is_personal: true
            };
            
            // Add to studentSchedule array
            window.studentSchedule.push(newEvent);
            
            console.log("Added new event:", newEvent);
            console.log("Updated studentSchedule:", window.studentSchedule);
            
            // Reset form
            personalEventForm.reset();
            
            // Set date field to today again
            if (eventDateInput) {
                const today = new Date();
                const year = today.getFullYear();
                const month = String(today.getMonth() + 1).padStart(2, '0');
                const day = String(today.getDate()).padStart(2, '0');
                eventDateInput.value = `${year}-${month}-${day}`;
            }
            
            // Refresh the schedule
            renderScheduleGrid(currentWeekStart);
            
            // Show success message
            const successMessage = document.createElement('div');
            successMessage.className = 'success-message';
            successMessage.textContent = 'Personal event added successfully!';
            personalEventForm.parentNode.insertBefore(successMessage, personalEventForm);
            
            // Add some styling to the success message
            successMessage.style.backgroundColor = "#d4edda";
            successMessage.style.color = "#155724";
            successMessage.style.padding = "10px";
            successMessage.style.margin = "10px 0";
            successMessage.style.borderRadius = "4px";
            
            // Remove message after 3 seconds
            setTimeout(() => {
                successMessage.remove();
            }, 3000);
        });
    }
});

// Helper function to get the start of the week (Monday)
function getWeekStart(date) {
    const day = date.getDay(); // 0 = Sunday, 1 = Monday, ...
    // Adjust for Monday as first day of week
    const diff = date.getDate() - day + (day === 0 ? -6 : 1);
    return new Date(date.setDate(diff));
}

// Update the week display text
function updateWeekDisplay(weekStart) {
    const weekEnd = new Date(weekStart);
    weekEnd.setDate(weekStart.getDate() + 6); // End of week (Sunday)
    
    const weekDisplay = document.getElementById('current-week-display');
    if (weekDisplay) {
        // Format dates
        const startMonth = weekStart.toLocaleString('default', { month: 'short' });
        const endMonth = weekEnd.toLocaleString('default', { month: 'short' });
        
        if (startMonth === endMonth) {
            weekDisplay.textContent = `${startMonth} ${weekStart.getDate()} - ${weekEnd.getDate()}, ${weekStart.getFullYear()}`;
        } else {
            weekDisplay.textContent = `${startMonth} ${weekStart.getDate()} - ${endMonth} ${weekEnd.getDate()}, ${weekStart.getFullYear()}`;
        }
    }
}

// Render the schedule grid for a specific week
// Initialize schedule data structure
let studentSchedule = [];
let currentWeekStart = new Date();
currentWeekStart.setDate(currentWeekStart.getDate() - currentWeekStart.getDay() + 1); // Set to Monday
let uniqueEventId = 1;

// DOM elements
const scheduleGridContainer = document.getElementById('schedule-grid-container');
const currentWeekDisplay = document.getElementById('current-week-display');
const prevWeekBtn = document.getElementById('prev-week');
const nextWeekBtn = document.getElementById('next-week');
const addEventForm = document.getElementById('add-personal-event-form');
const messageContainer = document.getElementById('message-container');

// Days of the week
const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Event types and colors
const eventTypes = {
    'lecture': { class: 'lecture', color: '#4ade80' },
    'lab': { class: 'lab', color: '#60a5fa' },
    'exam': { class: 'exam', color: '#f97316' },
    'other': { class: 'other', color: '#a78bfa' },
    'personal': { class: 'personal', color: '#ec4899' }
};

// Initialize the schedule grid
function initializeScheduleGrid() {
    scheduleGridContainer.innerHTML = '';
    
    // Create grid cells
    for (let hour = 0; hour < 17; hour++) { // 6:00 AM to 10:00 PM
        for (let day = 0; day < 6; day++) { // Monday to Saturday
            const cell = document.createElement('div');
            cell.className = 'schedule-cell';
            cell.dataset.day = day;
            cell.dataset.hour = hour;
            
            // Add data attributes for positioning events
            cell.dataset.dayName = days[day];
            cell.dataset.timeSlot = hour + 6; // Starting from 6 AM
            
            scheduleGridContainer.appendChild(cell);
        }
    }
}

// Format date for display
function formatDate(date) {
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

// Update current week display
function updateWeekDisplay() {
    const weekEnd = new Date(currentWeekStart);
    weekEnd.setDate(weekEnd.getDate() + 5); // Saturday
    
    currentWeekDisplay.textContent = `${formatDate(currentWeekStart)} - ${formatDate(weekEnd)}`;
}

// Navigate to previous week
prevWeekBtn.addEventListener('click', () => {
    currentWeekStart.setDate(currentWeekStart.getDate() - 7);
    updateWeekDisplay();
    displaySchedule();
});

// Navigate to next week
nextWeekBtn.addEventListener('click', () => {
    currentWeekStart.setDate(currentWeekStart.getDate() + 7);
    updateWeekDisplay();
    displaySchedule();
});

// Calculate position for an event
function calculateEventPosition(day, startHour, endHour) {
    // Convert times to grid positions
    const startPosition = (startHour - 6) * 60; // Start from 6 AM
    const duration = (endHour - startHour) * 60;
    
    return {
        dayIndex: day,
        top: startPosition,
        height: duration
    };
}

// Convert time string (HH:MM) to decimal hours
function timeToDecimalHours(timeStr) {
    const [hours, minutes] = timeStr.split(':').map(Number);
    return hours + (minutes / 60);
}

// Create and display a schedule item
function createScheduleItem(event) {
    // Find the right day cell
    const dayIndex = days.findIndex(d => d === event.day);
    if (dayIndex === -1) return;
    
    // Calculate the decimal hour values
    const startHour = timeToDecimalHours(event.startTime);
    const endHour = timeToDecimalHours(event.endTime);
    
    // Skip events outside our display range (6 AM - 10 PM)
    if (startHour < 6 || startHour > 22 || endHour < 6 || endHour > 22) return;
    
    // Calculate position
    const position = calculateEventPosition(dayIndex, startHour, endHour);
    
    // Create the event element
    const eventElement = document.createElement('div');
    eventElement.className = `schedule-item ${event.type}`;
    eventElement.dataset.eventId = event.id;
    eventElement.style.position = 'absolute';
    eventElement.style.left = `${(position.dayIndex * (100 / 6))}%`;
    eventElement.style.width = `${100 / 6 - 0.5}%`;
    eventElement.style.top = `${position.top}px`;
    eventElement.style.height = `${position.height}px`;
    
    // Event content
    const titleElement = document.createElement('div');
    titleElement.className = 'item-title';
    titleElement.textContent = event.title;
    eventElement.appendChild(titleElement);
    
    const detailsElement = document.createElement('div');
    detailsElement.className = 'item-details';
    detailsElement.textContent = `${event.startTime} - ${event.endTime}`;
    eventElement.appendChild(detailsElement);
    
    // Add delete button
    const deleteBtn = document.createElement('button');
    deleteBtn.className = 'delete-btn';
    deleteBtn.innerHTML = '×';
    deleteBtn.title = 'Delete event';
    deleteBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        deleteEvent(event.id);
    });
    eventElement.appendChild(deleteBtn);
    
    // Append to the schedule body
    document.querySelector('.schedule-body').appendChild(eventElement);
}

// Display the schedule for the current week
function displaySchedule() {
    // Clear existing events
    document.querySelectorAll('.schedule-item').forEach(item => item.remove());
    
    // For each event, check if it's in the current week and display it
    studentSchedule.forEach(event => {
        const eventDate = new Date(event.date);
        const weekStart = new Date(currentWeekStart);
        const weekEnd = new Date(currentWeekStart);
        weekEnd.setDate(weekEnd.getDate() + 5); // Saturday
        
        if (eventDate >= weekStart && eventDate <= weekEnd) {
            // Set the day based on the date
            const dayOfWeek = eventDate.getDay(); // 0 = Sunday, 1 = Monday, etc.
            event.day = days[dayOfWeek === 0 ? 6 : dayOfWeek - 1]; // Adjust for our Monday-Saturday format
            
            createScheduleItem(event);
        }
    });
}

// Delete an event
function deleteEvent(eventId) {
    if (confirm('Are you sure you want to delete this event?')) {
        const index = studentSchedule.findIndex(event => event.id === eventId);
        if (index !== -1) {
            studentSchedule.splice(index, 1);
            saveScheduleToLocalStorage();
            displaySchedule();
            showMessage('Event deleted successfully!', 'success');
        }
    }
}

// Show success/error message
function showMessage(message, type = 'success') {
    messageContainer.innerHTML = `<div class="${type}-message">${message}</div>`;
    
    // Auto-clear after 5 seconds
    setTimeout(() => {
        messageContainer.innerHTML = '';
    }, 5000);
}

// Save schedule to localStorage
function saveScheduleToLocalStorage() {
    localStorage.setItem('studentSchedule', JSON.stringify(studentSchedule));
    localStorage.setItem('lastEventId', uniqueEventId);
}

// Load schedule from localStorage
function loadScheduleFromLocalStorage() {
    const savedSchedule = localStorage.getItem('studentSchedule');
    const savedEventId = localStorage.getItem('lastEventId');
    
    if (savedSchedule) {
        studentSchedule = JSON.parse(savedSchedule);
    }
    
    if (savedEventId) {
        uniqueEventId = parseInt(savedEventId);
    }
}

// Add event form submission handler
addEventForm.addEventListener('submit', (e) => {
    e.preventDefault();
    
    const title = document.getElementById('event_title').value;
    const type = document.getElementById('event_type').value;
    const date = document.getElementById('event_date').value;
    const startTime = document.getElementById('event_start_time').value;
    const endTime = document.getElementById('event_end_time').value;
    const description = document.getElementById('event_description').value;
    
    // Validate end time is after start time
    if (endTime <= startTime) {
        showMessage('End time must be after start time!', 'error');
        return;
    }
    
    // Create event object
    const event = {
        id: uniqueEventId++,
        title,
        type,
        date,
        startTime,
        endTime,
        description
    };
    
    // Add to schedule
    studentSchedule.push(event);
    
    // Save and display
    saveScheduleToLocalStorage();
    displaySchedule();
    
    // Reset form
    addEventForm.reset();
    
    // Show success message
    showMessage('Event added successfully!', 'success');
});

// Initialize on page load
window.addEventListener('DOMContentLoaded', () => {
    initializeScheduleGrid();
    loadScheduleFromLocalStorage();
    updateWeekDisplay();
    displaySchedule();
    
    // Set default date to today
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    document.getElementById('event_date').value = `${yyyy}-${mm}-${dd}`;
});
    </script>
</body>

</html>