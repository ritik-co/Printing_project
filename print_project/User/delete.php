<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file_name'])) {

    $fileName = $_POST['file_name'];
    $userId   = $_SESSION['user_id'];
    $redirect = $_POST['redirect'] ?? 'dashboard'; // 'dashboard' or 'history'

    $stmt = $conn->prepare("SELECT file_path FROM print_jobs WHERE file_name = ? AND user_id = ?");
    $stmt->bind_param("si", $fileName, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row      = $result->fetch_assoc();
        $filePath = $row['file_path'];

        // Delete physical file
        if (file_exists($filePath)) unlink($filePath);

        // Delete DB record
        $del = $conn->prepare("DELETE FROM print_jobs WHERE file_name = ? AND user_id = ?");
        $del->bind_param("si", $fileName, $userId);

        if ($del->execute()) {
            $_SESSION['msg'] = "\"" . htmlspecialchars($fileName) . "\" deleted successfully.";
        } else {
            $_SESSION['error'] = "Database error: Could not remove record.";
        }
        $del->close();
    } else {
        $_SESSION['error'] = "File not found or unauthorized.";
    }
    $stmt->close();
}

// Smart redirect back to where the user came from
$back = ($_POST['redirect'] ?? '') === 'history' ? 'history.php' : 'dashboard.php';
header("Location: " . $back);
exit();
