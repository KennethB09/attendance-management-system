<?php
// Start the session to track login attempts
session_start();

// Initialize attempt counter if it doesn't exist
if (!isset($_SESSION['attempts'])) {
    $_SESSION['attempts'] = 0;
}

// Set the correct login credentials
$correct_username = "user";
$correct_password = "pass123";

// Initialize message variable
$message = "";

// Process the form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get username and password from the form
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Check if attempts exceeded
    if ($_SESSION['attempts'] >= 3) {
        $message = "Too many failed attempts. Please try again later.";
    }
    // Check if credentials are correct
    elseif ($username == $correct_username && $password == $correct_password) {
        $message = "Login successful!";
        $_SESSION['attempts'] = 0; // Reset attempts on success
    }
    // Handle incorrect login
    else {
        $_SESSION['attempts']++;
        $remaining = 3 - $_SESSION['attempts'];
        
        if ($remaining > 0) {
            $message = "Invalid username or password. $remaining attempts remaining.";
        } else {
            $message = "Too many failed attempts. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 300px;
        }
        h2 {
            text-align: center;
            color: #333;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px;
            margin: 8px 0;
            display: inline-block;
            border: 1px solid #ccc;
            box-sizing: border-box;
            border-radius: 4px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            margin: 8px 0;
            border: none;
            cursor: pointer;
            width: 100%;
            border-radius: 4px;
        }
        button:hover {
            opacity: 0.8;
        }
        .message {
            margin: 10px 0;
            padding: 10px;
            border-radius: 4px;
            text-align: center;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Login</h2>
        
        <?php if (!empty($message)): ?>
            <div class="message <?php echo (strpos($message, 'successful') !== false) ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($_SESSION['attempts'] < 3): ?>
            <form method="post" action="">
                <div>
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div>
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit">Login</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>