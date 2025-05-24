<?php
// Start the session (good practice to keep)
session_start();

// TEMPORARY FIX: Use a default student ID instead of requiring login
// Remove this when you implement proper user authentication
$student_id = 1; // Default student ID for testing

// Set the student ID in session for consistency
$_SESSION['user_id'] = $student_id;

// Database connection
$db_config = [
    'host' => 'localhost',
    'username' => 'your_db_username',
    'password' => 'your_db_password',
    'database' => 'your_database_name'
];

// Create connection
$conn = new mysqli(
    $db_config['host'],
    $db_config['username'],
    $db_config['password'],
    $db_config['database']
);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch personal events
$sql = "SELECT 
            id, 
            title, 
            event_type AS type, 
            event_date AS date, 
            start_time, 
            end_time, 
            description,
            1 AS is_personal
        FROM 
            personal_events 
        WHERE 
            student_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

$student_schedule = [];
while ($row = $result->fetch_assoc()) {
    // Format the date to YYYY-MM-DD for JavaScript
    $row['date'] = date('Y-m-d', strtotime($row['date']));
    
    // Add the event to the schedule array
    $student_schedule[] = $row;
}

// You might also want to fetch other types of events (courses, etc.) here
// and add them to the $student_schedule array

// Close the statement and connection
$stmt->close();
$conn->close();

// Note: The $student_schedule variable will be used in the schedule display page
// The schedule.js code will access this via the JSON encoded output
?>