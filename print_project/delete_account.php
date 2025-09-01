<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];
$enteredPassword = $_POST['confirm_password'] ?? '';

// Get actual password hash from DB
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ? AND email = ?");
$stmt->bind_param("is", $user_id, $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 1) {
    $stmt->bind_result($hashedPassword);
    $stmt->fetch();

    if (password_verify($enteredPassword, $hashedPassword)) {
        // Delete user
        $delete = $conn->prepare("DELETE FROM users WHERE id = ?");
        $delete->bind_param("i", $user_id);
        if ($delete->execute()) {
            session_destroy();
            echo "<script>alert('Account deleted successfully.'); window.location.href='registration.php';</script>";
            exit();
        } else {
            echo "<script>alert('Error deleting account.'); window.location.href='settings.php';</script>";
            exit();
        }
    } else {
        echo "<script>alert('Incorrect password.'); window.location.href='settings.php';</script>";
        exit();
    }
} else {
    echo "<script>alert('User not found.'); window.location.href='settings.php';</script>";
    exit();
}
?>
