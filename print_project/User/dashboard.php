<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['email'])) {
    header("Location: login.php"); exit();
}

$username = htmlspecialchars($_SESSION['username']);
$email    = $_SESSION['email'];
$user_id  = (int)$_SESSION['user_id'];

// Flash messages
$flashMsg = $_SESSION['msg']   ?? '';
$flashErr = $_SESSION['error'] ?? '';
unset($_SESSION['msg'], $_SESSION['error']);

// Latest job from upload redirect
$latest_job_id = $_SESSION['latest_job_id'] ?? null;
unset($_SESSION['latest_job_id']);

// Stats
$totalPrints = $pendingJobs = $completedJobs = 0;
$s = $conn->prepare("SELECT COUNT(*), SUM(status='Pending'), SUM(status='done') FROM print_jobs WHERE email=?");
$s->bind_param("s", $email);
$s->execute();
$s->bind_result($totalPrints, $pendingJobs, $completedJobs);
$s->fetch(); $s->close();

// Recent jobs — include payment_status and printed_at
$history = [];
$h = $conn->prepare(
    "SELECT id, file_name, pages, status, payment_status, uploaded_at, copies, print_type, print_sides, cost
     FROM print_jobs WHERE email=? ORDER BY uploaded_at DESC LIMIT 5"
);
$h->bind_param("s", $email);
$h->execute();
$res = $h->get_result();
while ($row = $res->fetch_assoc()) $history[] = $row;
$h->close();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | HyperPrint</title>
    <script>(function(){ document.documentElement.setAttribute('data-theme', localStorage.getItem('hp_theme')||'light'); })();</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Razorpay SDK (loaded here so Pay Now works from dashboard) -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <style>
        :root{--primary:#0ea5e9;--primary-hover:#0284c7;--primary-soft:#f0f9ff;--sidebar-width:272px;--bg:#f1f5f9;--surface:#ffffff;--surface2:#f8fafc;--border:#e2e8f0;--text:#0f172a;--text-muted:#64748b;--text-faint:#94a3b8;--shadow-sm:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);--shadow-md:0 4px 16px rgba(0,0,0,.08);--shadow-lg:0 10px 40px rgba(0,0,0,.10);--radius-sm:10px;--radius-md:16px;--radius-lg:24px;}
        [data-theme="dark"]{--bg:#0c1220;--surface:#141e30;--surface2:#1a2540;--border:#1e3050;--text:#f0f6ff;--text-muted:#8eaac8;--text-faint:#4a6a8a;--shadow-sm:0 1px 3px rgba(0,0,0,.3);--shadow-md:0 4px 16px rgba(0,0,0,.35);--shadow-lg:0 10px 40px rgba(0,0,0,.4);--primary-soft:#0c2a40;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;transition:background .3s,color .3s;}
        #sidebar{width:var(--sidebar-width);position:fixed;left:0;top:0;height:100vh;background:var(--surface);border-right:1px solid var(--border);z-index:900;transition:transform .35s cubic-bezier(.4,0,.2,1),background .3s,border-color .3s;}
        .sidebar-inner{display:flex;flex-direction:column;height:100%;overflow:hidden;}
        .sidebar-logo{display:flex;align-items:center;gap:12px;padding:24px 20px 20px;border-bottom:1px solid var(--border);transition:border-color .3s;}
        .logo-icon{width:40px;height:40px;background:var(--primary);border-radius:12px;display:flex;align-items:center;justify-content:center;color:white;font-size:20px;box-shadow:0 4px 12px rgba(14,165,233,.35);flex-shrink:0;}
        .logo-text{font-size:1.1rem;font-weight:800;color:var(--text);letter-spacing:-.02em;transition:color .3s;}
        .sidebar-nav{flex:1;padding:16px 12px;display:flex;flex-direction:column;gap:4px;overflow-y:auto;}
        .nav-item{display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:var(--radius-sm);color:var(--text-muted);text-decoration:none;font-weight:600;font-size:.875rem;transition:all .18s ease;}
        .nav-item i{font-size:1.2rem;flex-shrink:0;}
        .nav-item:hover{background:var(--primary-soft);color:var(--primary);transform:translateX(2px);}
        .nav-item.active{background:var(--primary);color:white;box-shadow:0 4px 12px rgba(14,165,233,.35);}
        .sidebar-bottom{padding:12px 12px 20px;border-top:1px solid var(--border);display:flex;flex-direction:column;gap:8px;transition:border-color .3s;}
        .theme-toggle-btn{display:flex;align-items:center;gap:10px;width:100%;padding:10px 14px;background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text-muted);font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;transition:all .2s;}
        .theme-toggle-btn:hover{background:var(--primary-soft);color:var(--primary);border-color:var(--primary);}
        .logout-btn{display:flex;align-items:center;gap:10px;width:100%;padding:10px 14px;background:#fef2f2;border:1px solid #fecaca;border-radius:var(--radius-sm);color:#dc2626;font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;transition:all .2s;}
        .logout-btn:hover{background:#fee2e2;}
        .main-wrapper{margin-left:var(--sidebar-width);padding:2rem;min-height:100vh;transition:margin-left .35s ease;}
        #overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:850;backdrop-filter:blur(2px);}
        #overlay.active{display:block;}
        #mobile-toggle{display:none;position:fixed;top:1rem;right:1rem;width:44px;height:44px;background:var(--surface);border:1px solid var(--border);border-radius:12px;align-items:center;justify-content:center;z-index:1000;cursor:pointer;box-shadow:var(--shadow-md);color:var(--text);transition:background .3s,border-color .3s;}
        .stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.5rem;box-shadow:var(--shadow-sm);transition:all .2s;position:relative;overflow:hidden;}
        .stat-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-md);}
        .stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;}
        .stat-card.sky::before{background:var(--primary);}
        .stat-card.amber::before{background:#f59e0b;}
        .stat-card.green::before{background:#22c55e;}
        .stat-value{font-size:2rem;font-weight:800;letter-spacing:-.04em;margin-top:4px;}
        .stat-label{font-size:.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;}
        .stat-icon{position:absolute;right:1.25rem;top:50%;transform:translateY(-50%);font-size:2.8rem;opacity:.07;}
        .card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);transition:background .3s,border-color .3s;overflow:hidden;}
        table{width:100%;border-collapse:collapse;}
        th{background:var(--surface2);color:var(--text-faint);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;padding:14px 16px;text-align:left;border-bottom:1px solid var(--border);}
        td{padding:12px 16px;border-bottom:1px solid var(--border);font-size:.875rem;}
        tr:last-child td{border-bottom:none;}
        tbody tr{transition:background .15s;}
        tbody tr:hover{background:var(--surface2);}
        .pill{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:9999px;font-size:.72rem;font-weight:700;}
        .pill-pending{background:#fef3c7;color:#92400e;}
        .pill-printed,.pill-completed{background:#dcfce7;color:#166534;}
        .pill-failed{background:#fee2e2;color:#991b1b;}
        .alert{display:flex;align-items:center;gap:10px;padding:13px 16px;border-radius:var(--radius-sm);font-size:.875rem;font-weight:600;margin-bottom:1.5rem;}
        .alert-error{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;}
        .alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#16a34a;}
        .btn{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:var(--radius-sm);font-family:inherit;font-size:.8rem;font-weight:700;cursor:pointer;border:none;transition:all .2s;text-decoration:none;white-space:nowrap;}
        .btn-primary{background:var(--primary);color:white;box-shadow:0 4px 12px rgba(14,165,233,.3);}
        .btn-primary:hover{background:var(--primary-hover);transform:translateY(-1px);}
        .btn-pay{background:#7c3aed;color:white;box-shadow:0 4px 12px rgba(124,58,237,.3);}
        .btn-pay:hover{background:#6d28d9;transform:translateY(-1px);}
        .btn-print{background:#16a34a;color:white;box-shadow:0 4px 12px rgba(22,163,74,.25);}
        .btn-print:hover{background:#15803d;transform:translateY(-1px);}
        .btn-icon{padding:7px;border-radius:8px;font-size:1.05rem;}
        .btn-icon-red{color:#dc2626;background:transparent;}
        .btn-icon-red:hover{background:#fef2f2;}
        .page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:2rem;}
        .page-header h1{font-size:1.6rem;font-weight:800;color:var(--text);letter-spacing:-.03em;}
        .page-header p{color:var(--text-muted);font-size:.9rem;margin-top:2px;}
        /* help pill */
        .help-pill{position:fixed;bottom:2rem;right:2rem;background:#7c3aed;color:white;padding:14px 26px;border-radius:9999px;display:flex;align-items:center;gap:10px;font-weight:700;font-size:.95rem;box-shadow:0 8px 28px rgba(124,58,237,.4);z-index:999;transition:.3s cubic-bezier(.175,.885,.32,1.275);text-decoration:none;}
        .help-pill:hover{transform:scale(1.05) translateY(-3px);background:#6d28d9;}
        /* spinner */
        .spinner{width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:white;border-radius:50%;animation:spin .7s linear infinite;display:inline-block;}
        @keyframes spin{to{transform:rotate(360deg)}}
        @media(max-width:1024px){#sidebar{transform:translateX(-100%);}#sidebar.open{transform:translateX(0);box-shadow:0 8px 32px rgba(0,0,0,.18);}.main-wrapper{margin-left:0!important;padding:1rem;padding-top:4.5rem;}#mobile-toggle{display:flex!important;}}
        @media(max-width:640px){.stat-grid{grid-template-columns:1fr!important;}.help-pill{position:relative;bottom:auto;right:auto;margin:10px auto 20px auto;width:max-content;}}
    </style>
</head>
<body>

<!-- <a href="support.php" class="help-pill">
    <i class='bx bxs-help-circle' style="font-size:1.3rem;"></i>
    <span>24*7 Help</span>
</a> -->

<div id="sidebar">
    <aside class="sidebar-inner">
        <div class="sidebar-logo">
            <div class="logo-icon"><i class='bx bxs-printer'></i></div>
            <span class="logo-text">HyperPrint</span>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item active"><i class='bx bxs-grid-alt'></i><span>Dashboard</span></a>
            <a href="upload.php"    class="nav-item"><i class='bx bxs-cloud-upload'></i><span>Upload File</span></a>
            <a href="history.php"   class="nav-item"><i class='bx bxs-time-five'></i><span>Print History</span></a>
            <a href="settings.php"  class="nav-item"><i class='bx bxs-cog'></i><span>Settings</span></a>
        </nav>
        <div class="sidebar-bottom">
            <button class="theme-toggle-btn" onclick="toggleTheme()">
                <i class='bx bx-moon' id="themeIcon"></i>
                <span id="themeLabel">Dark Mode</span>
            </button>
            <form action="logout.php" method="POST" style="margin:0;">
                <button type="submit" class="logout-btn"><i class='bx bx-log-out'></i><span>Logout</span></button>
            </form>
        </div>
    </aside>
</div>
<div id="overlay"></div>

<main class="main-wrapper">

    <?php if ($flashMsg): ?>
        <div class="alert alert-success"><i class='bx bxs-check-circle'></i><?= htmlspecialchars($flashMsg) ?></div>
    <?php endif; ?>
    <?php if ($flashErr): ?>
        <div class="alert alert-error"><i class='bx bxs-error-circle'></i><?= htmlspecialchars($flashErr) ?></div>
    <?php endif; ?>

    <div class="page-header">
        <div>
            <h1>Hello, <?= $username ?> 👋</h1>
            <p>Here's what's happening with your prints today.</p>
        </div>
        <a href="upload.php" class="btn btn-primary" style="padding:10px 20px;font-size:.875rem;">
            <i class='bx bx-plus'></i> New Print Job
        </a>
    </div>

    <!-- Stats -->
    <div class="stat-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:1.25rem;margin-bottom:2rem;">
        <div class="stat-card sky">
            <div class="stat-label">Total Prints</div>
            <div class="stat-value" style="color:var(--primary);"><?= (int)$totalPrints ?></div>
            <i class='bx bxs-printer stat-icon'></i>
        </div>
        <div class="stat-card amber">
            <div class="stat-label">Pending</div>
            <div class="stat-value" style="color:#f59e0b;"><?= (int)($pendingJobs ?? 0) ?></div>
            <i class='bx bxs-time stat-icon'></i>
        </div>
        <div class="stat-card green">
            <div class="stat-label">Printed</div>
            <div class="stat-value" style="color:#22c55e;"><?= (int)($completedJobs ?? 0) ?></div>
            <i class='bx bxs-check-circle stat-icon'></i>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="card">
        <div style="padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);">
            <h2 style="font-size:1rem;font-weight:700;color:var(--text);">Recent Activity</h2>
            <a href="history.php" style="font-size:.8rem;font-weight:700;color:var(--primary);text-decoration:none;">View All →</a>
        </div>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Config</th>
                        <th>Status</th>
                        <th>Cost</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($history)): ?>
                    <tr><td colspan="5" style="text-align:center;padding:3rem;color:var(--text-faint);">
                        <i class='bx bx-printer' style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
                        No recent activity.
                        <a href="upload.php" style="color:var(--primary);font-weight:700;">Upload your first file →</a>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($history as $row):
                        $isPaid    = ($row['payment_status'] === 'paid');
                        $isPrinted = ($row['status'] === 'done');
                        $s = strtolower($row['status']);
                        $pillClass = $isPrinted ? 'pill-printed' : ($s==='failed'?'pill-failed':'pill-pending');
                        $dot       = $isPrinted ? '✓' : ($s==='failed'?'✗':'⏳');
                        // highlight the job just uploaded
                        $isLatest  = ($latest_job_id && (int)$row['id'] === (int)$latest_job_id);
                    ?>
                    <tr style="<?= $isLatest ? 'background:var(--primary-soft);' : '' ?>">
                        <td>
                            <div style="font-weight:700;color:var(--text);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($row['file_name']) ?>">
                                <?= htmlspecialchars($row['file_name']) ?>
                                <?php if ($isLatest): ?>
                                    <span style="font-size:.65rem;background:var(--primary);color:white;padding:2px 7px;border-radius:9999px;margin-left:6px;font-weight:700;">NEW</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:.75rem;color:var(--text-faint);margin-top:2px;"><?= date('d M, Y · H:i', strtotime($row['uploaded_at'])) ?></div>
                        </td>
                        <td>
                            <span style="background:var(--surface2);border:1px solid var(--border);color:var(--text-muted);font-size:.75rem;font-weight:600;padding:3px 9px;border-radius:6px;white-space:nowrap;">
                                <?= $row['pages'] ?>p · <?= $row['copies'] ?>c · <?= strtoupper($row['print_type']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="pill <?= $pillClass ?>"><?= $dot ?> <?= $row['status'] ?></span>
                            <?php if (!$isPaid && !$isPrinted): ?>
                                <div style="font-size:.7rem;color:#92400e;font-weight:700;margin-top:3px;">⚠ Unpaid</div>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight:700;color:var(--text);">₹<?= number_format($row['cost'], 2) ?></td>
                        <td>
                            <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">

                                <?php if (!$isPaid && !$isPrinted): ?>
                                    <!-- PAY NOW button -->
                                    <button
                                        class="btn btn-pay"
                                        onclick="startPayment(<?= $row['id'] ?>, <?= $row['cost'] ?>, '<?= htmlspecialchars($row['file_name'], ENT_QUOTES) ?>')"
                                        id="payBtn-<?= $row['id'] ?>">
                                        <i class='bx bx-credit-card'></i> Pay Now
                                    </button>

                                <?php elseif ($isPaid && !$isPrinted): ?>
                                    <!-- PRINT button — only shows after payment verified -->
                                    <button
                                        class="btn btn-print"
                                        onclick="doPrint(<?= $row['id'] ?>, this)"
                                        id="printBtn-<?= $row['id'] ?>">
                                        <i class='bx bx-printer'></i> Print
                                    </button>

                                <?php else: ?>
                                    <!-- Already printed -->
                                    <span style="font-size:.78rem;color:#16a34a;font-weight:700;">✓ Printed</span>
                                <?php endif; ?>

                                <!-- Delete -->
                                <form method="POST" action="delete.php"
                                      onsubmit="return confirm('Delete «<?= htmlspecialchars($row['file_name'], ENT_QUOTES) ?>»?');"
                                      style="margin:0;">
                                    <input type="hidden" name="file_name" value="<?= htmlspecialchars($row['file_name']) ?>">
                                    <input type="hidden" name="redirect" value="dashboard">
                                    <button type="submit" class="btn btn-icon btn-icon-red" title="Delete">
                                        <i class='bx bx-trash'></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<button id="mobile-toggle"><i class='bx bx-menu text-2xl' id="toggle-icon"></i></button>

<!-- ── PAYMENT MODAL OVERLAY ── -->
<div id="payModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:2000;backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:24px;padding:2rem;width:100%;max-width:420px;margin:1rem;box-shadow:0 24px 64px rgba(0,0,0,.25);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
            <h3 style="font-size:1.1rem;font-weight:800;color:var(--text);">Complete Payment</h3>
            <button onclick="closePayModal()" style="background:none;border:none;color:var(--text-faint);cursor:pointer;font-size:1.4rem;line-height:1;">✕</button>
        </div>

        <div style="background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:16px;margin-bottom:1.5rem;">
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                <span style="color:var(--text-muted);font-size:.875rem;">File</span>
                <strong id="modal-filename" style="font-size:.875rem;color:var(--text);max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding-top:10px;border-top:1px solid var(--border);">
                <span style="font-weight:700;">Amount Due</span>
                <span style="font-size:1.4rem;font-weight:800;color:#7c3aed;" id="modal-amount">₹0.00</span>
            </div>
        </div>

        <p style="font-size:.8rem;color:var(--text-muted);margin-bottom:1.25rem;text-align:center;">
            Choose your preferred payment method below
        </p>

        <!-- UPI / Payment options shown by Razorpay checkout -->
        <button id="modal-pay-btn" onclick="openRazorpay()"
            style="width:100%;padding:14px;background:#7c3aed;color:white;border:none;border-radius:var(--radius-md);font-family:inherit;font-size:1rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;transition:all .2s;box-shadow:0 4px 16px rgba(124,58,237,.35);">
            <i class='bx bx-credit-card' style="font-size:1.2rem;"></i>
            Pay with GPay / PhonePe / UPI / Card
        </button>
        <p style="text-align:center;font-size:.72rem;color:var(--text-faint);margin-top:.75rem;">
            🔒 Secured by Razorpay &nbsp;·&nbsp; UPI · Cards · Wallets · NetBanking
        </p>
    </div>
</div>

<script>
let _activeJobId = null;
let _activeCost  = 0;
const userEmail  = '<?= htmlspecialchars($email) ?>';

// ── Open payment modal ──
function startPayment(jobId, cost, filename) {
    _activeJobId = jobId;
    _activeCost  = cost;
    document.getElementById('modal-filename').textContent = filename;
    document.getElementById('modal-amount').textContent   = '₹' + parseFloat(cost).toFixed(2);
    document.getElementById('modal-pay-btn').innerHTML    = '<i class="bx bx-credit-card" style="font-size:1.2rem;"></i> Pay with GPay / PhonePe / UPI / Card';
    document.getElementById('modal-pay-btn').disabled     = false;
    const modal = document.getElementById('payModal');
    modal.style.display = 'flex';
}
function closePayModal() {
    document.getElementById('payModal').style.display = 'none';
}
// Close on backdrop click
document.getElementById('payModal').addEventListener('click', function(e){
    if (e.target === this) closePayModal();
});

// ── Create Razorpay order + open checkout ──
async function openRazorpay() {
    const btn = document.getElementById('modal-pay-btn');
    btn.disabled = true;
    btn.innerHTML = '<div class="spinner"></div> Creating order...';

    try {
        const r = await fetch('create_order.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ job_id: _activeJobId })
        });
        const d = await r.json();
        if (!d.success) throw new Error(d.error || 'Could not create payment order.');

        const opts = {
            key:         d.razorpay_key,
            amount:      d.amount,
            currency:    'INR',
            name:        'HyperPrint',
            description: 'Print Job #' + _activeJobId,
            order_id:    d.razorpay_order_id,
            prefill:     { email: userEmail },
            theme:       { color: '#7c3aed' },
            // This makes GPay, PhonePe, UPI, Cards all show up in the checkout modal
            method: {
                upi:        true,
                card:       true,
                netbanking: true,
                wallet:     true,
            },
            handler: async function(response) {
                btn.innerHTML = '<div class="spinner"></div> Verifying payment...';
                await verifyAndRefresh(response);
            },
            modal: {
                ondismiss: function() {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bx bx-credit-card" style="font-size:1.2rem;"></i> Pay with GPay / PhonePe / UPI / Card';
                }
            }
        };

        const rzp = new Razorpay(opts);
        rzp.open();
        rzp.on('payment.failed', function(resp){
            btn.disabled = false;
            btn.innerHTML = '<i class="bx bx-credit-card" style="font-size:1.2rem;"></i> Retry Payment';
            showToast('Payment failed: ' + resp.error.description, 'error');
        });

    } catch(e) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bx bx-credit-card" style="font-size:1.2rem;"></i> Pay with GPay / PhonePe / UPI / Card';
        showToast(e.message, 'error');
    }
}

// ── Verify payment on server, then reload to show Print button ──
async function verifyAndRefresh(response) {
    try {
        const r = await fetch('verify_payment.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({
                razorpay_payment_id: response.razorpay_payment_id,
                razorpay_order_id:   response.razorpay_order_id,
                razorpay_signature:  response.razorpay_signature,
                job_id: _activeJobId
            })
        });
        const d = await r.json();
        if (d.success) {
            closePayModal();
            showToast('✅ Payment successful! You can now print your document.', 'success');
            // Reload after 1.5 s so the Print button appears
            setTimeout(()=> location.reload(), 1500);
        } else {
            showToast('Verification failed: ' + (d.message||d.error||'Unknown'), 'error');
            document.getElementById('modal-pay-btn').disabled = false;
            document.getElementById('modal-pay-btn').innerHTML = '<i class="bx bx-credit-card"></i> Retry Payment';
        }
    } catch(e) {
        showToast('Network error during verification. Contact support.', 'error');
    }
}

// ── Print ──
async function doPrint(jobId, btn) {
    if (!confirm('Send this document to the printer?')) return;
    btn.disabled = true;
    btn.innerHTML = '<div class="spinner"></div>';

    try {
        const r = await fetch('print_handler.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ job_id: jobId })
        });
        if (!r.ok) throw new Error('Server returned HTTP ' + r.status);
        const d = await r.json();
        if (d.success) {
            showToast('🖨️ Print job sent successfully!', 'success');
            setTimeout(()=> location.reload(), 1500);
        } else {
            showToast(d.message || d.error || 'Print failed.', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="bx bx-printer"></i> Print';
        }
    } catch(e) {
        showToast('Error: ' + e.message, 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bx bx-printer"></i> Print';
    }
}

// ── Toast notification ──
function showToast(msg, type='success'){
    let t = document.getElementById('__toast');
    if (!t) {
        t = document.createElement('div');
        t.id = '__toast';
        t.style.cssText = 'position:fixed;bottom:2rem;left:50%;transform:translateX(-50%);z-index:9999;padding:14px 22px;border-radius:12px;font-family:inherit;font-size:.875rem;font-weight:700;box-shadow:0 8px 28px rgba(0,0,0,.18);transition:opacity .4s;min-width:260px;text-align:center;';
        document.body.appendChild(t);
    }
    t.style.background = type==='success' ? '#16a34a' : '#dc2626';
    t.style.color = 'white';
    t.style.opacity = '1';
    t.textContent = msg;
    clearTimeout(t._timer);
    t._timer = setTimeout(()=>{ t.style.opacity='0'; }, 4000);
}

/* ── THEME ── */
function applyTheme(t){ document.documentElement.setAttribute('data-theme',t); localStorage.setItem('hp_theme',t); const ic=document.getElementById('themeIcon'),lb=document.getElementById('themeLabel'); if(ic)ic.className=t==='dark'?'bx bx-sun':'bx bx-moon'; if(lb)lb.textContent=t==='dark'?'Light Mode':'Dark Mode'; }
function toggleTheme(){ applyTheme(document.documentElement.getAttribute('data-theme')==='dark'?'light':'dark'); }

/* ── MOBILE SIDEBAR ── */
function openSidebar(){ document.getElementById('sidebar').classList.add('open'); document.getElementById('overlay').classList.add('active'); document.getElementById('toggle-icon').className='bx bx-x'; }
function closeSidebar(){ document.getElementById('sidebar').classList.remove('open'); document.getElementById('overlay').classList.remove('active'); document.getElementById('toggle-icon').className='bx bx-menu'; }
document.addEventListener('DOMContentLoaded', function(){
    applyTheme(localStorage.getItem('hp_theme')||'light');
    document.getElementById('mobile-toggle').addEventListener('click', ()=>{ document.getElementById('sidebar').classList.contains('open')?closeSidebar():openSidebar(); });
    document.getElementById('overlay').addEventListener('click', closeSidebar);
    document.addEventListener('keydown', e=>{ if(e.key==='Escape'){ closeSidebar(); closePayModal(); } });
    document.querySelectorAll('.nav-item').forEach(l=>l.addEventListener('click',()=>{ if(window.innerWidth<=1024) closeSidebar(); }));
});
</script>
</body>
</html>
