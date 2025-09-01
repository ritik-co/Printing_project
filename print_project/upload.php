<?php
session_start();
require_once 'db.php';

// Theme variable
$theme = $_SESSION['theme'] ?? 'dark'; // Default to dark if not set

$email = $_SESSION['email'] ?? '';
// Get user_id using email
$user_id = null;
if ($email) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document']) && $user_id) {
    $file = $_FILES['document'];
    $allowed = ['pdf', 'doc', 'docx'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        die("Only PDF, DOC, DOCX allowed.");
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        die("Upload failed.");
    }

    $upload_dir = "uploads/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $filename = time() . "_" . basename($file['name']);
    $filepath = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        die("Error saving file.");
    }

    // Count pages (basic for PDF)
    $page_count = 1;
    if ($ext === 'pdf') {
        $content = file_get_contents($filepath);
        preg_match_all("/\/Type\s*\/Page[^s]/", $content, $matches);
        $page_count = count($matches[0]) ?: 1;
    }

    // Cost and estimated time
    $print_type = $_POST['print_type'] ?? 'bw';
    $print_sides = $_POST['print_sides'] ?? 'single';
    $copies = intval($_POST['copies'] ?? 1);

    if ($print_type === 'bw') {
        $pricePerPage = ($print_sides === 'double') ? 1.00 : 2.00;
    } else {
        $pricePerPage = ($print_sides === 'double') ? 3.00 : 5.00;
    }

    $cost = $page_count * $copies * $pricePerPage;
    $estimated_time = $page_count * 2;

    // Insert into DB
    $insert = $conn->prepare("INSERT INTO print_jobs (user_id, email, file_name, file_path, cost, estimated_time, pages, status, uploaded_at, print_type, copies, print_sides) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', NOW(), ?, ?, ?)");
    $insert->bind_param("isssdiiiss", $user_id, $email, $filename, $filepath, $cost, $estimated_time, $page_count, $print_type, $copies, $print_sides);
    $insert->execute();
    $insert->close();

    echo "<div style='color:#66bb6a;font-size:1.2rem;margin:30px;text-align:center;'>Uploaded successfully! Pages: $page_count | Cost: ₹$cost<br>
    <a href='dashboard.php' style='color:#00ccff;text-decoration:none;'>Go to Dashboard</a></div>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Document | Hyperlocal Print System</title>
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

        .card {
            background-color: #1e1e2f;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 0 15px rgba(0,204,255,0.15);
            margin: 30px 0;
            text-align: center;
        }

        .card h3 {
            color: #00ccff;
            margin-bottom: 20px;
            font-size: 24px;
            text-transform: uppercase;
            letter-spacing: 1px;
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

        /* .qr-card {
            background-color: #1e1e2f;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 0 15px rgba(0,204,255,0.15);
        }

        .qr-card p {
            color: #ccc;
            margin-bottom: 15px;
        } */

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

        .card,
        .qr-card,
        #uploadForm {
            background-color: #ffffff !important;
            color: #000 !important;
        }

        .qr-card p,
        #uploadForm label {
            color: #000 !important;
        }

        #uploadForm input[type="file"],
        #uploadForm input[type="number"],
        #uploadForm select {
            background: #f7f8ff !important;
            color: #000 !important;
            border-color: #00ccff !important;
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

        .card h3 {
            color: #007a99 !important;
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
        <!-- <?php if (!empty($email)): ?>
            <div class="qr-card">
                <h3>Your QR Code</h3>
                <p>Scan to auto-fill your email for upload</p>
                <img src="https://chart.googleapis.com/chart?cht=qr&chs=180x180&chl=<?php echo urlencode($email); ?>" alt="QR Code" />
            </div>
        <?php else: ?>
            <div class="qr-card">
                <p style="color:#e57373;">No email found. Please login.</p>
            </div>
        <?php endif; ?> -->

        <div class="card">
            <h3>Upload Document</h3>
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
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
                <button type="submit">Upload</button>
            </form>
        </div>
    </div>

    <script>
        document.documentElement.setAttribute('data-theme', '<?= $theme ?>');
    </script>
</body>
</html>