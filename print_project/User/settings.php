<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['email'])) {
    header("Location: login.php"); exit();
}

$user_id  = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);
$email    = htmlspecialchars($_SESSION['email']);
$message  = ''; $msgType = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $newUsername = trim($_POST['username']);
    $newEmail    = trim($_POST['email']);
    if (empty($newUsername) || empty($newEmail)) {
        $message = "Username and email cannot be empty."; $msgType = 'error';
    } else {
        $stmt = $conn->prepare("UPDATE users SET username=?, email=? WHERE id=?");
        $stmt->bind_param("ssi", $newUsername, $newEmail, $user_id);
        if ($stmt->execute()) {
            $_SESSION['username'] = $newUsername;
            $_SESSION['email']    = $newEmail;
            $username = htmlspecialchars($newUsername);
            $email    = htmlspecialchars($newEmail);
            $message  = "Profile updated successfully!"; $msgType = 'success';
        } else { $message = "Error updating profile."; $msgType = 'error'; }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new     = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    if ($new !== $confirm) {
        $message = "New passwords do not match."; $msgType = 'error';
    } elseif (strlen($new) < 6) {
        $message = "Password must be at least 6 characters."; $msgType = 'error';
    } else {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute(); $stmt->bind_result($hashed); $stmt->fetch(); $stmt->close();
        if (password_verify($current, $hashed)) {
            $newHash = password_hash($new, PASSWORD_BCRYPT);
            $upd = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $upd->bind_param("si", $newHash, $user_id);
            $upd->execute();
            $message = "Password updated successfully!"; $msgType = 'success';
        } else { $message = "Current password is incorrect."; $msgType = 'error'; }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | HyperPrint</title>
    <script>(function(){ document.documentElement.setAttribute('data-theme', localStorage.getItem('hp_theme')||'light'); })();</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--primary:#0ea5e9;--primary-hover:#0284c7;--primary-soft:#f0f9ff;--sidebar-width:272px;--bg:#f8fafc;--surface:#ffffff;--surface2:#f8fafc;--border:#e2e8f0;--text:#0f172a;--text-muted:#64748b;--text-faint:#94a3b8;--shadow-sm:0 1px 3px rgba(0,0,0,.06);--shadow-md:0 4px 16px rgba(0,0,0,.08);--shadow-lg:0 10px 40px rgba(0,0,0,.10);--radius-sm:10px;--radius-md:16px;--radius-lg:24px;}
        [data-theme="dark"]{--bg:#0c1220;--surface:#141e30;--surface2:#1a2540;--border:#1e3050;--text:#f0f6ff;--text-muted:#8eaac8;--text-faint:#4a6a8a;--shadow-sm:0 1px 3px rgba(0,0,0,.3);--shadow-md:0 4px 16px rgba(0,0,0,.35);--shadow-lg:0 10px 40px rgba(0,0,0,.4);--primary-soft:#0c2a40;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;transition:background .3s,color .3s;}

        /* ── SIDEBAR ── */
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

        /* ── LAYOUT ── */
        .main-wrapper{margin-left:var(--sidebar-width);padding:2rem;min-height:100vh;transition:margin-left .35s ease;}
        #overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:850;backdrop-filter:blur(2px);}
        #overlay.active{display:block;}
        #mobile-toggle{display:none;position:fixed;top:1rem;right:1rem;width:44px;height:44px;background:var(--surface);border:1px solid var(--border);border-radius:12px;align-items:center;justify-content:center;z-index:1000;cursor:pointer;box-shadow:var(--shadow-md);color:var(--text);}

        /* ── SETTINGS CARDS ── */
        .settings-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);padding:2rem;transition:background .3s,border-color .3s;}
        .section-title{font-size:1.05rem;font-weight:800;color:var(--text);display:flex;align-items:center;gap:10px;margin-bottom:1.5rem;}
        .section-title i{font-size:1.4rem;color:var(--primary);}
        .form-label{display:block;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text-faint);margin-bottom:7px;}
        .input-field{width:100%;padding:12px 16px;border-radius:var(--radius-sm);border:1.5px solid var(--border);background:var(--surface2);color:var(--text);font-family:inherit;font-size:.95rem;outline:none;transition:all .2s;}
        .input-field:focus{border-color:var(--primary);background:var(--surface);box-shadow:0 0 0 4px rgba(14,165,233,.10);}
        .input-field::placeholder{color:var(--text-faint);}
        .btn-primary{padding:11px 24px;background:var(--primary);color:white;border:none;border-radius:var(--radius-sm);font-family:inherit;font-size:.875rem;font-weight:700;cursor:pointer;transition:all .2s;box-shadow:0 4px 12px rgba(14,165,233,.25);}
        .btn-primary:hover{background:var(--primary-hover);transform:translateY(-1px);}
        .btn-dark{padding:11px 24px;background:#1e293b;color:white;border:none;border-radius:var(--radius-sm);font-family:inherit;font-size:.875rem;font-weight:700;cursor:pointer;transition:all .2s;}
        .btn-dark:hover{background:#0f172a;transform:translateY(-1px);}
        [data-theme="dark"] .btn-dark{background:var(--surface2);border:1px solid var(--border);}
        [data-theme="dark"] .btn-dark:hover{background:var(--border);}

        /* Danger zone */
        .danger-zone{padding:2rem;border-radius:var(--radius-lg);border:2px dashed #fca5a5;background:#fff5f5;transition:background .3s;}
        [data-theme="dark"] .danger-zone{background:#1a0a0a;border-color:#7f1d1d;}

        /* Alerts */
        .alert{display:flex;align-items:center;gap:10px;padding:13px 16px;border-radius:var(--radius-sm);font-size:.875rem;font-weight:600;margin-bottom:1.5rem;}
        .alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#16a34a;}
        .alert-error{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;}
        [data-theme="dark"] .alert-success{background:#052e16;border-color:#166534;}
        [data-theme="dark"] .alert-error{background:#2a0a0a;border-color:#7f1d1d;}

        /* Theme picker */
        .theme-option{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-radius:var(--radius-md);border:2px solid var(--border);cursor:pointer;transition:all .2s;background:var(--surface2);}
        .theme-option:hover,.theme-option.selected{border-color:var(--primary);background:var(--primary-soft);}
        .theme-dot{width:20px;height:20px;border-radius:50%;border:2px solid var(--border);transition:all .2s;flex-shrink:0;}
        .theme-option.selected .theme-dot{background:var(--primary);border-color:var(--primary);}

        /* Password strength */
        .pw-track{height:4px;border-radius:9999px;background:var(--border);margin-top:8px;overflow:hidden;}
        .pw-bar{height:100%;border-radius:9999px;transition:all .3s;width:0;}

        /* Page header */
        .page-header{margin-bottom:2rem;}
        .page-header h1{font-size:1.6rem;font-weight:800;color:var(--text);letter-spacing:-.03em;}
        .page-header p{color:var(--text-muted);font-size:.9rem;margin-top:4px;}

        /* Grid */
        .two-col{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}

        /* Responsive */
        @media(max-width:1024px){
            #sidebar{transform:translateX(-100%);}
            #sidebar.open{transform:translateX(0);box-shadow:0 8px 32px rgba(0,0,0,.18);}
            .main-wrapper{margin-left:0!important;padding:1rem;padding-top:4.5rem;}
            #mobile-toggle{display:flex!important;}
        }
        @media(max-width:600px){
            .two-col{grid-template-columns:1fr!important;}
        }
    </style>
</head>
<body>

<!-- ═══ SIDEBAR (INLINE) ═══ -->
<div id="sidebar">
    <aside class="sidebar-inner">
        <div class="sidebar-logo">
            <div class="logo-icon"><i class='bx bxs-printer'></i></div>
            <span class="logo-text">HyperPrint</span>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class='bx bxs-grid-alt'></i><span>Dashboard</span>
            </a>
            <a href="upload.php" class="nav-item">
                <i class='bx bxs-cloud-upload'></i><span>Upload File</span>
            </a>
            <a href="history.php" class="nav-item">
                <i class='bx bxs-time-five'></i><span>Print History</span>
            </a>
            <a href="settings.php" class="nav-item active">
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

    <div class="page-header">
        <h1>Account Settings ⚙️</h1>
        <p>Manage your profile, security, and preferences.</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $msgType ?>">
            <i class='bx <?= $msgType==='success' ? 'bxs-check-circle' : 'bxs-error-circle' ?>' style="font-size:1.2rem;"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div style="max-width:860px;width:100%;margin:auto;display:flex;flex-direction:column;gap:1.5rem;">

        <!-- ── Profile ── -->
        <div class="settings-card">
            <div class="section-title">
                <i class='bx bxs-user-circle'></i> Profile Details
            </div>
            <form method="POST">
                <input type="hidden" name="update_profile" value="1">
                <div class="two-col" style="margin-bottom:1rem;">
                    <div>
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="input-field" value="<?= $username ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="input-field" value="<?= $email ?>" required>
                    </div>
                </div>
                <button type="submit" class="btn-primary">Save Profile</button>
            </form>
        </div>

        <!-- ── Password ── -->
        <div class="settings-card">
            <div class="section-title">
                <i class='bx bxs-shield-alt'></i> Security &amp; Password
            </div>
            <form method="POST" style="display:flex;flex-direction:column;gap:1rem;">
                <input type="hidden" name="change_password" value="1">
                <div>
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="input-field" placeholder="••••••••" required>
                </div>
                <div class="two-col">
                    <div>
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" id="newPw" class="input-field" placeholder="Min. 6 chars" required>
                        <div class="pw-track"><div class="pw-bar" id="pwBar"></div></div>
                    </div>
                    <div>
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" id="confirmPw" class="input-field" placeholder="••••••••" required>
                        <p id="pwMatch" style="font-size:.75rem;margin-top:6px;font-weight:600;display:none;"></p>
                    </div>
                </div>
                <div><button type="submit" class="btn-dark">Update Password</button></div>
            </form>
        </div>

        <!-- ── Appearance ── -->
        <div class="settings-card">
            <div class="section-title">
                <i class='bx bxs-palette'></i> Appearance
            </div>
            <p style="font-size:.875rem;color:var(--text-muted);margin-bottom:1.25rem;">Choose between light and dark mode. Your preference is saved locally.</p>
            <div class="two-col">
                <div class="theme-option" id="optLight" onclick="setTheme('light')">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:36px;height:36px;background:#f1f5f9;border-radius:10px;border:1px solid #e2e8f0;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">☀️</div>
                        <div>
                            <div style="font-weight:700;font-size:.875rem;color:var(--text);">Light Mode</div>
                            <div style="font-size:.75rem;color:var(--text-faint);">Clean &amp; bright</div>
                        </div>
                    </div>
                    <div class="theme-dot" id="dotLight"></div>
                </div>
                <div class="theme-option" id="optDark" onclick="setTheme('dark')">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:36px;height:36px;background:#1a2540;border-radius:10px;border:1px solid #1e3050;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">🌙</div>
                        <div>
                            <div style="font-weight:700;font-size:.875rem;color:var(--text);">Dark Mode</div>
                            <div style="font-size:.75rem;color:var(--text-faint);">Easy on the eyes</div>
                        </div>
                    </div>
                    <div class="theme-dot" id="dotDark"></div>
                </div>
            </div>
        </div>

        <!-- ── Danger Zone ── -->
        <div class="danger-zone">
            <div class="section-title" style="color:#dc2626;">
                <i class='bx bxs-error' style="color:#dc2626;"></i> Danger Zone
            </div>
            <p style="font-size:.875rem;color:var(--text-muted);margin-bottom:1.25rem;">Permanently delete your account and all print history. <strong>This cannot be undone.</strong></p>
            <form method="POST" action="delete_account.php" onsubmit="return confirm('⚠️ Permanently delete your account and ALL data. Are you sure?');">
                <div style="max-width:320px;margin-bottom:1rem;">
                    <label class="form-label" style="color:#dc2626;">Confirm your password to delete</label>
                    <input type="password" name="confirm_password" class="input-field" placeholder="Enter your password" required style="border-color:#fca5a5;">
                </div>
                <button type="submit" style="padding:11px 24px;background:#dc2626;color:white;border:none;border-radius:var(--radius-sm);font-family:inherit;font-size:.875rem;font-weight:700;cursor:pointer;transition:all .2s;">
                    <i class='bx bxs-trash-alt' style="margin-right:6px;"></i>Delete My Account
                </button>
            </form>
        </div>

    </div>
</main>

<button id="mobile-toggle"><i class='bx bx-menu' id="toggle-icon" style="font-size:1.4rem;"></i></button>

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
    applyTheme(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
}
function setTheme(theme) {
    applyTheme(theme);
    updateThemePicker();
}
function updateThemePicker() {
    const t = localStorage.getItem('hp_theme') || 'light';
    document.getElementById('optLight').classList.toggle('selected', t === 'light');
    document.getElementById('optDark').classList.toggle('selected',  t === 'dark');
    document.getElementById('dotLight').style.background = t === 'light' ? 'var(--primary)' : '';
    document.getElementById('dotDark').style.background  = t === 'dark'  ? 'var(--primary)' : '';
}

/* ── MOBILE SIDEBAR ── */
function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('overlay').classList.add('active');
    document.getElementById('toggle-icon').className = 'bx bx-x';
    document.getElementById('toggle-icon').style.fontSize = '1.4rem';
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('overlay').classList.remove('active');
    document.getElementById('toggle-icon').className = 'bx bx-menu';
    document.getElementById('toggle-icon').style.fontSize = '1.4rem';
}

document.addEventListener('DOMContentLoaded', function () {
    applyTheme(localStorage.getItem('hp_theme') || 'light');
    updateThemePicker();

    document.getElementById('mobile-toggle').addEventListener('click', function () {
        document.getElementById('sidebar').classList.contains('open') ? closeSidebar() : openSidebar();
    });
    document.getElementById('overlay').addEventListener('click', closeSidebar);
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeSidebar(); });
    document.querySelectorAll('.nav-item').forEach(function(link) {
        link.addEventListener('click', function() { if (window.innerWidth <= 1024) closeSidebar(); });
    });
});

/* ── PASSWORD STRENGTH ── */
const newPw = document.getElementById('newPw');
const bar   = document.getElementById('pwBar');
if (newPw) newPw.addEventListener('input', function() {
    const v = newPw.value;
    let s = 0;
    if (v.length >= 6) s++; if (v.length >= 10) s++;
    if (/[A-Z]/.test(v)) s++; if (/[0-9]/.test(v)) s++; if (/[^A-Za-z0-9]/.test(v)) s++;
    bar.style.width = ['0%','20%','40%','60%','80%','100%'][s];
    bar.style.background = ['','#ef4444','#f97316','#eab308','#22c55e','#0ea5e9'][s];
});

/* ── PASSWORD MATCH ── */
const confirmPw = document.getElementById('confirmPw');
const pwMatch   = document.getElementById('pwMatch');
if (confirmPw) confirmPw.addEventListener('input', function() {
    if (!confirmPw.value) { pwMatch.style.display = 'none'; return; }
    const match = newPw.value === confirmPw.value;
    pwMatch.style.display   = 'block';
    pwMatch.textContent     = match ? '✓ Passwords match' : '✗ Passwords do not match';
    pwMatch.style.color     = match ? '#16a34a' : '#dc2626';
});
</script>
</body>
</html>
