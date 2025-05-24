<?php
// Error logging function
function log_error($message, $context = []) {
    $log_file = __DIR__ . '/error_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    
    $log_entry = "[{$timestamp}] {$message}" . 
                 (empty($context) ? '' : ' - Context: ' . json_encode($context)) . 
                 PHP_EOL;
    
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Test database connection and log any issues
function test_db_connection($db_config) {
    try {
        $conn = new mysqli(
            $db_config['host'],
            $db_config['username'],
            $db_config['password'],
            $db_config['database']
        );
        
        if ($conn->connect_error) {
            log_error("Database connection failed", [
                'error' => $conn->connect_error,
                'errno' => $conn->connect_errno
            ]);
            return false;
        }
        
        $conn->close();
        return true;
    } catch (Exception $e) {
        log_error("Exception when connecting to database", [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return false;
    }
}

// Function to check if personal_events table exists
function check_table_exists($db_config, $table_name) {
    try {
        $conn = new mysqli(
            $db_config['host'],
            $db_config['username'],
            $db_config['password'],
            $db_config['database']
        );
        
        if ($conn->connect_error) {
            return false;
        }
        
        $result = $conn->query("SHOW TABLES LIKE '{$table_name}'");
        $exists = $result->num_rows > 0;
        
        $conn->close();
        return $exists;
    } catch (Exception $e) {
        log_error("Exception when checking if table exists", [
            'table' => $table_name,
            'message' => $e->getMessage()
        ]);
        return false;
    }
}

// Database configuration
$db_config = [
    'host' => 'localhost',
    'username' => 'your_db_username',
    'password' => 'your_db_password',
    'database' => 'your_database_name'
];

// Set content type for JSON response
header('Content-Type: application/json');

// Run tests
$db_connection_ok = test_db_connection($db_config);
$table_exists = check_table_exists($db_config, 'personal_events');

// Return results
echo json_encode([
    'database_connection' => $db_connection_ok ? 'OK' : 'Failed',
    'table_exists' => $table_exists ? 'Yes' : 'No',
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'post_max_size' => ini_get('post_max_size'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'max_execution_time' => ini_get('max_execution_time')
]);