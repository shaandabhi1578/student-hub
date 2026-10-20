<?php
// Practical 14 Intermediate Extension: Toggle User Active/Inactive Status
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

require_role('admin'); // Only admins can toggle user status

$userId = intval($_POST['user_id'] ?? 0);
$newStatus = $_POST['status'] ?? '';

if ($userId > 0 && in_array($newStatus, ['active', 'inactive'])) {
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
    $stmt->bind_param("si", $newStatus, $userId);
    if ($stmt->execute()) {
        log_audit($conn, "STATUS_TOGGLE", "Changed status of user #$userId to '$newStatus'", $_SESSION['username'], 'admin');
        header("Location: admin_dashboard.php?msg=" . urlencode("User #$userId status updated to " . ucfirst($newStatus)));
        exit;
    }
}

header("Location: admin_dashboard.php?err=" . urlencode("Failed to update user status."));
exit;
