<?php
session_start();
include 'db.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    if (empty($email) || empty($password)) {
        $message = "Please enter both email and password.";
    } else {
        $stmt = $conn->prepare("SELECT id,username,email,password FROM users WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows == 1) {
            $stmt->bind_result($id, $username, $db_email, $hashed_password);
            $stmt->fetch();
            if (password_verify($password, $hashed_password)) {
                $_SESSION["user_id"]  = $id;
                $_SESSION["username"] = $username;
                $_SESSION["email"]    = $db_email;
                header("Location: dashboard.php"); exit;
            } else { $message = "Incorrect password."; }
        } else { $message = "No account found with that email."; }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | HyperPrint</title>
    <script>(function(){ document.documentElement.setAttribute('data-theme', localStorage.getItem('hp_theme')||'light'); })();</script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root{--primary:#0ea5e9;--primary-hover:#0284c7;--primary-soft:#f0f9ff;--bg:#f1f5f9;--surface:#ffffff;--surface2:#f8fafc;--border:#e2e8f0;--text:#0f172a;--text-muted:#64748b;--text-faint:#94a3b8;--radius-sm:10px;--radius-md:16px;--radius-lg:24px;--radius-xl:32px;}
        [data-theme="dark"]{--bg:#0c1220;--surface:#141e30;--surface2:#1a2540;--border:#1e3050;--text:#f0f6ff;--text-muted:#8eaac8;--text-faint:#4a6a8a;--primary-soft:#0c2a40;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem;transition:background .3s,color .3s;}
        .auth-wrap{width:100%;max-width:440px;}
        .auth-logo{text-align:center;margin-bottom:2rem;}
        .logo-icon{width:56px;height:56px;background:var(--primary);border-radius:16px;display:inline-flex;align-items:center;justify-content:center;color:white;font-size:1.75rem;box-shadow:0 8px 24px rgba(14,165,233,.35);margin-bottom:1rem;}
        .auth-logo h1{font-size:1.75rem;font-weight:800;letter-spacing:-.03em;color:var(--text);}
        .auth-logo p{font-size:.9rem;color:var(--text-muted);margin-top:4px;}
        .auth-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-xl);padding:2.25rem;box-shadow:0 20px 40px rgba(0,0,0,.07);transition:background .3s,border-color .3s;}
        [data-theme="dark"] .auth-card{box-shadow:0 20px 40px rgba(0,0,0,.35);}
        .form-label{display:block;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text-faint);margin-bottom:7px;}
        .input-field{width:100%;padding:13px 14px;border-radius:var(--radius-sm);border:1.5px solid var(--border);background:var(--surface2);color:var(--text);font-family:inherit;font-size:.95rem;outline:none;transition:all .2s;}
        .input-field:focus{border-color:var(--primary);background:var(--surface);box-shadow:0 0 0 4px rgba(14,165,233,.12);}
        .input-field::placeholder{color:var(--text-faint);}
        .btn-primary{width:100%;padding:14px;background:var(--primary);color:white;border:none;border-radius:var(--radius-md);font-family:inherit;font-size:1rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .2s;box-shadow:0 4px 16px rgba(14,165,233,.3);}
        .btn-primary:hover{background:var(--primary-hover);transform:translateY(-1px);box-shadow:0 6px 24px rgba(14,165,233,.4);}
        .alert-error{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:12px 16px;border-radius:var(--radius-sm);font-size:.875rem;font-weight:600;display:flex;align-items:center;gap:10px;margin-bottom:1.5rem;}
        [data-theme="dark"] .alert-error{background:#2a0a0a;border-color:#7f1d1d;}
        .divider{border:none;border-top:1px solid var(--border);margin:1.5rem 0;}
        .footer-note{text-align:center;font-size:.72rem;color:var(--text-faint);font-weight:700;text-transform:uppercase;letter-spacing:.15em;margin-top:1.5rem;}
    </style>
</head>
<body>
<div class="auth-wrap">
    <div class="auth-logo">
        <div class="logo-icon"><i class='bx bxs-printer'></i></div>
        <h1>HyperPrint</h1>
        <p>Welcome back — sign in to continue</p>
    </div>

    <div class="auth-card">
        <?php if (!empty($message)): ?>
            <div class="alert-error"><i class='bx bxs-error-circle' style="font-size:1.2rem;flex-shrink:0;"></i><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" style="display:flex;flex-direction:column;gap:1.1rem;">
            <div>
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="input-field" placeholder="you@example.com" required
                    value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
            </div>
            <div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:7px;">
                    <label class="form-label" style="margin-bottom:0;">Password</label>
                    <a href="forgot_password.php" style="font-size:.75rem;font-weight:700;color:var(--primary);text-decoration:none;">Forgot password?</a>
                </div>
                <input type="password" name="password" class="input-field" placeholder="••••••••" required>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" id="remember" name="remember" style="width:16px;height:16px;accent-color:var(--primary);cursor:pointer;">
                <label for="remember" style="font-size:.875rem;color:var(--text-muted);font-weight:500;cursor:pointer;">Keep me signed in</label>
            </div>
            <button type="submit" class="btn-primary">
                Sign In <i class='bx bx-right-arrow-alt' style="font-size:1.2rem;"></i>
            </button>
        </form>

        <hr class="divider">
        <p style="text-align:center;font-size:.875rem;color:var(--text-muted);">
            Don't have an account?
            <a href="registration.php" style="color:var(--primary);font-weight:700;text-decoration:none;margin-left:4px;">Create </a>
        </p>
    </div>

    <p class="footer-note">&copy; 2026 HyperPrint System</p>
</div>
</body>
</html>
