<?php
// Database configuration and connection
$host = 'localhost';
$dbname = 'attendance_management_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // First, check if the admins table exists and get its structure
    $tableExists = false;
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'admins'");
        $tableExists = ($stmt->rowCount() > 0);
    } catch(PDOException $e) {
        // Table doesn't exist
    }

    if (!$tableExists) {
        // Create admins table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
            id INT PRIMARY KEY AUTO_INCREMENT,
            admin_id VARCHAR(20) UNIQUE NOT NULL,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'admin',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }

} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

session_start();
$error_message = '';
$success_message = '';

// Redirect if already logged in
if(isset($_SESSION['admin_id'])) {
    header("Location: admin_dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_id = trim($_POST['admin_id']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($admin_id) || empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = "All fields are required";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match";
    } else {
        try {
            // Check if admin ID already exists
            $check_stmt = $pdo->prepare("SELECT admin_id FROM admins WHERE admin_id = ?");
            $check_stmt->execute([$admin_id]);
            
            if ($check_stmt->rowCount() > 0) {
                $error_message = "Admin ID already exists";
            } else {
                // Check if email already exists
                $check_email = $pdo->prepare("SELECT email FROM admins WHERE email = ?");
                $check_email->execute([$email]);
                
                if ($check_email->rowCount() > 0) {
                    $error_message = "Email already registered";
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert new admin
                    $stmt = $pdo->prepare("INSERT INTO admins (admin_id, name, email, password) VALUES (?, ?, ?, ?)");
                    
                    if ($stmt->execute([$admin_id, $name, $email, $hashed_password])) {
                        $success_message = "Registration successful! Please login.";
                        
                        // Redirect to login after short delay
                        header("refresh:2;url=admin_login.php");
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
    <title>Admin Registration</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background-image: url('124155623.jpg'); /* Replace with your background image */
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            max-width: 450px;
            width: 90%;
            background: rgba(33, 41, 66, 0.85);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
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
            background: rgba(41, 98, 255, 0.7);
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
            background: rgba(41, 98, 255, 0.9);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(41, 98, 255, 0.4);
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
            color: rgba(41, 98, 255, 0.8);
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
        <h2>Admin Registration</h2>
        
        <?php if($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if($success_message): ?>
            <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group">
                <label>Admin ID</label>
                <input type="text" name="admin_id" required placeholder="Enter your admin ID"
                       value="<?php echo isset($_POST['admin_id']) ? htmlspecialchars($_POST['admin_id']) : ''; ?>">
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
            
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required placeholder="Confirm your password">
            </div>
            
            <button type="submit">Register as Admin</button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="admin_login.php">Login here</a>
        </div>
    </div>
</body>
</html>