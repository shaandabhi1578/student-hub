<?php
// Practical 10: Secure Login with Sessions, Role-Based Access, Timeout, and Remember-Me Token
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// If already logged in, redirect directly based on role
if (is_logged_in()) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
        exit;
    } else {
        header("Location: profile.php");
        exit;
    }
}

$errorMessage = "";
$successMessage = "";

// Check query alerts
if (isset($_GET['timeout'])) {
    $errorMessage = "⚠️ Session Expired: You were logged out due to 15 minutes of inactivity. Please sign in again.";
} elseif (isset($_GET['unauthorized'])) {
    $errorMessage = "🔒 Authentication Required: Please log in to access the protected portal page.";
} elseif (isset($_GET['forbidden'])) {
    $errorMessage = "⛔ Access Denied: You do not have administrative permissions to view that page.";
} elseif (isset($_GET['logged_out'])) {
    $successMessage = "✓ You have successfully logged out. Your secure session was destroyed.";
}

// Handle Login POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $loginInput = trim($_POST["username"] ?? "");
    $password   = $_POST["password"] ?? "";
    $rememberMe = isset($_POST["remember_me"]);

    if (empty($loginInput) || empty($password)) {
        $errorMessage = "Please enter both username/email and password.";
    } elseif (!$conn) {
        $errorMessage = "Database connection unavailable. Ensure MySQL server is running.";
    } else {
        // Query user using prepared statement
        $stmt = $conn->prepare("SELECT user_id, username, email, password, role, status FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->bind_param("ss", $loginInput, $loginInput);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        // Verify password using password_verify()
        if ($user && password_verify($password, $user['password'])) {
            // Check account active status
            if ($user['status'] !== 'active') {
                $errorMessage = "Account Inactive: Your account has been suspended or deactivated. Contact the administrator.";
                log_audit($conn, "LOGIN_FAILED_INACTIVE", "Attempted login to deactivated account: " . $user['username'], $user['username'], $user['role']);
            } else {
                // 1. Session Fixation Defense: Regenerate Session ID
                session_regenerate_id(true);

                // 2. Set Session State
                $_SESSION['user_id']       = $user['user_id'];
                $_SESSION['username']      = $user['username'];
                $_SESSION['email']         = $user['email'];
                $_SESSION['role']          = $user['role'];
                $_SESSION['last_activity'] = time();

                // 3. Intermediate Extension: Update last_login timestamp
                $updateLogin = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                $updateLogin->bind_param("i", $user['user_id']);
                $updateLogin->execute();
                $updateLogin->close();

                // 4. Advanced Extension: Secure Remember-Me Token (Stored as SHA-256 Hash in Database)
                if ($rememberMe) {
                    $randomToken = bin2hex(random_bytes(32));
                    $tokenHash   = hash('sha256', $randomToken);
                    
                    $remStmt = $conn->prepare("UPDATE users SET remember_token_hash = ?, remember_token_expiry = DATE_ADD(NOW(), INTERVAL 14 DAY) WHERE user_id = ?");
                    $remStmt->bind_param("si", $tokenHash, $user['user_id']);
                    $remStmt->execute();
                    $remStmt->close();

                    // Set secure HTTP-only cookie (14 days)
                    setcookie('studenthub_remember', $randomToken, [
                        'expires'  => time() + (86400 * 14),
                        'path'     => '/',
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                }

                // 5. Log audit trail
                log_audit($conn, "USER_LOGIN", "Successful login by '{$user['username']}' with role '{$user['role']}'. Session regenerated.", $user['username'], $user['role']);

                // 6. Role-Based Redirection
                if ($user['role'] === 'admin') {
                    header("Location: admin_dashboard.php");
                    exit;
                } else {
                    header("Location: profile.php");
                    exit;
                }
            }
        } else {
            $errorMessage = "Invalid credentials. Please verify your username/email and password.";
            log_audit($conn, "LOGIN_FAILED", "Failed authentication attempt for input: $loginInput", $loginInput, 'guest');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Login Portal - StudentHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #fdf2f8;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 30px 15px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .login-wrapper {
            width: 100%;
            max-width: 480px;
            margin: auto;
        }
        .header-box {
            text-align: center;
            margin-bottom: 25px;
        }
        .header-box h1 {
            color: #933b55;
            font-size: 2.2rem;
            margin: 0 0 6px 0;
            letter-spacing: 1px;
            font-weight: 800;
        }
        .header-box p {
            color: #6b7280;
            margin: 0;
            font-size: 0.95rem;
        }
        .login-card {
            background: #ffffff;
            padding: 35px 30px;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(147, 59, 85, 0.12);
            border: 1px solid #fce7f3;
        }
        .alert-banner {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        .alert-error { background: #fee2e2; border: 1px solid #ef4444; color: #b91c1c; }
        .alert-success { background: #dcfce7; border: 1px solid #22c55e; color: #15803d; }
        .form-group {
            margin-bottom: 18px;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #374151;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.95rem;
            box-sizing: border-box;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #933b55;
            box-shadow: 0 0 0 3px rgba(147, 59, 85, 0.15);
        }
        .checkbox-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            font-size: 0.88rem;
            color: #4b5563;
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }
        .btn-submit {
            width: 100%;
            padding: 13px;
            background: #933b55;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }
        .btn-submit:hover {
            background: #782941;
        }
        .btn-submit:active {
            transform: scale(0.99);
        }
        .demo-credentials {
            margin-top: 25px;
            background: #fdf4f6;
            border: 1px dashed #f472b6;
            padding: 14px;
            border-radius: 10px;
            font-size: 0.82rem;
            text-align: left;
        }
        .demo-credentials h4 {
            margin: 0 0 8px 0;
            color: #831843;
            font-size: 0.86rem;
        }
        .demo-btns {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }
        .btn-demo {
            flex: 1;
            padding: 6px 10px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.78rem;
            cursor: pointer;
            font-weight: 600;
            color: #933b55;
        }
        .btn-demo:hover { background: #fce7f3; }
        .footer-links {
            margin-top: 20px;
            text-align: center;
            font-size: 0.9rem;
            color: #6b7280;
        }
        .footer-links a {
            color: #933b55;
            text-decoration: none;
            font-weight: 600;
        }
        .footer-links a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="header-box">
            <h1>STUDENTHUB</h1>
            <p>Secure Authentication &amp; RBAC Portal (Practical 10)</p>
        </div>

        <div class="login-card">
            <?php if (!empty($errorMessage)): ?>
                <div class="alert-banner alert-error"><?php echo $errorMessage; ?></div>
            <?php endif; ?>

            <?php if (!empty($successMessage)): ?>
                <div class="alert-banner alert-success"><?php echo $successMessage; ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="username">Username or Email Address</label>
                    <input type="text" id="username" name="username" placeholder="e.g. admin or shaan" required autocomplete="username">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter password" required autocomplete="current-password">
                </div>

                <div class="checkbox-row">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember_me" id="remember_me" value="1">
                        Remember me (14 days token)
                    </label>
                    <span>Timeout: 15 min</span>
                </div>

                <button type="submit" class="btn-submit">Sign In to StudentHub</button>
            </form>

            <div class="demo-credentials">
                <h4>🔑 Demo Test Accounts (Ready for Viva &amp; Testing):</h4>
                <div><strong>Admin:</strong> <code>admin</code> / <code>Admin@123</code> (Redirects to Admin Dashboard)</div>
                <div style="margin-top: 4px;"><strong>Student:</strong> <code>shaan</code> / <code>Shaan@123</code> (Redirects to Student Profile)</div>
                <div class="demo-btns">
                    <button type="button" class="btn-demo" onclick="fillCreds('admin', 'Admin@123')">Fill Admin Demo</button>
                    <button type="button" class="btn-demo" onclick="fillCreds('shaan', 'Shaan@123')">Fill Student Demo</button>
                </div>
            </div>
        </div>

        <div class="footer-links">
            <p>Don't have an account? <a href="register.html">Register New Student</a></p>
            <p><a href="index.html">← Back to Main Home</a></p>
        </div>
    </div>

    <script>
        function fillCreds(u, p) {
            document.getElementById('username').value = u;
            document.getElementById('password').value = p;
        }
    </script>
</body>
</html>
