<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php"); exit();
}
include 'db.php';

// Create devices table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS devices (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)  NOT NULL,
    type        VARCHAR(50)   NOT NULL DEFAULT 'Printer',
    model       VARCHAR(100)  DEFAULT '',
    location    VARCHAR(150)  DEFAULT '',
    ip_address  VARCHAR(45)   DEFAULT '',
    status      ENUM('Active','Inactive','Maintenance') NOT NULL DEFAULT 'Active',
    login_user  VARCHAR(100)  DEFAULT '',
    login_pass  VARCHAR(255)  DEFAULT '',
    notes       TEXT,
    added_on    DATETIME      DEFAULT CURRENT_TIMESTAMP,
    updated_on  DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$flash = $_SESSION['msg'] ?? ''; $flashErr = $_SESSION['error'] ?? '';
unset($_SESSION['msg'], $_SESSION['error']);

// ── ADD ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_device'])) {
    $name   = trim($_POST['name']);
    $type   = trim($_POST['type']);
    $model  = trim($_POST['model']);
    $loc    = trim($_POST['location']);
    $ip     = trim($_POST['ip_address']);
    $status = in_array($_POST['status'], ['Active','Inactive','Maintenance']) ? $_POST['status'] : 'Active';
    $luser  = trim($_POST['login_user']);
    $lpass  = trim($_POST['login_pass']);
    $notes  = trim($_POST['notes']);
    if (!$name) { $_SESSION['error'] = "Device name is required."; }
    else {
        $stmt = $conn->prepare("INSERT INTO devices (name,type,model,location,ip_address,status,login_user,login_pass,notes) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("sssssssss", $name,$type,$model,$loc,$ip,$status,$luser,$lpass,$notes);
        if ($stmt->execute()) {
            $_SESSION['msg'] = "Device \"$name\" added successfully.";
        } else {
            $_SESSION['error'] = "Error adding device: " . $stmt->error;
        }
        $stmt->close();
    }
    header("Location: admin_devices.php"); exit();
}

// ── UPDATE ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_device'])) {
    $did    = intval($_POST['device_id']);
    $name   = trim($_POST['name']);
    $type   = trim($_POST['type']);
    $model  = trim($_POST['model']);
    $loc    = trim($_POST['location']);
    $ip     = trim($_POST['ip_address']);
    $status = in_array($_POST['status'], ['Active','Inactive','Maintenance']) ? $_POST['status'] : 'Active';
    $luser  = trim($_POST['login_user']);
    $lpass  = trim($_POST['login_pass']);
    $notes  = trim($_POST['notes']);
    $stmt = $conn->prepare("UPDATE devices SET name=?,type=?,model=?,location=?,ip_address=?,status=?,login_user=?,login_pass=?,notes=? WHERE id=?");
    $stmt->bind_param("sssssssssi", $name,$type,$model,$loc,$ip,$status,$luser,$lpass,$notes,$did);
    $_SESSION[$stmt->execute() ? 'msg' : 'error'] = "Device updated successfully.";
    header("Location: admin_devices.php"); exit();
}

// ── DELETE ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_device'])) {
    $did  = intval($_POST['device_id']);
    $stmt = $conn->prepare("DELETE FROM devices WHERE id=?");
    $stmt->bind_param("i", $did);
    if ($stmt->execute()) {
        $_SESSION['msg'] = "Device removed successfully.";
    } else {
        $_SESSION['error'] = "Error removing device.";
    }
    $stmt->close();
    header("Location: admin_devices.php"); exit();
}

// ── FETCH ALL ──
$devices = $conn->query("SELECT * FROM devices ORDER BY added_on DESC");
$editDevice = null;
if (isset($_GET['edit'])) {
    $eid  = intval($_GET['edit']);
    $res  = $conn->query("SELECT * FROM devices WHERE id=$eid");
    if ($res) $editDevice = $res->fetch_assoc();
}

$counts = [
    'total'       => $conn->query("SELECT COUNT(*) FROM devices")->fetch_row()[0],
    'active'      => $conn->query("SELECT COUNT(*) FROM devices WHERE status='Active'")->fetch_row()[0],
    'inactive'    => $conn->query("SELECT COUNT(*) FROM devices WHERE status='Inactive'")->fetch_row()[0],
    'maintenance' => $conn->query("SELECT COUNT(*) FROM devices WHERE status='Maintenance'")->fetch_row()[0],
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devices | HyperPrint Admin</title>
    <script>(function(){ document.documentElement.setAttribute('data-theme', localStorage.getItem('hp_theme')||'light'); })();</script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root{--primary:#0ea5e9;--primary-hover:#0284c7;--primary-soft:#f0f9ff;--sidebar-width:272px;--bg:#f1f5f9;--surface:#ffffff;--surface2:#f8fafc;--border:#e2e8f0;--text:#0f172a;--text-muted:#64748b;--text-faint:#94a3b8;--shadow-sm:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);--shadow-md:0 4px 16px rgba(0,0,0,.08);--radius-sm:10px;--radius-md:16px;--radius-lg:24px;}
        [data-theme="dark"]{--bg:#0c1220;--surface:#141e30;--surface2:#1a2540;--border:#1e3050;--text:#f0f6ff;--text-muted:#8eaac8;--text-faint:#4a6a8a;--primary-soft:#0c2a40;}
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
        .stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.25rem 1.5rem;box-shadow:var(--shadow-sm);position:relative;overflow:hidden;}
        .stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;}
        .stat-card.sky::before{background:var(--primary);}.stat-card.green::before{background:#22c55e;}.stat-card.amber::before{background:#f59e0b;}.stat-card.rose::before{background:#f43f5e;}
        .stat-value{font-size:1.8rem;font-weight:800;letter-spacing:-.04em;margin-top:4px;}
        .stat-label{font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;}
        .card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);overflow:hidden;}
        table{width:100%;border-collapse:collapse;}
        th{background:var(--surface2);color:var(--text-faint);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;padding:14px 16px;text-align:left;border-bottom:1px solid var(--border);}
        td{padding:13px 16px;border-bottom:1px solid var(--border);font-size:.875rem;}
        tr:last-child td{border-bottom:none;}
        tbody tr{transition:background .15s;}
        tbody tr:hover{background:var(--surface2);}
        .page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:2rem;}
        .page-header h1{font-size:1.6rem;font-weight:800;color:var(--text);letter-spacing:-.03em;}
        .btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:var(--radius-sm);font-family:inherit;font-size:.85rem;font-weight:700;cursor:pointer;border:none;transition:all .2s;text-decoration:none;}
        .btn-primary{background:var(--primary);color:white;box-shadow:0 4px 12px rgba(14,165,233,.25);}
        .btn-primary:hover{background:var(--primary-hover);transform:translateY(-1px);}
        .btn-sm{padding:6px 12px;font-size:.78rem;}
        .btn-danger{background:#fee2e2;color:#dc2626;border:1px solid #fecaca;}
        .btn-danger:hover{background:#fecaca;}
        .btn-edit{background:var(--primary-soft);color:var(--primary);border:1px solid rgba(14,165,233,.2);}
        .btn-edit:hover{background:rgba(14,165,233,.15);}
        .alert{display:flex;align-items:center;gap:10px;padding:13px 16px;border-radius:var(--radius-sm);font-size:.875rem;font-weight:600;margin-bottom:1.5rem;}
        .alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#16a34a;}
        .alert-error{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;}
        .form-panel{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.75rem;box-shadow:var(--shadow-sm);margin-bottom:1.5rem;}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
        .form-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;}
        .form-label{display:block;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text-faint);margin-bottom:6px;}
        .input-field{width:100%;padding:11px 14px;border-radius:var(--radius-sm);border:1.5px solid var(--border);background:var(--surface2);color:var(--text);font-family:inherit;font-size:.9rem;outline:none;transition:all .2s;}
        .input-field:focus{border-color:var(--primary);background:var(--surface);box-shadow:0 0 0 3px rgba(14,165,233,.10);}
        .section-title{font-size:1rem;font-weight:800;color:var(--text);margin-bottom:1.25rem;display:flex;align-items:center;gap:8px;}
        .section-title i{color:var(--primary);font-size:1.2rem;}
        /* Status pills */
        .s-active{background:#dcfce7;color:#166534;} .s-inactive{background:#f1f5f9;color:#64748b;} .s-maintenance{background:#fef3c7;color:#92400e;}
        .status-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:9999px;font-size:.72rem;font-weight:700;}
        /* Password toggle */
        .pw-wrap{position:relative;}
        .pw-wrap .input-field{padding-right:42px;}
        .pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-faint);font-size:1.1rem;padding:4px;}
        .pw-toggle:hover{color:var(--primary);}
        @media(max-width:1024px){#sidebar{transform:translateX(-100%);}#sidebar.open{transform:translateX(0);box-shadow:0 8px 32px rgba(0,0,0,.18);}.main-wrapper{margin-left:0!important;padding:1rem;padding-top:4.5rem;}#mobile-toggle{display:flex!important;}}
        @media(max-width:700px){.form-grid,.form-grid-3{grid-template-columns:1fr!important;}}
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
            <a href="admin_jobs.php" class="nav-item"><i class='bx bxs-printer'></i><span>Print Jobs</span></a>
            <a href="admin_collection.php" class="nav-item"><i class='bx bxs-report'></i><span>Daily Collection</span></a>
            <a href="admin_devices.php" class="nav-item active"><i class='bx bxs-devices'></i><span>Devices</span></a>
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
            <h1>Device Management 🖨️</h1>
            <p>Add, edit and manage printers and devices.</p>
        </div>
        <button onclick="toggleForm()" class="btn btn-primary" id="addBtn">
            <i class='bx bx-plus'></i> Add Device
        </button>
    </div>

    <?php if ($flash): ?><div class="alert alert-success"><i class='bx bxs-check-circle'></i><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <?php if ($flashErr): ?><div class="alert alert-error"><i class='bx bxs-error-circle'></i><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;">
        <div class="stat-card sky"><div class="stat-label">Total Devices</div><div class="stat-value" style="color:var(--primary);"><?= $counts['total'] ?></div></div>
        <div class="stat-card green"><div class="stat-label">Active</div><div class="stat-value" style="color:#22c55e;"><?= $counts['active'] ?></div></div>
        <div class="stat-card amber"><div class="stat-label">Maintenance</div><div class="stat-value" style="color:#f59e0b;"><?= $counts['maintenance'] ?></div></div>
        <div class="stat-card rose"><div class="stat-label">Inactive</div><div class="stat-value" style="color:#f43f5e;"><?= $counts['inactive'] ?></div></div>
    </div>

    <!-- Add / Edit Form -->
    <div class="form-panel" id="deviceForm" style="<?= $editDevice ? '' : 'display:none;' ?>">
        <div class="section-title">
            <i class='bx <?= $editDevice ? "bxs-edit" : "bx-plus-circle" ?>'></i>
            <span id="formTitle"><?= $editDevice ? "Edit Device" : "Add New Device" ?></span>
        </div>
        <form method="POST">
            <input type="hidden" name="<?= $editDevice ? 'update_device' : 'add_device' ?>" value="1" id="formAction">
            <?php if ($editDevice): ?>
                <input type="hidden" name="device_id" value="<?= $editDevice['id'] ?>">
            <?php endif; ?>
            <input type="hidden" name="device_id" id="editDeviceId" value="">

            <div class="form-grid-3" style="margin-bottom:1rem;">
                <div>
                    <label class="form-label">Device Name <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="name" class="input-field" placeholder="e.g. HP LaserJet 1" required value="<?= htmlspecialchars($editDevice['name'] ?? '') ?>">
                </div>
                <div>
                    <label class="form-label">Type</label>
                    <select name="type" class="input-field">
                        <?php foreach(['Printer','Scanner','Copier','Multi-Function','Other'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($editDevice['type'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Model</label>
                    <input type="text" name="model" class="input-field" placeholder="e.g. HP M404dn" value="<?= htmlspecialchars($editDevice['model'] ?? '') ?>">
                </div>
            </div>

            <div class="form-grid" style="margin-bottom:1rem;">
                <div>
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="input-field" placeholder="e.g. Room 101, Counter 2" value="<?= htmlspecialchars($editDevice['location'] ?? '') ?>">
                </div>
                <div>
                    <label class="form-label">IP Address</label>
                    <input type="text" name="ip_address" class="input-field" placeholder="e.g. 192.168.1.50" value="<?= htmlspecialchars($editDevice['ip_address'] ?? '') ?>">
                </div>
            </div>

            <div class="form-grid-3" style="margin-bottom:1rem;">
                <div>
                    <label class="form-label">Status</label>
                    <select name="status" class="input-field">
                        <?php foreach(['Active','Inactive','Maintenance'] as $st): ?>
                        <option value="<?= $st ?>" <?= ($editDevice['status'] ?? 'Active') === $st ? 'selected' : '' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Login Username</label>
                    <input type="text" name="login_user" class="input-field" placeholder="Device login username" value="<?= htmlspecialchars($editDevice['login_user'] ?? '') ?>">
                </div>
                <div>
                    <label class="form-label">Login Password</label>
                    <div class="pw-wrap">
                        <input type="password" name="login_pass" id="devPass" class="input-field" placeholder="Device login password" value="<?= htmlspecialchars($editDevice['login_pass'] ?? '') ?>">
                        <button type="button" class="pw-toggle" onclick="toggleDevPass()"><i class='bx bx-show' id="devPassIcon"></i></button>
                    </div>
                </div>
            </div>

            <div style="margin-bottom:1.25rem;">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="input-field" rows="2" placeholder="Any additional notes…" style="resize:vertical;"><?= htmlspecialchars($editDevice['notes'] ?? '') ?></textarea>
            </div>

            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary"><i class='bx bx-save'></i> <span id="saveLabel"><?= $editDevice ? 'Update Device' : 'Save Device' ?></span></button>
                <a href="admin_devices.php" class="btn" style="background:var(--surface2);color:var(--text-muted);border:1px solid var(--border);">Cancel</a>
            </div>
        </form>
    </div>

    <!-- Device List -->
    <div class="card">
        <div style="padding:1.25rem 1.5rem;border-bottom:1px solid var(--border);">
            <h2 style="font-size:1rem;font-weight:700;color:var(--text);">All Devices <span style="font-weight:600;color:var(--text-faint);font-size:.85rem;">(<?= $counts['total'] ?>)</span></h2>
        </div>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr><th>#</th><th>Device</th><th>Type</th><th>Location</th><th>IP Address</th><th>Login</th><th>Status</th><th>Added</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php if ($devices->num_rows === 0): ?>
                    <tr><td colspan="9" style="text-align:center;padding:3rem;color:var(--text-faint);">
                        <i class='bx bx-devices' style="font-size:2.5rem;display:block;margin-bottom:.75rem;opacity:.35;"></i>
                        No devices added yet. Click <strong>Add Device</strong> to get started.
                    </td></tr>
                <?php else: while ($dev = $devices->fetch_assoc()):
                    $pillClass = $dev['status']==='Active' ? 's-active' : ($dev['status']==='Maintenance' ? 's-maintenance' : 's-inactive');
                ?>
                <tr>
                    <td style="color:var(--text-faint);font-size:.8rem;">#<?= $dev['id'] ?></td>
                    <td>
                        <div style="font-weight:700;color:var(--text);"><?= htmlspecialchars($dev['name']) ?></div>
                        <?php if ($dev['model']): ?><div style="font-size:.75rem;color:var(--text-faint);"><?= htmlspecialchars($dev['model']) ?></div><?php endif; ?>
                    </td>
                    <td style="color:var(--text-muted);"><?= htmlspecialchars($dev['type']) ?></td>
                    <td style="color:var(--text-muted);font-size:.85rem;"><?= $dev['location'] ? htmlspecialchars($dev['location']) : '—' ?></td>
                    <td>
                        <?php if ($dev['ip_address']): ?>
                        <code style="background:var(--surface2);border:1px solid var(--border);padding:3px 8px;border-radius:6px;font-size:.78rem;color:var(--text-muted);"><?= htmlspecialchars($dev['ip_address']) ?></code>
                        <?php else: ?><span style="color:var(--text-faint);">—</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($dev['login_user']): ?>
                        <div style="font-size:.8rem;">
                            <span style="color:var(--text-muted);"><?= htmlspecialchars($dev['login_user']) ?></span><br>
                            <span style="color:var(--text-faint);" id="pass_<?= $dev['id'] ?>" data-pass="<?= htmlspecialchars($dev['login_pass']) ?>">••••••••</span>
                            <button onclick="togglePass(<?= $dev['id'] ?>)" style="background:none;border:none;cursor:pointer;color:var(--text-faint);font-size:.85rem;padding:2px 4px;" title="Show/Hide"><i class='bx bx-show'></i></button>
                        </div>
                        <?php else: ?><span style="color:var(--text-faint);">—</span><?php endif; ?>
                    </td>
                    <td><span class="status-pill <?= $pillClass ?>"><?= $dev['status'] ?></span></td>
                    <td style="color:var(--text-faint);font-size:.78rem;white-space:nowrap;"><?= date('d M Y', strtotime($dev['added_on'])) ?></td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <a href="?edit=<?= $dev['id'] ?>" class="btn btn-sm btn-edit"><i class='bx bx-edit'></i></a>
                            <form method="POST" onsubmit="return confirm('Delete device «<?= htmlspecialchars($dev['name'], ENT_QUOTES) ?>»?');" style="margin:0;">
                                <input type="hidden" name="delete_device" value="1">
                                <input type="hidden" name="device_id" value="<?= $dev['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><i class='bx bx-trash'></i></button>
                            </form>
                        </div>
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
    // Scroll to form if editing
    <?php if ($editDevice): ?>
    document.getElementById('deviceForm').scrollIntoView({behavior:'smooth',block:'start'});
    <?php endif; ?>
});
function toggleForm(){
    const f=document.getElementById('deviceForm');
    const visible = f.style.display !== 'none';
    f.style.display = visible ? 'none' : 'block';
    if (!visible) f.scrollIntoView({behavior:'smooth',block:'start'});
}
function toggleDevPass(){
    const i=document.getElementById('devPass'),ic=document.getElementById('devPassIcon');
    if(i.type==='password'){i.type='text';ic.className='bx bx-hide';}
    else{i.type='password';ic.className='bx bx-show';}
}
function togglePass(id){
    const el=document.getElementById('pass_'+id);
    if(!el)return;
    const showing=el.dataset.showing==='1';
    el.textContent=showing?'••••••••':el.dataset.pass;
    el.dataset.showing=showing?'':'1';
}
</script>
</body>
</html>
