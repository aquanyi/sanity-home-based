<?php
/**
 * db_optimize_indexes.php
 * S.H.T.A School Management System
 * 
 * Performance Tuning Migration:
 * This script scans the database tables and adds critical indexes on foreign keys
 * and search filter columns (like dates and statuses) if they don't already exist.
 * This dramatically speeds up dashboard read times and database concurrency.
 * 
 * INSTRUCTIONS:
 * 1. Upload this file to your Namecheap public_html/ folder.
 * 2. Visit: http://your-domain.com/db_optimize_indexes.php in your browser.
 * 3. Review the outputs to ensure indexes were successfully added.
 * 4. DELETE this file from your server after execution for security.
 */

header('Content-Type: text/html; charset=utf-8');
require_once 'security.php';
start_secure_session();

// Auth Guard: Admin access only
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    die("Access Denied: Only logged-in administrators can execute this optimization script.");
}

require_once 'db_connect.php';

echo "<h2>Starting Database Optimization for S.H.T.A...</h2>";
echo "<p>Checking and establishing missing performance indexes...</p><hr>";

/**
 * Checks if an index exists in a table, and adds it if missing.
 */
function addIndexIfNotExist(PDO $pdo, string $table, string $indexName, string $columnsSql): void {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = ? 
              AND INDEX_NAME = ?
        ");
        $stmt->execute([$table, $indexName]);
        $exists = $stmt->fetchColumn() > 0;
        
        if (!$exists) {
            $pdo->exec("ALTER TABLE `$table` ADD INDEX `$indexName` ($columnsSql)");
            echo "<span style='color: green;'>✔</span> Index <strong>$indexName</strong> successfully added to table <strong>$table</strong>.<br>";
        } else {
            echo "<span style='color: #888;'>•</span> Index <strong>$indexName</strong> already exists on table <strong>$table</strong>.<br>";
        }
    } catch (\PDOException $e) {
        echo "<span style='color: red;'>✘</span> Failed to process index <strong>$indexName</strong> on table <strong>$table</strong>: " . $e->getMessage() . "<br>";
    }
}

// ── 1. FOREIGN KEY INDEXES (Crucial for JOIN operations) ──
echo "<h3>1. Establishing Join Performance Indexes (Foreign Keys)</h3>";

addIndexIfNotExist($pdo, 'student_profiles', 'idx_student_profiles_user', 'user_id');
addIndexIfNotExist($pdo, 'student_profiles', 'idx_student_profiles_parent', 'parent_id');

addIndexIfNotExist($pdo, 'timetable_slots', 'idx_timetable_slots_student', 'student_id');
addIndexIfNotExist($pdo, 'timetable_slots', 'idx_timetable_slots_teacher', 'teacher_id');

addIndexIfNotExist($pdo, 'lessons', 'idx_lessons_slot', 'slot_id');

addIndexIfNotExist($pdo, 'exam_sessions', 'idx_exam_sessions_exam', 'exam_id');
addIndexIfNotExist($pdo, 'exam_sessions', 'idx_exam_sessions_invigilator', 'invigilator_teacher_id');

addIndexIfNotExist($pdo, 'exam_results', 'idx_exam_results_session', 'exam_session_id');
addIndexIfNotExist($pdo, 'exam_results', 'idx_exam_results_student', 'student_id');

addIndexIfNotExist($pdo, 'student_assignments', 'idx_student_assignments_student', 'student_id');
addIndexIfNotExist($pdo, 'student_assignments', 'idx_student_assignments_teacher', 'teacher_id');

addIndexIfNotExist($pdo, 'academic_reports', 'idx_academic_reports_student', 'student_id');
addIndexIfNotExist($pdo, 'academic_reports', 'idx_academic_reports_teacher', 'teacher_id');

addIndexIfNotExist($pdo, 'learning_resources', 'idx_learning_resources_uploaded', 'uploaded_by');

addIndexIfNotExist($pdo, 'student_invoices', 'idx_student_invoices_student', 'student_id');

addIndexIfNotExist($pdo, 'student_payments', 'idx_student_payments_student', 'student_id');
addIndexIfNotExist($pdo, 'student_payments', 'idx_student_payments_invoice', 'invoice_id');

addIndexIfNotExist($pdo, 'teacher_payments', 'idx_teacher_payments_teacher', 'teacher_id');


// ── 2. SEARCH & FILTER COLUMN INDEXES (Crucial for where clauses & sorting) ──
echo "<h3>2. Establishing Search & Filter Optimization Indexes</h3>";

addIndexIfNotExist($pdo, 'enrollment_inquiries', 'idx_enrollment_status', 'status, created_at');
addIndexIfNotExist($pdo, 'student_profiles', 'idx_student_grade', 'grade_level');
addIndexIfNotExist($pdo, 'timetable_slots', 'idx_timetable_slot_search', 'day_of_week, start_time');
addIndexIfNotExist($pdo, 'lessons', 'idx_lessons_search', 'lesson_date, session_status');
addIndexIfNotExist($pdo, 'student_payments', 'idx_payments_date', 'payment_date');
addIndexIfNotExist($pdo, 'extra_expenses', 'idx_expenses_date', 'expense_date');
addIndexIfNotExist($pdo, 'academic_reports', 'idx_reports_search', 'report_type, status');
addIndexIfNotExist($pdo, 'student_assignments', 'idx_assignments_search', 'status, due_date');

echo "<hr><p style='color: green; font-weight: bold;'>Database optimization run completed successfully!</p>";
echo "<p style='color: red; font-weight: bold;'>IMPORTANT SECURITY WARNING: Please delete the file 'db_optimize_indexes.php' from your server immediately.</p>";
?>
