<?php
// Practical 11: Student Management CRUD with Search, Filter, Soft Delete, and CSV Export
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

require_login(); // Protected page

$user = get_current_user_data();
$msg = "";
$msgType = "";

// -------------------------------------------------------------
// POST Actions: Create, Update, Soft Delete, Restore, Hard Delete
// -------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';

    // 1. CREATE Student
    if ($action === 'create') {
        $name     = trim(htmlspecialchars($_POST['name'] ?? ''));
        $email    = trim(htmlspecialchars($_POST['email'] ?? ''));
        $phone    = trim(htmlspecialchars($_POST['phone'] ?? ''));
        $course   = trim(htmlspecialchars($_POST['course'] ?? ''));
        $semester = intval($_POST['semester'] ?? 1);
        $gender   = $_POST['gender'] ?? 'Other';
        $enroll   = trim(htmlspecialchars($_POST['enrollment_no'] ?? ''));

        if (empty($enroll)) {
            $enroll = "23BE" . strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $course), 0, 2)) . rand(100, 999);
        }

        if (empty($name) || empty($email) || empty($phone) || empty($course)) {
            $msg = "Please fill in all required fields.";
            $msgType = "error";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = "Invalid email format.";
            $msgType = "error";
        } else {
            // Check duplicate email / enrollment
            $chk = $conn->prepare("SELECT student_id FROM students WHERE email = ? OR enrollment_no = ?");
            $chk->bind_param("ss", $email, $enroll);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $msg = "A student with this email or enrollment number already exists.";
                $msgType = "error";
            } else {
                $ins = $conn->prepare("INSERT INTO students (enrollment_no, name, email, phone, course, semester, gender, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
                $ins->bind_param("sssssis", $enroll, $name, $email, $phone, $course, $semester, $gender);
                if ($ins->execute()) {
                    $newId = $conn->insert_id;
                    $msg = "Student '<strong>" . htmlspecialchars($name) . "</strong>' added successfully! (ID: #$newId)";
                    $msgType = "success";
                    log_audit($conn, "STUDENT_CREATE", "Added student: $name (Enrollment: $enroll)", $user['username'], $user['role']);
                } else {
                    $msg = "Failed to add student: " . $conn->error;
                    $msgType = "error";
                }
                $ins->close();
            }
            $chk->close();
        }
    }

    // 2. UPDATE Student
    elseif ($action === 'update') {
        $id       = intval($_POST['student_id'] ?? 0);
        $name     = trim(htmlspecialchars($_POST['name'] ?? ''));
        $email    = trim(htmlspecialchars($_POST['email'] ?? ''));
        $phone    = trim(htmlspecialchars($_POST['phone'] ?? ''));
        $course   = trim(htmlspecialchars($_POST['course'] ?? ''));
        $semester = intval($_POST['semester'] ?? 1);
        $gender   = $_POST['gender'] ?? 'Other';
        $status   = $_POST['status'] ?? 'active';

        if ($id <= 0 || empty($name) || empty($email)) {
            $msg = "Invalid student details submitted.";
            $msgType = "error";
        } else {
            // Check duplicate email for other students
            $chk = $conn->prepare("SELECT student_id FROM students WHERE email = ? AND student_id != ?");
            $chk->bind_param("si", $email, $id);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $msg = "Email address is already used by another student.";
                $msgType = "error";
            } else {
                $upd = $conn->prepare("UPDATE students SET name = ?, email = ?, phone = ?, course = ?, semester = ?, gender = ?, status = ? WHERE student_id = ?");
                $upd->bind_param("ssssissi", $name, $email, $phone, $course, $semester, $gender, $status, $id);
                if ($upd->execute()) {
                    $msg = "Student record #$id updated successfully.";
                    $msgType = "success";
                    log_audit($conn, "STUDENT_UPDATE", "Updated student #$id: $name", $user['username'], $user['role']);
                } else {
                    $msg = "Update failed: " . $conn->error;
                    $msgType = "error";
                }
                $upd->close();
            }
            $chk->close();
        }
    }

    // 3. SOFT DELETE (Advanced Extension: status = 'archived')
    elseif ($action === 'soft_delete') {
        $id = intval($_POST['student_id'] ?? 0);
        $upd = $conn->prepare("UPDATE students SET status = 'archived' WHERE student_id = ?");
        $upd->bind_param("i", $id);
        if ($upd->execute()) {
            $msg = "Student #$id soft-deleted (moved to Archived status). Record preserved.";
            $msgType = "success";
            log_audit($conn, "STUDENT_SOFT_DELETE", "Archived student #$id", $user['username'], $user['role']);
        }
        $upd->close();
    }

    // 4. RESTORE ARCHIVED STUDENT
    elseif ($action === 'restore') {
        $id = intval($_POST['student_id'] ?? 0);
        $upd = $conn->prepare("UPDATE students SET status = 'active' WHERE student_id = ?");
        $upd->bind_param("i", $id);
        if ($upd->execute()) {
            $msg = "Student #$id restored to Active status.";
            $msgType = "success";
            log_audit($conn, "STUDENT_RESTORE", "Restored student #$id", $user['username'], $user['role']);
        }
        $upd->close();
    }

    // 5. PERMANENT DELETE (Hard delete with confirmation)
    elseif ($action === 'hard_delete') {
        $id = intval($_POST['student_id'] ?? 0);
        $del = $conn->prepare("DELETE FROM students WHERE student_id = ?");
        $del->bind_param("i", $id);
        if ($del->execute()) {
            $msg = "Student record #$id permanently removed from database.";
            $msgType = "success";
            log_audit($conn, "STUDENT_PERMANENT_DELETE", "Permanently deleted student #$id", $user['username'], $user['role']);
        } else {
            $msg = "Delete failed: " . $conn->error;
            $msgType = "error";
        }
        $del->close();
    }
}

// -------------------------------------------------------------
// GET Query: Search, Filter, and Fetch
// -------------------------------------------------------------
$searchTerm = trim($_GET['search'] ?? '');
$filterCourse = trim($_GET['course'] ?? '');
$filterStatus = trim($_GET['status'] ?? 'all');

$query = "SELECT student_id, enrollment_no, name, email, phone, course, semester, gender, status, created_at FROM students WHERE 1=1";
$params = [];
$types = "";

if (!empty($searchTerm)) {
    $query .= " AND (name LIKE ? OR email LIKE ? OR enrollment_no LIKE ? OR phone LIKE ?)";
    $like = "%" . $searchTerm . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "ssss";
}

if (!empty($filterCourse) && $filterCourse !== 'All') {
    $query .= " AND course = ?";
    $params[] = $filterCourse;
    $types .= "s";
}

if ($filterStatus !== 'all' && !empty($filterStatus)) {
    $query .= " AND status = ?";
    $params[] = $filterStatus;
    $types .= "s";
}

$query .= " ORDER BY student_id DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$studentsResult = $stmt->get_result();
$totalStudentsCount = $studentsResult->num_rows;

// Fetch unique courses for filter dropdown
$coursesRes = $conn->query("SELECT DISTINCT course FROM students ORDER BY course ASC");
$allCourses = [];
while ($c = $coursesRes->fetch_assoc()) {
    $allCourses[] = $c['course'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management CRUD - StudentHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #fdf2f8;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333333;
            margin: 0;
            padding: 20px;
        }
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
        }
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
        .nav-header h1 {
            margin: 0;
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: 0.5px;
        }
        .nav-header .sub {
            font-size: 0.85rem;
            opacity: 0.85;
            margin-top: 2px;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .nav-links a {
            color: #fce7f3;
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 0.88rem;
            font-weight: 600;
            transition: background 0.2s;
        }
        .nav-links a:hover, .nav-links a.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }
        .btn-logout {
            background: #ef4444 !important;
            color: white !important;
        }
        .btn-logout:hover {
            background: #dc2626 !important;
        }
        .alert-box {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }
        .alert-success { background: #dcfce7; border: 1px solid #22c55e; color: #15803d; }
        .alert-error { background: #fee2e2; border: 1px solid #ef4444; color: #b91c1c; }

        /* Toolbar Card */
        .toolbar-card {
            background: white;
            padding: 20px 24px;
            border-radius: 14px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #fce7f3;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            flex: 1;
        }
        .filter-form input[type="text"],
        .filter-form select {
            padding: 9px 13px;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.9rem;
            outline: none;
        }
        .filter-form input[type="text"]:focus,
        .filter-form select:focus {
            border-color: #933b55;
        }
        .btn-primary {
            padding: 9px 18px;
            background: #933b55;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary:hover { background: #782941; }
        .btn-secondary {
            padding: 9px 16px;
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-secondary:hover { background: #e5e7eb; }
        .btn-export {
            background: #059669;
            color: white;
            padding: 9px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-export:hover { background: #047857; }

        /* Table Card */
        .table-card {
            background: white;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #fce7f3;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.92rem;
        }
        thead th {
            background: #fdf2f8;
            color: #831843;
            font-weight: 700;
            padding: 14px 16px;
            border-bottom: 2px solid #fce7f3;
            white-space: nowrap;
        }
        tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid #f3e8ff;
            vertical-align: middle;
        }
        tbody tr:hover {
            background: #fff5f8;
        }
        .badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 12px;
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-active { background: #dcfce7; color: #15803d; }
        .badge-inactive { background: #fee2e2; color: #b91c1c; }
        .badge-archived { background: #f3f4f6; color: #6b7280; border: 1px solid #d1d5db; }

        .actions-cell {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .btn-table {
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-edit { background: #e0e7ff; color: #3730a3; }
        .btn-edit:hover { background: #c7d2fe; }
        .btn-archive { background: #fef3c7; color: #92400e; }
        .btn-archive:hover { background: #fde68a; }
        .btn-restore { background: #dcfce7; color: #166534; }
        .btn-delete { background: #fee2e2; color: #991b1b; }
        .btn-delete:hover { background: #fecaca; }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 28px;
            width: 95%;
            max-width: 550px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: left;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #fce7f3;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .modal-header h3 { margin: 0; color: #933b55; }
        .modal-close {
            background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #9ca3af;
        }
        .form-row { margin-bottom: 14px; }
        .form-row label { display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 4px; color: #374151; }
        .form-row input, .form-row select {
            width: 100%; padding: 10px; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; box-sizing: border-box;
        }
        .modal-footer {
            display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Top Nav Header -->
        <header class="nav-header">
            <div>
                <h1>STUDENTHUB MANAGEMENT</h1>
                <div class="sub">Practical 11: Student Records CRUD (Search, Filter, Soft Delete &amp; CSV Export)</div>
            </div>
            <nav class="nav-links">
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="admin_dashboard.php">Dashboard</a>
                <?php else: ?>
                    <a href="profile.php">My Profile</a>
                <?php endif; ?>
                <a href="students.php" class="active">Students CRUD</a>
                <a href="manage_events.php">Events CRUD</a>
                <a href="students_ajax.php">Fetch API CRUD</a>
                <a href="events.html">Campus Events</a>
                <a href="logout.php" class="btn-logout">Logout (<?php echo htmlspecialchars($user['username']); ?>)</a>
            </nav>
        </header>

        <!-- Feedback Alert Messages -->
        <?php if (!empty($msg)): ?>
            <div class="alert-box <?php echo $msgType === 'success' ? 'alert-success' : 'alert-error'; ?>">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <!-- Toolbar: Search, Filter, Add, Export -->
        <div class="toolbar-card">
            <form method="GET" action="students.php" class="filter-form">
                <input type="text" name="search" placeholder="Search name, email, enrollment..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                
                <select name="course">
                    <option value="All">All Courses</option>
                    <?php foreach ($allCourses as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>" <?php if ($filterCourse === $c) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($c); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status">
                    <option value="all" <?php if ($filterStatus === 'all') echo 'selected'; ?>>All Statuses</option>
                    <option value="active" <?php if ($filterStatus === 'active') echo 'selected'; ?>>Active Only</option>
                    <option value="inactive" <?php if ($filterStatus === 'inactive') echo 'selected'; ?>>Inactive Only</option>
                    <option value="archived" <?php if ($filterStatus === 'archived') echo 'selected'; ?>>Archived (Soft Deleted)</option>
                </select>

                <button type="submit" class="btn-primary">Filter</button>
                <?php if (!empty($searchTerm) || !empty($filterCourse) || $filterStatus !== 'all'): ?>
                    <a href="students.php" class="btn-secondary">Clear Filters</a>
                <?php endif; ?>
            </form>

            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn-primary" onclick="openAddModal()">+ Add New Student</button>
                <a href="export_students_csv.php?search=<?php echo urlencode($searchTerm); ?>&course=<?php echo urlencode($filterCourse); ?>&status=<?php echo urlencode($filterStatus); ?>" class="btn-export">
                    📥 Export CSV
                </a>
            </div>
        </div>

        <!-- Student Records Table -->
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Enrollment No.</th>
                        <th>Student Name</th>
                        <th>Email &amp; Phone</th>
                        <th>Course &amp; Sem</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($totalStudentsCount === 0): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: #6b7280;">
                                No students match your search/filter criteria. Try clearing filters or adding a student.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php while ($s = $studentsResult->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $s['student_id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($s['enrollment_no']); ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($s['name']); ?></strong><br>
                                    <small style="color: #6b7280;"><?php echo htmlspecialchars($s['gender']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($s['email']); ?><br>
                                    <small style="color: #6b7280;">+91 <?php echo htmlspecialchars($s['phone']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($s['course']); ?><br>
                                    <small style="color: #6b7280;">Semester <?php echo $s['semester']; ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($s['status']); ?>">
                                        <?php echo ucfirst($s['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions-cell">
                                        <!-- Edit Button -->
                                        <button type="button" class="btn-table btn-edit" onclick='openEditModal(<?php echo json_encode($s); ?>)'>Edit</button>
                                        
                                        <!-- Soft Delete / Restore -->
                                        <?php if ($s['status'] !== 'archived'): ?>
                                            <form method="POST" action="students.php" style="display:inline;" onsubmit="return confirm('Soft-delete this student to Archived status?');">
                                                <input type="hidden" name="action" value="soft_delete">
                                                <input type="hidden" name="student_id" value="<?php echo $s['student_id']; ?>">
                                                <button type="submit" class="btn-table btn-archive" title="Soft delete (preserve record)">Archive</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="students.php" style="display:inline;">
                                                <input type="hidden" name="action" value="restore">
                                                <input type="hidden" name="student_id" value="<?php echo $s['student_id']; ?>">
                                                <button type="submit" class="btn-table btn-restore">Restore</button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Hard Delete with Confirmation Modal -->
                                        <form method="POST" action="students.php" style="display:inline;" onsubmit="return confirm('⚠️ WARNING: Are you sure you want to permanently delete <?php echo htmlspecialchars($s['name']); ?> from MySQL? This action cannot be undone.');">
                                            <input type="hidden" name="action" value="hard_delete">
                                            <input type="hidden" name="student_id" value="<?php echo $s['student_id']; ?>">
                                            <button type="submit" class="btn-table btn-delete">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p style="margin-top: 15px; font-size: 0.88rem; color: #6b7280;">Showing <strong><?php echo $totalStudentsCount; ?></strong> student records from MySQL <code>students</code> table.</p>
    </div>

    <!-- MODAL 1: ADD STUDENT -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Student Record</h3>
                <button type="button" class="modal-close" onclick="closeModal('addModal')">&times;</button>
            </div>
            <form method="POST" action="students.php">
                <input type="hidden" name="action" value="create">

                <div class="form-row">
                    <label for="add_name">Full Name *</label>
                    <input type="text" id="add_name" name="name" required placeholder="e.g. Shaan Dabhi">
                </div>

                <div class="form-row">
                    <label for="add_email">Email Address *</label>
                    <input type="email" id="add_email" name="email" required placeholder="student@studenthub.edu">
                </div>

                <div class="form-row">
                    <label for="add_phone">Phone Number (10 digits) *</label>
                    <input type="tel" id="add_phone" name="phone" pattern="[6-9][0-9]{9}" required placeholder="9876543210">
                </div>

                <div class="form-row">
                    <label for="add_course">Course *</label>
                    <select id="add_course" name="course" required>
                        <option value="B.Tech Computer Engineering">B.Tech Computer Engineering</option>
                        <option value="B.Tech Information Technology">B.Tech Information Technology</option>
                        <option value="B.Tech AI & Data Science">B.Tech AI & Data Science</option>
                        <option value="B.Tech Electronics & Comm.">B.Tech Electronics & Comm.</option>
                        <option value="B.Tech Mechanical Engineering">B.Tech Mechanical Engineering</option>
                        <option value="B.Tech Civil Engineering">B.Tech Civil Engineering</option>
                    </select>
                </div>

                <div style="display: flex; gap: 10px;">
                    <div class="form-row" style="flex: 1;">
                        <label for="add_semester">Semester</label>
                        <input type="number" id="add_semester" name="semester" min="1" max="8" value="1">
                    </div>
                    <div class="form-row" style="flex: 1;">
                        <label for="add_gender">Gender</label>
                        <select id="add_gender" name="gender">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Student</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: EDIT STUDENT -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Student Record</h3>
                <button type="button" class="modal-close" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form method="POST" action="students.php">
                <input type="hidden" name="action" value="update">
                <input type="hidden" id="edit_id" name="student_id">

                <div class="form-row">
                    <label for="edit_enrollment">Enrollment No.</label>
                    <input type="text" id="edit_enrollment" disabled style="background: #f3f4f6;">
                </div>

                <div class="form-row">
                    <label for="edit_name">Full Name *</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>

                <div class="form-row">
                    <label for="edit_email">Email Address *</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>

                <div class="form-row">
                    <label for="edit_phone">Phone Number *</label>
                    <input type="tel" id="edit_phone" name="phone" required>
                </div>

                <div class="form-row">
                    <label for="edit_course">Course *</label>
                    <select id="edit_course" name="course" required>
                        <option value="B.Tech Computer Engineering">B.Tech Computer Engineering</option>
                        <option value="B.Tech Information Technology">B.Tech Information Technology</option>
                        <option value="B.Tech AI & Data Science">B.Tech AI & Data Science</option>
                        <option value="B.Tech Electronics & Comm.">B.Tech Electronics & Comm.</option>
                        <option value="B.Tech Mechanical Engineering">B.Tech Mechanical Engineering</option>
                        <option value="B.Tech Civil Engineering">B.Tech Civil Engineering</option>
                    </select>
                </div>

                <div style="display: flex; gap: 10px;">
                    <div class="form-row" style="flex: 1;">
                        <label for="edit_semester">Semester</label>
                        <input type="number" id="edit_semester" name="semester" min="1" max="8">
                    </div>
                    <div class="form-row" style="flex: 1;">
                        <label for="edit_gender">Gender</label>
                        <select id="edit_gender" name="gender">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <label for="edit_status">Account Status</label>
                    <select id="edit_status" name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="archived">Archived (Soft Deleted)</option>
                    </select>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Update Record</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }

        function openEditModal(student) {
            document.getElementById('edit_id').value = student.student_id;
            document.getElementById('edit_enrollment').value = student.enrollment_no;
            document.getElementById('edit_name').value = student.name;
            document.getElementById('edit_email').value = student.email;
            document.getElementById('edit_phone').value = student.phone;
            document.getElementById('edit_course').value = student.course;
            document.getElementById('edit_semester').value = student.semester;
            document.getElementById('edit_gender').value = student.gender;
            document.getElementById('edit_status').value = student.status;
            document.getElementById('editModal').classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Close modal on escape key
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(m => m.classList.remove('active'));
            }
        });
    </script>
</body>
</html>
