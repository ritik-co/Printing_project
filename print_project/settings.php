<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$email = $_SESSION['email'];

$theme = isset($_SESSION['theme']) ? $_SESSION['theme'] : 'light';

// Handle theme toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['theme'])) {
    $_SESSION['theme'] = $_POST['theme'];
    header("Location: settings.php");
    exit();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $newUsername = trim($_POST['username']);
    $newEmail = trim($_POST['email']);

    if ($newUsername && $newEmail) {
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
        $stmt->bind_param("ssi", $newUsername, $newEmail, $_SESSION['user_id']);
        if ($stmt->execute()) {
            $_SESSION['username'] = $newUsername;
            $_SESSION['email'] = $newEmail;
            header("Location: settings.php");
            exit();
        }
    }
}

// Handle password change
$passChangeMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if ($new !== $confirm) {
        $passChangeMsg = "New passwords do not match.";
    } else {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $stmt->bind_result($hashed);
        $stmt->fetch();
        $stmt->close();

        if (password_verify($current, $hashed)) {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $newHash, $_SESSION['user_id']);
            $stmt->execute();
            $passChangeMsg = "Password updated successfully.";
        } else {
            $passChangeMsg = "Current password incorrect.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings | Hyperlocal Print System</title>
    <link rel="stylesheet" href="form.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: url('ba1.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #fff;
            margin: 0;
        }
        .main {
            margin-left: 240px;
            padding: 40px;
            background: rgba(30,30,47,0.85);
            min-height: 100vh;
        }
        .settings-container {
            background-color: #1e1e2f;
            padding: 30px;
            border-radius: 12px;
            max-width: 700px;
            margin: auto;
            box-shadow: 0 0 15px rgba(0,204,255,0.15);
        }
        h2 {
            color: #00ccff;
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-top: 15px;
            color: #ccc;
        }
        input, select {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: none;
            margin-top: 6px;
            font-size: 16px;
        }
        .btn {
            margin-top: 20px;
            background: #00ccff;
            color: #1e1e2f;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
        }
        .msg {
            margin-top: 10px;
            color: lime;
        }
        .sidebar {
            width: 220px;
            height: 100vh;
            background-color: #29293d;
            padding: 20px;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 2;
            box-shadow: 2px 0 10px rgba(0,0,0,0.2);
        }
        .sidebar h2 {
            color: #00ccff;
            margin-bottom: 30px;
            text-align: center;
        }
        .sidebar a {
            display: flex;
            align-items: center;
            color: #ccc;
            padding: 12px;
            text-decoration: none;
            transition: 0.3s;
        }
        .sidebar a:hover {
            background: #00ccff;
            color: #fff;
            border-radius: 8px;
        }
        .sidebar a i {
            margin-right: 10px;
            font-size: 18px;
        }
        .logout-btn {
            background: #ff4d4d;
            padding: 10px 18px;
            border: none;
            color: #fff;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 20px;
            width: 100%;
        }
        <?php if ($theme === 'light'): ?>
<style>
    body {
        background: #f0f2f8 url('ba1.jpg') no-repeat center center fixed; /* reuse bg */
        background-size: cover;
        color: #000;
    }
    .main {
        background: rgba(255,255,255,0.9);
        color: #000;
    }
    .sidebar {
        background-color: #e4e7f2;
        color: #000;
    }
    .sidebar h2 { color: #007a99; }
    .sidebar a { color: #333; }
    .sidebar a:hover {
        background: #00ccff;
        color: #000;
    }
    .logout-btn {
        background: #ff4d4d;
        color: #fff;
    }

    /* Cards / containers */
    .settings-container,
    .card,
    .qr-card,
    table {
        background: #ffffff !important;
        color: #000 !important;
    }
    .qr-card p,
    label,
    th,
    td {
        color: #000 !important;
    }
    th {
        background: #dfe4ff !important;
        color: #007a99 !important;
    }
    tr:hover {
        background: #eef3ff !important;
    }

    /* Inputs */
    input, select {
        background: #f7f8ff !important;
        color: #000 !important;
    }

    /* Accents */
    .card p,
    .table-section h2,
    h2,
    h3 {
        color: #007a99 !important; /* softer accent for light mode */
    }
</style>
<?php endif; ?>

    </style>
</head>
<body>

<div class="sidebar">
    <h2>Print Panel</h2>
    <a href="dashboard.php"><i class='bx bx-grid-alt'></i> Dashboard</a>
    <a href="upload.php"><i class='bx bx-upload'></i> Upload File</a>
    <a href="print_history.php"><i class='bx bx-history'></i> Print History</a>
    <a href="settings.php"><i class='bx bx-cog'></i> Settings</a>
    <form action="logout.php" method="post">
        <button type="submit" class="logout-btn">Logout</button>
    </form>
</div>

<div class="main">
    <div class="settings-container">
        <h2>⚙️ Settings</h2>

        <!-- Theme Switch -->
        <form method="POST">
            <label for="theme">Theme</label>
            <select name="theme" id="theme">
                <option value="dark" <?= $theme === 'dark' ? 'selected' : '' ?>>Dark</option>
                <option value="light" <?= $theme === 'light' ? 'selected' : '' ?>>Light</option>
            </select>
            <button class="btn" type="submit">Apply Theme</button>
        </form>

        <!-- Profile Update -->
        <form method="POST" style="margin-top: 30px;">
            <input type="hidden" name="update_profile" value="1">
            <label for="username">Username</label>
            <input type="text" name="username" value="<?= htmlspecialchars($username); ?>" required>
            <label for="email">Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($email); ?>" required>
            <button class="btn" type="submit">Update Profile</button>
        </form>

        <!-- Password Change -->
        <form method="POST" style="margin-top: 30px;">
            <input type="hidden" name="change_password" value="1">
            <label>Current Password</label>
            <input type="password" name="current_password" required>
            <label>New Password</label>
            <input type="password" name="new_password" required>
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required>
            <button class="btn" type="submit">Change Password</button>
            <?php if ($passChangeMsg): ?>
                <p class="msg"><?= htmlspecialchars($passChangeMsg); ?></p>
            <?php endif; ?>
        </form>
<!-- Delete Account Card -->
            <div class="card" style="margin: 30px; background-color: #1e1e2f; color: #fff;">
                <h3>Delete My Account</h3>
                <form method="post" action="delete_account.php" onsubmit="return confirm('Are you sure you want to delete your account? This cannot be undone.');">
                    <label for="confirm_password">Enter Password to Confirm:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required style="width: 100%; padding: 10px; margin: 10px 0;">
                    <button type="submit" style="background-color: #ff4d4d; color: white; padding: 10px 20px; border: none;">Delete Account</button>
                </form>
            </div>

                        <!--  -->
    </div>
</div>

<script>
    document.documentElement.setAttribute('data-theme', '<?= $theme ?>');
</script>

</body>
</html>
