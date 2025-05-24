<?php
require_once 'config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<link href="style.css" rel="stylesheet">
</head>
<body>
    <div class="container">
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
