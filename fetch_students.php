<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['admin_id'])) {
    die(json_encode(['error' => 'Unauthorized']));
}

if (isset($_GET['id'])) {
    $studentId = mysqli_real_escape_string($conn, $_GET['id']);
    
    // Fetch basic student info
    $query = "SELECT s.*, sec.section_name 
              FROM students s 
              LEFT JOIN sections sec ON s.section_id = sec.id 
              WHERE s.student_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $studentId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $student = mysqli_fetch_assoc($result);
    
    // Fetch enrolled classes
    $classesQuery = "SELECT c.class_name 
                     FROM class_enrollments e 
                     JOIN classes c ON e.class_id = c.id 
                     WHERE e.user_id = (SELECT id FROM students WHERE student_id = ?)";
    $classesStmt = mysqli_prepare($conn, $classesQuery);
    mysqli_stmt_bind_param($classesStmt, "s", $studentId);
    mysqli_stmt_execute($classesStmt);
    $classesResult = mysqli_stmt_get_result($classesStmt);
    $classes = [];
    while ($row = mysqli_fetch_assoc($classesResult)) {
        $classes[] = $row['class_name'];
    }
    
    $student['classes'] = $classes;
    
    header('Content-Type: application/json');
    echo json_encode($student);
}
?>