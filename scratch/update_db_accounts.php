<?php
require_once 'db_connect.php';
try {
    // 1. Update users role enum
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'timetabler', 'teacher', 'parent', 'student', 'accounts') NOT NULL");
    echo "Successfully updated users role enum to include 'accounts'.\n";

    // 2. Add price_online and price_offline columns to student_profiles if they don't exist
    $checkOnline = $pdo->query("SHOW COLUMNS FROM student_profiles LIKE 'price_online'")->fetch();
    if (!$checkOnline) {
        $pdo->exec("ALTER TABLE student_profiles ADD COLUMN price_online DECIMAL(10,2) DEFAULT 0.00");
        echo "Added column price_online to student_profiles.\n";
    }

    $checkOffline = $pdo->query("SHOW COLUMNS FROM student_profiles LIKE 'price_offline'")->fetch();
    if (!$checkOffline) {
        $pdo->exec("ALTER TABLE student_profiles ADD COLUMN price_offline DECIMAL(10,2) DEFAULT 0.00");
        echo "Added column price_offline to student_profiles.\n";
    }

    echo "Database migrations completed successfully!\n";
} catch (\Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}
?>
