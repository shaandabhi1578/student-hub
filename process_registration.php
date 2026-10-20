<?php
// Practical 7: PHP Form Processing with Server-Side Validation and CSV/JSON Storage
error_reporting(0);

$errors = [];
$successMessage = "";
$formData = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // 1. Sanitize inputs
    $name = trim(htmlspecialchars($_POST["name"] ?? ""));
    $email = trim(htmlspecialchars($_POST["email"] ?? ""));
    $phone = trim(htmlspecialchars($_POST["phone"] ?? ""));
    $course = trim(htmlspecialchars($_POST["course"] ?? ""));

    // 2. Server-side validation
    if (empty($name)) {
        $errors[] = "Name field cannot be empty.";
    } elseif (strlen($name) < 3) {
        $errors[] = "Name must be at least 3 characters long.";
    }

    if (empty($email)) {
        $errors[] = "Email field cannot be empty.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please provide a valid email address.";
    }

    if (empty($phone)) {
        $errors[] = "Phone number is required.";
    } elseif (!preg_match("/^[6-9][0-9]{9}$/", $phone)) {
        $errors[] = "Phone number must be a valid 10-digit number starting with 6-9.";
    }

    if (empty($course)) {
        $errors[] = "Please select a course.";
    }

    // 3. If validation succeeds, store in CSV and JSON
    if (empty($errors)) {
        $record = [
            "timestamp" => date("Y-m-d H:i:s"),
            "name" => $name,
            "email" => $email,
            "phone" => $phone,
            "course" => $course
        ];

        // Safe CSV file writing
        $csvFile = "registrations.csv";
        $isNewFile = !file_exists($csvFile);
        $fileHandle = fopen($csvFile, "a");
        if ($fileHandle !== false) {
            if ($isNewFile) {
                fputcsv($fileHandle, ["Timestamp", "Name", "Email", "Phone", "Course"]);
            }
            fputcsv($fileHandle, [$record["timestamp"], $record["name"], $record["email"], $record["phone"], $record["course"]]);
            fclose($fileHandle);
        }

        // Safe JSON file writing
        $jsonFile = "registrations.json";
        $existingRecords = [];
        if (file_exists($jsonFile)) {
            $jsonContent = file_get_contents($jsonFile);
            $existingRecords = json_decode($jsonContent, true) ?? [];
        }
        $existingRecords[] = $record;
        file_put_contents($jsonFile, json_encode($existingRecords, JSON_PRETTY_PRINT));

        $successMessage = "Student registration successfully processed and stored on server!";
        $formData = $record;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Form Processing Result - StudentHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .result-box {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: left;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .error-alert {
            background: #fee2e2;
            border: 1px solid #ef4444;
            color: #b91c1c;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .success-alert {
            background: #dcfce7;
            border: 1px solid #22c55e;
            color: #15803d;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .details-table th, .details-table td {
            padding: 10px;
            border: 1px solid #e5e7eb;
        }
        .details-table th {
            background: #f9fafb;
            text-align: left;
        }
        .btn-link {
            display: inline-block;
            margin-top: 20px;
            padding: 8px 16px;
            background: #933b55;
            color: white;
            text-decoration: none;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <div class="result-box">
        <h2>Server Form Processing (PHP)</h2>

        <?php if (!empty($errors)): ?>
            <div class="error-alert">
                <strong>Submission Errors:</strong>
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo $err; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <a href="register.html" class="btn-link">← Go Back to Form</a>
        <?php elseif (!empty($successMessage)): ?>
            <div class="success-alert">
                <strong><?php echo $successMessage; ?></strong>
            </div>
            <h3>Saved Student Information:</h3>
            <table class="details-table">
                <tr><th>Timestamp</th><td><?php echo $formData['timestamp']; ?></td></tr>
                <tr><th>Full Name</th><td><?php echo $formData['name']; ?></td></tr>
                <tr><th>Email Address</th><td><?php echo $formData['email']; ?></td></tr>
                <tr><th>Phone Number</th><td><?php echo $formData['phone']; ?></td></tr>
                <tr><th>Course</th><td><?php echo $formData['course']; ?></td></tr>
                <tr><th>Storage Status</th><td>Saved to registrations.csv and registrations.json</td></tr>
            </table>
            <a href="view_records.php" class="btn-link">View All Stored Records →</a>
        <?php else: ?>
            <p>No form data received. Please submit the form via POST method.</p>
            <a href="register.html" class="btn-link">Go to Registration Page</a>
        <?php endif; ?>
    </div>
</body>
</html>
