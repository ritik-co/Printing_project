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

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);

    if (!empty($email)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $_SESSION["reset_email"] = $email;
            header("Location: reset_password.php");
            exit;
        } else {
            $message = "No account found with this email.";
        }
    } else {
        $message = "Please enter your email.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
    <link rel="stylesheet" href="form.css">
</head>
<body>
    <div class="wrapper">
        <form action="" method="POST">
            <h1>Forgot Password</h1>
            <?php if (!empty($message)): ?>
                <p style="color:red; text-align:center;"><?php echo $message; ?></p>
            <?php endif; ?>
            <div class="input-box">
                <input type="email" name="email" placeholder="Enter your registered email" required>
            </div>
            <button type="submit" class="Btn">Next</button>
        </form>
    </div>
</body>
</html>
