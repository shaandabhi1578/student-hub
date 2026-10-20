<?php

$name = $_POST["name"];
$email = $_POST["email"];
$course = $_POST["course"];
$semester = $_POST["semester"];

$file = fopen("student.csv", "w");

fputcsv($file, ["Name", "Email", "Course", "Semester"]);

fputcsv($file, [$name, $email, $course, $semester]);

fclose($file);

echo "CSV file generated successfully!";

?>