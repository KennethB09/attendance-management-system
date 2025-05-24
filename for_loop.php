<html>
<head><title>Login System</title></head>
<body>
    <form action="" method="POST">
        Username: <input type="text" name="username"><br>
        Password: <input type="password" name="password"><br>
        <input type="submit" name="submit" value="Login">
    </form>

<?php
session_start();

$correct_username = "rylbuo";
$correct_password = "darkcrow09";

if (!isset($_SESSION['attempts'])) {
    $_SESSION['attempts'] = 0;
}

if (isset($_POST['submit'])) {
    $input_username = $_POST['username'];
    $input_password = $_POST['password'];
    $_SESSION['attempts']++; 

    while (true) {
        if ($_SESSION['attempts'] >= 3) {
            echo "Access denied. You have exceeded the maximum attempts.<br>";
            session_destroy(); 
            break;
        } elseif ($input_username === $correct_username && $input_password === $correct_password) {
            echo "Access granted. Welcome $input_username!<br>";
            $_SESSION['attempts'] = 0; 
            break;
        } else {
            $remaining_attempts = 3 - $_SESSION['attempts'];
            echo "Incorrect username or password. You have $remaining_attempts attempt(s) left.<br>";
            break;
        }
    }
}
?>
</body>
</html>