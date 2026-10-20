<?php
// Practical 9: Secure User Registration with Database Insert, Duplicate Email Check, Password Hashing, CSRF, and Audit Logging
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$status = "";
$message = "";
$regDetails = [];

// Handle POST Submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // 1. Advanced Extension: CSRF Validation
    $submittedToken = $_POST["csrf_token"] ?? "";
    if (!empty($_SESSION['csrf_token']) && !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        $status = "error";
        $message = "Security Error: CSRF token validation failed. Please refresh the page and try again.";
    } else {
        // 2. Input Sanitization
        $name = trim(htmlspecialchars($_POST["name"] ?? $_POST["fullName"] ?? ""));
        $username = trim(htmlspecialchars($_POST["username"] ?? ""));
        if (empty($username) && !empty($name)) {
            // Derive clean username from name if not provided
            $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode(' ', $name)[0]) . rand(100, 999));
        }
        $email = trim(htmlspecialchars($_POST["email"] ?? ""));
        $phone = trim(htmlspecialchars($_POST["phone"] ?? $_POST["mobile"] ?? ""));
        $course = trim(htmlspecialchars($_POST["course"] ?? ""));
        $semester = intval($_POST["semester"] ?? 1);
        $password = $_POST["password"] ?? "";

        // 3. Backend Validation (Consistent with Frontend Rules)
        if (empty($name) || strlen($name) < 3) {
            $status = "error";
            $message = "Validation Error: Name must be at least 3 alphabetic characters.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $status = "error";
            $message = "Validation Error: Invalid email format (RFC compliant email required).";
        } elseif (!preg_match("/^[6-9][0-9]{9}$/", $phone)) {
            $status = "error";
            $message = "Validation Error: Mobile must be a valid 10-digit number starting with 6-9.";
        } elseif (strlen($password) < 8) {
            $status = "error";
            $message = "Validation Error: Password must be at least 8 characters long.";
        } elseif (empty($course)) {
            $status = "error";
            $message = "Validation Error: Please select an academic course.";
        } else {
            if (!$conn) {
                $status = "error";
                $message = "Database connection error. Please ensure MySQL server is running.";
            } else {
                // 4. Duplicate Check using Prepared Statements (Intermediate Extension)
                $dupQuery = "SELECT 'email' AS field FROM users WHERE email = ? 
                             UNION 
                             SELECT 'email' AS field FROM students WHERE email = ?
                             UNION
                             SELECT 'username' AS field FROM users WHERE username = ?";
                $stmt = $conn->prepare($dupQuery);
                $stmt->bind_param("sss", $email, $email, $username);
                $stmt->execute();
                $res = $stmt->get_result();
                $duplicateFound = $res->fetch_assoc();
                $stmt->close();

                if ($duplicateFound) {
                    $status = "duplicate_error";
                    $message = "Registration Rejected: An account with email '<strong>" . htmlspecialchars($email) . "</strong>' or username '<strong>" . htmlspecialchars($username) . "</strong>' already exists. Duplicate registrations are prohibited.";
                } else {
                    // 5. Password Hashing with password_hash() using Bcrypt
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                    // 6. Insert User into `users` table via Prepared Statement
                    $role = "student";
                    $userStmt = $conn->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, 'active')");
                    $userStmt->bind_param("ssss", $username, $email, $hashedPassword, $role);
                    
                    if ($userStmt->execute()) {
                        $newUserId = $conn->insert_id;
                        $userStmt->close();

                        // Generate unique enrollment number (e.g. 23BECE + 3-digit sequence)
                        $prefix = "23BE";
                        if (stripos($course, 'Information') !== false) $prefix .= "IT";
                        elseif (stripos($course, 'AI') !== false) $prefix .= "AI";
                        elseif (stripos($course, 'Mechanical') !== false) $prefix .= "ME";
                        elseif (stripos($course, 'Civil') !== false) $prefix .= "CD";
                        elseif (stripos($course, 'Electronics') !== false) $prefix .= "EC";
                        else $prefix .= "CE";
                        $enrollment_no = $prefix . str_pad($newUserId + 10, 3, '0', STR_PAD_LEFT);

                        // 7. Insert Student Profile into `students` table via Prepared Statement
                        $stuStmt = $conn->prepare("INSERT INTO students (user_id, enrollment_no, name, email, phone, course, semester, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
                        $stuStmt->bind_param("isssssi", $newUserId, $enrollment_no, $name, $email, $phone, $course, $semester);
                        $stuStmt->execute();
                        $stuStmt->close();

                        // 8. Advanced Extension: Audit Log Entry
                        log_audit($conn, "USER_REGISTER", "New student registered: $name ($email, Enrollment: $enrollment_no)", $username, "student");

                        $status = "success";
                        $message = "Account registered successfully with secure password hashing and duplicate checks!";
                        $regDetails = [
                            "user_id" => $newUserId,
                            "enrollment_no" => $enrollment_no,
                            "name" => $name,
                            "username" => $username,
                            "email" => $email,
                            "phone" => $phone,
                            "course" => $course,
                            "semester" => $semester,
                            "role" => $role,
                            "hash_preview" => substr($hashedPassword, 0, 26) . "..." . substr($hashedPassword, -6)
                        ];
                    } else {
                        $status = "error";
                        $message = "Failed to insert user record: " . $conn->error;
                        $userStmt->close();
                    }
                }
            }
        }
    }
}

// Generate new CSRF token for forms
$csrfToken = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure User Registration - StudentHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .result-card {
            max-width: 650px;
            margin: 40px auto;
            background: #ffffff;
            padding: 35px;
            border-radius: 16px;
            text-align: left;
            box-shadow: 0 10px 30px rgba(147, 59, 85, 0.12);
            border: 1px solid #fce7f3;
        }
        .result-card h2 {
            color: #933b55;
            margin-bottom: 20px;
            border-bottom: 2px solid #fce7f3;
            padding-bottom: 10px;
        }
        .alert-box {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        .alert-success { background: #dcfce7; border: 1px solid #22c55e; color: #15803d; }
        .alert-error { background: #fee2e2; border: 1px solid #ef4444; color: #b91c1c; }
        .alert-duplicate { background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #f3e8ff; padding: 10px 14px; font-size: 0.9rem; }
        th { background: #fdf2f8; text-align: left; color: #831843; font-weight: 600; width: 35%; }
        .code-snippet {
            background: #1e1e2f;
            color: #86efac;
            padding: 4px 8px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 0.85rem;
            display: inline-block;
        }
        .btn-group { display: flex; gap: 12px; margin-top: 25px; flex-wrap: wrap; }
        .btn-primary {
            display: inline-block;
            padding: 10px 20px;
            background: #933b55;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.2s;
        }
        .btn-primary:hover { background: #782941; }
        .btn-secondary {
            display: inline-block;
            padding: 10px 20px;
            background: #f3f4f6;
            color: #374151;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
        }
        .tech-pill {
            display: inline-block;
            background: #ede9fe;
            color: #5b21b6;
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: bold;
            margin-left: 6px;
        }
    </style>
</head>
<body>
    <div class="result-card">
        <h2>Secure Registration Handler (Practical 9)</h2>

        <?php if ($status === "success"): ?>
            <div class="alert-box alert-success">
                <strong>✓ <?php echo $message; ?></strong>
                <p style="margin-top: 6px; font-size: 0.88rem;">Both <code>users</code> and <code>students</code> tables updated. Audit log registered.</p>
            </div>

            <h3>Registration Record Summary:</h3>
            <table>
                <tr><th>Enrollment No.</th><td><strong><?php echo htmlspecialchars($regDetails['enrollment_no']); ?></strong></td></tr>
                <tr><th>Full Name</th><td><?php echo htmlspecialchars($regDetails['name']); ?></td></tr>
                <tr><th>Username</th><td><?php echo htmlspecialchars($regDetails['username']); ?></td></tr>
                <tr><th>Email Address</th><td><?php echo htmlspecialchars($regDetails['email']); ?></td></tr>
                <tr><th>Mobile Number</th><td>+91 <?php echo htmlspecialchars($regDetails['phone']); ?></td></tr>
                <tr><th>Academic Course</th><td><?php echo htmlspecialchars($regDetails['course']); ?> (Sem <?php echo $regDetails['semester']; ?>)</td></tr>
                <tr><th>Assigned Role</th><td><span class="tech-pill"><?php echo strtoupper($regDetails['role']); ?></span></td></tr>
                <tr><th>Hashing Method</th><td><code>password_hash(PASSWORD_DEFAULT)</code> (Bcrypt)</td></tr>
                <tr><th>Stored Hash Preview</th><td><span class="code-snippet"><?php echo $regDetails['hash_preview']; ?></span></td></tr>
                <tr><th>Duplicate Email Check</th><td>Passed (0 duplicate matches in MySQL)</td></tr>
                <tr><th>Audit Trail</th><td>Action <code>USER_REGISTER</code> logged into <code>audit_logs</code></td></tr>
            </table>

            <div class="btn-group">
                <a href="login.php" class="btn-primary">Proceed to Login →</a>
                <a href="register.html" class="btn-secondary">Register Another Student</a>
                <a href="index.html" class="btn-secondary">Back to Home</a>
            </div>

        <?php elseif ($status === "duplicate_error"): ?>
            <div class="alert-box alert-duplicate">
                <strong>⚠️ Duplicate Detection Warning:</strong><br>
                <?php echo $message; ?>
            </div>
            <p style="color: #4b5563; font-size: 0.92rem;">
                Our security rules prevent multiple accounts with identical email or username identifiers. 
                If you already have an account, you can sign in directly.
            </p>
            <div class="btn-group">
                <a href="login.php" class="btn-primary">Sign In to Your Account</a>
                <a href="register.html" class="btn-secondary">← Back to Registration</a>
            </div>

        <?php elseif ($status === "error"): ?>
            <div class="alert-box alert-error">
                <strong>✕ Registration Failed:</strong><br>
                <?php echo $message; ?>
            </div>
            <div class="btn-group">
                <a href="register.html" class="btn-primary">← Correct Errors in Form</a>
                <a href="index.html" class="btn-secondary">Back to Home</a>
            </div>

        <?php else: ?>
            <p>Direct access to this script. Please submit user registration through the official portal form.</p>
            <div class="btn-group">
                <a href="register.html" class="btn-primary">Go to Registration Page</a>
                <a href="login.php" class="btn-secondary">Go to Login</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
