<?php
// Practical 13: RESTful PHP JSON API for Students
// Headers, Status Codes, and Centralized Error Handling

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

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
    sendResponse(500, [
        "success" => false,
        "status" => 500,
        "message" => "Database connection error."
    ]);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Support method overriding via header or _method param
if ($method === 'POST') {
    $override = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_POST['_method'] ?? null;
    if ($override) {
        $method = strtoupper($override);
    }
}

// Read raw JSON body if content type is application/json
$rawInput = file_get_contents("php://input");
$jsonData = json_decode($rawInput, true) ?? [];

// -------------------------------------------------------------
// 1. GET: Fetch List or Single Student
// -------------------------------------------------------------
if ($method === 'GET') {
    $id = intval($_GET['id'] ?? 0);

    // Fetch single record
    if ($id > 0) {
        $stmt = $conn->prepare("SELECT student_id, enrollment_no, name, email, phone, course, semester, gender, status, created_at FROM students WHERE student_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($student) {
            sendResponse(200, [
                "success" => true,
                "status" => 200,
                "data" => $student
            ]);
        } else {
            sendResponse(404, [
                "success" => false,
                "status" => 404,
                "message" => "Student with ID #$id was not found."
            ]);
        }
    }

    // Fetch list with search & filter
    $search = trim($_GET['search'] ?? '');
    $course = trim($_GET['course'] ?? '');
    $status = trim($_GET['status'] ?? '');

    $sql = "SELECT student_id, enrollment_no, name, email, phone, course, semester, gender, status, created_at FROM students WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($search)) {
        $sql .= " AND (name LIKE ? OR email LIKE ? OR enrollment_no LIKE ? OR phone LIKE ?)";
        $term = "%" . $search . "%";
        $params = array_merge($params, [$term, $term, $term, $term]);
        $types .= "ssss";
    }

    if (!empty($course) && $course !== 'All') {
        $sql .= " AND course = ?";
        $params[] = $course;
        $types .= "s";
    }

    if (!empty($status) && $status !== 'All') {
        $sql .= " AND status = ?";
        $params[] = $status;
        $types .= "s";
    }

    $sql .= " ORDER BY student_id DESC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $students = [];
    while ($row = $res->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();

    sendResponse(200, [
        "success" => true,
        "status" => 200,
        "count" => count($students),
        "data" => $students
    ]);
}

// -------------------------------------------------------------
// 2. POST: Create New Student
// -------------------------------------------------------------
elseif ($method === 'POST') {
    $input = !empty($jsonData) ? $jsonData : $_POST;

    $name     = trim(htmlspecialchars($input['name'] ?? ''));
    $email    = trim(htmlspecialchars($input['email'] ?? ''));
    $phone    = trim(htmlspecialchars($input['phone'] ?? ''));
    $course   = trim(htmlspecialchars($input['course'] ?? ''));
    $semester = intval($input['semester'] ?? 1);
    $gender   = $input['gender'] ?? 'Other';
    $enroll   = trim(htmlspecialchars($input['enrollment_no'] ?? ''));

    // Validation
    $errors = [];
    if (empty($name) || strlen($name) < 3) $errors[] = "Name must be at least 3 characters long.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (empty($phone) || !preg_match("/^[6-9][0-9]{9}$/", $phone)) $errors[] = "Mobile must be a valid 10-digit number.";
    if (empty($course)) $errors[] = "Academic course is required.";

    if (!empty($errors)) {
        sendResponse(400, [
            "success" => false,
            "status" => 400,
            "message" => "Validation Error",
            "errors" => $errors
        ]);
    }

    // Generate enrollment number if empty
    if (empty($enroll)) {
        $enroll = "23BE" . strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $course), 0, 2)) . rand(100, 999);
    }

    // Check duplicate email
    $chk = $conn->prepare("SELECT student_id FROM students WHERE email = ?");
    $chk->bind_param("s", $email);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $chk->close();
        sendResponse(409, [
            "success" => false,
            "status" => 409,
            "message" => "Conflict: An account with this email address already exists."
        ]);
    }
    $chk->close();

    $stmt = $conn->prepare("INSERT INTO students (enrollment_no, name, email, phone, course, semester, gender, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
    $stmt->bind_param("sssssis", $enroll, $name, $email, $phone, $course, $semester, $gender);
    if ($stmt->execute()) {
        $newId = $conn->insert_id;
        $stmt->close();
        log_audit($conn, "API_STUDENT_CREATE", "API Created student #$newId ($name)", "API_Client", "api");

        sendResponse(201, [
            "success" => true,
            "status" => 201,
            "message" => "Student created successfully.",
            "data" => [
                "student_id" => $newId,
                "enrollment_no" => $enroll,
                "name" => $name,
                "email" => $email,
                "course" => $course
            ]
        ]);
    } else {
        $err = $conn->error;
        $stmt->close();
        sendResponse(500, [
            "success" => false,
            "status" => 500,
            "message" => "Database insertion error: " . $err
        ]);
    }
}

// -------------------------------------------------------------
// 3. PUT: Update Existing Student
// -------------------------------------------------------------
elseif ($method === 'PUT') {
    $input = !empty($jsonData) ? $jsonData : $_POST;

    $id       = intval($input['student_id'] ?? $_GET['id'] ?? 0);
    $name     = trim(htmlspecialchars($input['name'] ?? ''));
    $email    = trim(htmlspecialchars($input['email'] ?? ''));
    $phone    = trim(htmlspecialchars($input['phone'] ?? ''));
    $course   = trim(htmlspecialchars($input['course'] ?? ''));
    $semester = intval($input['semester'] ?? 1);
    $gender   = $input['gender'] ?? 'Other';
    $status   = $input['status'] ?? 'active';

    if ($id <= 0) {
        sendResponse(400, [
            "success" => false,
            "status" => 400,
            "message" => "Missing or invalid student_id parameter."
        ]);
    }

    // Verify record exists
    $chk = $conn->prepare("SELECT student_id FROM students WHERE student_id = ?");
    $chk->bind_param("i", $id);
    $chk->execute();
    if ($chk->get_result()->num_rows === 0) {
        $chk->close();
        sendResponse(404, [
            "success" => false,
            "status" => 404,
            "message" => "Student with ID #$id does not exist."
        ]);
    }
    $chk->close();

    $stmt = $conn->prepare("UPDATE students SET name = ?, email = ?, phone = ?, course = ?, semester = ?, gender = ?, status = ? WHERE student_id = ?");
    $stmt->bind_param("ssssissi", $name, $email, $phone, $course, $semester, $gender, $status, $id);
    if ($stmt->execute()) {
        $stmt->close();
        log_audit($conn, "API_STUDENT_UPDATE", "API Updated student #$id ($name)", "API_Client", "api");

        sendResponse(200, [
            "success" => true,
            "status" => 200,
            "message" => "Student record #$id updated successfully."
        ]);
    } else {
        $err = $conn->error;
        $stmt->close();
        sendResponse(500, [
            "success" => false,
            "status" => 500,
            "message" => "Database update failed: " . $err
        ]);
    }
}

// -------------------------------------------------------------
// 4. DELETE: Delete Student Record
// -------------------------------------------------------------
elseif ($method === 'DELETE') {
    $input = !empty($jsonData) ? $jsonData : $_POST;
    $id = intval($input['student_id'] ?? $_GET['id'] ?? 0);

    if ($id <= 0) {
        sendResponse(400, [
            "success" => false,
            "status" => 400,
            "message" => "Missing or invalid student_id to delete."
        ]);
    }

    $stmt = $conn->prepare("DELETE FROM students WHERE student_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        log_audit($conn, "API_STUDENT_DELETE", "API Deleted student #$id", "API_Client", "api");
        sendResponse(200, [
            "success" => true,
            "status" => 200,
            "message" => "Student record #$id permanently deleted."
        ]);
    } else {
        sendResponse(404, [
            "success" => false,
            "status" => 404,
            "message" => "Student with ID #$id was not found or already deleted."
        ]);
    }
}

else {
    sendResponse(405, [
        "success" => false,
        "status" => 405,
        "message" => "HTTP Method '$method' not allowed."
    ]);
}
