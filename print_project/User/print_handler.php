<?php
/**
 * print_handler.php
 * Called after payment is verified to trigger the actual print.
 * Validates payment status, marks job as 'Printed', and confirms success.
 */

session_start();
require_once 'db.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// ── Auth guard ──
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorised. Please log in.']);
    exit();
}

$data   = json_decode(file_get_contents('php://input'), true);
$job_id = isset($data['job_id']) ? (int)$data['job_id'] : 0;

if ($job_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid job ID.']);
    exit();
}

// ── Fetch job — must belong to the logged-in user ──
$stmt = $conn->prepare(
    "SELECT payment_status, status, file_path, file_name
     FROM print_jobs
     WHERE id = ? AND user_id = ?"
);
$stmt->bind_param('ii', $job_id, $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($payment_status, $status, $file_path, $file_name);
$stmt->fetch();
$stmt->close();

if ($payment_status === null) {
    echo json_encode(['success' => false, 'message' => 'Job not found or unauthorised.']);
    exit();
}

if ($payment_status !== 'paid') {
    echo json_encode(['success' => false, 'message' => 'Payment required before printing.']);
    exit();
}

// if ($status === 'Printed') {
//     echo json_encode(['success' => true, 'message' => 'Already printed.']);
//     exit();
// }

if ($status === 'done') {
    echo json_encode(['success' => true, 'message' => 'Already printed.']);
    exit();
}

// ── Mark as Printed in DB ──
$upd = $conn->prepare(
    "UPDATE print_jobs SET status = 'done', printed_at = NOW() WHERE id = ?"
);
$upd->bind_param('i', $job_id);

if (!$upd->execute()) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
    exit();
}
$upd->close();

// ── (Optional) Real printer integration hook ──
// Uncomment and customise if you want to send to a network printer via CUPS or similar:
//
// $escaped_path = escapeshellarg($file_path);
// $result = shell_exec("lp -d PrinterName $escaped_path 2>&1");
// if ($result === null) {
//     echo json_encode(['success' => false, 'message' => 'Printer command failed.']);
//     exit();
// }




////////    ========= for tesing print button code ////////==========
// $log = fopen(__DIR__ . "/print_test.txt", "a");

// fwrite(
//     $log,
//     date("Y-m-d H:i:s")
//     . " | Job ID: " . $job_id
//     . " | File Path: " . $file_path
//     . PHP_EOL
// );




// ===== REAL PRINTER CODE =====

// Location of SumatraPDF.exe
$sumatraPath = '"C:\\Users\\ritik\\AppData\\Local\\SumatraPDF\\SumatraPDF.exe"';

// Replace with your printer name
$printerName = "Microsoft Print to PDF";

// Escape file path safely
$escapedFile = escapeshellarg($file_path);

// Send PDF to printer
$command = $sumatraPath .
           ' -print-to "' . $printerName . '" ' .
           $escapedFile;

// Execute command
shell_exec($command);







fclose($log);

echo json_encode([
    'success'   => true,
    'message'   => 'Print job sent successfully.',
    'job_id'    => $job_id,
    'file_name' => $file_name,
]);
