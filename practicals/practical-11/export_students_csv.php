<?php
// Practical 11 Intermediate Extension: Export Student Records to CSV
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

require_login(); // Ensure user is authenticated

$search = trim($_GET['search'] ?? '');
$course = trim($_GET['course'] ?? '');
$status = trim($_GET['status'] ?? 'all');

$sql = "SELECT student_id, enrollment_no, name, email, phone, course, semester, gender, status, created_at FROM students WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR email LIKE ? OR enrollment_no LIKE ?)";
    $term = "%" . $search . "%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $types .= "sss";
}

if (!empty($course) && $course !== 'All') {
    $sql .= " AND course = ?";
    $params[] = $course;
    $types .= "s";
}

if ($status !== 'all' && !empty($status)) {
    $sql .= " AND status = ?";
    $params[] = $status;
    $types .= "s";
}

$sql .= " ORDER BY student_id ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Set CSV headers for file download
$filename = "studenthub_students_" . date("Ymd_His") . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
// Write UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Header row
fputcsv($output, ['Student ID', 'Enrollment No', 'Full Name', 'Email Address', 'Phone Number', 'Course', 'Semester', 'Gender', 'Status', 'Registered Date']);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['student_id'],
        $row['enrollment_no'],
        $row['name'],
        $row['email'],
        $row['phone'],
        $row['course'],
        $row['semester'],
        $row['gender'],
        ucfirst($row['status']),
        $row['created_at']
    ]);
}

fclose($output);
exit;
