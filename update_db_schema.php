<?php
/**
 * update_db_schema.php
 * Script to safely add new authentication & recovery columns to existing `users` table.
 */
require_once 'db_connect.php';

try {
    $columnsToAdd = [
        "staff_id" => "ALTER TABLE users ADD COLUMN staff_id VARCHAR(50) UNIQUE NULL AFTER id",
        "must_change_password" => "ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) DEFAULT 1 AFTER role",
        "security_question" => "ALTER TABLE users ADD COLUMN security_question VARCHAR(255) NULL AFTER must_change_password",
        "security_answer" => "ALTER TABLE users ADD COLUMN security_answer VARCHAR(255) NULL AFTER security_question"
    ];

    foreach ($columnsToAdd as $col => $sql) {
        $check = $pdo->query("SHOW COLUMNS FROM users LIKE '$col'")->fetch();
        if (!$check) {
            $pdo->exec($sql);
            echo "Added column: $col\n";
        } else {
            echo "Column $col already exists.\n";
        }
    }

    // Add teacher_subjects table check & create
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'teacher_subjects'")->fetch();
    if (!$tableCheck) {
        $pdo->exec("CREATE TABLE teacher_subjects (
            teacher_id INT NOT NULL,
            subject_id INT NOT NULL,
            PRIMARY KEY (teacher_id, subject_id),
            FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subject_areas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");
        echo "Created table: teacher_subjects\n";
    } else {
        echo "Table teacher_subjects already exists.\n";
    }

    // ── grading_scales table ──────────────────────────────────────────────
    $tableCheck2 = $pdo->query("SHOW TABLES LIKE 'grading_scales'")->fetch();
    if (!$tableCheck2) {
        $pdo->exec("CREATE TABLE grading_scales (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            grade_level VARCHAR(100) NOT NULL,
            letter_grade VARCHAR(10) NOT NULL,
            min_mark    DECIMAL(5,2) NOT NULL,
            max_mark    DECIMAL(5,2) NOT NULL,
            remarks_template TEXT NULL,
            UNIQUE KEY unique_grade_letter (grade_level, letter_grade)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "Created table: grading_scales\n";
    } else {
        echo "Table grading_scales already exists.\n";
    }

    // ── Ensure exam_results has a unique key for proper upsert ───────────
    try {
        $pdo->exec("ALTER TABLE exam_results ADD UNIQUE KEY unique_result (exam_session_id, student_id)");
        echo "Added unique key on exam_results.\n";
    } catch (\PDOException $e2) {
        echo "exam_results unique key already exists or skipped.\n";
    }

    // ── Add student_id to exam_sessions if it doesn't exist ──
    $checkSessionStudent = $pdo->query("SHOW COLUMNS FROM exam_sessions LIKE 'student_id'")->fetch();
    if (!$checkSessionStudent) {
        $pdo->exec("ALTER TABLE exam_sessions ADD COLUMN student_id INT NULL");
        $pdo->exec("ALTER TABLE exam_sessions ADD CONSTRAINT fk_exam_sessions_student FOREIGN KEY (student_id) REFERENCES student_profiles(id) ON DELETE CASCADE");
        echo "Added student_id column and foreign key to exam_sessions.\n";
    } else {
        echo "student_id column already exists in exam_sessions.\n";
    }

    echo "Database schema updated successfully!\n";
} catch (\PDOException $e) {
    echo "Schema update failed: " . $e->getMessage() . "\n";
}
?>
