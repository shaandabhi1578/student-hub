<?php
// Student Profile & Dashboard Page (RBAC Protected)
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

require_login();
$user = get_current_user_data();

// Look up student details from students table
$student = null;
if ($conn) {
    $stmt = $conn->prepare("SELECT student_id, enrollment_no, name, email, phone, course, semester, gender, status, created_at FROM students WHERE user_id = ? OR email = ? LIMIT 1");
    $stmt->bind_param("is", $user['id'], $user['email']);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fallback student details if not linked
if (!$student) {
    $student = [
        'student_id' => 0,
        'enrollment_no' => 'STU-' . str_pad($user['id'], 4, '0', STR_PAD_LEFT),
        'name' => ucfirst($user['username']),
        'email' => $user['email'],
        'phone' => 'Not Provided',
        'course' => 'B.Tech Computer Engineering',
        'semester' => 4,
        'gender' => 'Student',
        'status' => 'active',
        'created_at' => date('Y-m-d')
    ];
}

// Fetch registered events for this student
$registeredEvents = [];
if ($student['student_id'] > 0 && $conn) {
    $rStmt = $conn->prepare("
        SELECT e.event_id, e.title, e.category, e.event_date, e.location, e.status, r.reg_date
        FROM registrations r
        JOIN events e ON r.event_id = e.event_id
        WHERE r.student_id = ?
        ORDER BY e.event_date ASC
    ");
    $rStmt->bind_param("i", $student['student_id']);
    $rStmt->execute();
    $rRes = $rStmt->get_result();
    while ($rRow = $rRes->fetch_assoc()) {
        $registeredEvents[] = $rRow;
    }
    $rStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile &amp; Dashboard - StudentHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #fdf2f8;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333333;
            margin: 0;
            padding: 20px;
        }
        .main-container { max-width: 1050px; margin: 0 auto; }
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
        .nav-links { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .nav-links a {
            color: #fce7f3; text-decoration: none; padding: 8px 14px; border-radius: 8px; font-size: 0.88rem; font-weight: 600;
        }
        .nav-links a:hover, .nav-links a.active { background: rgba(255, 255, 255, 0.2); color: white; }
        .btn-logout { background: #ef4444 !important; color: white !important; }

        .profile-layout {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 25px;
        }
        @media (max-width: 768px) {
            .profile-layout { grid-template-columns: 1fr; }
        }

        .card {
            background: white;
            padding: 28px;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #fce7f3;
            margin-bottom: 25px;
        }
        .profile-sidebar {
            text-align: center;
        }
        .avatar {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            background: #933b55;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.8rem;
            font-weight: bold;
            margin: 0 auto 16px auto;
            border: 4px solid #fce7f3;
            box-shadow: 0 4px 12px rgba(147, 59, 85, 0.2);
        }
        .enroll-tag {
            display: inline-block;
            background: #ede9fe;
            color: #5b21b6;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-bottom: 15px;
        }
        .role-badge {
            display: inline-block;
            background: #dcfce7;
            color: #15803d;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.78rem;
            text-transform: uppercase;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f3e8ff;
            font-size: 0.9rem;
        }
        .detail-label { color: #6b7280; font-weight: 600; }
        .detail-val { color: #1f2937; font-weight: 600; }

        .card h3 {
            margin: 0 0 16px 0;
            color: #933b55;
            border-bottom: 2px solid #fce7f3;
            padding-bottom: 8px;
            font-size: 1.15rem;
        }
        .events-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
            text-align: left;
        }
        .events-table th {
            background: #fdf2f8; color: #831843; padding: 10px 12px; font-weight: 600; border-bottom: 1px solid #fce7f3;
        }
        .events-table td {
            padding: 10px 12px; border-bottom: 1px solid #f3e8ff; vertical-align: middle;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Top Nav Header -->
        <header class="nav-header">
            <div>
                <h1>STUDENTHUB PORTAL</h1>
                <div style="font-size:0.85rem; opacity:0.85; margin-top:2px;">Logged in as: <?php echo htmlspecialchars($user['username']); ?> (<?php echo strtoupper($user['role']); ?>)</div>
            </div>
            <nav class="nav-links">
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="admin_dashboard.php">Admin Dashboard</a>
                <?php endif; ?>
                <a href="profile.php" class="active">My Profile</a>
                <a href="events.html">Campus Events</a>
                <a href="students.php">Students Directory</a>
                <a href="logout.php" class="btn-logout">Logout</a>
            </nav>
        </header>

        <div class="profile-layout">
            <!-- Sidebar Card -->
            <div class="card profile-sidebar">
                <div class="avatar">
                    <?php echo strtoupper(substr($student['name'], 0, 1)); ?>
                </div>
                <h2 style="margin: 0 0 6px 0; color: #831843;"><?php echo htmlspecialchars($student['name']); ?></h2>
                <div class="enroll-tag"><?php echo htmlspecialchars($student['enrollment_no']); ?></div>
                <div><span class="role-badge">Account Status: <?php echo ucfirst($student['status']); ?></span></div>

                <div style="margin-top: 25px; text-align: left;">
                    <div class="detail-row">
                        <span class="detail-label">Username:</span>
                        <span class="detail-val"><?php echo htmlspecialchars($user['username']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Email:</span>
                        <span class="detail-val"><?php echo htmlspecialchars($student['email']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Mobile:</span>
                        <span class="detail-val">+91 <?php echo htmlspecialchars($student['phone']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Role:</span>
                        <span class="detail-val"><?php echo ucfirst($user['role']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Right Content Area -->
            <div>
                <!-- Academic Information -->
                <div class="card">
                    <h3>Academic Enrollment Information</h3>
                    <div class="detail-row">
                        <span class="detail-label">Enrolled Degree Program:</span>
                        <span class="detail-val"><?php echo htmlspecialchars($student['course']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Current Academic Semester:</span>
                        <span class="detail-val">Semester <?php echo $student['semester']; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Institute / Department:</span>
                        <span class="detail-val">Department of Computer Engineering</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Registration Timestamp:</span>
                        <span class="detail-val"><?php echo date("F d, Y", strtotime($student['created_at'])); ?></span>
                    </div>
                </div>

                <!-- Enrolled Campus Events -->
                <div class="card">
                    <h3>My Registered Campus Events (<?php echo count($registeredEvents); ?>)</h3>
                    <?php if (empty($registeredEvents)): ?>
                        <p style="color: #6b7280; font-size: 0.9rem;">You have not registered for any upcoming events yet.</p>
                        <a href="events.html" style="display:inline-block; margin-top:8px; color:#933b55; font-weight:600; text-decoration:none;">Browse Campus Events →</a>
                    <?php else: ?>
                        <table class="events-table">
                            <thead>
                                <tr>
                                    <th>Event Title</th>
                                    <th>Category</th>
                                    <th>Date</th>
                                    <th>Venue</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registeredEvents as $re): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($re['title']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($re['category']); ?></td>
                                        <td><?php echo date("M d, Y", strtotime($re['event_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($re['location']); ?></td>
                                        <td><span style="color:green; font-weight:bold;">Confirmed</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
