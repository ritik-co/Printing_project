<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$username = htmlspecialchars($_SESSION['username']);
$email = $_SESSION['email'];

// Fetch card stats
$totalPrints = 0;
$pendingJobs = 0;
$completedJobs = 0;

$statsStmt = $conn->prepare("SELECT 
    COUNT(*) AS total,
    SUM(status='Pending') AS pending,
    SUM(status='Completed') AS completed
    FROM print_jobs WHERE email = ?");
$statsStmt->bind_param("s", $email);
$statsStmt->execute();
$statsStmt->bind_result($totalPrints, $pendingJobs, $completedJobs);
$statsStmt->fetch();
$statsStmt->close();

// Fetch recent print history
$history = [];
$historyStmt = $conn->prepare("SELECT file_name, pages, status, uploaded_at, copies, print_type, print_sides FROM print_jobs WHERE email = ? ORDER BY uploaded_at DESC LIMIT 5");
$historyStmt->bind_param("s", $email);
$historyStmt->execute();
$historyStmt->bind_result($fileName, $pages, $status, $uploadedAt, $copies, $printType, $printSides);
while ($historyStmt->fetch()) {
    $history[] = [
        'file_name' => $fileName,
        'pages' => $pages,
        'status' => $status,
        'uploaded_at' => $uploadedAt,
        'copies' => $copies,
        'print_type' => $printType,
        'print_sides' => $printSides
    ];
}
$theme = isset($_SESSION['theme']) ? $_SESSION['theme'] : 'light';
echo "<script>document.documentElement.setAttribute('data-theme', '$theme');</script>";

$historyStmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Hyperlocal Print System</title>
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
        .main {
            margin-left: 240px;
            padding: 40px 30px 30px 30px;
            min-height: 100vh;
            background: rgba(30,30,47,0.85);
        }
        .welcome-banner {
            background: rgba(0,204,255,0.12);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 2px 12px rgba(0,204,255,0.08);
        }
        .welcome-banner h1 {
            font-size: 2.5rem;
            color: #00ccff;
            margin: 0;
        }
        .welcome-banner p {
            font-size: 1.2rem;
            color: #fff;
            margin-top: 8px;
        }
        .top-bar {
            margin-bottom: 20px;
        }
        .top-bar h1 {
            font-size: 26px;
            color: #00ccff;
        }
        .logout-btn {
            background: #ff4d4d;
            padding: 10px 18px;
            border: none;
            color: #fff;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 20px;
        }
        .qr-card {
            margin: 30px auto;
            background-color: #1e1e2f;
            text-align: center;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.1);
            width: 90%;
            max-width: 500px;
            border: 2px solid #00ccff;
        }
        .qr-card h3 {
            color: #00ccff;
            margin-bottom: 10px;
            font-size: 22px;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-shadow: 1px 1px 5px #00ccff;
        }
        .qr-card p {
            color: #ccc;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .qr-card img {
            width: 180px;
            height: 180px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 255, 255, 0.2);
        }
        .cards {
            margin-top: 30px;
            display: flex;
            gap: 30px;
            justify-content: flex-start;
            flex-wrap: wrap;
        }
        .card {
            background: #29293d;
            padding: 25px 40px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 0 15px rgba(0,0,0,0.3);
            min-width: 220px;
            flex: 1 1 220px;
        }
        .card h3 {
            margin-bottom: 10px;
            font-size: 20px;
        }
        .card p {
            font-size: 28px;
            font-weight: bold;
            color: #00ccff;
        }
        .table-section {
            margin-top: 40px;
        }
        .table-section h2 {
            margin-bottom: 15px;
            color: #00ccff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #29293d;
            border-radius: 10px;
            overflow: hidden;
        }
        th, td {
            padding: 14px 20px;
            text-align: left;
            border-bottom: 1px solid #444;
        }
        th {
            background: #33334d;
            color: #00ccff;
        }
        tr:hover {
            background-color: #33334d;
        }
        #uploadForm {
            display: block;
            text-align: left;
            padding: 20px;
            border-radius: 12px;
            background-color: #2b2b3d;
        }
        #uploadForm label {
            color: #f0f0f0;
            font-weight: 600;
            margin-top: 12px;
            display: block;
        }
        #uploadForm input[type="file"],
        #uploadForm input[type="number"],
        #uploadForm select {
            width: 100%;
            padding: 8px 12px;
            margin-bottom: 15px;
            border: 2px solid #00ccff;
            border-radius: 8px;
            background: #0d0d1a;
            color: #00ffff;
            font-size: 14px;
            transition: 0.3s;
        }
        #uploadForm input[type="file"]::file-selector-button {
            background: #00ccff;
            border: none;
            padding: 6px 12px;
            color: #1e1e2f;
            font-weight: bold;
            border-radius: 5px;
            cursor: pointer;
        }
        #uploadForm input:focus,
        #uploadForm select:focus {
            outline: none;
            border-color: #00ccff;
            box-shadow: 0 0 6px #00ccff;
        }
        #uploadForm button {
            background: #00ccff;
            color: #1e1e2f;
            border: none;
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.3s ease;
            width: 100%;
        }
        #uploadForm button:hover {
            background: #00aacc;
            transform: scale(1.03);
            color: #fff;
        }
        @media screen and (max-width: 900px) {
            .main { margin-left: 0; padding: 20px; }
            .sidebar { position: static; width: 100%; height: auto; }
            .cards { flex-direction: column; gap: 20px; }
        }
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
        .settings-container,
        .card,
        .qr-card,
        table,
        #uploadForm {
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
        input, select {
            background: #f7f8ff !important;
            color: #000 !important;
        }
        #uploadForm input[type="file"]::file-selector-button {
            background: #00ccff !important;
            color: #1e1e2f !important;
        }
        #uploadForm button {
            background: #00ccff !important;
            color: #1e1e2f !important;
        }
        #uploadForm button:hover {
            background: #00aacc !important;
            color: #fff !important;
        }
        .card p,
        .table-section h2,
        h2,
        h3 {
            color: #007a99 !important;
        }
        <?php endif; ?>
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>Print Panel</h2>
        <a href="dashboard.php"><i class='bx bx-grid-alt'></i> Dashboard</a>
        <a href="upload.php"><i class='bx bx-upload'></i> Upload File</a>
        <a href="print_history.php"><i class='bx bx-history'></i> Print History</a>
        <a href="settings.php"><i class='bx bx-cog'></i> Settings</a>
        <form action="logout.php" method="post">
            <button type="submit" class="logout-btn" style="width:100%;">Logout</button>
        </form>
    </div>

    <!-- Main Content -->
    <div class="main">
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h1>👋 Welcome, <?php echo $username; ?>!</h1>
            <p>Glad to see you back. Here’s your print summary:</p>
        </div>

        <!-- Upload via QR Code Section
        <div class="card">
            <h3>Upload & Print Options</h3>
            <form method="POST" enctype="multipart/form-data" id="uploadForm" action="upload.php">
                <label for="document">Choose File:</label>
                <input type="file" name="document" id="document" accept=".pdf,.doc,.docx" required>
                <label for="print_type">Print Type:</label>
                <select name="print_type" id="print_type" required>
                    <option value="bw">Black & White</option>
                    <option value="color">Color</option>
                </select>
                <label for="print_sides">Print Sides:</label>
                <select name="print_sides" id="print_sides" required>
                    <option value="single">Single Side</option>
                    <option value="double">Double Side</option>
                </select>
                <label for="copies">Number of Copies:</label>
                <input type="number" name="copies" id="copies" min="1" value="1" required>
                <div style="margin-top:10px;">
                    <span id="costDisplay">₹0.00</span>
                </div>
                <button type="submit">Upload & Print <i class='bx bx-printer' style="margin-left:8px; font-size:1.2em; vertical-align:middle;"></i></button>
            </form>
            <div style="margin-top:18px;">
                <h4 style="color:#00ccff;">Or Scan QR Code:</h4>
                <video id="preview" width="300" height="200" style="border:1px solid #00ccff; border-radius:8px;"></video>
            </div>
        </div> -->

        <!-- QR Code Display
        <div class="card qr-card">
            <p>Scan to go to Upload Section </p>
            <img src="qrcode.png" alt="QR Code" />
        </div> -->
            <?php
                $latestFile = '';
                if (!empty($history)) {
                    $latestFile = 'uploads/' . rawurlencode($history[0]['file_name']); // make sure uploads/ is accessible
                }
            ?>


        <!-- QR Code Display -->
<div class="card qr-card">
    <p>Scan to go to Upload Section </p>
    <img src="qrcode.png" alt="QR Code" />

    <!-- Print Uploaded File Button -->
    <?php if (!empty($latestFile)): ?>
        <div style="margin-top: 20px;">
        <a href="<?php echo $latestFile; ?>" target="_blank" style="
        background: #00ccff;
                color: #1e1e2f;
                border: none;
                padding: 10px 24px;
                border-radius: 12px;
                font-weight: bold;
                font-size: 16px;
                text-decoration: none;
                display: inline-block;
                transition: background 0.3s ease, transform 0.3s ease;">
                Print Last Uploaded File <i class='bx bx-printer' style="margin-left:8px; font-size:1.2em; vertical-align:middle;"></i>
            </a>
        </div>
    <?php else: ?>
        <p style="color: #ccc; margin-top: 10px;">No recent file found to print.</p>
    <?php endif; ?>
</div>


        <!-- Cards -->
        <div class="cards">
            <div class="card"><h3>Total Prints</h3><p><?php echo $totalPrints; ?></p></div>
            <div class="card"><h3>Pending Jobs</h3><p><?php echo $pendingJobs; ?></p></div>
            <div class="card"><h3>Completed Jobs</h3><p><?php echo $completedJobs; ?></p></div>
        </div>

        <!-- History Table -->
        <div class="table-section">
            <h2>Recent Print History</h2>
            <table>
                <thead>
                    <tr>
                        <th>File Name</th>
                        <th>Pages</th>
                        <th>Copies</th>
                        <th>Type</th>
                        <th>Sides</th>
                        <th>Status</th>
                        <th>Uploaded At</th>
                        <th>Total Cost</th>
                        <th>Print</th>
                        <th>Delete</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr><td colspan="10" style="text-align:center;">No print jobs found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($history as $row): ?>
                            <?php
                                // Calculate cost
                                $copies = isset($row['copies']) ? $row['copies'] : 1;
                                $type = isset($row['print_type']) ? $row['print_type'] : 'bw';
                                $sides = isset($row['print_sides']) ? $row['print_sides'] : 'single';
                                $pages = $row['pages'];
                                if ($type === 'bw') {
                                    $pricePerPage = ($sides === 'double') ? 1.00 : 2.00;
                                } else {
                                    $pricePerPage = ($sides === 'double') ? 3.00 : 5.00;
                                }
                                $totalCost = $pages * $copies * $pricePerPage;
                                // Debug: Log print_type to verify database value
                                error_log("Print Job: {$row['file_name']} | Print Type: $type | Sides: $sides | Cost: $totalCost");
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['file_name']); ?></td>
                                <td><?php echo htmlspecialchars($pages); ?></td>
                                <td><?php echo htmlspecialchars($copies); ?></td>
                                <td><?php echo htmlspecialchars($type === 'bw' ? 'B&W' : 'Color'); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($sides)); ?></td>
                                <td style="color: <?php echo ($row['status'] === 'Completed') ? 'lime' : 'orange'; ?>;">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['uploaded_at']); ?></td>
                                <td style="color:#00ccff;">₹<?php echo number_format($totalCost, 2); ?></td>
                                <td style="text-align:center;">
                                    <a href="print.php?file=<?php echo urlencode($row['file_name']); ?>" title="Print">
                                        <i class='bx bx-printer' style="font-size:1.5em; color:#00ccff; cursor:pointer;"></i>
                                    </a>
                                </td>
                                <td>
                                    <form method="POST" action="delete.php" onsubmit="return confirm('Are you sure you want to delete this document?');" style="display:inline;">
                                        <input type="hidden" name="file_name" value="<?php echo htmlspecialchars($row['file_name']); ?>">
                                        <button type="submit" style="background:#ff4d4d; color:#fff; border:none; padding:6px 12px; border-radius:6px; cursor:pointer;">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script>
        const scannedInput = document.getElementById('scanned_email');
        const uploadForm = document.getElementById('uploadForm');
        const fileInput = uploadForm.querySelector('input[name="document"]');
        const printTypeSelect = document.getElementById('print_type');
        const printSidesSelect = document.getElementById('print_sides');
        const copiesInput = document.getElementById('copies');
        const costDisplay = document.getElementById('costDisplay');

        function onScanSuccess(decodedText, decodedResult) {
            const urlParams = new URLSearchParams(new URL(decodedText).search);
            if (urlParams.get('print')) {
                window.print();
            } else {
                window.location.href = decodedText;
            }
        }

        const html5QrCode = new Html5Qrcode("preview");
        html5QrCode.start(
            { facingMode: "environment" },
            {
                fps: 10,
                qrbox: { width: 250, height: 250 }
            },
            onScanSuccess
        );

        // Cost calculation logic (aligned with upload.php)
        function updateCost() {
            let pages = 1; // Default for demo, actual page count needs backend
            let copies = parseInt(copiesInput.value) || 1;
            let printType = printTypeSelect.value;
            let printSides = printSidesSelect.value;
            let pricePerPage;
            if (printType === 'bw') {
                pricePerPage = (printSides === 'double') ? 1.00 : 2.00;
            } else {
                pricePerPage = (printSides === 'double') ? 3.00 : 5.00;
            }
            let totalCost = pages * copies * pricePerPage;
            costDisplay.textContent = "₹" + totalCost.toFixed(2);
        }

        printTypeSelect.addEventListener('change', updateCost);
        printSidesSelect.addEventListener('change', updateCost);
        copiesInput.addEventListener('input', updateCost);
        fileInput.addEventListener('change', updateCost);

        updateCost();
    </script>
</body>
</html>