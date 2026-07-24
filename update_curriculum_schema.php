<?php
/**
 * update_curriculum_schema.php
 * Safely creates the curriculums table, adds columns to enrollment_inquiries and student_profiles.
 */
if (php_sapi_name() === 'cli') {
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}
require_once 'db_connect.php';

try {
    // 1. Create Curriculums table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS curriculums (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) UNIQUE NOT NULL,
            is_approved TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Table 'curriculums' verified/created.\n";

    // 2. Seed default approved curriculums
    $defaultCurriculums = ['CBC', '8-4-4', 'IGCSE (British)', 'Cambridge', 'American Curriculum', 'IB (International)'];
    $stmtSeed = $pdo->prepare("INSERT IGNORE INTO curriculums (name, is_approved) VALUES (?, 1)");
    foreach ($defaultCurriculums as $c) {
        $stmtSeed->execute([$c]);
    }
    echo "Default curriculums seeded.\n";

    // 3. Add columns to enrollment_inquiries
    $checkCurricCol = $pdo->query("SHOW COLUMNS FROM enrollment_inquiries LIKE 'curriculum_id'")->fetch();
    if (!$checkCurricCol) {
        $pdo->exec("ALTER TABLE enrollment_inquiries ADD COLUMN curriculum_id INT NULL AFTER loc_link");
        $pdo->exec("ALTER TABLE enrollment_inquiries ADD CONSTRAINT fk_enrollment_curriculum FOREIGN KEY (curriculum_id) REFERENCES curriculums(id) ON DELETE SET NULL");
        echo "Added 'curriculum_id' column to 'enrollment_inquiries'.\n";
    }

    $checkStudyTypeCol = $pdo->query("SHOW COLUMNS FROM enrollment_inquiries LIKE 'study_type'")->fetch();
    if (!$checkStudyTypeCol) {
        $pdo->exec("ALTER TABLE enrollment_inquiries ADD COLUMN study_type ENUM('tuition', 'homeschooling') NOT NULL DEFAULT 'tuition' AFTER curriculum_id");
        echo "Added 'study_type' column to 'enrollment_inquiries'.\n";
    }

    // 4. Add columns to student_profiles
    $checkStudentCurricCol = $pdo->query("SHOW COLUMNS FROM student_profiles LIKE 'curriculum_id'")->fetch();
    if (!$checkStudentCurricCol) {
        $pdo->exec("ALTER TABLE student_profiles ADD COLUMN curriculum_id INT NULL AFTER loc_link");
        $pdo->exec("ALTER TABLE student_profiles ADD CONSTRAINT fk_student_curriculum FOREIGN KEY (curriculum_id) REFERENCES curriculums(id) ON DELETE SET NULL");
        echo "Added 'curriculum_id' column to 'student_profiles'.\n";
    }

    $checkStudentStudyTypeCol = $pdo->query("SHOW COLUMNS FROM student_profiles LIKE 'study_type'")->fetch();
    if (!$checkStudentStudyTypeCol) {
        $pdo->exec("ALTER TABLE student_profiles ADD COLUMN study_type ENUM('tuition', 'homeschooling') NOT NULL DEFAULT 'tuition' AFTER curriculum_id");
        echo "Added 'study_type' column to 'student_profiles'.\n";
    }

    // 5. Create curriculum_subjects junction table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS curriculum_subjects (
            curriculum_id INT NOT NULL,
            subject_id INT NOT NULL,
            PRIMARY KEY (curriculum_id, subject_id),
            FOREIGN KEY (curriculum_id) REFERENCES curriculums(id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subject_areas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Table 'curriculum_subjects' verified/created.\n";

    echo "🎉 Database schema upgraded successfully!\n";
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
}
?>
