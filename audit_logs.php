<?php
// Practical 14: Comprehensive Security & System Audit Logs Viewer
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

require_role('admin');
$user = get_current_user_data();

$filterAction = trim($_GET['action'] ?? 'All');
$search = trim($_GET['search'] ?? '');

$sql = "SELECT log_id, action, performed_by, user_role, details, ip_address, user_agent, created_at FROM audit_logs WHERE 1=1";
$params = [];
$types = "";

if ($filterAction !== 'All' && !empty($filterAction)) {
    $sql .= " AND action LIKE ?";
    $term = "%" . $filterAction . "%";
    $params[] = $term;
    $types .= "s";
}

if (!empty($search)) {
    $sql .= " AND (performed_by LIKE ? OR details LIKE ? OR ip_address LIKE ?)";
    $term = "%" . $search . "%";
    $params = array_merge($params, [$term, $term, $term]);
    $types .= "sss";
}

$sql .= " ORDER BY log_id DESC LIMIT 100";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logsResult = $stmt->get_result();

// Distinct action types for filter
$actionsRes = $conn->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC");
$allActions = [];
while ($a = $actionsRes->fetch_assoc()) {
    $allActions[] = $a['action'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Audit Logs - StudentHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #fdf2f8; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333; margin: 0; padding: 20px;
        }
        .main-container { max-width: 1200px; margin: 0 auto; }
        .nav-header {
            background: #933b55; color: white; padding: 18px 28px; border-radius: 16px;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;
            margin-bottom: 25px; box-shadow: 0 6px 20px rgba(147, 59, 85, 0.2);
        }
        .nav-header h1 { margin: 0; font-size: 1.6rem; font-weight: 800; }
        .nav-links { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .nav-links a { color: #fce7f3; text-decoration: none; padding: 8px 14px; border-radius: 8px; font-size: 0.88rem; font-weight: 600; }
        .nav-links a:hover, .nav-links a.active { background: rgba(255, 255, 255, 0.2); color: white; }
        .btn-logout { background: #ef4444 !important; color: white !important; }

        .toolbar-card {
            background: white; padding: 18px 24px; border-radius: 14px; margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #fce7f3;
            display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px;
        }
        .filter-form { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .filter-form input, .filter-form select {
            padding: 9px 13px; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.9rem;
        }
        .btn-primary {
            padding: 9px 16px; background: #933b55; color: white; border: none; border-radius: 8px;
            font-weight: 600; font-size: 0.9rem; cursor: pointer; text-decoration: none;
        }
        .btn-secondary {
            padding: 9px 14px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db;
            border-radius: 8px; font-weight: 600; font-size: 0.9rem; text-decoration: none;
        }

        .table-card {
            background: white; border-radius: 14px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #fce7f3; overflow-x: auto;
        }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem; }
        thead th {
            background: #fdf2f8; color: #831843; font-weight: 700; padding: 14px 16px;
            border-bottom: 2px solid #fce7f3; white-space: nowrap;
        }
        tbody td { padding: 12px 16px; border-bottom: 1px solid #f3e8ff; vertical-align: top; }
        tbody tr:hover { background: #fff5f8; }

        .action-tag {
            display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 0.76rem;
            font-weight: 700; font-family: monospace;
        }
        .act-auth { background: #e0e7ff; color: #3730a3; }
        .act-create { background: #dcfce7; color: #166534; }
        .act-update { background: #fef3c7; color: #92400e; }
        .act-delete { background: #fee2e2; color: #991b1b; }
        .act-sec { background: #fae8ff; color: #86198f; }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Top Nav -->
        <header class="nav-header">
            <div>
                <h1>STUDENTHUB AUDIT LOGS</h1>
                <div style="font-size:0.85rem; opacity:0.85; margin-top:2px;">Practical 14: Traceability &amp; Activity Monitoring for System Operations</div>
            </div>
            <nav class="nav-links">
                <a href="admin_dashboard.php">Dashboard</a>
                <a href="students.php">Students CRUD</a>
                <a href="manage_events.php">Events CRUD</a>
                <a href="students_ajax.php">Fetch API CRUD</a>
                <a href="audit_logs.php" class="active">Audit Logs</a>
                <a href="logout.php" class="btn-logout">Logout (<?php echo htmlspecialchars($user['username']); ?>)</a>
            </nav>
        </header>

        <!-- Toolbar -->
        <div class="toolbar-card">
            <form method="GET" action="audit_logs.php" class="filter-form">
                <input type="text" name="search" placeholder="Search user, IP, or details..." value="<?php echo htmlspecialchars($search); ?>">

                <select name="action">
                    <option value="All">All Actions</option>
                    <?php foreach ($allActions as $a): ?>
                        <option value="<?php echo htmlspecialchars($a); ?>" <?php if ($filterAction === $a) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($a); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn-primary">Filter Logs</button>
                <?php if ($filterAction !== 'All' || !empty($search)): ?>
                    <a href="audit_logs.php" class="btn-secondary">Clear</a>
                <?php endif; ?>
            </form>
            <div>
                <a href="admin_dashboard.php" class="btn-secondary">← Back to Dashboard</a>
            </div>
        </div>

        <!-- Table Card -->
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Log ID</th>
                        <th>Timestamp</th>
                        <th>Action</th>
                        <th>Performed By</th>
                        <th>Role</th>
                        <th>Event Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logsResult->num_rows === 0): ?>
                        <tr><td colspan="7" style="text-align:center; padding:30px; color:#6b7280;">No audit records match your filters.</td></tr>
                    <?php else: ?>
                        <?php while ($log = $logsResult->fetch_assoc()): ?>
                            <?php 
                                $act = $log['action'];
                                $tagClass = "act-auth";
                                if (strpos($act, 'CREATE') !== false || strpos($act, 'REGISTER') !== false) $tagClass = "act-create";
                                elseif (strpos($act, 'UPDATE') !== false || strpos($act, 'TOGGLE') !== false) $tagClass = "act-update";
                                elseif (strpos($act, 'DELETE') !== false) $tagClass = "act-delete";
                                elseif (strpos($act, 'UNAUTHORIZED') !== false || strpos($act, 'FAILED') !== false) $tagClass = "act-sec";
                            ?>
                            <tr>
                                <td>#<?php echo $log['log_id']; ?></td>
                                <td style="white-space:nowrap; font-size:0.85rem; color:#4b5563;"><?php echo date("Y-m-d H:i:s", strtotime($log['created_at'])); ?></td>
                                <td><span class="action-tag <?php echo $tagClass; ?>"><?php echo htmlspecialchars($act); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($log['performed_by']); ?></strong></td>
                                <td><span style="font-size:0.8rem; text-transform:uppercase; color:#6b7280;"><?php echo htmlspecialchars($log['user_role']); ?></span></td>
                                <td style="max-width:400px;"><?php echo htmlspecialchars($log['details']); ?></td>
                                <td style="font-family:monospace; font-size:0.82rem; color:#6b7280;"><?php echo htmlspecialchars($log['ip_address']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p style="margin-top:15px; font-size:0.85rem; color:#6b7280;">Showing up to 100 most recent immutable audit log entries recorded in MySQL <code>audit_logs</code> table.</p>
    </div>
</body>
</html>
