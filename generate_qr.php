<?php
require_once 'config.php';

// Default values
$student_id = '';
$qr_data = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $qr_data = $student_id;
    $section_id = 1; // Default section ID (assuming it exists)

    try {
        // Insert student with section_id
        $stmt = $pdo->prepare("INSERT INTO students 
                              (student_id, name, email, password, qr_code, section_id) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$student_id, $name, $email, $password, $qr_data, $section_id])) {
            // Success - will show QR code below
        }
    } catch(PDOException $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Generator</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            text-align: center;
        }
        #qrcode {
            margin: 20px auto;
            display: inline-block;
        }
        .error {
            color: red;
            margin: 20px 0;
        }
        .success {
            color: green;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($error): ?>
            <div class="error">Error: <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error): ?>
            <div class="success">Registration successful! Your QR code is ready.</div>
            <div id="qrcode"></div>
        <?php endif; ?>
    </div>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            new QRCode(document.getElementById('qrcode'), {
                text: "<?php echo $student_id; ?>",
                width: 200,
                height: 200,
                colorDark: "#4361ee",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>