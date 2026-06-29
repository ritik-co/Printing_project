<?php
/**
 * db.php — Database connection
 *
 * ⚠️  SECURITY: Never commit real credentials to version control.
 *     Consider storing secrets in environment variables or a .env file
 *     that is excluded from your repo via .gitignore.
 *
 * Required MySQL schema:
 *   See schema.sql for full table definitions.
 */

$host     = "localhost";
$user     = "root";              // ← change to your DB username
$password = "Ritik@150320";  // ← change to your DB password
$dbname   = "print_system";

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    // In production: log error privately, show generic message to user
    error_log("DB connection failed: " . $conn->connect_error);
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Database unavailable. Please try again later.']));
}

$conn->set_charset("utf8mb4");
