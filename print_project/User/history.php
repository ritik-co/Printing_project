<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['email'])) {
    header("Location: login.php"); exit();
}

$user_id = $_SESSION['user_id'];

$flashMsg = $_SESSION['msg']   ?? '';
$flashErr = $_SESSION['error'] ?? '';
unset($_SESSION['msg'], $_SESSION['error']);

$stmt = $conn->prepare("SELECT file_name,uploaded_at,status,cost,print_type,print_sides,pages,copies
    FROM print_jobs WHERE user_id=? ORDER BY uploaded_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print History | Smart Printing System</title>
    <script>(function(){ document.documentElement.setAttribute('data-theme', localStorage.getItem('hp_theme')||'light'); })();</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--primary:#0ea5e9;--primary-hover:#0284c7;--primary-soft:#f0f9ff;--sidebar-width:272px;--bg:#f1f5f9;--surface:#ffffff;--surface2:#f8fafc;--border:#e2e8f0;--text:#0f172a;--text-muted:#64748b;--text-faint:#94a3b8;--shadow-sm:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);--shadow-md:0 4px 16px rgba(0,0,0,.08);--shadow-lg:0 10px 40px rgba(0,0,0,.10);--radius-sm:10px;--radius-md:16px;--radius-lg:24px;}
        [data-theme="dark"]{--bg:#0c1220;--surface:#141e30;--surface2:#1a2540;--border:#1e3050;--text:#f0f6ff;--text-muted:#8eaac8;--text-faint:#4a6a8a;--shadow-sm:0 1px 3px rgba(0,0,0,.3);--shadow-md:0 4px 16px rgba(0,0,0,.35);--shadow-lg:0 10px 40px rgba(0,0,0,.4);--primary-soft:#0c2a40;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;transition:background .3s,color .3s;}
        #sidebar{width:var(--sidebar-width);position:fixed;left:0;top:0;height:100vh;background:var(--surface);border-right:1px solid var(--border);z-index:900;transition:transform .35s cubic-bezier(.4,0,.2,1),background .3s,border-color .3s;}
        .sidebar-inner{display:flex;flex-direction:column;height:100%;overflow:hidden;}
        .sidebar-logo{display:flex;align-items:center;gap:12px;padding:24px 20px 20px;border-bottom:1px solid var(--border);}
        .logo-icon{width:40px;height:40px;background:var(--primary);border-radius:12px;display:flex;align-items:center;justify-content:center;color:white;font-size:20px;box-shadow:0 4px 12px rgba(14,165,233,.35);flex-shrink:0;}
        .logo-text{font-size:1.1rem;font-weight:800;color:var(--text);letter-spacing:-.02em;}
        .sidebar-nav{flex:1;padding:16px 12px;display:flex;flex-direction:column;gap:4px;overflow-y:auto;}
        .nav-item{display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:var(--radius-sm);color:var(--text-muted);text-decoration:none;font-weight:600;font-size:.875rem;transition:all .18s;}
        .nav-item i{font-size:1.2rem;}
        .nav-item:hover{background:var(--primary-soft);color:var(--primary);transform:translateX(2px);}
        .nav-item.active{background:var(--primary);color:white;box-shadow:0 4px 12px rgba(14,165,233,.35);}
        .sidebar-bottom{padding:12px 12px 20px;border-top:1px solid var(--border);display:flex;flex-direction:column;gap:8px;}
        .theme-toggle-btn{display:flex;align-items:center;gap:10px;width:100%;padding:10px 14px;background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text-muted);font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;transition:all .2s;}
        .theme-toggle-btn:hover{background:var(--primary-soft);color:var(--primary);border-color:var(--primary);}
        .logout-btn{display:flex;align-items:center;gap:10px;width:100%;padding:10px 14px;background:#fef2f2;border:1px solid #fecaca;border-radius:var(--radius-sm);color:#dc2626;font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;transition:all .2s;}
        .logout-btn:hover{background:#fee2e2;}
        .main-wrapper{margin-left:var(--sidebar-width);padding:2rem;min-height:100vh;transition:margin-left .35s ease;}
        #overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:850;backdrop-filter:blur(2px);}
        #overlay.active{display:block;}
        #mobile-toggle{display:none;position:fixed;top:1rem;right:1rem;width:44px;height:44px;background:var(--surface);border:1px solid var(--border);border-radius:12px;align-items:center;justify-content:center;z-index:1000;cursor:pointer;box-shadow:var(--shadow-md);color:var(--text);}
        .card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);overflow:hidden;}
        table{width:100%;border-collapse:collapse;}
        th{background:var(--surface2);color:var(--text-faint);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;padding:14px 16px;text-align:left;border-bottom:1px solid var(--border);}
        td{padding:14px 16px;border-bottom:1px solid var(--border);font-size:.875rem;}
        tr:last-child td{border-bottom:none;}
        tbody tr{transition:background .15s;}
        tbody tr:hover{background:var(--surface2);}
        .pill{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:9999px;font-size:.72rem;font-weight:700;}
        .pill-pending{background:#fef3c7;color:#92400e;}
        .pill-completed,.pill-printed{background:#dcfce7;color:#166534;}
        .pill-failed{background:#fee2e2;color:#991b1b;}
        .alert{display:flex;align-items:center;gap:10px;padding:13px 16px;border-radius:var(--radius-sm);font-size:.875rem;font-weight:600;margin-bottom:1.5rem;}
        .alert-error{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;}
        .alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#16a34a;}
        .btn-icon{display:inline-flex;align-items:center;justify-content:center;padding:8px;border-radius:8px;font-size:1.1rem;cursor:pointer;border:none;font-family:inherit;transition:all .2s;}
        .btn-icon-red{color:#dc2626;background:transparent;}
        .btn-icon-red:hover{background:#fef2f2;}
        .page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:2rem;}
        .page-header h1{font-size:1.6rem;font-weight:800;color:var(--text);letter-spacing:-.03em;}
        .page-header p{color:var(--text-muted);font-size:.9rem;margin-top:2px;}
        @media(max-width:1024px){#sidebar{transform:translateX(-100%);}#sidebar.open{transform:translateX(0);box-shadow:0 8px 32px rgba(0,0,0,.18);}.main-wrapper{margin-left:0!important;padding:1rem;padding-top:4.5rem;}#mobile-toggle{display:flex!important;}}
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
            <a href="dashboard.php" class="nav-item ">
                <i class='bx bxs-grid-alt'></i><span>Dashboard</span>
            </a>
            <a href="upload.php" class="nav-item ">
                <i class='bx bxs-cloud-upload'></i><span>Upload File</span>
            </a>
            <a href="history.php" class="nav-item active">
                <i class='bx bxs-time-five'></i><span>Print History</span>
            </a>
            <a href="settings.php" class="nav-item ">
                <i class='bx bxs-cog'></i><span>Settings</span>
            </a>
        </nav>
        <div class="sidebar-bottom">
            <button class="theme-toggle-btn" onclick="toggleTheme()">
                <i class='bx bx-moon' id="themeIcon"></i>
                <span id="themeLabel">Dark Mode</span>
            </button>
            <form action="logout.php" method="POST" style="margin:0;">
                <button type="submit" class="logout-btn">
                    <i class='bx bx-log-out'></i><span>Logout</span>
                </button>
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
            <h1>Print History 📄</h1>
            <p>All your past print jobs in one place.</p>
        </div>
        <a href="upload.php" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:var(--primary);color:white;border-radius:var(--radius-sm);font-weight:700;font-size:.875rem;text-decoration:none;box-shadow:0 4px 12px rgba(14,165,233,.3);transition:all .2s;">
            <i class='bx bx-plus'></i> New Print
        </a>
    </div>

    <div class="card">
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr><th>File Name</th><th>Date &amp; Time</th><th>Status</th><th>Config</th><th>Cost</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()):
                    //     $s = strtolower($row['status']);
                    //     $pillClass = ($s==='printed'||$s==='completed') ? 'pill-completed' : ($s==='failed' ? 'pill-failed' : 'pill-pending');
                    //     $dot = ($s==='printed'||$s==='completed') ? '✓' : ($s==='failed' ? '✗' : '⏳');
                            $s = strtolower($row['status']);

if ($s == 'done') {
    $pillClass = 'pill-completed';
    $dot = '✓';
}
elseif ($s == 'printing') {
    $pillClass = 'pill-printing';
    $dot = '🖨️';
}
else {
    $pillClass = 'pill-pending';
    $dot = '⏳';
}
                        ?>
                    <tr>
                        <td style="font-weight:700;color:var(--text);max-width:200px;">
                            <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($row['file_name']) ?>">
                                <?= htmlspecialchars($row['file_name']) ?>
                            </div>
                        </td>
                        <td style="color:var(--text-muted);white-space:nowrap;"><?= date("d M Y, h:i A", strtotime($row['uploaded_at'])) ?></td>
                        <td><span class="pill <?= $pillClass ?>"><?= $dot ?> <?= ucfirst($row['status']) ?></span></td>
                        <td>
                            <span style="background:var(--surface2);border:1px solid var(--border);color:var(--text-muted);font-size:.75rem;font-weight:600;padding:3px 9px;border-radius:6px;white-space:nowrap;">
                                <?= ucfirst($row['print_type']) ?> · <?= ucfirst($row['print_sides']) ?> · <?= $row['pages'] ?>p · <?= $row['copies'] ?>c
                            </span>
                        </td>
                        <td style="font-weight:700;color:var(--text);white-space:nowrap;">₹<?= number_format($row['cost'], 2) ?></td>
                        <td>
                            <form method="POST" action="delete.php" onsubmit="return confirm('Delete «<?= htmlspecialchars($row['file_name'], ENT_QUOTES) ?>»? This cannot be undone.');" style="margin:0;">
                                <input type="hidden" name="file_name" value="<?= htmlspecialchars($row['file_name']) ?>">
                                <input type="hidden" name="redirect" value="history">
                                <button type="submit" class="btn-icon btn-icon-red" title="Delete file">
                                    <i class='bx bx-trash'></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center;padding:3.5rem;color:var(--text-faint);">
                        <i class='bx bx-history' style="font-size:2.5rem;display:block;margin-bottom:.75rem;opacity:.4;"></i>
                        No print history yet.<br>
                        <a href="upload.php" style="color:var(--primary);font-weight:700;text-decoration:none;">Upload your first file →</a>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<button id="mobile-toggle"><i class='bx bx-menu text-2xl' id="toggle-icon"></i></button>
<script>
/* ── THEME ── */
function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('hp_theme', theme);
    const icon  = document.getElementById('themeIcon');
    const label = document.getElementById('themeLabel');
    if (icon)  icon.className   = theme === 'dark' ? 'bx bx-sun' : 'bx bx-moon';
    if (label) label.textContent = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
}
function toggleTheme() {
    const cur = document.documentElement.getAttribute('data-theme') || 'light';
    applyTheme(cur === 'dark' ? 'light' : 'dark');
}
/* ── MOBILE SIDEBAR ── */
function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('overlay').classList.add('active');
    const i = document.getElementById('toggle-icon');
    if (i) i.className = 'bx bx-x';
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('overlay').classList.remove('active');
    const i = document.getElementById('toggle-icon');
    if (i) i.className = 'bx bx-menu';
}
document.addEventListener('DOMContentLoaded', function () {
    applyTheme(localStorage.getItem('hp_theme') || 'light');
    const toggle = document.getElementById('mobile-toggle');
    const overlay = document.getElementById('overlay');
    if (toggle) toggle.addEventListener('click', function () {
        document.getElementById('sidebar').classList.contains('open') ? closeSidebar() : openSidebar();
    });
    if (overlay) overlay.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeSidebar(); });
    document.querySelectorAll('.nav-item').forEach(function(link) {
        link.addEventListener('click', function() { if (window.innerWidth <= 1024) closeSidebar(); });
    });
});
</script>
</body>
</html>
