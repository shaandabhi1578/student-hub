<?php
// Practical 10: Authentication, Session Management, RBAC & Timeout Middleware
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session cookie parameters
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// Session Timeout Threshold (15 minutes of inactivity)
define('SESSION_TIMEOUT_SECONDS', 900);

/**
 * Check and refresh session activity, handle inactivity timeout
 */
function check_session_timeout() {
    if (isset($_SESSION['user_id'])) {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT_SECONDS)) {
            // Inactivity timeout exceeded - destroy session
            $username = $_SESSION['username'] ?? 'unknown';
            global $conn;
            if ($conn) {
                log_audit($conn, "SESSION_TIMEOUT", "Session expired due to 15 mins of inactivity for user: $username", $username, $_SESSION['role'] ?? 'guest');
            }
            destroy_user_session();
            header("Location: login.php?timeout=1");
            exit;
        }
        // Update activity timestamp
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Attempt auto-login via secure Remember-Me cookie if session is absent
 */
function check_remember_me_cookie($conn) {
    if (!isset($_SESSION['user_id']) && isset($_COOKIE['studenthub_remember']) && $conn) {
        $cookieToken = $_COOKIE['studenthub_remember'];
        $tokenHash = hash('sha256', $cookieToken);
        
        $stmt = $conn->prepare("SELECT user_id, username, email, role, status FROM users WHERE remember_token_hash = ? AND remember_token_expiry > NOW() AND status = 'active' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $tokenHash);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($user = $res->fetch_assoc()) {
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();

                log_audit($conn, "REMEMBER_ME_LOGIN", "Auto-authenticated via secure remember token for user: " . $user['username'], $user['username'], $user['role']);
            }
            $stmt->close();
        }
    }
}

/**
 * Destroy session and remove session + remember cookies
 */
function destroy_user_session() {
    global $conn;
    if (isset($_SESSION['user_id']) && $conn) {
        $userId = $_SESSION['user_id'];
        // Clear remember token hash in DB
        $stmt = $conn->prepare("UPDATE users SET remember_token_hash = NULL, remember_token_expiry = NULL WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Unset all session variables
    $_SESSION = [];

    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Delete remember cookie
    if (isset($_COOKIE['studenthub_remember'])) {
        setcookie('studenthub_remember', '', time() - 3600, '/');
    }

    session_destroy();
}

/**
 * Enforce authentication guard
 */
function require_login() {
    global $conn;
    check_remember_me_cookie($conn);
    check_session_timeout();

    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php?unauthorized=1");
        exit;
    }
}

/**
 * Enforce Role-Based Access Control (RBAC)
 * @param array|string $allowed_roles e.g. 'admin' or ['admin', 'student']
 */
function require_role($allowed_roles) {
    require_login();
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        // Log unauthorized attempt
        global $conn;
        if ($conn) {
            log_audit($conn, "UNAUTHORIZED_ACCESS_ATTEMPT", "User '{$_SESSION['username']}' attempted to access restricted resource requiring role(s): " . implode(', ', $allowed_roles), $_SESSION['username'], $_SESSION['role']);
        }
        header("Location: login.php?forbidden=1");
        exit;
    }
}

/**
 * Helper to check login state without redirecting
 */
function is_logged_in() {
    global $conn;
    check_remember_me_cookie($conn);
    check_session_timeout();
    return isset($_SESSION['user_id']);
}

/**
 * Get currently logged-in user array
 */
function get_current_user_data() {
    if (!is_logged_in()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'] ?? 'guest'
    ];
}
