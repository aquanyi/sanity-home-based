<?php
/**
 * update_grading_scales_schema.php
 * Upgrades the database schema to support curriculum-specific grading scales.
 */
header('Content-Type: text/plain; charset=utf-8');
if (php_sapi_name() === 'cli') {
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}
require_once 'db_connect.php';

echo "=== STARTING DATABASE GRADING SCALES SCHEMA UPGRADE ===\n\n";

try {
    // 1. Add curriculum_id to grading_scales table
    $stmtCol = $pdo->query("SHOW COLUMNS FROM grading_scales LIKE 'curriculum_id'");
    if (!$stmtCol->fetch()) {
        echo "1. Adding 'curriculum_id' column to 'grading_scales' table...\n";
        $pdo->exec("ALTER TABLE grading_scales ADD COLUMN curriculum_id INT NULL");
        
        try {
            $pdo->exec("ALTER TABLE grading_scales ADD CONSTRAINT fk_grading_scales_curriculum FOREIGN KEY (curriculum_id) REFERENCES curriculums(id) ON DELETE CASCADE");
            echo "   Added foreign key constraint referencing 'curriculums' table.\n";
        } catch (\PDOException $ex) {
            echo "   Warning: Failed to add foreign key constraint: " . $ex->getMessage() . "\n";
        }
    } else {
        echo "1. Column 'curriculum_id' already exists in 'grading_scales'.\n";
    }

    // 1b. Adjust unique index to include curriculum_id
    try {
        echo "   Modifying 'letter_grade' column size to VARCHAR(50)...\n";
        $pdo->exec("ALTER TABLE grading_scales MODIFY COLUMN letter_grade VARCHAR(50) NOT NULL");

        $stmtIndexes = $pdo->query("SHOW INDEX FROM grading_scales WHERE Key_name = 'unique_grade_letter'");
        if ($stmtIndexes->fetch()) {
            echo "   Dropping old unique index 'unique_grade_letter'...\n";
            $pdo->exec("ALTER TABLE grading_scales DROP INDEX unique_grade_letter");
        }
        
        $stmtNewIndex = $pdo->query("SHOW INDEX FROM grading_scales WHERE Key_name = 'unique_curric_grade_letter'");
        if (!$stmtNewIndex->fetch()) {
            echo "   Creating new unique index 'unique_curric_grade_letter' (curriculum_id, grade_level, letter_grade)...\n";
            $pdo->exec("ALTER TABLE grading_scales ADD UNIQUE KEY unique_curric_grade_letter (curriculum_id, grade_level, letter_grade)");
        }
    } catch (\PDOException $ex) {
        echo "   Warning during index/column adjustment: " . $ex->getMessage() . "\n";
    }

    // 2. Discover curriculum IDs based on names
    $currics = $pdo->query("SELECT id, name FROM curriculums")->fetchAll(PDO::FETCH_ASSOC);
    $cbcId = null;
    $id844 = null;
    $igcseId = null;
    $cambridgeId = null;

    foreach ($currics as $c) {
        $name = strtolower($c['name']);
        if (strpos($name, 'cbc') !== false || strpos($name, 'cbe') !== false) {
            $cbcId = $c['id'];
        } elseif (strpos($name, '8-4-4') !== false) {
            $id844 = $c['id'];
        } elseif (strpos($name, 'igcse') !== false) {
            $igcseId = $c['id'];
        } elseif (strpos($name, 'cambridge') !== false) {
            $cambridgeId = $c['id'];
        }
    }

    echo "\n2. Seeding default grading scales per curriculum...\n";

    // Setup helper to seed scales
    $seedScale = function($curriculumId, $gradeLevel, $letterGrade, $minMark, $maxMark, $remark) use ($pdo) {
        if ($curriculumId === null) return;
        
        // Check if scale entry already exists for this curriculum and letter grade
        $check = $pdo->prepare("SELECT id FROM grading_scales WHERE curriculum_id = ? AND letter_grade = ? AND min_mark = ? AND max_mark = ?");
        $check->execute([$curriculumId, $letterGrade, $minMark, $maxMark]);
        if (!$check->fetch()) {
            $ins = $pdo->prepare("INSERT INTO grading_scales (curriculum_id, grade_level, letter_grade, min_mark, max_mark, remarks_template) VALUES (?, ?, ?, ?, ?, ?)");
            $ins->execute([$curriculumId, $gradeLevel, $letterGrade, $minMark, $maxMark, $remark]);
        }
    };

    // A. Seed CBC / CBE (Kenyan CBE Grading system from image)
    if ($cbcId) {
        echo "   Seeding CBC (ID $cbcId) grading scales...\n";
        $seedScale($cbcId, 'All', 'EE1', 90, 100, 'Exceeding Expectation (8 Points)');
        $seedScale($cbcId, 'All', 'EE2', 75, 89, 'Exceeding Expectation (7 Points)');
        $seedScale($cbcId, 'All', 'ME1', 58, 74, 'Meeting Expectation (6 Points)');
        $seedScale($cbcId, 'All', 'ME2', 42, 57, 'Meeting Expectation (5 Points)');
        $seedScale($cbcId, 'All', 'AE2', 31, 40, 'Approaching Expectation (4 Points)');
        $seedScale($cbcId, 'All', 'AE1', 21, 30, 'Approaching Expectation (3 Points)');
        $seedScale($cbcId, 'All', 'BE2', 11, 20, 'Below Expectation (2 Points)');
        $seedScale($cbcId, 'All', 'BE1', 0, 10, 'Below Expectation (1 Point)');
    }

    // B. Seed 8-4-4 (Kenyan standard KCSE grading)
    if ($id844) {
        echo "   Seeding 8-4-4 (ID $id844) grading scales...\n";
        $seedScale($id844, 'All', 'A', 80, 100, 'Excellent');
        $seedScale($id844, 'All', 'A-', 75, 79, 'Very Good');
        $seedScale($id844, 'All', 'B+', 70, 74, 'Good');
        $seedScale($id844, 'All', 'B', 65, 69, 'Average');
        $seedScale($id844, 'All', 'B-', 60, 64, 'Above Average');
        $seedScale($id844, 'All', 'C+', 55, 59, 'Pass');
        $seedScale($id844, 'All', 'C', 50, 54, 'Satisfactory');
        $seedScale($id844, 'All', 'C-', 45, 49, 'Fair');
        $seedScale($id844, 'All', 'D+', 40, 44, 'Needs Improvement');
        $seedScale($id844, 'All', 'D', 35, 39, 'Poor');
        $seedScale($id844, 'All', 'D-', 30, 34, 'Very Poor');
        $seedScale($id844, 'All', 'E', 0, 29, 'Fail');
    }

    // C. Seed Cambridge (White table from image)
    if ($cambridgeId) {
        echo "   Seeding Cambridge (ID $cambridgeId) grading scales...\n";
        $seedScale($cambridgeId, 'All', 'OUTSTANDING', 90, 100, 'Outstanding Performance');
        $seedScale($cambridgeId, 'All', 'EXCELLENT', 80, 89, 'Excellent Performance');
        $seedScale($cambridgeId, 'All', 'HIGH', 70, 79, 'High Performance');
        $seedScale($cambridgeId, 'All', 'GOOD', 60, 69, 'Good Performance');
        $seedScale($cambridgeId, 'All', 'ASPIRING', 50, 59, 'Aspiring Performance');
        $seedScale($cambridgeId, 'All', 'BASIC', 40, 49, 'Basic Performance');
        $seedScale($cambridgeId, 'All', 'UNGRADED', 0, 39, 'Ungraded');
    }

    // D. Seed IGCSE (British) (White table from image)
    if ($igcseId) {
        echo "   Seeding IGCSE (ID $igcseId) grading scales...\n";
        $seedScale($igcseId, 'All', 'OUTSTANDING', 90, 100, 'Outstanding Performance');
        $seedScale($igcseId, 'All', 'EXCELLENT', 80, 89, 'Excellent Performance');
        $seedScale($igcseId, 'All', 'HIGH', 70, 79, 'High Performance');
        $seedScale($igcseId, 'All', 'GOOD', 60, 69, 'Good Performance');
        $seedScale($igcseId, 'All', 'ASPIRING', 50, 59, 'Aspiring Performance');
        $seedScale($igcseId, 'All', 'BASIC', 40, 49, 'Basic Performance');
        $seedScale($igcseId, 'All', 'UNGRADED', 0, 39, 'Ungraded');
    }

    echo "\n🎉 DATABASE GRADING SCALES UPGRADE COMPLETED SUCCESSFULLY!\n";
} catch (Exception $e) {
    echo "\n❌ Database Upgrade Failed: " . $e->getMessage() . "\n";
}
?>
