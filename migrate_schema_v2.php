<?php
require_once 'db_connect.php';

$queries = [
    "ALTER TABLE parents ADD COLUMN nationality VARCHAR(100) NULL AFTER phone",
    "ALTER TABLE students ADD COLUMN admission_no VARCHAR(50) NULL AFTER staff_id",
    "ALTER TABLE student_profiles ADD COLUMN dob DATE NULL AFTER grade_level",
    "ALTER TABLE student_profiles ADD COLUMN nationality VARCHAR(100) NULL AFTER dob",
    "ALTER TABLE student_profiles ADD COLUMN first_language VARCHAR(100) NULL AFTER nationality",
    "ALTER TABLE enrollment_inquiries ADD COLUMN parent_nationality VARCHAR(100) NULL AFTER parent_email",
    "ALTER TABLE enrollment_inquiries ADD COLUMN students_json TEXT NULL AFTER student_grade"
];

foreach ($queries as $q) {
    try {
        $pdo->exec($q);
        echo "OK: $q\n";
    } catch (\PDOException $e) {
        echo "INFO/ERR: " . $e->getMessage() . "\n";
    }
}
?>
