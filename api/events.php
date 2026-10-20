<?php
// Practical 13: RESTful PHP JSON API for Events
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../db.php';

function sendResponse($statusCode, $data) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$conn) {
    sendResponse(500, ["success" => false, "message" => "Database connection error."]);
}

$category = trim($_GET['category'] ?? 'All');
$search   = trim($_GET['search'] ?? '');

$sql = "SELECT event_id, event_id AS id, title, category, description, event_date, event_date AS date, location, seats, available_seats, status, poster_image FROM events WHERE 1=1";
$params = [];
$types = "";

if ($category !== 'All' && !empty($category)) {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

if (!empty($search)) {
    $sql .= " AND (title LIKE ? OR description LIKE ? OR location LIKE ?)";
    $term = "%" . $search . "%";
    $params = array_merge($params, [$term, $term, $term]);
    $types .= "sss";
}

$sql .= " ORDER BY event_date ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

$events = [];
while ($row = $res->fetch_assoc()) {
    $events[] = $row;
}
$stmt->close();

sendResponse(200, [
    "success" => true,
    "status" => 200,
    "count" => count($events),
    "data" => $events
]);
