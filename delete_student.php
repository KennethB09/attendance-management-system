<?php
//wla kasi delete_student.php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['admin_id'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

if (isset($_POST['id'])) {
    $studentId = mysqli_real_escape_string($conn, $_POST['id']);
    
    // First get the user ID
    $getIdQuery = "SELECT id FROM students WHERE student_id = ?";
    $getIdStmt = mysqli_prepare($conn, $getIdQuery);
    mysqli_stmt_bind_param($getIdStmt, "s", $studentId);
    mysqli_stmt_execute($getIdStmt);
    $result = mysqli_stmt_get_result($getIdStmt);
    $student = mysqli_fetch_assoc($result);
    
    if (!$student) {
        die(json_encode(['success' => false, 'message' => 'Student not found']));
    }
    
    $userId = $student['id'];
    
    // Begin transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Delete from enrollments
        mysqli_query($conn, "DELETE FROM class_enrollments WHERE user_id = $userId");
        
        // Delete from attendance
        mysqli_query($conn, "DELETE FROM attendance WHERE user_id = $userId");
        
        // Delete from grades
        mysqli_query($conn, "DELETE FROM grades WHERE user_id = $userId");
        
        // Finally delete the student
        mysqli_query($conn, "DELETE FROM students WHERE id = $userId");
        
        mysqli_commit($conn);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>