<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php"); exit();
}
include 'db.php';

$flash = $_SESSION['msg'] ?? ''; $flashErr = $_SESSION['error'] ?? '';
unset($_SESSION['msg'], $_SESSION['error']);

// Update job status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $jid = intval($_POST['job_id']);

    $status = in_array($_POST['status'], ['pending','printing','done'])
        ? $_POST['status']
        : 'pending';

    $stmt = $conn->prepare("UPDATE print_jobs SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $jid);

    if ($stmt->execute()) {
        $_SESSION['msg'] = "Job #$jid status updated to $status.";
    } else {
        $_SESSION['error'] = "Error updating status.";
    }

    $stmt->close();
    header("Location: admin_jobs.php");
    exit();
}
// Delete job
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_job'])) {
    $jid  = intval($_POST['job_id']);
    $res  = $conn->query("SELECT file_path FROM print_jobs WHERE id=$jid");
    if ($res && $file = $res->fetch_row()) {
        if (file_exists($file[0])) unlink($file[0]);
    }
    $stmt = $conn->prepare("DELETE FROM print_jobs WHERE id=?");
    $stmt->bind_param("i", $jid);
    if ($stmt->execute()) {
        $_SESSION['msg'] = "Print job #$jid deleted successfully.";
    } else {
        $_SESSION['error'] = "Error deleting job.";
    }
    $stmt->close();
    header("Location: admin_jobs.php"); exit();
}

// Filters
$search    = trim($_GET['q']       ?? '');
$statusF   = trim($_GET['status']  ?? '');
$userIdF   = intval($_GET['user_id'] ?? 0);
$conditions = [];
if ($search)   $conditions[] = "(pj.file_name LIKE '%".$conn->real_escape_string($search)."%' OR u.username LIKE '%".$conn->real_escape_string($search)."%')";
if ($statusF)  $conditions[] = "pj.status = '".$conn->real_escape_string($statusF)."'";
if ($userIdF)  $conditions[] = "pj.user_id = $userIdF";
$where = $conditions ? "WHERE ".implode(" AND ", $conditions) : '';

$jobs = $conn->query("SELECT pj.id, pj.file_name, pj.status, pj.cost, pj.uploaded_at,
    pj.print_type, pj.print_sides, pj.pages, pj.copies, u.username
    FROM print_jobs pj LEFT JOIN users u ON pj.user_id = u.id
    $where ORDER BY pj.uploaded_at DESC");
$totalJobs = $conn->query("SELECT COUNT(*) FROM print_jobs pj LEFT JOIN users u ON pj.user_id = u.id $where")->fetch_row()[0];

// Filter username if filtering by user_id
$filterUser = '';
if ($userIdF) {
    $r = $conn->query("SELECT username FROM users WHERE id=$userIdF");
    if ($r) $filterUser = $r->fetch_row()[0] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Jobs | HyperPrint Admin</title>
    <script>(function(){ document.documentElement.setAttribute('data-theme', localStorage.getItem('hp_theme')||'light'); })();</script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root{--primary:#0ea5e9;--primary-hover:#0284c7;--primary-soft:#f0f9ff;--sidebar-width:272px;--bg:#f1f5f9;--surface:#ffffff;--surface2:#f8fafc;--border:#e2e8f0;--text:#0f172a;--text-muted:#64748b;--text-faint:#94a3b8;--shadow-sm:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);--shadow-md:0 4px 16px rgba(0,0,0,.08);--shadow-lg:0 10px 40px rgba(0,0,0,.10);--radius-sm:10px;--radius-md:16px;--radius-lg:24px;}
        [data-theme="dark"]{--bg:#0c1220;--surface:#141e30;--surface2:#1a2540;--border:#1e3050;--text:#f0f6ff;--text-muted:#8eaac8;--text-faint:#4a6a8a;--shadow-sm:0 1px 3px rgba(0,0,0,.3);--shadow-md:0 4px 16px rgba(0,0,0,.35);--shadow-lg:0 10px 40px rgba(0,0,0,.4);--primary-soft:#0c2a40;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;transition:background .3s,color .3s;}
        #sidebar{width:var(--sidebar-width);position:fixed;left:0;top:0;height:100vh;background:var(--surface);border-right:1px solid var(--border);z-index:900;transition:transform .35s cubic-bezier(.4,0,.2,1),background .3s,border-color .3s;}
        .sidebar-inner{display:flex;flex-direction:column;height:100%;overflow:hidden;}
        .sidebar-logo{display:flex;align-items:center;gap:12px;padding:22px 20px 18px;border-bottom:1px solid var(--border);}
        .logo-icon{width:40px;height:40px;background:linear-gradient(135deg,#7c3aed,#0ea5e9);border-radius:12px;display:flex;align-items:center;justify-content:center;color:white;font-size:20px;box-shadow:0 4px 12px rgba(124,58,237,.35);flex-shrink:0;}
        .logo-text{font-size:1.05rem;font-weight:800;color:var(--text);letter-spacing:-.02em;display:block;line-height:1.2;}
        .admin-badge{display:inline-block;font-size:.58rem;font-weight:800;letter-spacing:.12em;background:linear-gradient(135deg,#7c3aed,#0ea5e9);color:white;padding:2px 7px;border-radius:9999px;margin-top:2px;}
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
        .stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.5rem;box-shadow:var(--shadow-sm);transition:all .2s;position:relative;overflow:hidden;}
        .stat-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-md);}
        .stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;}
        .stat-card.sky::before{background:var(--primary);} .stat-card.amber::before{background:#f59e0b;} .stat-card.green::before{background:#22c55e;} .stat-card.purple::before{background:#7c3aed;} .stat-card.rose::before{background:#f43f5e;}
        .stat-value{font-size:2rem;font-weight:800;letter-spacing:-.04em;margin-top:4px;}
        .stat-label{font-size:.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;}
        .stat-icon{position:absolute;right:1.25rem;top:50%;transform:translateY(-50%);font-size:2.8rem;opacity:.07;}
        .card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);overflow:hidden;transition:background .3s,border-color .3s;}
        table{width:100%;border-collapse:collapse;}
        th{background:var(--surface2);color:var(--text-faint);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;padding:14px 16px;text-align:left;border-bottom:1px solid var(--border);}
        td{padding:13px 16px;border-bottom:1px solid var(--border);font-size:.875rem;}
        tr:last-child td{border-bottom:none;}
        tbody tr{transition:background .15s;}
        tbody tr:hover{background:var(--surface2);}
        .pill{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:9999px;font-size:.72rem;font-weight:700;}
        .pill-pending{background:#fef3c7;color:#92400e;} .pill-completed,.pill-printed{background:#dcfce7;color:#166534;} .pill-failed{background:#fee2e2;color:#991b1b;}
        .page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:2rem;}
        .page-header h1{font-size:1.6rem;font-weight:800;color:var(--text);letter-spacing:-.03em;}
        .page-header p{color:var(--text-muted);font-size:.9rem;margin-top:2px;}
        .btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:var(--radius-sm);font-family:inherit;font-size:.85rem;font-weight:700;cursor:pointer;border:none;transition:all .2s;text-decoration:none;}
        .btn-primary{background:var(--primary);color:white;box-shadow:0 4px 12px rgba(14,165,233,.25);}
        .btn-primary:hover{background:var(--primary-hover);transform:translateY(-1px);}
        .btn-danger{background:#fee2e2;color:#dc2626;border:1px solid #fecaca;}
        .btn-danger:hover{background:#fecaca;}
        .btn-success{background:#dcfce7;color:#16a34a;border:1px solid #bbf7d0;}
        .btn-success:hover{background:#bbf7d0;}
        .btn-sm{padding:6px 12px;font-size:.78rem;}
        .alert{display:flex;align-items:center;gap:10px;padding:13px 16px;border-radius:var(--radius-sm);font-size:.875rem;font-weight:600;margin-bottom:1.5rem;}
        .alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#16a34a;}
        .alert-error{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;}
        .search-bar{padding:10px 14px;border-radius:var(--radius-sm);border:1.5px solid var(--border);background:var(--surface2);color:var(--text);font-family:inherit;font-size:.875rem;outline:none;transition:all .2s;min-width:240px;}
        .search-bar:focus{border-color:var(--primary);background:var(--surface);box-shadow:0 0 0 4px rgba(14,165,233,.10);}
        select.filter-select{padding:10px 14px;border-radius:var(--radius-sm);border:1.5px solid var(--border);background:var(--surface2);color:var(--text);font-family:inherit;font-size:.875rem;outline:none;transition:all .2s;cursor:pointer;}
        select.filter-select:focus{border-color:var(--primary);}
        @media(max-width:1024px){#sidebar{transform:translateX(-100%);}#sidebar.open{transform:translateX(0);box-shadow:0 8px 32px rgba(0,0,0,.18);}.main-wrapper{margin-left:0!important;padding:1rem;padding-top:4.5rem;}#mobile-toggle{display:flex!important;}}
        @media(max-width:640px){.stat-grid{grid-template-columns:1fr 1fr!important;}}
        .status-select{padding:5px 10px;border-radius:8px;border:1.5px solid var(--border);background:var(--surface2);color:var(--text);font-family:inherit;font-size:.78rem;font-weight:600;cursor:pointer;outline:none;}
        .status-select:focus{border-color:var(--primary);}
    </style>
</head>
<body>

<div id="sidebar">
    <aside class="sidebar-inner">
        <div class="sidebar-logo">
            <div class="logo-icon"><i class='bx bxs-shield-alt'></i></div>
            <div><span class="logo-text">HyperPrint</span><span class="admin-badge">ADMIN</span></div>
        </div>
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php" class="nav-item"><i class='bx bxs-grid-alt'></i><span>Dashboard</span></a>
            <a href="admin_users.php" class="nav-item"><i class='bx bxs-group'></i><span>Manage Users</span></a>
            <a href="admin_jobs.php" class="nav-item active"><i class='bx bxs-printer'></i><span>Print Jobs</span></a>
            <a href="admin_collection.php" class="nav-item"><i class='bx bxs-report'></i><span>Daily Collection</span></a>
            <a href="admin_devices.php" class="nav-item"><i class='bx bxs-devices'></i><span>Devices</span></a>
        </nav>
        <div class="sidebar-bottom">
            <button class="theme-toggle-btn" onclick="toggleTheme()"><i class='bx bx-moon' id="themeIcon"></i><span id="themeLabel">Dark Mode</span></button>
            <form action="admin_logout.php" method="POST" style="margin:0;">
                <button type="submit" class="logout-btn"><i class='bx bx-log-out'></i><span>Logout</span></button>
            </form>
        </div>
    </aside>
</div>
<div id="overlay"></div>

<main class="main-wrapper">
    <div class="page-header">
        <div>
            <h1>Print Jobs 🖨️</h1>
            <p><?= $totalJobs ?> job<?= $totalJobs != 1 ? 's' : '' ?>
                <?= $filterUser ? " for user <strong>".htmlspecialchars($filterUser)."</strong>" : '' ?>
                <?= $statusF ? " · status: <strong>".htmlspecialchars($statusF)."</strong>" : '' ?>
                <?= $search ? " · matching \"".htmlspecialchars($search)."\"" : '' ?>
            </p>
        </div>
    </div>

    <?php if ($flash): ?><div class="alert alert-success"><i class='bx bxs-check-circle'></i><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <?php if ($flashErr): ?><div class="alert alert-error"><i class='bx bxs-error-circle'></i><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

    <!-- Filters -->
    <div style="margin-bottom:1.5rem;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <input type="hidden" name="user_id" value="<?= $userIdF ?: '' ?>">
            <input type="text" name="q" class="search-bar" placeholder="Search file or username…" value="<?= htmlspecialchars($search) ?>">
            <select name="status" class="filter-select">
                <option value="">All Statuses</option>
                <?php
                    $statusMap = [
                        'pending'  => 'Pending',
                        'printing' => 'Printing',
                        'done'     => 'Done'
                    ];

                    foreach ($statusMap as $value => $label):
                    ?> 
                        <option value="<?= $value ?>"
                            <?= strtolower($statusF) == strtolower($value) ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary"><i class='bx bx-filter'></i> Filter</button>
            <?php if($search || $statusF || $userIdF): ?>
                <a href="admin_jobs.php" class="btn" style="background:var(--surface2);color:var(--text-muted);border:1px solid var(--border);">Clear All</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr><th>#</th><th>User</th><th>File Name</th><th>Config</th><th>Cost</th><th>Date</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php if ($jobs->num_rows === 0): ?>
                    <tr><td colspan="8" style="text-align:center;padding:3rem;color:var(--text-faint);">
                        <i class='bx bx-printer' style="font-size:2rem;display:block;margin-bottom:.5rem;opacity:.4;"></i>No print jobs found.
                    </td></tr>
                <?php else: while($row = $jobs->fetch_assoc()):
                  $s = strtolower($row['status']);
                    if ($s == 'done') {
                        $pill = 'pill-completed';
                    } else {
                        $pill = 'pill-pending';
                    } 
                ?>
                <tr>
                    <td style="color:var(--text-faint);font-size:.8rem;">#<?= $row['id'] ?></td>
                    <td style="font-weight:600;color:var(--text);"><?= htmlspecialchars($row['username'] ?? '—') ?></td>
                    <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text-muted);" title="<?= htmlspecialchars($row['file_name']) ?>">
                        <?= htmlspecialchars($row['file_name']) ?>
                    </td>
                    <td>
                        <span style="background:var(--surface2);border:1px solid var(--border);color:var(--text-muted);font-size:.73rem;font-weight:600;padding:3px 8px;border-radius:6px;white-space:nowrap;">
                            <?= strtoupper($row['print_type']) ?> · <?= ucfirst($row['print_sides']) ?> · <?= $row['pages'] ?>p · <?= $row['copies'] ?>c
                        </span>
                    </td>
                    <td style="font-weight:700;color:var(--text);white-space:nowrap;">₹<?= number_format($row['cost'],2) ?></td>
                    <td style="color:var(--text-muted);font-size:.8rem;white-space:nowrap;"><?= date('d M Y', strtotime($row['uploaded_at'])) ?></td>
                    <td>
                        <!-- Inline status update -->
                    <td>

                            <!-- Status Badge -->
                            <span class="pill <?= $pill ?>">
                                <?= ucfirst($row['status']) ?>
                            </span>

                            <br><br>

                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="update_status" value="1">
                                <input type="hidden" name="job_id" value="<?= $row['id'] ?>">

                                <select name="status" class="status-select" onchange="this.form.submit()">
                                    <?php
                                    $statusMap = [
                                        'pending'  => 'Pending',
                                        'printing' => 'Printing',
                                        'done'     => 'Done'
                                    ];

                                    foreach ($statusMap as $value => $label):
                                    ?>
                                    <option value="<?= $value ?>"
                                        <?= strtolower($row['status']) == strtolower($value) ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>

                        </td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Delete this print job? This cannot be undone.');" style="margin:0;">
                            <input type="hidden" name="delete_job" value="1">
                            <input type="hidden" name="job_id" value="<?= $row['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger" title="Delete job"><i class='bx bx-trash'></i></button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<button id="mobile-toggle"><i class='bx bx-menu' id="toggle-icon" style="font-size:1.4rem;"></i></button>
<script>
function applyTheme(theme){document.documentElement.setAttribute('data-theme',theme);localStorage.setItem('hp_theme',theme);const i=document.getElementById('themeIcon'),l=document.getElementById('themeLabel');if(i)i.className=theme==='dark'?'bx bx-sun':'bx bx-moon';if(l)l.textContent=theme==='dark'?'Light Mode':'Dark Mode';}
function toggleTheme(){applyTheme(document.documentElement.getAttribute('data-theme')==='dark'?'light':'dark');}
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('active');const i=document.getElementById('toggle-icon');if(i)i.className='bx bx-x';}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('active');const i=document.getElementById('toggle-icon');if(i)i.className='bx bx-menu';}
document.addEventListener('DOMContentLoaded',function(){
    applyTheme(localStorage.getItem('hp_theme')||'light');
    const t=document.getElementById('mobile-toggle'),o=document.getElementById('overlay');
    if(t)t.addEventListener('click',function(){document.getElementById('sidebar').classList.contains('open')?closeSidebar():openSidebar();});
    if(o)o.addEventListener('click',closeSidebar);
    document.addEventListener('keydown',function(e){if(e.key==='Escape')closeSidebar();});
    document.querySelectorAll('.nav-item').forEach(function(l){l.addEventListener('click',function(){if(window.innerWidth<=1024)closeSidebar();});});
});
</script>
</body>
</html>
