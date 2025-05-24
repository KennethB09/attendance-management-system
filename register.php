<?php
require 'config.php';
$error_message = '';
$success_message = '';

// Fetch available sections
$sections = [];
try {
    $stmt = $pdo->query("SELECT id, section_name FROM sections");
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no sections exist, create a default one
    if (empty($sections)) {
        $pdo->exec("INSERT INTO sections (section_name, description, schedule, admin_id) 
                   VALUES ('Default Section', 'Initial section', 'Mon-Fri', 1)");
        $sections = [['id' => $pdo->lastInsertId(), 'section_name' => 'Default Section']];
    }
} catch(PDOException $e) {
    $error_message = "Failed to load sections: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = trim($_POST['student_id']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $section_id = (int)$_POST['section_id'];

    // Validation
    if (empty($student_id) || empty($name) || empty($email) || empty($password)) {
        $error_message = "All fields are required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format";
    } else {
        try {
            // Check if student ID exists
            $check_stmt = $pdo->prepare("SELECT student_id FROM students WHERE student_id = ?");
            $check_stmt->execute([$student_id]);
            
            if ($check_stmt->rowCount() > 0) {
                $error_message = "Student ID already exists";
            } else {
                // Check if email exists
                $check_email = $pdo->prepare("SELECT email FROM students WHERE email = ?");
                $check_email->execute([$email]);
                
                if ($check_email->rowCount() > 0) {
                    $error_message = "Email already registered";
                } else {
                    // Hash password and prepare data
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $qr_data = $student_id;
                    
                    // Insert student with section_id
                    $stmt = $pdo->prepare("INSERT INTO students 
                        (student_id, name, email, password, qr_code, section_id) 
                        VALUES (?, ?, ?, ?, ?, ?)");
                    
                    if ($stmt->execute([$student_id, $name, $email, $hashed_password, $qr_data, $section_id])) {
                        $success_message = "Registration successful!";
                        $_SESSION['student_id'] = $student_id;
                        $_SESSION['student_name'] = $name;
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
        <div class="logo">
            <h1>Student Registration</h1>
            <p>Create your new account</p>
        </div>

        <?php if(!empty($error_message)): ?>
            <div class="error-message">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <?php if(!empty($success_message)): ?>
            <div class="success-message">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group">
                <label>Student ID</label>
                <input 
                    type="text" 
                    name="student_id" 
                    required 
                    placeholder="Enter your student ID"
                    autocomplete="off"
                    value="<?php echo isset($_POST['student_id']) ? htmlspecialchars($_POST['student_id']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>Full Name</label>
                <input 
                    type="text" 
                    name="name" 
                    required 
                    placeholder="Enter your full name"
                    autocomplete="off"
                    value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>Email</label>
                <input 
                    type="email" 
                    name="email" 
                    required 
                    placeholder="Enter your email"
                    autocomplete="off"
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input 
                    type="password" 
                    name="password" 
                    required 
                    placeholder="Create a password">
            </div>
            
            <div class="form-group">
                <label>Section</label>
                <select name="section_id" required>
                    <?php foreach($sections as $section): ?>
                        <option value="<?= $section['id'] ?>">
                            <?= htmlspecialchars($section['section_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit">Register</button>
            
            <div class="links">
                <a href="login.php">Already have an account? Login here</a>
            </div>
        </form>

        <?php if(!empty($success_message)): ?>
        <div id="qrcode"></div>
        <script>
            // Generate QR code after successful registration
            new QRCode(document.getElementById('qrcode'), {
                text: "<?= $student_id ?>",
                width: 128,
                height: 128
            });
        </script>
        <?php endif; ?>
    </div>
</body>
</html>