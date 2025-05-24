<?php
// Start the session (good practice to keep)
session_start();

// Set headers for JSON response
header('Content-Type: application/json');

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
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit;
}

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form inputs
    $errors = [];
    
    // Check required fields
    $required_fields = ['event_title', 'event_type', 'event_date', 'event_start_time', 'event_end_time'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
    }
    
    // If there are validation errors
    if (!empty($errors)) {
        echo json_encode([
            'success' => false,
            'errors' => $errors
        ]);
        exit;
    }
    
    // Sanitize inputs
    $title = $conn->real_escape_string($_POST['event_title']);
    $type = $conn->real_escape_string($_POST['event_type']);
    $date = $conn->real_escape_string($_POST['event_date']);
    $start_time = $conn->real_escape_string($_POST['event_start_time']);
    $end_time = $conn->real_escape_string($_POST['event_end_time']);
    $description = isset($_POST['event_description']) ? $conn->real_escape_string($_POST['event_description']) : '';
    
    // Check if end time is after start time
    if ($start_time >= $end_time) {
        echo json_encode([
            'success' => false,
            'error' => 'End time must be after start time.'
        ]);
        exit;
    }
    
    // Check for scheduling conflicts
    $conflict_sql = "SELECT * FROM personal_events 
                    WHERE student_id = ? 
                    AND event_date = ? 
                    AND ((start_time <= ? AND end_time > ?) 
                    OR (start_time < ? AND end_time >= ?) 
                    OR (start_time >= ? AND end_time <= ?))";
                    
    $stmt = $conn->prepare($conflict_sql);
    $stmt->bind_param("isssssss", $student_id, $date, $end_time, $start_time, $end_time, $start_time, $start_time, $end_time);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Time conflict with an existing schedule item.'
        ]);
        exit;
    }
    
    // Insert the new event
    $sql = "INSERT INTO personal_events (student_id, title, event_type, event_date, start_time, end_time, description) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssss", $student_id, $title, $type, $date, $start_time, $end_time, $description);
    
    if ($stmt->execute()) {
        $event_id = $conn->insert_id;
        
        echo json_encode([
            'success' => true,
            'message' => 'Event added successfully!',
            'event_id' => $event_id
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Error: ' . $stmt->error
        ]);
    }
    
    $stmt->close();
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method.'
    ]);
}

$conn->close();
?>