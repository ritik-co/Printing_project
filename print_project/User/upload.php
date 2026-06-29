<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); exit();
}

$user_id = $_SESSION['user_id'];
$email   = $_SESSION['email'];

// ─────────────────────────────────────────────────────────────
//  ★ REPLACE WITH YOUR LIVE RAZORPAY KEY ID ★
//  Live Key ID starts with: rzp_live_
//  Get it from: https://dashboard.razorpay.com → Settings → API Keys → Live Mode
// ─────────────────────────────────────────────────────────────
define('RAZORPAY_KEY_ID', 'rzp_live_XXXXXXXXXXXXXXXX'); // ← paste your Live Key ID here

?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload & Print | HyperPrint</title>
    <script>(function(){ document.documentElement.setAttribute('data-theme', localStorage.getItem('hp_theme')||'light'); })();</script>
    <!-- Razorpay Checkout SDK -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--primary:#0ea5e9;--primary-hover:#0284c7;--primary-soft:#f0f9ff;--sidebar-width:272px;--bg:#f1f5f9;--surface:#ffffff;--surface2:#f8fafc;--border:#e2e8f0;--text:#0f172a;--text-muted:#64748b;--text-faint:#94a3b8;--radius-sm:10px;--radius-md:16px;--radius-lg:24px;}
        [data-theme="dark"]{--bg:#0c1220;--surface:#141e30;--surface2:#1a2540;--border:#1e3050;--text:#f0f6ff;--text-muted:#8eaac8;--text-faint:#4a6a8a;--primary-soft:#0c2a40;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;transition:background .3s,color .3s;}
        #sidebar{width:var(--sidebar-width);position:fixed;left:0;top:0;height:100vh;background:var(--surface);border-right:1px solid var(--border);z-index:900;transition:transform .35s cubic-bezier(.4,0,.2,1),background .3s;}
        .sidebar-inner{display:flex;flex-direction:column;height:100%;overflow:hidden;}
        .sidebar-logo{display:flex;align-items:center;gap:12px;padding:24px 20px 20px;border-bottom:1px solid var(--border);}
        .logo-icon{width:40px;height:40px;background:var(--primary);border-radius:12px;display:flex;align-items:center;justify-content:center;color:white;font-size:20px;box-shadow:0 4px 12px rgba(14,165,233,.35);flex-shrink:0;}
        .logo-text{font-size:1.1rem;font-weight:800;color:var(--text);letter-spacing:-.02em;}
        .sidebar-nav{flex:1;padding:16px 12px;display:flex;flex-direction:column;gap:4px;overflow-y:auto;}
        .nav-item{display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:var(--radius-sm);color:var(--text-muted);text-decoration:none;font-weight:600;font-size:.875rem;transition:all .18s;}
        .nav-item i{font-size:1.2rem;}
        .nav-item:hover{background:var(--primary-soft);color:var(--primary);}
        .nav-item.active{background:var(--primary);color:white;box-shadow:0 4px 12px rgba(14,165,233,.35);}
        .sidebar-bottom{padding:12px 12px 20px;border-top:1px solid var(--border);display:flex;flex-direction:column;gap:8px;}
        .theme-toggle-btn{display:flex;align-items:center;gap:10px;width:100%;padding:10px 14px;background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text-muted);font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;transition:all .2s;}
        .theme-toggle-btn:hover{background:var(--primary-soft);color:var(--primary);}
        .logout-btn{display:flex;align-items:center;gap:10px;width:100%;padding:10px 14px;background:#fef2f2;border:1px solid #fecaca;border-radius:var(--radius-sm);color:#dc2626;font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;transition:all .2s;}
        .logout-btn:hover{background:#fee2e2;}
        .main-wrapper{margin-left:var(--sidebar-width);padding:2rem;min-height:100vh;}
        #overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:850;backdrop-filter:blur(2px);}
        #overlay.active{display:block;}
        #mobile-toggle{display:none;position:fixed;top:1rem;right:1rem;width:44px;height:44px;background:var(--surface);border:1px solid var(--border);border-radius:12px;align-items:center;justify-content:center;z-index:1000;cursor:pointer;box-shadow:0 4px 16px rgba(0,0,0,.08);color:var(--text);}
        .page-header{margin-bottom:2rem;}
        .page-header h1{font-size:1.6rem;font-weight:800;color:var(--text);letter-spacing:-.03em;}
        .page-header p{color:var(--text-muted);font-size:.9rem;margin-top:4px;}
        .card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);box-shadow:0 1px 3px rgba(0,0,0,.06);padding:2rem;}
        .stepper{display:flex;align-items:center;gap:0;margin-bottom:2rem;}
        .step{display:flex;align-items:center;gap:8px;flex:1;}
        .step-num{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:800;border:2px solid var(--border);background:var(--surface2);color:var(--text-faint);transition:all .3s;flex-shrink:0;}
        .step.active .step-num{border-color:var(--primary);background:var(--primary);color:white;box-shadow:0 0 0 4px rgba(14,165,233,.2);}
        .step.done .step-num{border-color:#22c55e;background:#22c55e;color:white;}
        .step-label{font-size:.78rem;font-weight:700;color:var(--text-faint);text-transform:uppercase;letter-spacing:.06em;}
        .step.active .step-label,.step.done .step-label{color:var(--text);}
        .step-line{flex:1;height:2px;background:var(--border);margin:0 8px;border-radius:9999px;transition:background .3s;}
        .step-line.done{background:#22c55e;}
        .drop-zone{border:2px dashed var(--border);border-radius:var(--radius-md);padding:2.5rem 2rem;text-align:center;background:var(--surface2);transition:all .25s;cursor:pointer;}
        .drop-zone:hover,.drop-zone.drag-over{border-color:var(--primary);background:var(--primary-soft);}
        .drop-zone.has-file{border-color:#22c55e;background:#f0fdf4;border-style:solid;}
        [data-theme="dark"] .drop-zone.has-file{background:#052e16;}
        .dz-icon{font-size:2.75rem;color:var(--primary);margin-bottom:.5rem;display:block;}
        #previewSection{display:none;margin-top:1.5rem;border:1px solid var(--border);border-radius:var(--radius-md);overflow:hidden;background:var(--surface2);}
        .preview-header{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid var(--border);background:var(--surface);}
        .preview-header span{font-size:.85rem;font-weight:700;color:var(--text);}
        #previewFrame{width:100%;height:420px;border:none;display:block;}
        #previewImg{width:100%;max-height:420px;object-fit:contain;display:none;padding:1rem;}
        .config-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-top:1.5rem;}
        .form-label{display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text-faint);margin-bottom:7px;}
        .sel-wrap{position:relative;}
        .sel-wrap::after{content:'▾';position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--text-faint);pointer-events:none;}
        select,.num-input{width:100%;padding:11px 14px;border-radius:var(--radius-sm);border:1.5px solid var(--border);background:var(--surface2);color:var(--text);font-family:inherit;font-size:.9rem;outline:none;transition:all .2s;appearance:none;}
        select:focus,.num-input:focus{border-color:var(--primary);box-shadow:0 0 0 4px rgba(14,165,233,.12);}
        #costBox{display:none;margin-top:1.25rem;background:var(--primary-soft);border:1px solid rgba(14,165,233,.3);border-radius:var(--radius-sm);padding:12px 16px;font-size:.9rem;font-weight:600;color:var(--primary);}
        .action-row{display:flex;gap:1rem;margin-top:1.5rem;flex-wrap:wrap;}
        .btn{padding:13px 22px;border-radius:var(--radius-md);font-family:inherit;font-size:.9rem;font-weight:700;cursor:pointer;border:none;display:inline-flex;align-items:center;justify-content:center;gap:8px;transition:all .2s;}
        .btn-primary{background:var(--primary);color:white;box-shadow:0 4px 14px rgba(14,165,233,.3);flex:1;}
        .btn-primary:hover:not(:disabled){background:var(--primary-hover);transform:translateY(-1px);}
        .btn-pay{background:#7c3aed;color:white;box-shadow:0 4px 14px rgba(124,58,237,.3);flex:1;}
        .btn-pay:hover:not(:disabled){background:#6d28d9;transform:translateY(-1px);}
        .btn-green{background:#16a34a;color:white;box-shadow:0 4px 14px rgba(22,163,74,.3);flex:1;}
        .btn-green:hover:not(:disabled){background:#15803d;transform:translateY(-1px);}
        .btn-gray{background:var(--surface2);border:1.5px solid var(--border);color:var(--text-muted);flex:1;}
        .btn:disabled{opacity:.45;cursor:not-allowed;transform:none !important;}
        .alert{display:flex;align-items:flex-start;gap:10px;padding:13px 16px;border-radius:var(--radius-sm);font-size:.875rem;font-weight:600;margin-bottom:1.25rem;}
        .alert i{font-size:1.2rem;flex-shrink:0;margin-top:1px;}
        .alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#16a34a;}
        .alert-error{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;}
        .alert-info{background:var(--primary-soft);border:1px solid rgba(14,165,233,.3);color:var(--primary);}
        .alert-warn{background:#fffbeb;border:1px solid #fde68a;color:#92400e;}
        .spinner{width:20px;height:20px;border:3px solid rgba(255,255,255,.3);border-top-color:white;border-radius:50%;animation:spin .7s linear infinite;display:inline-block;}
        @keyframes spin{to{transform:rotate(360deg)}}
        #paymentBadge{display:none;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:var(--radius-sm);padding:14px 18px;margin-bottom:1.25rem;align-items:center;gap:12px;}
        #paymentBadge.show{display:flex;}
        #step3Section{display:none;text-align:center;padding:3rem 2rem;}
        @media(max-width:1024px){#sidebar{transform:translateX(-100%);}#sidebar.open{transform:translateX(0);box-shadow:0 8px 32px rgba(0,0,0,.18);}.main-wrapper{margin-left:0!important;padding:1rem;padding-top:4.5rem;}#mobile-toggle{display:flex!important;}}
        @media(max-width:640px){.config-grid{grid-template-columns:1fr!important;}}
    </style>
</head>
<body>

<!-- ═══ SIDEBAR ═══ -->
<div id="sidebar">
    <aside class="sidebar-inner">
        <div class="sidebar-logo">
            <div class="logo-icon"><i class='bx bxs-printer'></i></div>
            <span class="logo-text">HyperPrint</span>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item"><i class='bx bxs-grid-alt'></i><span>Dashboard</span></a>
            <a href="upload.php"    class="nav-item active"><i class='bx bxs-cloud-upload'></i><span>Upload File</span></a>
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
    <div class="page-header">
        <h1>Upload &amp; Print 🖨️</h1>
        <p>Upload your document, configure print settings, pay securely and print.</p>
    </div>

    <div class="card">

        <!-- Stepper -->
        <div class="stepper">
            <div class="step active" id="s1">
                <div class="step-num">1</div>
                <span class="step-label">Upload</span>
            </div>
            <div class="step-line" id="l1"></div>
            <div class="step" id="s2">
                <div class="step-num">2</div>
                <span class="step-label">Pay</span>
            </div>
            <div class="step-line" id="l2"></div>
            <div class="step" id="s3">
                <div class="step-num">3</div>
                <span class="step-label">Print</span>
            </div>
        </div>

        <div id="globalAlert"></div>

        <!-- ── STEP 1: Upload ── -->
        <div id="step1Section">

            <!-- Drop zone -->
            <div class="drop-zone" id="dropZone" onclick="document.getElementById('fileInput').click()">
                <i class='bx bxs-cloud-upload dz-icon' id="dzIcon"></i>
                <div id="dzText" style="font-weight:700;font-size:1rem;color:var(--text);margin-bottom:4px;">Click or drag &amp; drop your file here</div>
                <div id="dzSub" style="font-size:.8rem;color:var(--text-muted);">PDF, JPG, PNG, DOC, DOCX — max 20 MB</div>
            </div>
            <input type="file" id="fileInput" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="display:none">

            <!-- Preview -->
            <div id="previewSection">
                <div class="preview-header">
                    <span id="previewLabel">Preview</span>
                    <button onclick="clearFile()" style="background:none;border:none;color:var(--text-faint);cursor:pointer;font-size:.8rem;font-weight:700;">✕ Remove</button>
                </div>
                <iframe id="previewFrame" title="Document Preview"></iframe>
                <img id="previewImg" alt="Image Preview">
            </div>

            <!-- Print config -->
            <div class="config-grid">
                <div>
                    <label class="form-label">Print Type</label>
                    <div class="sel-wrap">
                        <select id="printType" onchange="calcCost()">
                            <option value="bw">Black &amp; White — ₹3/page</option>
                            <option value="color">Color — ₹10/page</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="form-label">Sides</label>
                    <div class="sel-wrap">
                        <select id="printSides" onchange="calcCost()">
                            <option value="single">Single-sided</option>
                            <option value="double">Double-sided (save 33%)</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="form-label">Copies</label>
                    <input type="number" id="copies" class="num-input" value="1" min="1" max="99" oninput="calcCost()">
                </div>
            </div>

            <div id="costBox"></div>

            <div class="action-row">
                <button class="btn btn-primary" id="uploadBtn" onclick="doUpload()" disabled>
                    <i class='bx bxs-cloud-upload'></i> Upload &amp; Continue
                </button>
            </div>
        </div>

        <!-- ── STEP 2: Pay ── -->
        <div id="step2Section" style="display:none;">

            <!-- Payment verified badge -->
            <div id="paymentBadge">
                <i class='bx bxs-check-circle' style="font-size:1.5rem;color:#16a34a;flex-shrink:0;"></i>
                <div>
                    <div style="font-weight:700;color:#15803d;">Payment Verified ✓</div>
                    <div id="paymentTxnId" style="font-size:.78rem;color:#4ade80;margin-top:2px;"></div>
                </div>
            </div>

            <div style="background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:16px;margin-bottom:1.5rem;">
                <div style="font-size:.8rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">Order Summary</div>
                <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:.875rem;">
                    <span style="color:var(--text-muted);">File</span>
                    <strong id="s2FileName" style="color:var(--text);max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></strong>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:.875rem;">
                    <span style="color:var(--text-muted);">Config</span>
                    <span id="s2Config" style="font-weight:600;color:var(--text);"></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding-top:10px;border-top:1px solid var(--border);">
                    <span style="font-weight:700;">Total</span>
                    <span id="s2Cost" style="font-size:1.4rem;font-weight:800;color:#7c3aed;"></span>
                </div>
            </div>

            <div id="globalAlert2"></div>

            <div class="action-row">
                <button class="btn btn-pay" id="payBtn" onclick="doPayment()">
                    <i class='bx bx-credit-card'></i> Pay with Razorpay
                </button>
                <button class="btn btn-green" id="printBtn" onclick="doPrint()" disabled>
                    <i class='bx bx-printer'></i> Print Document
                </button>
            </div>

            <p style="text-align:center;font-size:.72rem;color:var(--text-faint);margin-top:1rem;">
                🔒 Secured by Razorpay &nbsp;·&nbsp; UPI · GPay · PhonePe · Cards · NetBanking
            </p>
        </div>

        <!-- ── STEP 3: Done ── -->
        <div id="step3Section">
            <i class='bx bxs-check-circle' style="font-size:3.5rem;color:#22c55e;display:block;margin-bottom:1rem;"></i>
            <h2 style="font-size:1.4rem;font-weight:800;color:var(--text);margin-bottom:.5rem;">Print Job Sent! 🎉</h2>
            <p id="successMsg" style="color:var(--text-muted);margin-bottom:2rem;">Your document has been sent to the printer successfully.</p>
            <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;">
                <a href="upload.php" class="btn btn-primary" style="flex:0;text-decoration:none;padding:12px 24px;">
                    <i class='bx bx-plus'></i> New Print Job
                </a>
                <a href="history.php" class="btn btn-gray" style="flex:0;text-decoration:none;padding:12px 24px;">
                    <i class='bx bx-history'></i> View History
                </a>
            </div>
        </div>

    </div>
</main>

<button id="mobile-toggle"><i class='bx bx-menu' id="toggle-icon" style="font-size:1.4rem;"></i></button>

<script>
/* ── State ── */
let selectedFile     = null;
let uploadedJobId    = null;
let uploadedFileName = null;
let uploadedPages    = 1;
let uploadedCost     = 0;
let paymentVerified  = false;

const PRICES = { bw: { single: 3, double: 2 }, color: { single: 10, double: 8 } };

/* ── File Selection ── */
const dropZone  = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');

fileInput.addEventListener('change', e => { if (e.target.files[0]) handleFile(e.target.files[0]); });
dropZone.addEventListener('dragover',  e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    if (e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]);
});

function handleFile(file) {
    const allowed = ['application/pdf','image/jpeg','image/png',
        'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    if (!allowed.some(t => file.type === t) && !file.name.match(/\.(pdf|docx?|jpe?g|png)$/i)) {
        showAlert('File type not supported. Use PDF, JPG, PNG, DOC or DOCX.', 'error'); return;
    }
    if (file.size > 20 * 1024 * 1024) {
        showAlert('File too large. Maximum size is 20 MB.', 'error'); return;
    }
    selectedFile = file;
    dropZone.classList.add('has-file');
    document.getElementById('dzIcon').className = 'bx bxs-check-circle dz-icon';
    document.getElementById('dzIcon').style.color = '#22c55e';
    document.getElementById('dzText').textContent = file.name;
    document.getElementById('dzSub').textContent  = (file.size/1024/1024).toFixed(2) + ' MB';

    // Show preview
    const pSec   = document.getElementById('previewSection');
    const pFrame = document.getElementById('previewFrame');
    const pImg   = document.getElementById('previewImg');
    document.getElementById('previewLabel').textContent = file.name;
    pSec.style.display = 'block';
    const url = URL.createObjectURL(file);
    if (file.type === 'application/pdf') {
        pFrame.src = url; pFrame.style.display = 'block'; pImg.style.display = 'none';
    } else if (file.type.startsWith('image/')) {
        pImg.src = url; pImg.style.display = 'block'; pFrame.style.display = 'none';
    } else {
        pFrame.style.display = 'none'; pImg.style.display = 'none';
    }

    document.getElementById('uploadBtn').disabled = false;
    calcCost();
}

function clearFile() {
    selectedFile = null;
    fileInput.value = '';
    dropZone.classList.remove('has-file');
    document.getElementById('dzIcon').className = 'bx bxs-cloud-upload dz-icon';
    document.getElementById('dzIcon').style.color = '';
    document.getElementById('dzText').textContent = 'Click or drag & drop your file here';
    document.getElementById('dzSub').textContent  = 'PDF, JPG, PNG, DOC, DOCX — max 20 MB';
    document.getElementById('previewSection').style.display = 'none';
    document.getElementById('uploadBtn').disabled = true;
    document.getElementById('costBox').style.display = 'none';
}

function calcCost() {
    if (!selectedFile) return;
    const type   = document.getElementById('printType').value;
    const sides  = document.getElementById('printSides').value;
    const copies = Math.max(1, parseInt(document.getElementById('copies').value) || 1);
    const ppp    = PRICES[type][sides];
    const box    = document.getElementById('costBox');
    box.style.display = 'block';
    box.textContent   = `Estimated cost: ₹${(1 * copies * ppp).toFixed(2)} (1 page × ${copies} copies × ₹${ppp}/page) — exact cost after upload`;
}

/* ── STEP 1: Upload ── */
async function doUpload() {
    if (!selectedFile) { showAlert('Please select a file first.', 'error'); return; }
    const btn = document.getElementById('uploadBtn');
    btn.disabled = true;
    btn.innerHTML = '<div class="spinner"></div> Uploading…';

    const fd = new FormData();
    fd.append('document',    selectedFile);
    fd.append('print_type',  document.getElementById('printType').value);
    fd.append('print_sides', document.getElementById('printSides').value);
    fd.append('copies',      document.getElementById('copies').value);

    try {
        const r = await fetch('upload_handler.php', { method: 'POST', body: fd });
        const d = await r.json();

        if (!d.success) {
            showAlert(d.error || 'Upload failed. Please try again.', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="bx bxs-cloud-upload"></i> Upload & Continue';
            return;
        }

        uploadedJobId    = d.job_id;
        uploadedFileName = d.file_name;
        uploadedPages    = d.pages;
        uploadedCost     = d.cost;

        // Advance stepper to step 2
        document.getElementById('s1').classList.remove('active');
        document.getElementById('s1').classList.add('done');
        document.getElementById('l1').classList.add('done');
        document.getElementById('s2').classList.add('active');

        // Populate summary
        document.getElementById('s2FileName').textContent = d.file_name;
        document.getElementById('s2Config').textContent   =
            `${d.pages} page(s) × ${d.copies} cop(ies) · ` +
            document.getElementById('printType').options[document.getElementById('printType').selectedIndex].text;
        document.getElementById('s2Cost').textContent = '₹' + parseFloat(d.cost).toFixed(2);

        // Switch section
        document.getElementById('step1Section').style.display = 'none';
        document.getElementById('step2Section').style.display = 'block';

    } catch(e) {
        showAlert('Network error. Please check your connection.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bx bxs-cloud-upload"></i> Upload & Continue';
    }
}

/* ── STEP 2: Pay ── */
async function doPayment() {
    const payBtn = document.getElementById('payBtn');
    payBtn.disabled = true;
    payBtn.innerHTML = '<div class="spinner"></div> Creating order…';

    try {
        // 1. Create Razorpay order server-side
        const r = await fetch('create_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ job_id: uploadedJobId })
        });
        const d = await r.json();

        if (!d.success) {
            showAlert2(d.error || 'Could not create payment order.', 'error');
            payBtn.disabled = false;
            payBtn.innerHTML = '<i class="bx bx-credit-card"></i> Retry Payment';
            return;
        }

        // 2. Open Razorpay checkout
        const opts = {
            key:         d.razorpay_key,
            amount:      d.amount,
            currency:    'INR',
            name:        'HyperPrint',
            description: 'Print Job #' + uploadedJobId,
            order_id:    d.razorpay_order_id,
            prefill:     { email: '<?= htmlspecialchars($email, ENT_QUOTES) ?>' },
            theme:       { color: '#7c3aed' },
            method: { upi: true, card: true, netbanking: true, wallet: true },
            handler: async function(response) {
                payBtn.innerHTML = '<div class="spinner"></div> Verifying…';
                await verifyPayment(response);
            },
            modal: {
                ondismiss: function() {
                    payBtn.disabled = false;
                    payBtn.innerHTML = '<i class="bx bx-credit-card"></i> Retry Payment';
                }
            }
        };

        const rzp = new Razorpay(opts);
        rzp.on('payment.failed', function(resp) {
            payBtn.disabled = false;
            payBtn.innerHTML = '<i class="bx bx-credit-card"></i> Retry Payment';
            showAlert2('Payment failed: ' + resp.error.description, 'error');
        });
        rzp.open();

    } catch(e) {
        showAlert2('Network error. Please try again.', 'error');
        payBtn.disabled = false;
        payBtn.innerHTML = '<i class="bx bx-credit-card"></i> Pay with Razorpay';
    }
}

async function verifyPayment(response) {
    const payBtn = document.getElementById('payBtn');
    try {
        const r = await fetch('verify_payment.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                job_id:               uploadedJobId,
                razorpay_payment_id:  response.razorpay_payment_id,
                razorpay_order_id:    response.razorpay_order_id,
                razorpay_signature:   response.razorpay_signature,
            })
        });
        const d = await r.json();

        if (d.success) {
            paymentVerified = true;

            // Show badge
            const badge = document.getElementById('paymentBadge');
            badge.classList.add('show');
            document.getElementById('paymentTxnId').textContent = 'Txn ID: ' + response.razorpay_payment_id;

            // Advance stepper
            document.getElementById('s2').classList.remove('active');
            document.getElementById('s2').classList.add('done');
            document.getElementById('l2').classList.add('done');
            document.getElementById('s3').classList.add('active');

            payBtn.style.display = 'none';
            const printBtn = document.getElementById('printBtn');
            printBtn.disabled = false;
            printBtn.focus();

            showAlert2('✅ Payment verified! You can now print your document.', 'success');
        } else {
            showAlert2('Verification failed: ' + (d.message || 'Please contact support.'), 'error');
            payBtn.disabled = false;
            payBtn.innerHTML = '<i class="bx bx-credit-card"></i> Retry Payment';
        }
    } catch(e) {
        showAlert2('Server error during verification. Please contact support.', 'error');
        payBtn.disabled = false;
        payBtn.innerHTML = '<i class="bx bx-credit-card"></i> Retry Payment';
    }
}

/* ── STEP 3: Print ── */
async function doPrint() {
    if (!paymentVerified || !uploadedJobId) return;
    const printBtn = document.getElementById('printBtn');
    printBtn.disabled = true;
    printBtn.innerHTML = '<div class="spinner"></div> Sending to printer…';

    try {
        const r = await fetch('print_handler.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ job_id: uploadedJobId })
        });
        const d = await r.json();

        if (d.success) {
            document.getElementById('step2Section').style.display = 'none';
            document.getElementById('step3Section').style.display = 'block';
            document.getElementById('s3').classList.remove('active');
            document.getElementById('s3').classList.add('done');
            document.getElementById('successMsg').textContent = 'Document "' + uploadedFileName + '" sent to printer!';
        } else {
            showAlert2(d.message || 'Print failed. Please try again.', 'error');
            printBtn.disabled = false;
            printBtn.innerHTML = '<i class="bx bx-printer"></i> Retry Print';
        }
    } catch(e) {
        showAlert2('Network error. Please check your connection.', 'error');
        printBtn.disabled = false;
        printBtn.innerHTML = '<i class="bx bx-printer"></i> Retry Print';
    }
}

/* ── Alerts ── */
const alertIcons = { success:'bxs-check-circle', error:'bxs-error-circle', info:'bx-info-circle', warn:'bxs-error' };
function showAlert(msg, type='info') {
    const a = document.getElementById('globalAlert');
    a.innerHTML = `<div class="alert alert-${type}"><i class="bx ${alertIcons[type]||'bx-info-circle'}"></i><span>${msg}</span></div>`;
    a.scrollIntoView({ behavior:'smooth', block:'nearest' });
    if (type !== 'error') setTimeout(() => { a.innerHTML = ''; }, 6000);
}
function showAlert2(msg, type='info') {
    const a = document.getElementById('globalAlert2');
    a.innerHTML = `<div class="alert alert-${type}"><i class="bx ${alertIcons[type]||'bx-info-circle'}"></i><span>${msg}</span></div>`;
}

/* ── Theme ── */
function applyTheme(t) {
    document.documentElement.setAttribute('data-theme', t);
    localStorage.setItem('hp_theme', t);
    const ic = document.getElementById('themeIcon');
    const lb = document.getElementById('themeLabel');
    if(ic) ic.className   = t === 'dark' ? 'bx bx-sun'  : 'bx bx-moon';
    if(lb) lb.textContent = t === 'dark' ? 'Light Mode' : 'Dark Mode';
}
function toggleTheme() { applyTheme(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'); }

/* ── Mobile Sidebar ── */
function openSidebar()  { document.getElementById('sidebar').classList.add('open');    document.getElementById('overlay').classList.add('active');    document.getElementById('toggle-icon').className='bx bx-x';    document.getElementById('toggle-icon').style.fontSize='1.4rem'; }
function closeSidebar() { document.getElementById('sidebar').classList.remove('open'); document.getElementById('overlay').classList.remove('active'); document.getElementById('toggle-icon').className='bx bx-menu'; document.getElementById('toggle-icon').style.fontSize='1.4rem'; }

document.addEventListener('DOMContentLoaded', function() {
    applyTheme(localStorage.getItem('hp_theme') || 'light');
    document.getElementById('mobile-toggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.contains('open') ? closeSidebar() : openSidebar();
    });
    document.getElementById('overlay').addEventListener('click', closeSidebar);
    document.addEventListener('keydown', e => { if(e.key === 'Escape') closeSidebar(); });
});
</script>
</body>
</html>