<?php
$host     = "localhost";
$user     = "root";
$password = "Ritik@150320";   // ← change to your real DB password
$dbname   = "print_system";

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    error_log("DB connection failed: " . $conn->connect_error);
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Database unavailable.']));
}

$conn->set_charset("utf8mb4");
