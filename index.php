<?php
require_once 'config.php';  
?>
<!DOCTYPE html>
<html>
<head>
    <title>Student Registration</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <link rel="stylesheet" href="index.css">
    <!-- bro what do we about this shit-->
</head>
<body>
    <div class="container">
        <h2>Welcome to Student Registration</h2>
        <form id="registrationForm" method="post">
            <div class="form-group">
                <label>Student ID</label>
                <input type="text" name="student_id" required placeholder="Enter your student ID">
            </div>
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" required placeholder="Enter your full name">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required placeholder="Enter your email address">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Choose a strong password">
            </div>
            <button type="submit">Create Account</button>
        </form>
        <div id="qrcode"></div>
    </div>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $student_id = $_POST['student_id'];
        $name = $_POST['name'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $qr_data = $student_id;
        
        try {
            $stmt = $pdo->prepare("INSERT INTO students (student_id, name, email, password, qr_code) VALUES (?, ?, ?, ?, ?)");
            if($stmt->execute([$student_id, $name, $email, $password, $qr_data])) {
                echo "<script>
                    document.getElementById('qrcode').style.display = 'block';
                    document.querySelector('.success-message').style.display = 'block';
                    document.querySelector('.success-message').style.animation = 'slideIn 0.3s ease-out';
                    new QRCode(document.getElementById('qrcode'), {
                        text: '$qr_data',
                        width: 128,
                        height: 128,
                        colorDark: '#4361ee',
                        colorLight: '#ffffff',
                    });
                </script>";
            }
        } catch(PDOException $e) {
            echo "<div style='color: #dc2626; padding: 1rem; text-align: center; margin-top: 1rem;'>
                    Error: " . $e->getMessage() . "
                  </div>";
        }
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $student_id = $_POST['student_id'];
        $password = $_POST['password'];
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch();
            
            if ($student && password_verify($password, $student['password'])) {
                $_SESSION['student_id'] = $student['student_id'];
                $_SESSION['student_name'] = $student['name'];
                
                // Log successful login
                $log_stmt = $pdo->prepare("INSERT INTO login_logs (student_id, status) VALUES (?, 'success')");
                $log_stmt->execute([$student_id]);
                
                header("Location: student_dashboard.php");
                exit();
            } else {
                // Log failed login attempt
                $log_stmt = $pdo->prepare("INSERT INTO login_logs (student_id, status) VALUES (?, 'failed')");
                $log_stmt->execute([$student_id]);
                
                $error_message = "Invalid student ID or password.";
                header("Location: index.php?error=" . urlencode($error_message));
                exit();
            }
        } catch(PDOException $e) {
            $error_message = "Login error: " . $e->getMessage();
            header("Location: index.php?error=" . urlencode($error_message));
            exit();
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $admin_id = $_POST['admin_id'];
        $email = $_POST['email'];
        
        try {
            // Verify admin exists with matching email
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE admin_id = ? AND email = ?");
            $stmt->execute([$admin_id, $email]);
            $admin = $stmt->fetch();
            
            if ($admin) {
                // Generate temporary password
                $temp_password = bin2hex(random_bytes(8));
                $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
                
                // Update password in database
                $update_stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE admin_id = ?");
                $update_stmt->execute([$hashed_password, $admin_id]);
                
                // Email the temporary password (in production, use proper email service)
                $to = $email;
                $subject = "Password Reset";
                $message = "Your temporary password is: " . $temp_password;
                $headers = "From: noreply@yourdomain.com";
                
                mail($to, $subject, $message, $headers);
                
                header("Location: admin_login.php?success=Password reset instructions sent to your email");
                exit();
            } else {
                header("Location: admin_login.php?error=Invalid admin ID or email");
                exit();
            }
        } catch(PDOException $e) {
            header("Location: admin_login.php?error=An error occurred during password reset");
            exit();
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $admin_id = $_POST['admin_id'];
        $password = $_POST['password'];
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE admin_id = ?");
            $stmt->execute([$admin_id]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['admin_name'] = $admin['name'];
                
                // Log successful login
                $log_stmt = $pdo->prepare("INSERT INTO admin_login_logs (admin_id, status) VALUES (?, 'success')");
                $log_stmt->execute([$admin_id]);
                
                header("Location: admin_dashboard.php");
                exit();
            } else {
                // Log failed login attempt
                $log_stmt = $pdo->prepare("INSERT INTO admin_login_logs (admin_id, status) VALUES (?, 'failed')");
                $log_stmt->execute([$admin_id]);
                
                header("Location: admin_login.php?error=Invalid admin ID or password");
                exit();
            }
        } catch(PDOException $e) {
            header("Location: admin_login.php?error=Login error occurred");
            exit();
        }
    }
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const qrcodeElement = document.getElementById('qrcode');
            new QRCode(qrcodeElement, {
                text: '<?php echo $student_id; ?>',
                width: 128,
                height: 128
            });
            
            qrcodeElement.classList.add('show');
            
            const successMessage = document.createElement('div');
            successMessage.className = 'success-message';
            successMessage.textContent = 'Registration successful! Your QR code is ready.';
            qrcodeElement.after(successMessage);
            
            setTimeout(() => {
                successMessage.classList.add('show');
            }, 100);
        });
    </script>
    
</body>
</html>