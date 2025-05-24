<?php
require 'config.php'; // Include database connection file
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
    <link rel="stylesheet" href="register.css">
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