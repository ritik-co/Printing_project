<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php"); exit();
}
include 'db.php';

$uid = intval($_GET['id'] ?? 0);
if (!$uid) { header("Location: admin_users.php"); exit(); }

// Fetch user
$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param("i", $uid); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) { header("Location: admin_users.php"); exit(); }

// Flash
$flash = $_SESSION['msg'] ?? ''; $flashErr = $_SESSION['error'] ?? '';
unset($_SESSION['msg'], $_SESSION['error']);

// Handle inline edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $newUser  = trim($_POST['username']);
    $newEmail = trim($_POST['email']);
    $stmt2 = $conn->prepare("UPDATE users SET username=?, email=? WHERE id=?");
    $stmt2->bind_param("ssi", $newUser, $newEmail, $uid);
    if ($stmt2->execute()) {
        $_SESSION['msg'] = "User profile updated.";
        $user['username'] = $newUser; $user['email'] = $newEmail;
    } else { $_SESSION['error'] = "Update failed."; }
    header("Location: admin_user_detail.php?id=$uid"); exit();
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $newPass = $_POST['new_password'];
    if (strlen($newPass) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters.";
    } else {
        $hash = password_hash($newPass, PASSWORD_BCRYPT);
        $stmt3 = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt3->bind_param("si", $hash, $uid);
        if ($stmt3->execute()) {
            $_SESSION['msg'] = "Password reset successfully.";
        } else {
            $_SESSION['error'] = "Error resetting password.";
        }
        $stmt3->close();
    }
    header("Location: admin_user_detail.php?id=$uid"); exit();
}

// Stats for this user
$stats = $conn->query("SELECT
    COUNT(*) as total,
    SUM(status='done') as completed,
    SUM(status='pending') as pending,
    SUM(status='printing') as failed,
    COALESCE(SUM(cost),0) as total_spent,
    COALESCE(SUM(CASE WHEN status='done' THEN cost ELSE 0 END),0) as paid,
    COALESCE(SUM(pages*copies),0) as total_pages
    FROM print_jobs WHERE user_id=$uid")->fetch_assoc();
// Print history
$jobs = $conn->query("SELECT * FROM print_jobs WHERE user_id=$uid ORDER BY uploaded_at DESC");
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Detail | HyperPrint Admin</title>
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
        .info-row{display:flex;flex-direction:column;gap:4px;}
        .info-label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text-faint);}
        .info-value{font-size:.95rem;font-weight:600;color:var(--text);}
        .section-title{font-size:1rem;font-weight:800;color:var(--text);margin-bottom:1.25rem;display:flex;align-items:center;gap:8px;}
        .section-title i{color:var(--primary);font-size:1.2rem;}
        .form-label{display:block;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text-faint);margin-bottom:6px;}
        .input-field{width:100%;padding:11px 14px;border-radius:var(--radius-sm);border:1.5px solid var(--border);background:var(--surface2);color:var(--text);font-family:inherit;font-size:.9rem;outline:none;transition:all .2s;}
        .input-field:focus{border-color:var(--primary);background:var(--surface);box-shadow:0 0 0 3px rgba(14,165,233,.10);}
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
            <a href="admin_users.php" class="nav-item active"><i class='bx bxs-group'></i><span>Manage Users</span></a>
            <a href="admin_jobs.php" class="nav-item"><i class='bx bxs-printer'></i><span>Print Jobs</span></a>
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

    <!-- Breadcrumb -->
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:1.5rem;font-size:.85rem;color:var(--text-muted);">
        <a href="admin_users.php" style="color:var(--primary);font-weight:700;text-decoration:none;">Users</a>
        <i class='bx bx-chevron-right'></i>
        <span style="font-weight:600;color:var(--text);"><?= htmlspecialchars($user['username']) ?></span>
    </div>

    <?php if ($flash): ?><div class="alert alert-success"><i class='bx bxs-check-circle'></i><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <?php if ($flashErr): ?><div class="alert alert-error"><i class='bx bxs-error-circle'></i><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

    <!-- Profile Header -->
    <div class="card" style="padding:2rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;">
        <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#7c3aed,#0ea5e9);display:flex;align-items:center;justify-content:center;color:white;font-size:2rem;font-weight:800;flex-shrink:0;">
            <?= strtoupper(substr($user['username'],0,1)) ?>
        </div>
        <div style="flex:1;">
            <h1 style="font-size:1.5rem;font-weight:800;color:var(--text);letter-spacing:-.02em;"><?= htmlspecialchars($user['username']) ?></h1>
            <p style="color:var(--text-muted);font-size:.9rem;margin-top:2px;"><?= htmlspecialchars($user['email']) ?></p>
            <p style="color:var(--text-faint);font-size:.78rem;margin-top:4px;">User ID #<?= $user['id'] ?></p>
        </div>
        <a href="admin_users.php" class="btn" style="background:var(--surface2);color:var(--text-muted);border:1px solid var(--border);">
            <i class='bx bx-arrow-back'></i> Back to Users
        </a>
    </div>

    <!-- Stats Row -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;">
        <div class="stat-card sky">
            <div class="stat-label">Total Jobs</div>
            <div class="stat-value" style="color:var(--primary);"><?= $stats['total'] ?></div>
            <i class='bx bxs-printer stat-icon'></i>
        </div>
        <div class="stat-card green">
            <div class="stat-label">Completed</div>
            <div class="stat-value" style="color:#22c55e;"><?= $stats['completed'] ?></div>
            <i class='bx bxs-check-circle stat-icon'></i>
        </div>
        <div class="stat-card amber">
            <div class="stat-label">Pending</div>
            <div class="stat-value" style="color:#f59e0b;"><?= $stats['pending'] ?></div>
            <i class='bx bxs-time stat-icon'></i>
        </div>
        <div class="stat-card rose">
            <div class="stat-label">Total Spent</div>
            <div class="stat-value" style="color:#f43f5e;font-size:1.5rem;">₹<?= number_format($stats['total_spent'],0) ?></div>
            <i class='bx bxs-wallet stat-icon'></i>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">

        <!-- Edit Profile -->
        <div class="card" style="padding:1.75rem;">
            <div class="section-title"><i class='bx bxs-edit'></i> Edit Profile</div>
            <form method="POST" style="display:flex;flex-direction:column;gap:1rem;">
                <input type="hidden" name="update_user" value="1">
                <div>
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="input-field" value="<?= htmlspecialchars($user['username']) ?>" required>
                </div>
                <div>
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="input-field" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary" style="align-self:flex-start;">
                        <i class='bx bx-save'></i> Save Changes
                    </button>
                </div>
            </form>
        </div>

        <!-- Reset Password -->
        <div class="card" style="padding:1.75rem;">
            <div class="section-title"><i class='bx bxs-lock-alt'></i> Reset Password</div>
            <form method="POST" style="display:flex;flex-direction:column;gap:1rem;">
                <input type="hidden" name="reset_password" value="1">
                <div>
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" id="newPw" class="input-field" placeholder="Min. 6 characters" required>
                    <div style="height:4px;border-radius:9999px;background:var(--border);margin-top:8px;overflow:hidden;">
                        <div id="pwBar" style="height:100%;border-radius:9999px;transition:all .3s;width:0;"></div>
                    </div>
                </div>
                <div style="background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:10px 14px;font-size:.8rem;color:var(--text-muted);">
                    <i class='bx bxs-info-circle' style="color:var(--primary);"></i>
                    This will override the user's current password. The user must be notified manually.
                </div>
                <div>
                    <button type="submit" class="btn" style="background:#1e293b;color:white;align-self:flex-start;" onclick="return confirm('Reset password for <?= htmlspecialchars($user['username'], ENT_QUOTES) ?>?')">
                        <i class='bx bxs-key'></i> Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Print History -->
    <div class="card">
        <div style="padding:1.25rem 1.5rem;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);">
            <h2 style="font-size:1rem;font-weight:700;color:var(--text);">
                <i class='bx bx-history' style="color:var(--primary);"></i> Print History
                <span style="font-size:.8rem;font-weight:600;color:var(--text-faint);margin-left:8px;"><?= $stats['total'] ?> jobs · <?= $stats['total_pages'] ?> total pages</span>
            </h2>
            <span style="font-size:.85rem;font-weight:700;color:#22c55e;">₹<?= number_format($stats['paid'],2) ?> collected</span>
        </div>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr><th>File Name</th><th>Date</th><th>Config</th><th>Pages</th><th>Copies</th><th>Cost</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php if ($jobs->num_rows === 0): ?>
                    <tr><td colspan="7" style="text-align:center;padding:2.5rem;color:var(--text-faint);">
                        <i class='bx bx-printer' style="font-size:2rem;display:block;margin-bottom:.5rem;opacity:.4;"></i>No print jobs yet.
                    </td></tr>
                <?php else: while($row = $jobs->fetch_assoc()):
                $s = strtolower($row['status']);
                        if ($s == 'done') {
                            $pill = 'pill-completed';   // Green
                        } elseif ($s == 'printing') {
                            $pill = 'pill-failed';      // Red
                        } else {
                            $pill = 'pill-pending';     // Yellow
                        }
                ?>
                <tr>
                    <td style="font-weight:600;color:var(--text);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($row['file_name']) ?>">
                        <?= htmlspecialchars($row['file_name']) ?>
                    </td>
                    <td style="color:var(--text-muted);font-size:.8rem;white-space:nowrap;"><?= date('d M Y, h:i A', strtotime($row['uploaded_at'])) ?></td>
                    <td>
                        <span style="background:var(--surface2);border:1px solid var(--border);color:var(--text-muted);font-size:.73rem;font-weight:600;padding:3px 8px;border-radius:6px;white-space:nowrap;">
                            <?= strtoupper($row['print_type']) ?> · <?= ucfirst($row['print_sides']) ?>
                        </span>
                    </td>
                    <td style="font-weight:600;color:var(--text);text-align:center;"><?= $row['pages'] ?></td>
                    <td style="font-weight:600;color:var(--text);text-align:center;"><?= $row['copies'] ?></td>
                    <td style="font-weight:700;color:var(--text);white-space:nowrap;">₹<?= number_format($row['cost'],2) ?></td>
                    <td><span class="pill <?= $pill ?>"><?= ucfirst($row['status']) ?></span></td>
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
// Password strength
const pw=document.getElementById('newPw'),bar=document.getElementById('pwBar');
if(pw)pw.addEventListener('input',function(){
    const v=pw.value;let s=0;
    if(v.length>=6)s++;if(v.length>=10)s++;if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;
    bar.style.width=['0%','20%','40%','60%','80%','100%'][s];
    bar.style.background=['','#ef4444','#f97316','#eab308','#22c55e','#0ea5e9'][s];
});
</script>
</body>
</html>
