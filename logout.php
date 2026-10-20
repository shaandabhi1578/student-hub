<?php
// Practical 10: Secure Logout Handler
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentUser = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'guest';

// Log logout action in audit_logs table
if ($conn) {
    log_audit($conn, "USER_LOGOUT", "User '$currentUser' signed out. Session and security tokens revoked.", $currentUser, $role);
}

// Destroy session and cookies
destroy_user_session();

// Redirect to login page with confirmation message
header("Location: login.php?logged_out=1");
exit;
