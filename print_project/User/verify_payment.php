<?php
/**
 * verify_payment.php
 * Called after Razorpay checkout succeeds on the frontend.
 *
 * Performs FULL server-side signature verification using HMAC-SHA256
 * before marking the job as paid — this prevents payment fraud/tampering.
 *
 * POST body (JSON):
 *   razorpay_payment_id  — from Razorpay handler response
 *   razorpay_order_id    — from Razorpay handler response
 *   razorpay_signature   — from Razorpay handler response
 *   job_id               — your print job ID
 */

session_start();
require_once 'db.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// ── Auth guard ──
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorised.']);
    exit();
}

// ── Same live secret used in create_order.php ──
// This file needs the KEY_SECRET only (not Key ID)
if (!defined('RAZORPAY_KEY_SECRET')) {
    define('RAZORPAY_KEY_SECRET', 'aK5Ub8JmnuOgnX619Vggmlvh'); // ← same as create_order.php
}

$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['razorpay_payment_id', 'razorpay_order_id', 'razorpay_signature', 'job_id'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
        exit();
    }
}

$payment_id = preg_replace('/[^a-zA-Z0-9_]/', '', $data['razorpay_payment_id']);
$order_id   = preg_replace('/[^a-zA-Z0-9_]/', '', $data['razorpay_order_id']);
$signature  = preg_replace('/[^a-fA-F0-9]/', '', $data['razorpay_signature']);
$job_id     = (int) $data['job_id'];

// ── Step 1: Verify HMAC-SHA256 signature ──
// Razorpay spec: signature = HMAC_SHA256(order_id + "|" + payment_id, key_secret)
$expected_signature = hash_hmac(
    'sha256',
    $order_id . '|' . $payment_id,
    RAZORPAY_KEY_SECRET
);

if (!hash_equals($expected_signature, strtolower($signature))) {
    // Log failed attempt (do not expose details)
    error_log("HyperPrint: Signature mismatch for job $job_id | payment $payment_id | order $order_id");
    echo json_encode(['success' => false, 'message' => 'Payment verification failed. Please contact support.']);
    exit();
}

// ── Step 2: Confirm job belongs to the logged-in user and order_id matches ──
$stmt = $conn->prepare(
    "SELECT id, payment_status, razorpay_order_id FROM print_jobs WHERE id = ? AND user_id = ?"
);
$stmt->bind_param('ii', $job_id, $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($db_id, $db_payment_status, $db_order_id);
$stmt->fetch();
$stmt->close();

if (!$db_id) {
    echo json_encode(['success' => false, 'message' => 'Job not found or unauthorised.']);
    exit();
}

if ($db_payment_status === 'paid') {
    // Idempotent — already paid, return success
    echo json_encode(['success' => true, 'message' => 'Already paid.', 'updated_id' => $job_id]);
    exit();
}

// Verify the order_id stored at creation time matches what Razorpay returned
if ($db_order_id && $db_order_id !== $order_id) {
    error_log("HyperPrint: Order ID mismatch for job $job_id — expected $db_order_id got $order_id");
    echo json_encode(['success' => false, 'message' => 'Order mismatch. Please contact support.']);
    exit();
}

// ── Step 3: Mark job as paid ──
$upd = $conn->prepare(
    "UPDATE print_jobs
     SET payment_status        = 'paid',
         razorpay_payment_id   = ?,
         razorpay_order_id     = ?,
         razorpay_signature    = ?
     WHERE id = ?"
);

// if ($upd) {
//     $upd->bind_param('sssi', $payment_id, $order_id, $signature, $job_id);
//     $upd->execute();
//     $upd->close();
// } else {
//     // Fallback: column might not exist yet — use minimal update
//     $upd2 = $conn->prepare("UPDATE print_jobs SET payment_status = 'paid' WHERE id = ?");
//     $upd2->bind_param('i', $job_id);
//     $upd2->execute();
//     $upd2->close();
// }

if ($upd) {

    $upd->bind_param('sssi', $payment_id, $order_id, $signature, $job_id);

    if (!$upd->execute()) {
        echo json_encode([
            'success' => false,
            'message' => 'SQL Update Failed: ' . $upd->error
        ]);
        exit();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Database updated successfully',
        'job_id' => $job_id
    ]);
    exit();

} else {
    echo json_encode([
        'success' => false,
        'message' => 'Prepare Failed: ' . $conn->error
    ]);
    exit();
}

echo json_encode([
    'success'            => true,
    'message'            => 'Payment verified successfully.',
    'updated_id'         => $job_id,
    'razorpay_payment_id'=> $payment_id,
]);
