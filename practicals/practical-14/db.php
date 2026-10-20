<?php
// StudentHub Central Database & Security Configuration
// Used across Practicals 8, 9, 10, 11, 12, 13, 14

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

$db_host = "127.0.0.1";
$db_user = "root";
$db_pass = "";
$db_name = "studenthub_db";
$db_port = 3306;

// 1. MySQLi Connection (used in Practicals 9, 11, 12, 13, 14)
$conn = null;
try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
    if ($conn->connect_error) {
        $mysqli_error = $conn->connect_error;
        $conn = null;
    } else {
        $conn->set_charset("utf8mb4");
    }
} catch (Exception $e) {
    $mysqli_error = $e->getMessage();
    $conn = null;
}

// 2. PDO Connection (used in Practical 8 & advanced queries)
$pdo = null;
$isConnected = false;
$connectionStatus = "";

try {
    $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    $connectionStatus = "Connected successfully to database '$db_name' via MySQLi & PDO!";
    $isConnected = true;
} catch (PDOException $e) {
    $connectionStatus = "Database Connection Failed: " . $e->getMessage();
    $isConnected = false;
}

/**
 * Log an event to the audit_logs table (Practicals 9, 10, 12, 14)
 */
function log_audit($conn, $action, $details = '', $performed_by = 'system', $user_role = 'guest') {
    if (!$conn) return false;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Agent', 0, 250);
    
    $stmt = $conn->prepare("INSERT INTO audit_logs (action, performed_by, user_role, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssssss", $action, $performed_by, $user_role, $details, $ip, $ua);
        $stmt->execute();
        $stmt->close();
        return true;
    }
    return false;
}

/**
 * CSRF Token Management (Practical 9 & Forms)
 */
function generate_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Standalone direct browser access diagnostic view
if (basename($_SERVER["SCRIPT_FILENAME"]) === "db.php") {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Database Connection & Diagnostics - StudentHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .db-status-card {
            max-width: 700px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            text-align: left;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 10px 15px;
            border-radius: 6px;
            font-weight: bold;
            margin: 15px 0;
        }
        .status-success { background: #dcfce7; color: #15803d; border: 1px solid #22c55e; }
        .status-fail { background: #fee2e2; color: #b91c1c; border: 1px solid #ef4444; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; font-size: 0.9rem; }
        th { background: #933b55; color: white; }
        .nav-links { margin-top: 20px; display: flex; gap: 10px; }
        .btn { padding: 8px 16px; background: #933b55; color: white; text-decoration: none; border-radius: 6px; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="db-status-card">
        <h2>StudentHub Database Status</h2>
        <div class="status-badge <?php echo $isConnected ? 'status-success' : 'status-fail'; ?>">
            <?php echo $connectionStatus; ?>
        </div>
        <p><strong>Host:</strong> <?php echo $db_host; ?>:<?php echo $db_port; ?> | <strong>Database:</strong> <?php echo $db_name; ?></p>
        <p><strong>Drivers:</strong> MySQLi (Active) &amp; PDO (Active)</p>

        <?php if ($conn): ?>
            <h3>Table Statistics:</h3>
            <?php
            $counts = [];
            foreach (['users', 'students', 'events', 'registrations', 'audit_logs'] as $tbl) {
                $res = $conn->query("SELECT COUNT(*) AS c FROM $tbl");
                $counts[$tbl] = $res ? $res->fetch_assoc()['c'] : 0;
            }
            ?>
            <table>
                <tr><th>Table Name</th><th>Record Count</th><th>Status</th></tr>
                <?php foreach ($counts as $tbl => $cnt): ?>
                    <tr>
                        <td><strong><?php echo $tbl; ?></strong></td>
                        <td><?php echo $cnt; ?></td>
                        <td><span style="color: green; font-weight: bold;">Ready</span></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <div class="nav-links">
            <a href="index.html" class="btn">← Back to Home</a>
            <a href="login.php" class="btn">Login Portal</a>
            <a href="admin_dashboard.php" class="btn">Admin Dashboard</a>
        </div>
    </div>
</body>
</html>
<?php
}
