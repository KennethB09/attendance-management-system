<?php
// Database configuration and connection
$host = 'localhost';
$dbname = 'attendance_management_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // First, check if the students table exists and get its structure
    $tableExists = false;
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'students'");
        $tableExists = ($stmt->rowCount() > 0);
    } catch(PDOException $e) {
        // Table doesn't exist
    }

    if ($tableExists) {
        // Check if name column exists
        $nameColumnExists = false;
        try {
            $stmt = $pdo->query("DESCRIBE students");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $nameColumnExists = in_array('name', $columns);
        } catch(PDOException $e) {
            // Cannot describe table
        }
        
        // Add name column if it doesn't exist
        if (!$nameColumnExists) {
            try {
                $pdo->exec("ALTER TABLE students ADD COLUMN name VARCHAR(100) NOT NULL AFTER student_id");
            } catch(PDOException $e) {
                die("Failed to add name column: " . $e->getMessage());
            }
        }
    } else {
        // Create students table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS students (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id VARCHAR(20) UNIQUE NOT NULL,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            qr_code TEXT NOT NULL
        )");
        
        // Create attendance table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS attendance (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id VARCHAR(50) NOT NULL,
            check_in DATETIME,
            check_out DATETIME,
            date DATE,
            FOREIGN KEY (student_id) REFERENCES students(student_id)
        )");
    }

} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

session_start();
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = trim($_POST['student_id']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Basic validation
    if (empty($student_id) || empty($name) || empty($email) || empty($password)) {
        $error_message = "All fields are required";
    } else {
        try {
            // Check if student ID already exists
            $check_stmt = $pdo->prepare("SELECT student_id FROM students WHERE student_id = ?");
            $check_stmt->execute([$student_id]);
            
            if ($check_stmt->rowCount() > 0) {
                $error_message = "Student ID already exists";
            } else {
                // Check if email already exists
                $check_email = $pdo->prepare("SELECT email FROM students WHERE email = ?");
                $check_email->execute([$email]);
                
                if ($check_email->rowCount() > 0) {
                    $error_message = "Email already registered";
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Generate QR code data (using student ID)
                    $qr_data = $student_id;
                    
                    // Insert new student
                    $stmt = $pdo->prepare("INSERT INTO students (student_id, name, email, password, qr_code) VALUES (?, ?, ?, ?, ?)");
                    
                    if ($stmt->execute([$student_id, $name, $email, $hashed_password, $qr_data])) {
                        $success_message = "Registration successful! Please login.";
                        
                        // Optional: Automatically log in the user
                        $_SESSION['student_id'] = $student_id;
                        $_SESSION['student_name'] = $name;
                        
                        // Redirect to dashboard after short delay
                        header("refresh:2;url=dashboard.php");
                    }
                }
            }
        } catch(PDOException $e) {
            $error_message = "Registration failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background-image: url('124155623.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            max-width: 400px;
            width: 90%;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        h2 {
            color: #ffffff;
            text-align: center;
            margin-bottom: 1.5rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #ffffff;
            font-weight: 500;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
        }

        input {
            width: 100%;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            font-size: 1rem;
            color: #ffffff;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.1);
        }

        button {
            width: 100%;
            padding: 0.75rem;
            background: rgba(67, 97, 238, 0.6);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
        }

        button:hover {
            background: rgba(67, 97, 238, 0.8);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
        }

        .error-message {
            background: rgba(239, 68, 68, 0.2);
            color: #ffffff;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .success-message {
            background: rgba(16, 185, 129, 0.2);
            color: #ffffff;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        #qrcode {
            text-align: center;
            margin-top: 1.5rem;
            display: none;
            background: white;
            padding: 1rem;
            border-radius: 8px;
            max-width: 150px;
            margin-left: auto;
            margin-right: auto;
        }

        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            color: #ffffff;
        }

        .login-link a {
            color: #ffffff;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .login-link a:hover {
            color: rgba(67, 97, 238, 0.8);
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .container {
                width: 95%;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Student Registration</h2>
        
        <?php if($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if($success_message): ?>
            <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group">
                <label>Student ID</label>
                <input type="text" name="student_id" required placeholder="Enter your student ID"
                       value="<?php echo isset($_POST['student_id']) ? htmlspecialchars($_POST['student_id']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" required placeholder="Enter your full name"
                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required placeholder="Enter your email address"
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Choose a strong password">
            </div>
            
            <button type="submit">Register</button>
        </form>

        <div id="qrcode"></div>
        
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>

    <?php if($success_message): ?>
    <script>
        // Generate QR code after successful registration
        var qrcodeDiv = document.getElementById('qrcode');
        qrcodeDiv.style.display = 'block';
        new QRCode(qrcodeDiv, {
            text: "<?php echo $student_id; ?>",
            width: 128,
            height: 128
        });
    </script>
    <?php endif; ?>
</body>
</html>