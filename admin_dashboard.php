<?php
// Practical 14: Admin Dashboard with RBAC, Dynamic Menu, Analytics & Audit Logging
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

require_role('admin'); // Restrict strictly to administrators

$user = get_current_user_data();

// Query 1: Total Students Count
$studentCountRes = $conn->query("SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_cnt FROM students");
$studentStats = $studentCountRes->fetch_assoc();
$totalStudents = $studentStats['total'] ?? 0;
$activeStudents = $studentStats['active_cnt'] ?? 0;

// Query 2: Events Stats (Open vs Closed)
$eventsStatsRes = $conn->query("SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) AS open_cnt, SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) AS closed_cnt FROM events");
$eventStats = $eventsStatsRes->fetch_assoc();
$totalEvents = $eventStats['total'] ?? 0;
$openEvents = $eventStats['open_cnt'] ?? 0;
$closedEvents = $eventStats['closed_cnt'] ?? 0;

// Query 3: Total Registrations Count
$regCountRes = $conn->query("SELECT COUNT(*) AS total FROM registrations");
$totalRegistrations = $regCountRes->fetch_assoc()['total'] ?? 0;

// Query 4: Total Users & Active Accounts
$usersStatsRes = $conn->query("SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_users FROM users");
$userStats = $usersStatsRes->fetch_assoc();
$totalUsers = $userStats['total'] ?? 0;
$activeUsers = $userStats['active_users'] ?? 0;

// Query 5: Latest 5 Event Registrations (Joined query)
$latestRegsRes = $conn->query("
    SELECT r.reg_id, s.name AS student_name, s.enrollment_no, e.title AS event_title, e.category, r.reg_date, r.status
    FROM registrations r
    JOIN students s ON r.student_id = s.student_id
    JOIN events e ON r.event_id = e.event_id
    ORDER BY r.reg_id DESC
    LIMIT 6
");

// Query 6: Latest 6 Audit Log Entries
$latestAuditRes = $conn->query("SELECT log_id, action, performed_by, user_role, details, ip_address, created_at FROM audit_logs ORDER BY log_id DESC LIMIT 6");

// Query 7: Users Table for Active/Inactive Toggle (Intermediate Extension)
$usersListRes = $conn->query("SELECT user_id, username, email, role, status, last_login FROM users ORDER BY user_id ASC LIMIT 10");

// Query 8: Analytics Data - Registrations by Event Category (Advanced Extension)
$catAnalyticsRes = $conn->query("
    SELECT e.category, COUNT(r.reg_id) AS reg_count
    FROM events e
    LEFT JOIN registrations r ON e.event_id = r.event_id
    GROUP BY e.category
");
$analyticsData = [];
while ($row = $catAnalyticsRes->fetch_assoc()) {
    $analyticsData[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard &amp; Analytics - StudentHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #fdf2f8;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333333;
            margin: 0;
            padding: 20px;
        }
        .main-container { max-width: 1250px; margin: 0 auto; }

        /* Dynamic Menu Nav */
        .nav-header {
            background: #933b55;
            color: white;
            padding: 18px 28px;
            border-radius: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
            box-shadow: 0 6px 20px rgba(147, 59, 85, 0.2);
        }
        .nav-header h1 { margin: 0; font-size: 1.6rem; font-weight: 800; }
        .nav-header .sub { font-size: 0.85rem; opacity: 0.85; margin-top: 2px; }
        .nav-links { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .nav-links a {
            color: #fce7f3; text-decoration: none; padding: 8px 14px; border-radius: 8px; font-size: 0.88rem; font-weight: 600;
            transition: background 0.2s;
        }
        .nav-links a:hover, .nav-links a.active { background: rgba(255, 255, 255, 0.2); color: white; }
        .btn-logout { background: #ef4444 !important; color: white !important; }
        .btn-logout:hover { background: #dc2626 !important; }

        /* Banner alerts */
        .alert-banner {
            padding: 12px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 0.92rem;
            background: #dcfce7; border: 1px solid #22c55e; color: #15803d;
        }

        /* KPI Cards Grid */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .kpi-card {
            background: white;
            padding: 22px;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #fce7f3;
            display: flex;
            align-items: center;
            gap: 18px;
            transition: transform 0.2s;
        }
        .kpi-card:hover { transform: translateY(-3px); }
        .kpi-icon {
            width: 54px; height: 54px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem; flex-shrink: 0;
        }
        .icon-students { background: #fdf2f8; color: #933b55; }
        .icon-events { background: #eff6ff; color: #1d4ed8; }
        .icon-regs { background: #ecfdf5; color: #047857; }
        .icon-users { background: #faf5ff; color: #7e22ce; }
        .kpi-info h4 { margin: 0 0 4px 0; color: #6b7280; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; }
        .kpi-info .kpi-num { margin: 0; font-size: 1.8rem; font-weight: 800; color: #1f2937; }
        .kpi-info .kpi-sub { font-size: 0.78rem; color: #9ca3af; margin-top: 3px; }

        /* Two Column Layout */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }
        @media (max-width: 900px) {
            .dashboard-grid { grid-template-columns: 1fr; }
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #fce7f3;
            margin-bottom: 25px;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #fce7f3;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .card-header h3 { margin: 0; color: #933b55; font-size: 1.15rem; }
        .card-header a { font-size: 0.85rem; color: #933b55; text-decoration: none; font-weight: 600; }
        .card-header a:hover { text-decoration: underline; }

        table { width: 100%; border-collapse: collapse; font-size: 0.88rem; text-align: left; }
        th { background: #fdf2f8; color: #831843; padding: 10px 12px; font-weight: 600; border-bottom: 1px solid #fce7f3; }
        td { padding: 10px 12px; border-bottom: 1px solid #f3e8ff; vertical-align: middle; }
        tr:hover { background: #fff5f8; }

        .badge {
            display: inline-block; padding: 3px 8px; border-radius: 10px; font-size: 0.74rem; font-weight: 700;
        }
        .badge-active { background: #dcfce7; color: #15803d; }
        .badge-inactive { background: #fee2e2; color: #b91c1c; }
        .badge-admin { background: #ede9fe; color: #5b21b6; }
        .badge-student { background: #e0e7ff; color: #3730a3; }

        /* Status Toggle Form */
        .toggle-btn {
            padding: 4px 9px; border-radius: 6px; font-size: 0.76rem; font-weight: bold; border: none; cursor: pointer;
        }
        .btn-deactivate { background: #fee2e2; color: #b91c1c; }
        .btn-activate { background: #dcfce7; color: #15803d; }

        /* Analytics Chart Container */
        .chart-box {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 10px;
        }
        .chart-row {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.85rem;
        }
        .chart-label { width: 90px; font-weight: 600; color: #4b5563; }
        .chart-bar-bg {
            flex: 1; height: 18px; background: #f3f4f6; border-radius: 9px; overflow: hidden;
        }
        .chart-bar-fill {
            height: 100%; background: linear-gradient(90deg, #933b55, #db2777); border-radius: 9px;
            transition: width 0.5s ease;
        }
        .chart-val { width: 35px; text-align: right; font-weight: bold; color: #933b55; }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Top Nav Header with Role-Based Menu (Practical 14) -->
        <header class="nav-header">
            <div>
                <h1>STUDENTHUB ADMIN PORTAL</h1>
                <div class="sub">Role-Based Dashboard, Real-time Aggregation, User Status Control &amp; Audit Trail</div>
            </div>
            <nav class="nav-links">
                <a href="admin_dashboard.php" class="active">Dashboard</a>
                <a href="students.php">Students CRUD</a>
                <a href="manage_events.php">Events CRUD</a>
                <a href="students_ajax.php">Fetch API CRUD</a>
                <a href="audit_logs.php">Audit Logs</a>
                <a href="logout.php" class="btn-logout">Logout (<?php echo htmlspecialchars($user['username']); ?>)</a>
            </nav>
        </header>

        <?php if (!empty($_GET['msg'])): ?>
            <div class="alert-banner">✓ <?php echo htmlspecialchars($_GET['msg']); ?></div>
        <?php endif; ?>

        <!-- Dynamic Statistics KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-icon icon-students">🎓</div>
                <div class="kpi-info">
                    <h4>Total Students</h4>
                    <p class="kpi-num"><?php echo $totalStudents; ?></p>
                    <span class="kpi-sub"><?php echo $activeStudents; ?> Active records</span>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon icon-events">🎪</div>
                <div class="kpi-info">
                    <h4>Campus Events</h4>
                    <p class="kpi-num"><?php echo $totalEvents; ?></p>
                    <span class="kpi-sub"><?php echo $openEvents; ?> Open | <?php echo $closedEvents; ?> Closed</span>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon icon-regs">📝</div>
                <div class="kpi-info">
                    <h4>Registrations</h4>
                    <p class="kpi-num"><?php echo $totalRegistrations; ?></p>
                    <span class="kpi-sub">Across all event categories</span>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon icon-users">👥</div>
                <div class="kpi-info">
                    <h4>Active Accounts</h4>
                    <p class="kpi-num"><?php echo $activeUsers; ?> / <?php echo $totalUsers; ?></p>
                    <span class="kpi-sub">RBAC Authenticated</span>
                </div>
            </div>
        </div>

        <!-- Main Dashboard Split Grid -->
        <div class="dashboard-grid">
            <!-- Left Column: Latest Registrations & Users Management -->
            <div>
                <!-- Recent Registrations -->
                <div class="card">
                    <div class="card-header">
                        <h3>Latest Student Event Registrations</h3>
                        <a href="manage_events.php">View Events →</a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Enrollment</th>
                                <th>Event Title</th>
                                <th>Category</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($r = $latestRegsRes->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($r['student_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($r['enrollment_no']); ?></td>
                                    <td><?php echo htmlspecialchars($r['event_title']); ?></td>
                                    <td><span class="badge" style="background:#fce7f3; color:#933b55;"><?php echo htmlspecialchars($r['category']); ?></span></td>
                                    <td><?php echo date("M d, Y", strtotime($r['reg_date'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- User Accounts & Active/Inactive Status Toggle (Intermediate Extension) -->
                <div class="card">
                    <div class="card-header">
                        <h3>User Accounts &amp; Access Status Toggle</h3>
                        <span style="font-size:0.8rem; color:#6b7280;">Practical 14 Intermediate Extension</span>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Username</th>
                                <th>Email Address</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Action Toggle</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($u = $usersListRes->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $u['user_id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td><span class="badge badge-<?php echo $u['role']; ?>"><?php echo strtoupper($u['role']); ?></span></td>
                                    <td><span class="badge badge-<?php echo $u['status']; ?>"><?php echo ucfirst($u['status']); ?></span></td>
                                    <td>
                                        <?php if ($u['username'] !== 'admin'): ?>
                                            <form method="POST" action="toggle_user_status.php" style="display:inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                                <?php if ($u['status'] === 'active'): ?>
                                                    <input type="hidden" name="status" value="inactive">
                                                    <button type="submit" class="toggle-btn btn-deactivate">Deactivate</button>
                                                <?php else: ?>
                                                    <input type="hidden" name="status" value="active">
                                                    <button type="submit" class="toggle-btn btn-activate">Activate</button>
                                                <?php endif; ?>
                                            </form>
                                        <?php else: ?>
                                            <span style="color:#9ca3af; font-size:0.75rem;">Root Admin</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right Column: Analytics Chart & Recent Audit Logs -->
            <div>
                <!-- Analytics Chart: Registrations by Event Category (Advanced Extension) -->
                <div class="card">
                    <div class="card-header">
                        <h3>Event Analytics</h3>
                        <span style="font-size:0.8rem; color:#6b7280;">By Category</span>
                    </div>
                    <div class="chart-box">
                        <?php 
                            $maxCount = 1;
                            foreach ($analyticsData as $d) {
                                if ($d['reg_count'] > $maxCount) $maxCount = $d['reg_count'];
                            }
                            foreach ($analyticsData as $d):
                                $pct = round(($d['reg_count'] / $maxCount) * 100);
                        ?>
                            <div class="chart-row">
                                <span class="chart-label"><?php echo htmlspecialchars($d['category']); ?></span>
                                <div class="chart-bar-bg">
                                    <div class="chart-bar-fill" style="width: <?php echo max($pct, 8); ?>%;"></div>
                                </div>
                                <span class="chart-val"><?php echo $d['reg_count']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Recent Audit Log Entries -->
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Audit Trail</h3>
                        <a href="audit_logs.php">All Logs →</a>
                    </div>
                    <div style="font-size: 0.84rem; display: flex; flex-direction: column; gap: 12px;">
                        <?php while ($log = $latestAuditRes->fetch_assoc()): ?>
                            <div style="border-left: 3px solid #933b55; padding-left: 10px;">
                                <div style="display:flex; justify-content:space-between; margin-bottom: 2px;">
                                    <strong style="color: #933b55;"><?php echo htmlspecialchars($log['action']); ?></strong>
                                    <span style="color: #9ca3af; font-size: 0.75rem;"><?php echo date("H:i:s", strtotime($log['created_at'])); ?></span>
                                </div>
                                <div style="color: #4b5563; font-size: 0.8rem;"><?php echo htmlspecialchars($log['details']); ?></div>
                                <div style="color: #9ca3af; font-size: 0.72rem; margin-top: 2px;">
                                    By: <?php echo htmlspecialchars($log['performed_by']); ?> (<?php echo htmlspecialchars($log['ip_address']); ?>)
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
