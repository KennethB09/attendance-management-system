<?php
require_once 'config.php';

try {
    // Create tables
    $queries = [
        "CREATE TABLE IF NOT EXISTS admins (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        "CREATE TABLE IF NOT EXISTS sections (
            id INT PRIMARY KEY AUTO_INCREMENT,
            section_name VARCHAR(50) NOT NULL,
            description TEXT,
            schedule VARCHAR(100),
            admin_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES admins(id)
        )",

        "CREATE TABLE IF NOT EXISTS classes (
            id INT PRIMARY KEY AUTO_INCREMENT,
            class_name VARCHAR(100) NOT NULL,
            code VARCHAR(20) NOT NULL UNIQUE,
            description TEXT,
            start_time TIME,
            end_time TIME,
            days VARCHAR(20),
            late_threshold INT NOT NULL,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES admins(id)
        )",

        "CREATE TABLE IF NOT EXISTS students (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id VARCHAR(20) UNIQUE NOT NULL,
            name VARCHAR(100) NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100) NOT NULL,
            section_id INT NOT NULL,
            photo VARCHAR(255),
            qr_code TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (section_id) REFERENCES sections(id)
        )",

        " CREATE TABLE IF NOT EXISTS class_enrollments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            class_id INT NOT NULL,
            enrolled_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES students(id),
            FOREIGN KEY (class_id) REFERENCES classes(id)
        )",

        "CREATE TABLE IF NOT EXISTS announcements (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            class_id INT DEFAULT NULL,
            is_global TINYINT(1) DEFAULT 0,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES admins(id)
        )",

        "CREATE TABLE IF NOT EXISTS attendance (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id VARCHAR(20) NOT NULL,
            date DATE NOT NULL,
            status ENUM('present', 'absent', 'late') NOT NULL,
            check_in TIMESTAMP NULL DEFAULT NULL,
            check_out TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(student_id)
        )",

        // Create a default admin user
        "INSERT INTO admins (username, password, name) 
         VALUES ('admin', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'Administrator')
         ON DUPLICATE KEY UPDATE id=id",

        // Create a default section
        "INSERT INTO sections (section_name, description, schedule, admin_id)
         VALUES ('Default Section', 'Initial section for new students', 'Mon-Fri 8am-5pm', 1)
         ON DUPLICATE KEY UPDATE id=id"

    ];

    // Execute each query
    foreach ($queries as $query) {
        $pdo->exec($query);
    }

    echo "Database tables created successfully! Default admin credentials:<br>";
    echo "Username: admin<br>";
    echo "Password: admin123";
    echo "Default section created for student registration";
} catch (PDOException $e) {
    die("Setup failed: " . $e->getMessage());
}
