<?php
session_start();
$host = "localhost";
$db_user = "root";
$db_pass = "Ritik@150320";
$db_name = "print_system";

$conn = new mysqli($host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION["reset_email"])) {
    header("Location: forgot_password.php");
    exit;
}

$email = $_SESSION["reset_email"];
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = trim($_POST["new_password"]);
    $confirm_password = trim($_POST["confirm_password"]);

    if ($new_password !== $confirm_password) {
        $message = "Passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $message = "Password must be at least 6 characters.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashed_password, $email);
        if ($stmt->execute()) {
            unset($_SESSION["reset_email"]);
            $message = "Password successfully updated. <a href='login.php'>Login now</a>";
        } else {
            $message = "Failed to update password.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <link rel="stylesheet" href="form.css">
</head>
<body>
    <div class="wrapper">
        <form action="" method="POST">
            <h1>Set New Password</h1>
            <?php if (!empty($message)): ?>
                <p style="color:red; text-align:center;"><?php echo $message; ?></p>
            <?php endif; ?>
            <div class="input-box">
                <input type="password" name="new_password" placeholder="New Password" required>
            </div>
            <div class="input-box">
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            </div>
            <button type="submit" class="Btn">Update Password</button>
        </form>
    </div>
</body>
</html>
