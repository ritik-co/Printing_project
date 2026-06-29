<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', '1');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

include 'db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection not available.');
}

function column_exists(mysqli $conn, string $table, string $column): bool {
    $sql = "SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

function ensure_column(mysqli $conn, string $table, string $column, string $definition): void {
    if (!column_exists($conn, $table, $column)) {
        @$conn->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

function clean_date(?string $value, string $fallback): string {
    $value = trim((string)$value);
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if ($dt && $dt->format('Y-m-d') === $value) {
        return $value;
    }
    return $fallback;
}

function fetch_rows(mysqli $conn, string $sql): array {
    $rows = [];
    $result = $conn->query($sql);
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function qval(mysqli $conn, string $value): string {
    return $conn->real_escape_string($value);
}

// Optional self-healing columns for this report page.
ensure_column($conn, 'print_jobs', 'is_paid', 'TINYINT(1) NOT NULL DEFAULT 0');
ensure_column($conn, 'print_jobs', 'payment_status', "VARCHAR(20) NOT NULL DEFAULT 'pending'");

$hasIsPaid = column_exists($conn, 'print_jobs', 'is_paid');
$hasPaymentStatus = column_exists($conn, 'print_jobs', 'payment_status');

$today = date('Y-m-d');
$fromDate  = clean_date($_GET['from'] ?? date('Y-m-01'), date('Y-m-01'));
$toDate    = clean_date($_GET['to'] ?? $today, $today);
$filterUid = (int)($_GET['user_id'] ?? 0);
$activeTab  = $_GET['tab'] ?? 'daily';
$allowedTabs = ['daily', 'chart', 'hourly', 'monthly', 'topusers', 'paid'];
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'daily';
}

$from = qval($conn, $fromDate);
$to   = qval($conn, $toDate);

$filterUserName = '';
if ($filterUid > 0) {
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $filterUid);
        $stmt->execute();
        $stmt->bind_result($uname);
        if ($stmt->fetch()) {
            $filterUserName = (string)$uname;
        }
        $stmt->close();
    }
}

$dateFilterFor = function (string $alias = '') use ($from, $to, $filterUid): string {
    $field = $alias ? "{$alias}.uploaded_at" : "uploaded_at";
    $sql = "DATE({$field}) BETWEEN '{$from}' AND '{$to}'";
    if ($filterUid > 0) {
        $uidField = $alias ? "{$alias}.user_id" : "user_id";
        $sql .= " AND {$uidField} = " . (int)$filterUid;
    }
    return $sql;
};

$plainUserFilter = $filterUid > 0 ? " AND user_id = " . (int)$filterUid : "";
$aliasUserFilter = $filterUid > 0 ? " AND pj.user_id = " . (int)$filterUid : "";
$monthlyFilter = $filterUid > 0 ? "WHERE user_id = " . (int)$filterUid : "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_paid'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (!$hasIsPaid) {
        echo json_encode(['ok' => false, 'message' => 'The is_paid column is missing.']);
        exit();
    }

    $jid = (int)($_POST['job_id'] ?? 0);
    if ($jid <= 0) {
        echo json_encode(['ok' => false, 'message' => 'Invalid job id.']);
        exit();
    }

    $stmt = $conn->prepare("UPDATE print_jobs SET is_paid = 1 - is_paid WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['ok' => false, 'message' => $conn->error]);
        exit();
    }

    $stmt->bind_param("i", $jid);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("SELECT is_paid FROM print_jobs WHERE id = ?");
    $paidValue = 0;
    if ($stmt) {
        $stmt->bind_param("i", $jid);
        $stmt->execute();
        $stmt->bind_result($paidValue);
        $stmt->fetch();
        $stmt->close();
    }

    echo json_encode(['ok' => true, 'is_paid' => (int)$paidValue]);
    exit();
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $rows = fetch_rows($conn, "
    SELECT
        DATE(uploaded_at) AS day,
        COUNT(*) AS jobs,
        COALESCE(SUM(pages * copies), 0) AS pages,
        COALESCE(SUM(status = 'done'), 0) AS completed,
        COALESCE(SUM(status = 'pending'), 0) AS pending,
        COALESCE(SUM(status = 'printing'), 0) AS failed,
        COALESCE(SUM(CASE WHEN print_type = 'bw' THEN cost ELSE 0 END), 0) AS bw_rev,
        COALESCE(SUM(CASE WHEN print_type = 'color' THEN cost ELSE 0 END), 0) AS color_rev,
        COALESCE(SUM(CASE WHEN status = 'done' THEN cost ELSE 0 END), 0) AS collected,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN cost ELSE 0 END), 0) AS pending_amt,
        COALESCE(SUM(cost), 0) AS total_billed
    FROM print_jobs
    WHERE DATE(uploaded_at) BETWEEN '{$from}' AND '{$to}'{$plainUserFilter}
    GROUP BY DATE(uploaded_at)
    ORDER BY day ASC
    ");

    $filenameFrom = preg_replace('/[^0-9\-]/', '', $fromDate);
    $filenameTo   = preg_replace('/[^0-9\-]/', '', $toDate);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="collection_' . $filenameFrom . '_to_' . $filenameTo . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Day', 'Jobs', 'Pages', 'Completed', 'Pending', 'Failed', 'B&W Rev (₹)', 'Color Rev (₹)', 'Collected (₹)', 'Pending Amt (₹)', 'Total Billed (₹)']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['day'],
            date('D', strtotime($r['day'])),
            $r['jobs'],
            $r['pages'],
            $r['completed'],
            $r['pending'],
            $r['failed'],
            number_format((float)$r['bw_rev'], 2, '.', ''),
            number_format((float)$r['color_rev'], 2, '.', ''),
            number_format((float)$r['collected'], 2, '.', ''),
            number_format((float)$r['pending_amt'], 2, '.', ''),
            number_format((float)$r['total_billed'], 2, '.', ''),
        ]);
    }
    fclose($out);
    exit();
}

// Daily / hourly / totals / charts
$dailyRows = fetch_rows($conn, "
 SELECT
    DATE(uploaded_at) AS day,
    COUNT(*) AS total_jobs,
    COALESCE(SUM(pages * copies), 0) AS total_pages,

    COALESCE(SUM(status='done'), 0) AS completed,
    COALESCE(SUM(status='pending'), 0) AS pending,
    COALESCE(SUM(status='printing'), 0) AS failed,

    COALESCE(SUM(CASE WHEN print_type = 'bw' THEN cost ELSE 0 END), 0) AS bw_revenue,
    COALESCE(SUM(CASE WHEN print_type = 'color' THEN cost ELSE 0 END), 0) AS color_revenue,

    COALESCE(SUM(CASE WHEN status='done' THEN cost ELSE 0 END), 0) AS collected,
    COALESCE(SUM(CASE WHEN status='pending' THEN cost ELSE 0 END), 0) AS pending_amount

FROM print_jobs
WHERE {$dateFilterFor()}
GROUP BY DATE(uploaded_at)
ORDER BY day DESC
");

$hourlyRows = fetch_rows($conn, "
SELECT
    HOUR(uploaded_at) AS hr,
    COUNT(*) AS jobs,
    COALESCE(SUM(cost), 0) AS revenue,
    COALESCE(SUM(CASE WHEN status='done' THEN cost ELSE 0 END), 0) AS collected
FROM print_jobs
WHERE {$dateFilterFor()}
GROUP BY HOUR(uploaded_at)
ORDER BY hr ASC
");

$monthlyRows = fetch_rows($conn, "
SELECT
    DATE_FORMAT(uploaded_at, '%Y-%m') AS month_key,
    MAX(DATE_FORMAT(uploaded_at, '%b %Y')) AS label,
    COUNT(*) AS jobs,
    COALESCE(SUM(pages * copies), 0) AS pages,
    COALESCE(SUM(CASE WHEN status='done' THEN cost ELSE 0 END), 0) AS collected,
    COALESCE(SUM(cost), 0) AS total_billed
FROM print_jobs
{$monthlyFilter}
GROUP BY DATE_FORMAT(uploaded_at, '%Y-%m')
ORDER BY month_key DESC
LIMIT 12
");
$topUsersRows = fetch_rows($conn, "
  SELECT
    u.username,
    u.id,
    COUNT(pj.id) AS jobs,
    COALESCE(SUM(pj.cost), 0) AS total_spent,
    COALESCE(SUM(CASE WHEN pj.status='done' THEN pj.cost ELSE 0 END), 0) AS collected
FROM print_jobs pj
JOIN users u ON pj.user_id = u.id
WHERE DATE(pj.uploaded_at) BETWEEN '{$from}' AND '{$to}'" . ($filterUid > 0 ? " AND pj.user_id = " . (int)$filterUid : "") . "
GROUP BY pj.user_id, u.username, u.id
ORDER BY total_spent DESC
LIMIT 10
");
$jobsListRows = fetch_rows($conn, "
    SELECT
        pj.id,
        pj.file_name,
        pj.cost,
        pj.status,
        pj.print_type,
        pj.uploaded_at,
        " . ($hasIsPaid ? "COALESCE(pj.is_paid, 0)" : "0") . " AS is_paid,
        " . ($hasPaymentStatus ? "COALESCE(pj.payment_status, 'pending')" : "'pending'") . " AS payment_status,
        u.username
    FROM print_jobs pj
    LEFT JOIN users u ON pj.user_id = u.id
    WHERE DATE(pj.uploaded_at) BETWEEN '{$from}' AND '{$to}'{$aliasUserFilter}
    ORDER BY pj.uploaded_at DESC
    LIMIT 200
");

$totalsRow = [
    'total_jobs' => 0,
    'total_pages' => 0,
    'completed' => 0,
    'pending' => 0,
    'collected' => 0,
    'pending_amount' => 0,
    'total_billed' => 0,
    'bw_revenue' => 0,
    'color_revenue' => 0,
    'paid_amount' => 0,
    'paid_count' => 0,
];

 $totalsRes = $conn->query("
    SELECT
        COUNT(*) AS total_jobs,
        COALESCE(SUM(pages * copies), 0) AS total_pages,
        COALESCE(SUM(status='done'), 0) AS completed,
        COALESCE(SUM(status='pending'), 0) AS pending,
        COALESCE(SUM(CASE WHEN status='done' THEN cost ELSE 0 END), 0) AS collected,
        COALESCE(SUM(CASE WHEN status='pending' THEN cost ELSE 0 END), 0) AS pending_amount,
        COALESCE(SUM(cost), 0) AS total_billed,
        COALESCE(SUM(CASE WHEN print_type = 'bw' THEN cost ELSE 0 END), 0) AS bw_revenue,
        COALESCE(SUM(CASE WHEN print_type = 'color' THEN cost ELSE 0 END), 0) AS color_revenue,
        " . ($hasIsPaid ? "
            COALESCE(SUM(CASE WHEN is_paid = 1 THEN cost ELSE 0 END), 0) AS paid_amount,
            COALESCE(SUM(is_paid = 1), 0) AS paid_count
        " : "0 AS paid_amount, 0 AS paid_count") . "
    FROM print_jobs
    WHERE DATE(uploaded_at) BETWEEN '{$from}' AND '{$to}'{$plainUserFilter}
");   
if ($totalsRes instanceof mysqli_result) {
    $tmp = $totalsRes->fetch_assoc();
    if (is_array($tmp)) {
        $totalsRow = array_merge($totalsRow, $tmp);
    }
}

// Chart arrays
$chartDays = [];
$chartCollected = [];
$chartPending = [];
$chartBw = [];
$chartColor = [];
foreach (array_reverse($dailyRows) as $r) {
    $chartDays[] = date('d M', strtotime($r['day']));
    $chartCollected[] = round((float)$r['collected'], 2);
    $chartPending[] = round((float)$r['pending_amount'], 2);
    $chartBw[] = round((float)$r['bw_revenue'], 2);
    $chartColor[] = round((float)$r['color_revenue'], 2);
}

$hourlyData = array_fill(0, 24, 0);
$hourlyRev = array_fill(0, 24, 0);
foreach ($hourlyRows as $r) {
    $h = (int)$r['hr'];
    $hourlyData[$h] = (int)$r['jobs'];
    $hourlyRev[$h] = round((float)$r['revenue'], 2);
}

$monthLabels = [];
$monthRevenue = [];
foreach (array_reverse($monthlyRows) as $r) {
    $monthLabels[] = $r['label'];
    $monthRevenue[] = round((float)$r['collected'], 2);
}

$allUsersRows = fetch_rows($conn, "SELECT id, username FROM users ORDER BY username");

$activeTabTitle = [
    'daily' => 'Daily Breakdown',
    'chart' => 'Revenue Chart',
    'hourly' => 'Hourly Analysis',
    'monthly' => 'Monthly Summary',
    'topusers' => 'Top Users',
    'paid' => 'Paid / Unpaid',
][$activeTab];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Collection | HyperPrint Admin</title>
    <script>(function(){ document.documentElement.setAttribute('data-theme', localStorage.getItem('hp_theme') || 'light'); })();</script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root{
            --primary:#0ea5e9;--primary-hover:#0284c7;--primary-soft:#f0f9ff;
            --sidebar-width:282px;--bg:#f1f5f9;--surface:#ffffff;--surface2:#f8fafc;
            --border:#e2e8f0;--text:#0f172a;--text-muted:#64748b;--text-faint:#94a3b8;
            --shadow-sm:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);
            --shadow-md:0 4px 16px rgba(0,0,0,.08);--radius-sm:10px;--radius-md:16px;--radius-lg:24px;
        }
        [data-theme="dark"]{
            --bg:#0c1220;--surface:#141e30;--surface2:#1a2540;--border:#1e3050;
            --text:#f0f6ff;--text-muted:#8eaac8;--text-faint:#4a6a8a;
            --shadow-sm:0 1px 3px rgba(0,0,0,.3);--shadow-md:0 4px 16px rgba(0,0,0,.4);
            --primary-soft:#0c2a40;
        }
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;transition:background .3s,color .3s}
        a{text-decoration:none}
        button,input,select{font:inherit}
        #sidebar{width:var(--sidebar-width);position:fixed;left:0;top:0;height:100vh;background:var(--surface);border-right:1px solid var(--border);z-index:900;transition:transform .35s cubic-bezier(.4,0,.2,1),background .3s,border-color .3s;display:flex}
        .sidebar-inner{display:flex;flex-direction:column;height:100%;width:100%;overflow:hidden}
        .sidebar-logo{display:flex;align-items:center;gap:12px;padding:22px 20px 18px;border-bottom:1px solid var(--border)}
        .logo-icon{width:40px;height:40px;background:linear-gradient(135deg,#7c3aed,#0ea5e9);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;flex-shrink:0}
        .logo-text{font-size:1.05rem;font-weight:800;line-height:1.2}
        .admin-badge{display:inline-block;font-size:.58rem;font-weight:800;letter-spacing:.12em;background:linear-gradient(135deg,#7c3aed,#0ea5e9);color:#fff;padding:2px 7px;border-radius:9999px;margin-top:2px}
        .sidebar-nav{flex:1;padding:16px 12px;display:flex;flex-direction:column;gap:4px;overflow-y:auto}
        .nav-item{display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:var(--radius-sm);color:var(--text-muted);text-decoration:none;font-weight:600;font-size:.875rem;transition:all .18s}
        .nav-item i{font-size:1.2rem}
        .nav-item:hover{background:var(--primary-soft);color:var(--primary);transform:translateX(2px)}
        .nav-item.active{background:var(--primary);color:#fff;box-shadow:0 4px 12px rgba(14,165,233,.35)}
        .sidebar-bottom{padding:12px 12px 20px;border-top:1px solid var(--border);display:flex;flex-direction:column;gap:8px}
        .theme-toggle-btn,.logout-btn,.mobile-theme-btn{
            display:flex;align-items:center;gap:10px;width:100%;padding:10px 14px;border-radius:var(--radius-sm);
            font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;transition:all .2s;border:1px solid var(--border)
        }
        .theme-toggle-btn,.mobile-theme-btn{background:var(--surface2);color:var(--text-muted)}
        .theme-toggle-btn:hover,.mobile-theme-btn:hover{background:var(--primary-soft);color:var(--primary);border-color:var(--primary)}
        .logout-btn{background:#fef2f2;border-color:#fecaca;color:#dc2626}
        .logout-btn:hover{background:#fee2e2}
        .main-wrapper{margin-left:var(--sidebar-width);padding:2rem;min-height:100vh;transition:margin-left .35s ease}
        #overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:850;backdrop-filter:blur(2px)}
        #overlay.active{display:block}
        .mobile-topbar{display:none;position:sticky;top:0;z-index:820;align-items:center;justify-content:space-between;gap:12px;background:var(--bg);padding:.75rem 1rem .5rem;margin:-2rem -2rem 1rem}
        .mobile-title{font-weight:800;font-size:1rem}
        #mobile-toggle{display:none;width:44px;height:44px;background:var(--surface);border:1px solid var(--border);border-radius:12px;align-items:center;justify-content:center;cursor:pointer;box-shadow:var(--shadow-md);color:var(--text)}
        .page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem}
        .page-header h1{font-size:1.6rem;font-weight:800;letter-spacing:-.03em}
        .card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);overflow:hidden}
        .card-header{padding:1.05rem 1.3rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:.75rem}
        .card-title{font-size:1rem;font-weight:700}
        .chart-wrap{padding:1.2rem;background:var(--surface);position:relative}
        .stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.15rem 1.3rem;box-shadow:var(--shadow-sm);position:relative;overflow:hidden}
        .stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px}
        .stat-card.green::before{background:#22c55e}.stat-card.amber::before{background:#f59e0b}.stat-card.sky::before{background:var(--primary)}.stat-card.purple::before{background:#7c3aed}.stat-card.indigo::before{background:#6366f1}.stat-card.rose::before{background:#f43f5e}.stat-card.teal::before{background:#14b8a6}
        .stat-value{font-size:1.7rem;font-weight:800;letter-spacing:-.04em;margin-top:4px}
        .stat-label{font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em}
        .stat-sub{font-size:.72rem;color:var(--text-faint);margin-top:3px}
        .stat-icon{position:absolute;right:1.15rem;top:50%;transform:translateY(-50%);font-size:2.8rem;opacity:.06}
        .tabs{display:flex;gap:4px;background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius-md);padding:4px;margin-bottom:1.2rem;flex-wrap:wrap}
        .tab-btn{padding:9px 16px;border-radius:var(--radius-sm);font-size:.85rem;font-weight:700;cursor:pointer;border:none;background:transparent;color:var(--text-muted);transition:all .2s;display:flex;align-items:center;gap:7px}
        .tab-btn.active{background:var(--surface);color:var(--primary);box-shadow:var(--shadow-sm)}
        .tab-btn:hover:not(.active){color:var(--text)}
        .tab-content{display:none}
        .tab-content.active{display:block}
        .btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:var(--radius-sm);font-size:.85rem;font-weight:700;cursor:pointer;border:none;transition:all .2s;text-decoration:none}
        .btn-primary{background:var(--primary);color:#fff}
        .btn-primary:hover{background:var(--primary-hover);transform:translateY(-1px)}
        .btn-sm{padding:5px 12px;font-size:.78rem}
        .date-input,.filter-select{padding:9px 12px;border-radius:var(--radius-sm);border:1.5px solid var(--border);background:var(--surface2);color:var(--text);font-size:.875rem;outline:none;transition:all .2s}
        .date-input:focus,.filter-select:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(14,165,233,.10)}
        table{width:100%;border-collapse:collapse}
        th{background:var(--surface2);color:var(--text-faint);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;padding:12px 16px;text-align:left;border-bottom:1px solid var(--border);white-space:nowrap}
        td{padding:12px 16px;border-bottom:1px solid var(--border);font-size:.875rem;vertical-align:middle}
        tr:last-child td{border-bottom:none}
        tbody tr:hover{background:var(--surface2)}
        .paid-toggle{width:52px;height:26px;border-radius:9999px;border:none;cursor:pointer;transition:all .3s;position:relative;flex-shrink:0}
        .paid-toggle.unpaid{background:var(--border)}
        .paid-toggle.paid{background:#22c55e}
        .paid-toggle::after{content:'';position:absolute;width:20px;height:20px;border-radius:50%;background:#fff;top:3px;transition:.3s;box-shadow:0 1px 4px rgba(0,0,0,.2)}
        .paid-toggle.unpaid::after{left:3px}
        .paid-toggle.paid::after{left:29px}
        .badge{display:inline-flex;align-items:center;justify-content:center;padding:3px 8px;border-radius:6px;font-size:.72rem;font-weight:700}
        .muted{color:var(--text-muted)}
        .no-print{}
        @media (max-width: 1024px){
            #sidebar{transform:translateX(-100%)}
            #sidebar.open{transform:translateX(0);box-shadow:0 8px 32px rgba(0,0,0,.18)}
            .main-wrapper{margin-left:0!important;padding:1rem;padding-top:3.5rem}
            .mobile-topbar{display:flex}
            #mobile-toggle{
                                display:flex;
                                position:fixed;
                                top:24px;
                                right:24px;
                                left:auto;
                                z-index:1200;
                            }
            .page-header{margin-top:.5rem}
        }
        @media (max-width: 900px){
            .stat-grid,.stat-grid-2{grid-template-columns:repeat(2,1fr)!important}
            .tabs{gap:2px}
            .tab-btn{padding:8px 12px;font-size:.8rem}
        }
        @media (max-width: 640px){
            .stat-grid,.stat-grid-2{grid-template-columns:1fr!important}
            .page-header h1{font-size:1.35rem}
            .card-header{padding:1rem}
            .chart-wrap{padding:1rem}
            td,th{padding:10px 12px}
            .filters form{gap:8px!important}
        }
        @media print{
            #sidebar,#mobile-toggle,#overlay,.no-print,.mobile-topbar{display:none!important}
            .main-wrapper{margin-left:0!important;padding:0!important}
            .tabs{display:none!important}
            .tab-content{display:block!important}
        }
    </style>
</head>
<body>

<div id="sidebar">
    <aside class="sidebar-inner">
        <div class="sidebar-logo">
            <div class="logo-icon"><i class='bx bxs-shield-alt'></i></div>
            <div>
                <span class="logo-text">HyperPrint</span><br>
                <span class="admin-badge">ADMIN</span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php" class="nav-item"><i class='bx bxs-grid-alt'></i><span>Dashboard</span></a>
            <a href="admin_users.php" class="nav-item"><i class='bx bxs-group'></i><span>Manage Users</span></a>
            <a href="admin_jobs.php" class="nav-item"><i class='bx bxs-printer'></i><span>Print Jobs</span></a>
            <a href="admin_collection.php" class="nav-item active"><i class='bx bxs-report'></i><span>Daily Collection</span></a>
            <a href="admin_devices.php" class="nav-item"><i class='bx bxs-devices'></i><span>Devices</span></a>
        </nav>
        <div class="sidebar-bottom">
            <button class="theme-toggle-btn" type="button" id="themeToggleSidebar">
                <i class='bx bx-moon' id="themeIcon"></i><span id="themeLabel">Dark Mode</span>
            </button>
            <form action="admin_logout.php" method="POST" style="margin:0">
                <button type="submit" class="logout-btn"><i class='bx bx-log-out'></i><span>Logout</span></button>
            </form>
        </div>
    </aside>
</div>
<div id="overlay"></div>
<?php
$currentPage = 'collection';
include 'sidebar.php';
?>

<main class="main-wrapper">
    <div class="mobile-topbar no-print">
        <button id="mobile-toggle" type="button" aria-label="Open menu"><i class='bx bx-menu' id="toggleIcon" style="font-size:1.4rem"></i></button>
        <div style="display:flex;flex-direction:column;gap:2px;min-width:0">
            <div class="mobile-title"><?= htmlspecialchars($activeTabTitle) ?></div>
            <div style="font-size:.78rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                <?= htmlspecialchars($fromDate) ?> → <?= htmlspecialchars($toDate) ?>
                <?= $filterUserName !== '' ? ' · ' . htmlspecialchars($filterUserName) : '' ?>
            </div>
        </div>
        <!-- <button class="mobile-theme-btn" type="button" id="themeToggleMobile" style="width:auto;white-space:nowrap">
            <i class='bx bx-moon' id="themeIconMobile"></i>
        </button> -->
    </div>

    <div class="page-header">
        <div>
            <h1>Collection Report 📊</h1>
            <p class="muted" style="font-size:.9rem;margin-top:2px;">
                <?= date('d M Y', strtotime($fromDate)) ?> — <?= date('d M Y', strtotime($toDate)) ?>
                <?= $filterUserName !== '' ? ' · <strong>' . htmlspecialchars($filterUserName) . '</strong>' : '' ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap" class="no-print">
            <a href="?from=<?= htmlspecialchars($fromDate) ?>&to=<?= htmlspecialchars($toDate) ?>&user_id=<?= (int)$filterUid ?>&export=csv"
               class="btn" style="background:#16a34a;color:#fff">
                <i class='bx bx-download'></i> Export CSV
            </a>
            <button onclick="window.print()" class="btn" style="background:var(--surface2);color:var(--text-muted);border:1px solid var(--border)">
                <i class='bx bx-printer'></i> Print
            </button>
        </div>
    </div>

    <div class="card no-print filters" style="padding:1.1rem 1.25rem;margin-bottom:1.15rem;">
        <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
            <div>
                <label style="display:block;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text-faint);margin-bottom:5px;">From</label>
                <input type="date" name="from" class="date-input" value="<?= htmlspecialchars($fromDate) ?>">
            </div>
            <div>
                <label style="display:block;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text-faint);margin-bottom:5px;">To</label>
                <input type="date" name="to" class="date-input" value="<?= htmlspecialchars($toDate) ?>">
            </div>
            <div>
                <label style="display:block;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text-faint);margin-bottom:5px;">User</label>
                <select name="user_id" class="filter-select">
                    <option value="0">All Users</option>
                    <?php foreach ($allUsersRows as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= $filterUid === (int)$u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class='bx bx-search'></i> Apply</button>
            <?php
            $presets = [
                'Today'      => [date('Y-m-d'), date('Y-m-d')],
                'This Week'  => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d')],
                'This Month' => [date('Y-m-01'), date('Y-m-d')],
                'Last Month' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last month'))],
            ];
            foreach ($presets as $label => $range):
                list($f, $t) = $range;
                $isActive = ($fromDate === $f && $toDate === $t);
            ?>
                <a href="?from=<?= htmlspecialchars($f) ?>&to=<?= htmlspecialchars($t) ?>&user_id=<?= (int)$filterUid ?>&tab=<?= htmlspecialchars($activeTab) ?>"
                   class="btn btn-sm"
                   style="background:<?= $isActive ? 'var(--primary)' : 'var(--surface2)' ?>;color:<?= $isActive ? 'white' : 'var(--text-muted)' ?>;border:1px solid var(--border)">
                    <?= htmlspecialchars($label) ?>
                </a>
            <?php endforeach; ?>
            <?php if ($filterUid > 0): ?>
                <a href="?from=<?= htmlspecialchars($fromDate) ?>&to=<?= htmlspecialchars($toDate) ?>&tab=<?= htmlspecialchars($activeTab) ?>"
                   class="btn btn-sm" style="background:#fee2e2;color:#dc2626;border:1px solid #fecaca">
                    <i class='bx bx-x'></i> Clear User
                </a>
            <?php endif; ?>
        </form>
    </div>

    <div class="stat-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1rem">
        <div class="stat-card green">
            <div class="stat-label">Collected</div>
            <div class="stat-value" style="color:#22c55e;">₹<?= number_format((float)$totalsRow['collected'], 0) ?></div>
            <div class="stat-sub"><?= (int)$totalsRow['completed'] ?> completed jobs</div>
            <i class='bx bxs-wallet stat-icon'></i>
        </div>
        <div class="stat-card teal">
            <div class="stat-label">Marked Paid</div>
            <div class="stat-value" style="color:#14b8a6;">₹<?= number_format((float)$totalsRow['paid_amount'], 0) ?></div>
            <div class="stat-sub"><?= (int)$totalsRow['paid_count'] ?> jobs marked paid</div>
            <i class='bx bxs-badge-check stat-icon'></i>
        </div>
        <div class="stat-card amber">
            <div class="stat-label">Pending ₹</div>
            <div class="stat-value" style="color:#f59e0b;">₹<?= number_format((float)$totalsRow['pending_amount'], 0) ?></div>
            <div class="stat-sub"><?= (int)$totalsRow['pending'] ?> pending jobs</div>
            <i class='bx bxs-time stat-icon'></i>
        </div>
        <div class="stat-card sky">
            <div class="stat-label">Total Billed</div>
            <div class="stat-value" style="color:var(--primary);">₹<?= number_format((float)$totalsRow['total_billed'], 0) ?></div>
            <div class="stat-sub"><?= (int)$totalsRow['total_jobs'] ?> total jobs</div>
            <i class='bx bxs-receipt stat-icon'></i>
        </div>
    </div>

    <div class="stat-grid-2" style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.2rem">
        <div class="stat-card purple">
            <div class="stat-label">B&W Revenue</div>
            <div class="stat-value" style="color:#7c3aed;font-size:1.4rem;">₹<?= number_format((float)$totalsRow['bw_revenue'], 0) ?></div>
            <i class='bx bxs-printer stat-icon'></i>
        </div>
        <div class="stat-card indigo">
            <div class="stat-label">Color Revenue</div>
            <div class="stat-value" style="color:#6366f1;font-size:1.4rem;">₹<?= number_format((float)$totalsRow['color_revenue'], 0) ?></div>
            <i class='bx bxs-color-fill stat-icon'></i>
        </div>
        <div class="stat-card rose">
            <div class="stat-label">Total Pages</div>
            <div class="stat-value" style="color:#f43f5e;"><?= number_format((float)$totalsRow['total_pages'], 0) ?></div>
            <i class='bx bxs-file stat-icon'></i>
        </div>
        <div class="stat-card sky">
            <div class="stat-label">Avg per Job</div>
            <div class="stat-value" style="color:var(--primary);font-size:1.4rem;">
                ₹<?= ((int)$totalsRow['total_jobs']) > 0 ? number_format(((float)$totalsRow['total_billed']) / ((int)$totalsRow['total_jobs']), 1) : '0.0' ?>
            </div>
            <i class='bx bxs-calculator stat-icon'></i>
        </div>
    </div>

    <div class="tabs no-print" id="tabBar">
        <button class="tab-btn <?= $activeTab === 'daily' ? 'active' : '' ?>" onclick="switchTab('daily', this)"><i class='bx bx-calendar'></i> Daily Breakdown</button>
        <button class="tab-btn <?= $activeTab === 'chart' ? 'active' : '' ?>" onclick="switchTab('chart', this)"><i class='bx bx-bar-chart-alt-2'></i> Revenue Chart</button>
        <button class="tab-btn <?= $activeTab === 'hourly' ? 'active' : '' ?>" onclick="switchTab('hourly', this)"><i class='bx bx-time'></i> Hourly Analysis</button>
        <button class="tab-btn <?= $activeTab === 'monthly' ? 'active' : '' ?>" onclick="switchTab('monthly', this)"><i class='bx bx-calendar-alt'></i> Monthly Summary</button>
        <button class="tab-btn <?= $activeTab === 'topusers' ? 'active' : '' ?>" onclick="switchTab('topusers', this)"><i class='bx bxs-crown'></i> Top Users</button>
        <button class="tab-btn <?= $activeTab === 'paid' ? 'active' : '' ?>" onclick="switchTab('paid', this)"><i class='bx bxs-badge-check'></i> Paid / Unpaid</button>
    </div>

    <div class="tab-content <?= $activeTab === 'daily' ? 'active' : '' ?>" id="tab-daily">
        <div class="card">
            <div class="card-header">
                <span class="card-title">Day-by-Day Breakdown</span>
                <span class="muted" style="font-size:.8rem;"><?= count($dailyRows) ?> active days</span>
            </div>
            <div style="overflow-x:auto">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th><th>Day</th><th>Jobs</th><th>Pages</th><th>Done</th><th>Pending</th><th>Failed</th><th>B&W</th><th>Color</th><th>Collected ₹</th><th>Pending ₹</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (count($dailyRows) === 0): ?>
                        <tr>
                            <td colspan="11" style="text-align:center;padding:3rem;color:var(--text-faint);">
                                <i class='bx bx-calendar-x' style="font-size:2rem;display:block;margin-bottom:.5rem;opacity:.4;"></i>No data for this range.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dailyRows as $row): ?>
                            <?php $isToday = $row['day'] === date('Y-m-d'); ?>
                            <tr <?= $isToday ? 'style="background:var(--primary-soft)"' : '' ?>>
                                <td style="font-weight:700;white-space:nowrap;">
                                    <?= date('d M Y', strtotime($row['day'])) ?>
                                    <?php if ($isToday): ?>
                                        <span style="font-size:.68rem;background:var(--primary);color:#fff;padding:2px 7px;border-radius:9999px;margin-left:5px;">TODAY</span>
                                    <?php endif; ?>
                                </td>
                                <td class="muted"><?= date('D', strtotime($row['day'])) ?></td>
                                <td style="font-weight:700;text-align:center;"><?= (int)$row['total_jobs'] ?></td>
                                <td style="color:var(--text-muted);text-align:center;"><?= number_format((float)$row['total_pages'], 0) ?></td>
                                <td style="text-align:center;"><span class="badge" style="background:#dcfce7;color:#166534;"><?= (int)$row['completed'] ?></span></td>
                                <td style="text-align:center;"><span class="badge" style="background:#fef3c7;color:#92400e;"><?= (int)$row['pending'] ?></span></td>
                                <td style="text-align:center;"><span class="badge" style="background:#fee2e2;color:#991b1b;"><?= (int)$row['failed'] ?></span></td>
                                <td style="color:#7c3aed;font-weight:600;">₹<?= number_format((float)$row['bw_revenue'], 2) ?></td>
                                <td style="color:#6366f1;font-weight:600;">₹<?= number_format((float)$row['color_revenue'], 2) ?></td>
                                <td style="font-weight:800;color:#16a34a;">₹<?= number_format((float)$row['collected'], 2) ?></td>
                                <td style="color:#f59e0b;font-weight:600;">₹<?= number_format((float)$row['pending_amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="background:var(--surface2);border-top:2px solid var(--border);">
                            <td colspan="2" style="font-weight:800;padding:13px 16px;">PERIOD TOTAL</td>
                            <td style="font-weight:800;text-align:center;"><?= (int)$totalsRow['total_jobs'] ?></td>
                            <td style="font-weight:700;text-align:center;color:var(--text-muted);"><?= number_format((float)$totalsRow['total_pages'], 0) ?></td>
                            <td style="text-align:center;font-weight:800;color:#166534;"><?= (int)$totalsRow['completed'] ?></td>
                            <td style="text-align:center;font-weight:800;color:#92400e;"><?= (int)$totalsRow['pending'] ?></td>
                            <td></td>
                            <td style="font-weight:800;color:#7c3aed;">₹<?= number_format((float)$totalsRow['bw_revenue'], 2) ?></td>
                            <td style="font-weight:800;color:#6366f1;">₹<?= number_format((float)$totalsRow['color_revenue'], 2) ?></td>
                            <td style="font-weight:800;color:#16a34a;font-size:1rem;">₹<?= number_format((float)$totalsRow['collected'], 2) ?></td>
                            <td style="font-weight:800;color:#f59e0b;">₹<?= number_format((float)$totalsRow['pending_amount'], 2) ?></td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-content <?= $activeTab === 'chart' ? 'active' : '' ?>" id="tab-chart">
        <div class="card" style="margin-bottom:1.2rem">
            <div class="card-header">
                <span class="card-title">Revenue Over Time</span>
                <div style="display:flex;gap:8px">
                    <button onclick="setChart('bar')" id="btnBar" class="btn btn-sm" style="background:var(--primary);color:#fff">Bar</button>
                    <button onclick="setChart('line')" id="btnLine" class="btn btn-sm" style="background:var(--surface2);color:var(--text-muted);border:1px solid var(--border)">Line</button>
                </div>
            </div>
            <div class="chart-wrap" style="height:360px">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span class="card-title">B&W vs Color Split</span></div>
            <div class="chart-wrap" style="height:300px;display:flex;align-items:center;justify-content:center">
                <canvas id="splitChart" style="max-width:300px;max-height:280px"></canvas>
            </div>
        </div>
    </div>

    <div class="tab-content <?= $activeTab === 'hourly' ? 'active' : '' ?>" id="tab-hourly">
        <div class="card" style="margin-bottom:1.2rem">
            <div class="card-header">
                <span class="card-title">Jobs by Hour of Day</span>
                <span class="muted" style="font-size:.8rem">All days in selected range combined</span>
            </div>
            <div class="chart-wrap" style="height:300px">
                <canvas id="hourlyChart"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span class="card-title">Revenue by Hour</span></div>
            <div style="overflow-x:auto">
                <table>
                    <thead>
                        <tr><th>Hour</th><th>Time Slot</th><th>Jobs</th><th>Revenue</th><th>Activity</th></tr>
                    </thead>
                    <tbody>
                    <?php
                        $maxJobs = max(array_merge($hourlyData, [1]));
                        for ($h = 0; $h < 24; $h++):
                            $jobs = $hourlyData[$h];
                            $rev  = $hourlyRev[$h];
                            $pct  = (int)round(($jobs / $maxJobs) * 100);
                            $label = $h < 12 ? sprintf('%d:00 AM', $h ?: 12) : ($h === 12 ? '12:00 PM' : sprintf('%d:00 PM', $h - 12));
                    ?>
                        <tr <?= $jobs > 0 ? '' : 'style="opacity:.4"' ?>>
                            <td style="font-weight:700;color:var(--text);"><?= str_pad((string)$h, 2, '0', STR_PAD_LEFT) ?>:00</td>
                            <td class="muted" style="font-size:.85rem;"><?= $label ?></td>
                            <td style="font-weight:700;text-align:center;"><?= $jobs ?></td>
                            <td style="font-weight:700;color:var(--primary);">₹<?= number_format($rev, 2) ?></td>
                            <td style="width:200px">
                                <div style="height:8px;background:var(--border);border-radius:9999px;overflow:hidden">
                                    <div style="height:100%;width:<?= $pct ?>%;background:<?= $pct > 70 ? '#22c55e' : ($pct > 30 ? 'var(--primary)' : '#94a3b8') ?>;border-radius:9999px;transition:.3s"></div>
                                </div>
                            </td>
                        </tr>
                    <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-content <?= $activeTab === 'monthly' ? 'active' : '' ?>" id="tab-monthly">
        <div class="card" style="margin-bottom:1.2rem">
            <div class="card-header"><span class="card-title">Monthly Revenue Trend</span></div>
            <div class="chart-wrap" style="height:300px">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span class="card-title">Month-by-Month Summary</span></div>
            <div style="overflow-x:auto">
                <table>
                    <thead>
                        <tr><th>Month</th><th>Jobs</th><th>Pages</th><th>Collected ₹</th><th>Total Billed ₹</th></tr>
                    </thead>
                    <tbody>
                    <?php if (count($monthlyRows) === 0): ?>
                        <tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--text-faint)">No monthly data available.</td></tr>
                    <?php else: ?>
                        <?php foreach ($monthlyRows as $r): ?>
                            <tr>
                                <td style="font-weight:700;"><?= htmlspecialchars($r['label']) ?></td>
                                <td style="text-align:center;"><?= (int)$r['jobs'] ?></td>
                                <td style="text-align:center;color:var(--text-muted);"><?= number_format((float)$r['pages'], 0) ?></td>
                                <td style="font-weight:800;color:#16a34a;">₹<?= number_format((float)$r['collected'], 2) ?></td>
                                <td style="font-weight:700;color:var(--primary);">₹<?= number_format((float)$r['total_billed'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-content <?= $activeTab === 'topusers' ? 'active' : '' ?>" id="tab-topusers">
        <div class="card">
            <div class="card-header">
                <span class="card-title">Top Users by Spending</span>
                <span class="muted" style="font-size:.8rem">For selected period</span>
            </div>
            <div style="overflow-x:auto">
                <table>
                    <thead>
                        <tr><th>Rank</th><th>User</th><th>Jobs</th><th>Collected ₹</th><th>Total Spent ₹</th><th>Avg per Job</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php if (count($topUsersRows) === 0): ?>
                        <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--text-faint)">No users found for this range.</td></tr>
                    <?php else: ?>
                        <?php
                            $rank = 0;
                            $medals = ['🥇', '🥈', '🥉'];
                            foreach ($topUsersRows as $r):
                                $rank++;
                                $avg = ((int)$r['jobs'] > 0) ? ((float)$r['total_spent'] / (int)$r['jobs']) : 0;
                        ?>
                            <tr>
                                <td style="font-size:1.3rem;text-align:center;"><?= $medals[$rank - 1] ?? ('#' . $rank) ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px">
                                        <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#7c3aed,#0ea5e9);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.85rem;font-weight:800;flex-shrink:0;">
                                            <?= strtoupper(substr((string)$r['username'], 0, 1)) ?>
                                        </div>
                                        <span style="font-weight:700;"><?= htmlspecialchars($r['username']) ?></span>
                                    </div>
                                </td>
                                <td style="text-align:center;font-weight:600;"><?= (int)$r['jobs'] ?></td>
                                <td style="font-weight:800;color:#16a34a;">₹<?= number_format((float)$r['collected'], 2) ?></td>
                                <td style="font-weight:700;color:var(--primary);">₹<?= number_format((float)$r['total_spent'], 2) ?></td>
                                <td style="color:var(--text-muted);">₹<?= number_format($avg, 1) ?></td>
                                <td>
                                    <a href="?from=<?= htmlspecialchars($fromDate) ?>&to=<?= htmlspecialchars($toDate) ?>&user_id=<?= (int)$r['id'] ?>"
                                       class="btn btn-sm" style="background:var(--primary-soft);color:var(--primary);border:1px solid rgba(14,165,233,.2)">
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-content <?= $activeTab === 'paid' ? 'active' : '' ?>" id="tab-paid">
        <div class="card">
            <div class="card-header"><span class="card-title">Paid / Unpaid Jobs</span></div>
            <div style="overflow-x:auto">
                <table>
                    <thead>
                        <tr><th>User</th><th>File</th><th>Date</th><th>Type</th><th>Cost</th><th>Digital Payment</th><th>Manual Switch</th></tr>
                    </thead>
                    <tbody>
                    <?php if (count($jobsListRows) === 0): ?>
                        <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--text-faint)">No jobs found for this range.</td></tr>
                    <?php else: ?>
                        <?php foreach ($jobsListRows as $r): ?>
                                <?php
                                    $status = strtolower((string)$r['status']);

                                    if ($status == 'done') {
                                        $pill = 'background:#dcfce7;color:#166534;';      // Green
                                    } elseif ($status == 'printing') {
                                        $pill = 'background:#dbeafe;color:#1d4ed8;';      // Blue
                                    } else { // pending
                                        $pill = 'background:#fef3c7;color:#92400e;';      // Yellow
                                    }
                                ?>
                            <tr>
                                <td style="font-weight:600;"><?= htmlspecialchars($r['username'] ?? '—') ?></td>
                                <td style="color:var(--text-muted);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($r['file_name']) ?>">
                                    <?= htmlspecialchars($r['file_name']) ?>
                                </td>
                                <td style="color:var(--text-muted);font-size:.8rem;white-space:nowrap;"><?= date('d M Y', strtotime($r['uploaded_at'])) ?></td>
                                <td><span class="badge" style="background:var(--surface2);border:1px solid var(--border);font-weight:600;"><?= strtoupper((string)$r['print_type']) ?></span></td>
                                <td style="font-weight:700;">₹<?= number_format((float)$r['cost'], 2) ?></td>
                                <td>
                                    <?php if (strtolower((string)$r['payment_status']) === 'paid'): ?>
                                        <span class="badge" style="background:#dcfce7;color:#166534;">Online Paid ✅</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#fef3c7;color:#92400e;">Pending ⏳</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($hasIsPaid): ?>
                                        <button class="paid-toggle <?= ((int)$r['is_paid'] === 1) ? 'paid' : 'unpaid' ?>"
                                                data-id="<?= (int)$r['id'] ?>"
                                                title="<?= ((int)$r['is_paid'] === 1) ? 'Paid — click to unmark' : 'Unpaid — click to mark paid' ?>"></button>
                                    <?php else: ?>
                                        <span class="badge" style="background:#fee2e2;color:#991b1b;">Disabled</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
let chartsBuilt = false;
let hourlyBuilt = false;
let monthlyBuilt = false;
let currentType = 'bar';
let revenueChart = null;

const activeTab = <?= json_encode($activeTab) ?>;
const days = <?= json_encode($chartDays) ?>;
const collected = <?= json_encode($chartCollected) ?>;
const pending = <?= json_encode($chartPending) ?>;
const bwData = <?= json_encode($chartBw) ?>;
const colorData = <?= json_encode($chartColor) ?>;
const hourlyJobs = <?= json_encode(array_values($hourlyData)) ?>;
const monthLabels = <?= json_encode($monthLabels) ?>;
const monthRevenue = <?= json_encode($monthRevenue) ?>;

function isDark() { return document.documentElement.getAttribute('data-theme') === 'dark'; }
function gridColor() { return isDark() ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.06)'; }
function textColor() { return isDark() ? '#8eaac8' : '#94a3b8'; }

function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('hp_theme', theme);
    const icon = document.getElementById('themeIcon');
    const label = document.getElementById('themeLabel');
    const iconMobile = document.getElementById('themeIconMobile');
    if (icon) icon.className = theme === 'dark' ? 'bx bx-sun' : 'bx bx-moon';
    if (iconMobile) iconMobile.className = theme === 'dark' ? 'bx bx-sun' : 'bx bx-moon';
    if (label) label.textContent = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
}
function toggleTheme() {
    applyTheme(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
}

function openSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    if (!sidebar || !overlay) return;
    sidebar.classList.add('open');
    overlay.classList.add('active');
    const icon = document.getElementById('toggleIcon');
    if (icon) icon.className = 'bx bx-x';
}
function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    if (!sidebar || !overlay) return;
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
    const icon = document.getElementById('toggleIcon');
    if (icon) icon.className = 'bx bx-menu';
}
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
}

function switchTab(name, btn) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));

    const pane = document.getElementById('tab-' + name);
    if (pane) pane.classList.add('active');
    if (btn) btn.classList.add('active');

    if (name === 'chart' && !chartsBuilt) buildCharts();
    if (name === 'hourly' && !hourlyBuilt) buildHourly();
    if (name === 'monthly' && !monthlyBuilt) buildMonthly();
}

function buildCharts() {
    chartsBuilt = true;
    const revenueCanvas = document.getElementById('revenueChart');
    const splitCanvas = document.getElementById('splitChart');
    if (!revenueCanvas || !splitCanvas) return;

    revenueChart = new Chart(revenueCanvas.getContext('2d'), {
        type: currentType,
        data: {
            labels: days,
            datasets: [
                { label: 'Collected ₹', data: collected, backgroundColor: 'rgba(34,197,94,.7)', borderColor: '#22c55e', borderWidth: 2, borderRadius: 6 },
                { label: 'Pending ₹', data: pending, backgroundColor: 'rgba(245,158,11,.5)', borderColor: '#f59e0b', borderWidth: 2, borderRadius: 6 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { color: textColor(), font: { family: 'Plus Jakarta Sans', weight: '600' } } } },
            scales: {
                x: { ticks: { color: textColor() }, grid: { color: gridColor() } },
                y: { ticks: { color: textColor(), callback: v => '₹' + v }, grid: { color: gridColor() }, beginAtZero: true }
            }
        }
    });

    new Chart(splitCanvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['B&W', 'Color'],
            datasets: [{ data: [<?= json_encode((float)$totalsRow['bw_revenue']) ?>, <?= json_encode((float)$totalsRow['color_revenue']) ?>], backgroundColor: ['#7c3aed', '#6366f1'], borderWidth: 0, hoverOffset: 8 }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { color: textColor(), font: { family: 'Plus Jakarta Sans', weight: '700' } } } }
        }
    });
}

function buildHourly() {
    hourlyBuilt = true;
    const hourlyCanvas = document.getElementById('hourlyChart');
    if (!hourlyCanvas) return;
    const labels = Array.from({ length: 24 }, (_, i) => i < 12 ? (i || 12) + ' AM' : (i === 12 ? '12 PM' : (i - 12) + ' PM'));
    new Chart(hourlyCanvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{ label: 'Jobs', data: hourlyJobs, backgroundColor: 'rgba(14,165,233,.7)', borderColor: '#0ea5e9', borderWidth: 2, borderRadius: 6 }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { color: textColor() } } },
            scales: {
                x: { ticks: { color: textColor() }, grid: { color: gridColor() } },
                y: { ticks: { color: textColor() }, grid: { color: gridColor() }, beginAtZero: true }
            }
        }
    });
}

function buildMonthly() {
    monthlyBuilt = true;
    const monthlyCanvas = document.getElementById('monthlyChart');
    if (!monthlyCanvas) return;
    new Chart(monthlyCanvas.getContext('2d'), {
        type: 'line',
        data: {
            labels: monthLabels,
            datasets: [{ label: 'Collected ₹', data: monthRevenue, borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,.1)', borderWidth: 3, fill: true, tension: .4, pointRadius: 5, pointBackgroundColor: '#22c55e' }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { color: textColor() } } },
            scales: {
                x: { ticks: { color: textColor() }, grid: { color: gridColor() } },
                y: { ticks: { color: textColor(), callback: v => '₹' + v }, grid: { color: gridColor() }, beginAtZero: true }
            }
        }
    });
}

function setChart(type) {
    currentType = type;
    if (revenueChart) {
        revenueChart.destroy();
        revenueChart = null;
        chartsBuilt = false;
        buildCharts();
    }
    const btnBar = document.getElementById('btnBar');
    const btnLine = document.getElementById('btnLine');
    if (!btnBar || !btnLine) return;
    btnBar.style.background = type === 'bar' ? 'var(--primary)' : 'var(--surface2)';
    btnLine.style.background = type === 'line' ? 'var(--primary)' : 'var(--surface2)';
    btnBar.style.color = type === 'bar' ? 'white' : 'var(--text-muted)';
    btnLine.style.color = type === 'line' ? 'white' : 'var(--text-muted)';
}

document.addEventListener('DOMContentLoaded', function() {
    applyTheme(localStorage.getItem('hp_theme') || 'light');

    const sidebarToggle = document.getElementById('mobile-toggle');
    const overlay = document.getElementById('overlay');
    const themeToggleSidebar = document.getElementById('themeToggleSidebar');
    const themeToggleMobile = document.getElementById('themeToggleMobile');

    if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);
    if (themeToggleSidebar) themeToggleSidebar.addEventListener('click', toggleTheme);
    if (themeToggleMobile) themeToggleMobile.addEventListener('click', toggleTheme);

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeSidebar();
    });

    document.querySelectorAll('.nav-item').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 1024) closeSidebar();
        });
    });

    if (activeTab === 'chart') buildCharts();
    if (activeTab === 'hourly') buildHourly();
    if (activeTab === 'monthly') buildMonthly();

    document.querySelectorAll('.paid-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            fetch('admin_collection.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'toggle_paid=1&job_id=' + encodeURIComponent(id)
            })
            .then(r => r.json())
            .then(data => {
                if (!data || !data.ok) return;
                if (data.is_paid) {
                    this.classList.remove('unpaid');
                    this.classList.add('paid');
                    this.title = 'Paid — click to unmark';
                } else {
                    this.classList.remove('paid');
                    this.classList.add('unpaid');
                    this.title = 'Unpaid — click to mark paid';
                }
            })
            .catch(() => {});
        });
    });
});

window.switchTab = switchTab;
window.setChart = setChart;
window.toggleSidebar = toggleSidebar;
window.toggleTheme = toggleTheme;
</script>
</body>
</html>
