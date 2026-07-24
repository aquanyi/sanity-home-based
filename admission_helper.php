<?php
/**
 * admission_helper.php
 * Helper functions for generating unique Admission Numbers & Staff IDs.
 */

function generate_unique_admission_no($pdo) {
    ensure_schema_updated($pdo);
    try {
        // Find highest existing numerical suffix between S and A
        $stmt = $pdo->query("SELECT admission_no FROM students WHERE admission_no REGEXP '^S[0-9]+A$'");
        $maxNum = 0;
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $adm = $row['admission_no'];
                $numPart = (int)substr($adm, 1, -1);
                if ($numPart > $maxNum) {
                    $maxNum = $numPart;
                }
            }
        }
        $nextNum = $maxNum + 1;
        $candidate = 'S' . sprintf('%03d', $nextNum) . 'A';
        
        $chk = $pdo->prepare("SELECT COUNT(*) FROM students WHERE admission_no = ?");
        $chk->execute([$candidate]);
        if ($chk->fetchColumn() == 0) {
            return $candidate;
        }
    } catch (\PDOException $ex) {}
    
    // Fallback if collision
    return 'S' . sprintf('%03d', rand(100, 9999)) . 'A';
}

function auto_assign_missing_admission_nos($pdo) {
    ensure_schema_updated($pdo);
    ensure_student_profiles_exist($pdo);
    try {
        $stmt = $pdo->query("SELECT id FROM students WHERE admission_no IS NULL OR TRIM(admission_no) = '' ORDER BY id ASC");
        if ($stmt) {
            $missingStudents = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($missingStudents)) {
                $updateStmt = $pdo->prepare("UPDATE students SET admission_no = ? WHERE id = ?");
                foreach ($missingStudents as $stId) {
                    $newAdm = generate_unique_admission_no($pdo);
                    $updateStmt->execute([$newAdm, $stId]);
                }
            }
        }
    } catch (\PDOException $ex) {}
}

function ensure_student_profiles_exist($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT u.id FROM students u
            LEFT JOIN student_profiles sp ON u.id = sp.user_id
            WHERE sp.id IS NULL
        ");
        if ($stmt) {
            $orphanUserIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($orphanUserIds)) {
                $ins = $pdo->prepare("INSERT INTO student_profiles (user_id, grade_level) VALUES (?, 'Grade 1')");
                foreach ($orphanUserIds as $uId) {
                    $ins->execute([$uId]);
                }
            }
        }
    } catch (\PDOException $e) {}
}

function ensure_schema_updated($pdo) {
    static $done = false;
    if ($done) return;
    try {
        $cols = [
            "ALTER TABLE students ADD COLUMN admission_no VARCHAR(50) NULL AFTER staff_id",
            "ALTER TABLE parents ADD COLUMN nationality VARCHAR(100) NULL AFTER phone",
            "ALTER TABLE student_profiles ADD COLUMN dob DATE NULL AFTER grade_level",
            "ALTER TABLE student_profiles ADD COLUMN nationality VARCHAR(100) NULL AFTER dob",
            "ALTER TABLE student_profiles ADD COLUMN first_language VARCHAR(100) NULL AFTER nationality",
            "ALTER TABLE enrollment_inquiries ADD COLUMN parent_nationality VARCHAR(100) NULL AFTER parent_email",
            "ALTER TABLE enrollment_inquiries ADD COLUMN students_json TEXT NULL AFTER student_grade"
        ];
        
        foreach ($cols as $colSql) {
            try { $pdo->exec($colSql); } catch (\PDOException $ex) { /* Column likely exists */ }
        }

        // Create student_subjects table if not exists
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS student_subjects (
                    student_id INT NOT NULL,
                    subject_id INT NOT NULL,
                    PRIMARY KEY (student_id, subject_id),
                    FOREIGN KEY (student_id) REFERENCES student_profiles(id) ON DELETE CASCADE,
                    FOREIGN KEY (subject_id) REFERENCES subject_areas(id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ");
        } catch (\PDOException $ex) {}

        $done = true;
    } catch (\PDOException $e) {
        // Ignore
    }
}

/**
 * Saves subject IDs or subject names for a student profile.
 */
function save_student_subjects($pdo, $profileId, $subjectInputs) {
    if (!$profileId) return;
    try {
        // Clear existing subjects for this profile
        $del = $pdo->prepare("DELETE FROM student_subjects WHERE student_id = ?");
        $del->execute([$profileId]);

        if (empty($subjectInputs)) return;
        if (!is_array($subjectInputs)) {
            $subjectInputs = explode(',', $subjectInputs);
        }

        $ins = $pdo->prepare("INSERT IGNORE INTO student_subjects (student_id, subject_id) VALUES (?, ?)");

        foreach ($subjectInputs as $item) {
            $item = trim($item);
            if (empty($item)) continue;

            $subId = null;
            if (is_numeric($item)) {
                $subId = (int)$item;
            } else {
                // Find or insert subject area by name
                $stmt = $pdo->prepare("SELECT id FROM subject_areas WHERE LOWER(name) = LOWER(?)");
                $stmt->execute([$item]);
                $sub = $stmt->fetch();
                if ($sub) {
                    $subId = $sub['id'];
                } else {
                    $insSub = $pdo->prepare("INSERT INTO subject_areas (name) VALUES (?)");
                    $insSub->execute([$item]);
                    $subId = $pdo->lastInsertId();
                }
            }

            if ($subId) {
                $ins->execute([$profileId, $subId]);
            }
        }
    } catch (\PDOException $e) {
        error_log("[SAVE STUDENT SUBJECTS ERROR] " . $e->getMessage());
    }
}

/**
 * Fetches selected subject IDs and names for a student profile.
 */
function get_student_subjects($pdo, $profileId) {
    if (!$profileId) return [];
    try {
        $stmt = $pdo->prepare("
            SELECT sa.id, sa.name
            FROM student_subjects ss
            JOIN subject_areas sa ON ss.subject_id = sa.id
            WHERE ss.student_id = ?
            ORDER BY sa.name ASC
        ");
        $stmt->execute([$profileId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        return [];
    }
}
?>
