<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$email = $_SESSION['email'];
$theme = isset($_SESSION['theme']) ? $_SESSION['theme'] : 'dark';

// Fetch print history
$stmt = $conn->prepare("SELECT file_name, uploaded_at, status, cost, print_type, print_sides, pages, copies FROM print_jobs WHERE user_id = ? ORDER BY uploaded_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print History | Hyperlocal Print System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css">
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

        .table-container {
            background-color: #1e1e2f;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 0 15px rgba(0,204,255,0.15);
        }

        h2 {
            color: #00ccff;
            margin-bottom: 20px;
        }

        .table-container table {
            background-color: #1e1e2f;
            color: #eee;
            width: 100%;
        }

        .table-container th,
        .table-container td {
            color: #eee !important;
            background-color: #1e1e2f !important;
            border-color: #333 !important;
        }

        .table-container thead th {
            background-color: #2a2a3d !important;
            color: #00ccff !important;
        }

        .table-container tbody tr:nth-child(odd) {
            background-color: #262637 !important;
        }

        .status-pending { color: #fbc02d; }
        .status-printed { color: #66bb6a; }
        .status-failed { color: #e57373; }

        <?php if ($theme === 'light'): ?>
        body {
            background: #f0f2f8 url('ba1.jpg') no-repeat center center fixed;
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

        .table-container,
        .table-container table {
            background-color: #ffffff !important;
            color: #000 !important;
        }

        .table-container th,
        .table-container td {
            color: #000 !important;
            border-color: #ccc !important;
        }

        .table-container thead th {
            background-color: #dfe4ff !important;
            color: #007a99 !important;
        }

        .table-container tbody tr:nth-child(odd) {
            background-color: #f8f9fc !important;
        }
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
    <div class="table-container">
        <h2>📄 Your Print History</h2>
        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle">
                <thead>
                    <tr>
                        <th>File Name</th>
                        <th>Uploaded At</th>
                        <th>Status</th>
                        <th>Type</th>
                        <th>Sides</th>
                        <th>Pages</th>
                        <th>Copies</th>
                        <th>Cost (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['file_name']) ?></td>
                                <td><?= date("d M Y, h:i A", strtotime($row['uploaded_at'])) ?></td>
                                <td class="<?= $row['status'] === 'Printed' ? 'status-printed' : ($row['status'] === 'Failed' ? 'status-failed' : 'status-pending') ?>">
                                    <?= ucfirst($row['status']) ?>
                                </td>
                                <td><?= ucfirst($row['print_type']) ?></td>
                                <td><?= ucfirst($row['print_sides']) ?></td>
                                <td><?= $row['pages'] ?></td>
                                <td><?= $row['copies'] ?></td>
                                <td><?= number_format($row['cost'], 2) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8">No print history found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    document.documentElement.setAttribute('data-theme', '<?= $theme ?>');
</script>

</body>
</html>
