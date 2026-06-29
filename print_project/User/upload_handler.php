<?php
/**
 * upload_handler.php
 * AJAX endpoint — receives the file + print config, saves to DB, returns JSON.
 * Called by upload.php via fetch().
 */
session_start();
require_once 'db.php';

header('Content-Type: application/json');

// ── Auth guard ──
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false, 'error'=>'Unauthorised. Please log in.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['document'])) {
    echo json_encode(['success'=>false, 'error'=>'Invalid request.']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$email   = $_SESSION['email'];
$file    = $_FILES['document'];

// ── Validation ──
$allowed  = ['pdf','jpg','jpeg','png','doc','docx'];
$ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$maxSize  = 20 * 1024 * 1024; // 20 MB

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success'=>false, 'error'=>'Upload error code: '.$file['error']]);
    exit();
}
if (!in_array($ext, $allowed, true)) {
    echo json_encode(['success'=>false, 'error'=>'File type not allowed. Use PDF, JPG, PNG, DOC or DOCX.']);
    exit();
}
if ($file['size'] > $maxSize) {
    echo json_encode(['success'=>false, 'error'=>'File exceeds 20 MB limit.']);
    exit();
}

// ── Save file ──
$upload_dir = __DIR__ . '/uploads/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

// Sanitise original name to prevent path traversal
$safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
$filename  = time() . '_' . $safe_name;
$filepath  = $upload_dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode(['success'=>false, 'error'=>'Could not save file. Check server permissions.']);
    exit();
}

// ── Page count (PDF only) ──
$page_count = 1;
if ($ext === 'pdf') {
    $content = @file_get_contents($filepath);
    if ($content !== false) {
        preg_match_all('/\/Type\s*\/Page[^s]/s', $content, $m);
        $page_count = max(1, count($m[0]));
    }
}

// ── Print config ──
$print_type  = in_array($_POST['print_type']  ?? '', ['bw','color'],          true) ? $_POST['print_type']  : 'bw';
$print_sides = in_array($_POST['print_sides'] ?? '', ['single','double'],      true) ? $_POST['print_sides'] : 'single';
$copies      = max(1, min(99, (int)($_POST['copies'] ?? 1)));

$price_map = [
    'bw'    => ['single'=>3.00, 'double'=>2.00],
    'color' => ['single'=>10.00,'double'=>8.00],
];
$ppp  = $price_map[$print_type][$print_sides];
$cost = $page_count * $copies * $ppp;
$estimated_time = $page_count * 2; // seconds per page estimate

// ── Insert print job (payment_status = 'pending') ──
$stmt = $conn->prepare(
    "INSERT INTO print_jobs
        (user_id, email, file_name, file_path, cost, estimated_time, pages,
         status, payment_status, uploaded_at, print_type, copies, print_sides)
     VALUES
        (?, ?, ?, ?, ?, ?, ?, 'Pending', 'pending', NOW(), ?, ?, ?)"
);
$stmt->bind_param('isssdiiiss',
    $user_id, $email, $filename, $filepath, $cost,
    $estimated_time, $page_count, $print_type, $copies, $print_sides
);

if (!$stmt->execute()) {
    // Clean up orphan file
    if (file_exists($filepath)) unlink($filepath);
    echo json_encode(['success'=>false, 'error'=>'Database error: '.$stmt->error]);
    exit();
}

$job_id = $stmt->insert_id;
$stmt->close();

echo json_encode([
    'success'   => true,
    'job_id'    => $job_id,
    'file_name' => $filename,
    'pages'     => $page_count,
    'copies'    => $copies,
    'cost'      => $cost,
]);
