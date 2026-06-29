<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_password'])) {
    $enteredPassword = $_POST['confirm_password'];

    // Get actual password hash from DB
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? AND email = ?");
    $stmt->bind_param("is", $user_id, $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($hashedPassword);
        $stmt->fetch();
        $stmt->close(); // Close this stmt before opening a new one

        if (password_verify($enteredPassword, $hashedPassword)) {
            // Delete user - Note: If your DB has foreign keys (print_jobs), 
            // ensure they are set to ON DELETE CASCADE or delete them manually first.
            
            $delete = $conn->prepare("DELETE FROM users WHERE id = ?");
            $delete->bind_param("i", $user_id);
            
            if ($delete->execute()) {
                // Clear session and destroy
                $_SESSION = array();
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000,
                        $params["path"], $params["domain"],
                        $params["secure"], $params["httponly"]
                    );
                }
                session_destroy();

                echo "<script>alert('Account deleted successfully.'); window.location.href='registration.php';</script>";
                exit();
            } else {
                echo "<script>alert('Error deleting account. Please try again.'); window.location.href='settings.php';</script>";
                exit();
            }
        } else {
            echo "<script>alert('Incorrect password. Deletion cancelled.'); window.location.href='settings.php';</script>";
            exit();
        }
    } else {
        $stmt->close();
        echo "<script>alert('User record not found.'); window.location.href='settings.php';</script>";
        exit();
    }
} else {
    // If someone tries to access this file directly via GET
    header("Location: settings.php");
    exit();
}
?>