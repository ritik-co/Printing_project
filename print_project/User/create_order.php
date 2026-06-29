<?php
/**
 * create_order.php
 * Creates a Razorpay order server-side and returns the order_id + key to the frontend.
 * Called via AJAX (fetch) from upload.php and dashboard.php before opening Razorpay checkout.
 *
 * ─────────────────────────────────────────────────────────────
 *  SETUP (one-time):
 *  1. Go to https://dashboard.razorpay.com → Settings → API Keys
 *  2. Switch to LIVE mode, generate Live API keys
 *  3. Replace RAZORPAY_KEY_ID and RAZORPAY_KEY_SECRET below
 * ─────────────────────────────────────────────────────────────
 */

session_start();
require_once 'db.php';

header('Content-Type: application/json');

// ── Auth guard ──
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorised. Please log in.']);
    exit();
}

// ─────────────────────────────────────────────────────────────
//  ★ REPLACE THESE WITH YOUR LIVE RAZORPAY CREDENTIALS ★
//  Live Key ID  starts with: rzp_live_
//  Test Key ID  starts with: rzp_test_   (for testing only)
// ─────────────────────────────────────────────────────────────
define('RAZORPAY_KEY_ID',     'rzp_test_SXjifjycniOQmP');   // ← Your Live Key ID
define('RAZORPAY_KEY_SECRET', 'aK5Ub8JmnuOgnX619Vggmlvh');    // ← Your Live Key Secret

// ── Read input ──
$input  = json_decode(file_get_contents('php://input'), true);
$job_id = isset($input['job_id']) ? (int)$input['job_id'] : 0;

if ($job_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid job ID.']);
    exit();
}

// ── Fetch job cost from DB (never trust client-sent amount) ──
$stmt = $conn->prepare(
    "SELECT cost, payment_status FROM print_jobs WHERE id = ? AND user_id = ?"
);
$stmt->bind_param('ii', $job_id, $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($cost, $payment_status);
$stmt->fetch();
$stmt->close();

if ($cost === null) {
    echo json_encode(['success' => false, 'error' => 'Job not found or unauthorised.']);
    exit();
}

if ($payment_status === 'paid') {
    echo json_encode(['success' => false, 'error' => 'This job is already paid.']);
    exit();
}

// Razorpay amount is in paise (INR × 100), minimum ₹1 = 100 paise
$amount_paise = (int) round($cost * 100);
if ($amount_paise < 100) {
    echo json_encode(['success' => false, 'error' => 'Amount too small (min ₹1).']);
    exit();
}

// ── Create Razorpay order via REST API ──
$orderData = [
    'amount'          => $amount_paise,
    'currency'        => 'INR',
    'receipt'         => 'hyperprint_job_' . $job_id,
    'notes'           => [
        'job_id'   => $job_id,
        'user_id'  => $_SESSION['user_id'],
        'email'    => $_SESSION['email'] ?? '',
    ],
    'payment_capture' => 1,   // auto-capture
];

$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($orderData),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_USERPWD        => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT        => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    echo json_encode(['success' => false, 'error' => 'Network error: ' . $curlErr]);
    exit();
}

$order = json_decode($response, true);

if ($httpCode !== 200 || empty($order['id'])) {
    $errMsg = $order['error']['description'] ?? ('Razorpay error (HTTP ' . $httpCode . ')');
    echo json_encode(['success' => false, 'error' => $errMsg]);
    exit();
}

// ── Store Razorpay order_id in DB for later signature verification ──
$upd = $conn->prepare("UPDATE print_jobs SET razorpay_order_id = ? WHERE id = ?");
$upd->bind_param('si', $order['id'], $job_id);
$upd->execute();
$upd->close();

echo json_encode([
    'success'          => true,
    'razorpay_key'     => RAZORPAY_KEY_ID,
    'razorpay_order_id'=> $order['id'],
    'amount'           => $amount_paise,
    'currency'         => 'INR',
]);
