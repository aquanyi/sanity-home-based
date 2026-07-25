<?php
require_once __DIR__ . '/../db_connect.php';
try {
    $pdo->exec("ALTER TABLE term_dates ADD COLUMN curriculum_id INT NULL");
    echo "term_dates altered.\n";
} catch (Exception $e) { echo "term_dates: " . $e->getMessage() . "\n"; }
try {
    $pdo->exec("ALTER TABLE school_exams ADD COLUMN curriculum_id INT NULL");
    echo "school_exams altered.\n";
} catch (Exception $e) { echo "school_exams: " . $e->getMessage() . "\n"; }
