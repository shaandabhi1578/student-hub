<?php
// Practical 7: Display Stored Records from CSV / JSON
error_reporting(0);
$records = [];
$csvFile = "registrations.csv";

if (file_exists($csvFile)) {
    if (($handle = fopen($csvFile, "r")) !== false) {
        $header = fgetcsv($handle);
        while (($data = fgetcsv($handle)) !== false) {
            $records[] = $data;
        }
        fclose($handle);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stored Student Registrations - StudentHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .table-box {
            max-width: 800px;
            margin: 30px auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            text-align: left;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            font-size: 0.95rem;
        }
        th {
            background-color: #933b55;
            color: white;
        }
        tr:nth-child(even) { background-color: #fdf2f4; }
        .back-btn {
            display: inline-block;
            margin-top: 15px;
            color: #933b55;
            font-weight: bold;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="table-box">
        <h2>Stored Registrations (Read from CSV file)</h2>
        <?php if (empty($records)): ?>
            <p>No records found in CSV file yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Course</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r[0] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($r[1] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($r[2] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($r[3] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($r[4] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <br>
        <a href="register.html" class="back-btn">← Back to Registration Form</a>
    </div>
</body>
</html>
