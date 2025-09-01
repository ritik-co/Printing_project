<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['email']) || !isset($_POST['file_name'])) {
    header("Location: dashboard.php");
    exit();
}

$email = $_SESSION['email'];
$file_name = $_POST['file_name'];

// Get file path from DB
$stmt = $conn->prepare("SELECT file_path FROM print_jobs WHERE email = ? AND file_name = ?");
$stmt->bind_param("ss", $email, $file_name);
$stmt->execute();
$stmt->bind_result($file_path);
if ($stmt->fetch()) {
    $stmt->close();
    // Fix: Ensure correct path
    $full_path = (strpos($file_path, 'uploads/') === 0) ? $file_path : "uploads/" . $file_path;
    if (!empty($full_path) && file_exists($full_path)) {
        unlink($full_path);
    }
    // Delete DB record
    $del = $conn->prepare("DELETE FROM print_jobs WHERE email = ? AND file_name = ?");
    $del->bind_param("ss", $email, $file_name);
    $del->execute();
    $del->close();
}
else {
    $stmt->close();
}

header("Location: dashboard.php");
exit();
?>