<?php
/**
 * api/api_settings.php
 * System-wide configuration API — Term Dates & Academic Calendar.
 * Access restricted to admin role only.
 */
header('Content-Type: application/json; charset=utf-8');
require_once '../security.php';
start_secure_session();
require_once '../db_connect.php';

// Auth guard
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated.']); exit;
}
if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'timetabler'])) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Admin access required.']); exit;
}
session_write_close(); // Release session lock early

// Auto-create the term_dates table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS term_dates (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            academic_year VARCHAR(10) NOT NULL,
            term_number TINYINT NOT NULL,
            term_name   VARCHAR(100) NOT NULL,
            start_date  DATE NOT NULL,
            end_date    DATE NOT NULL,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_term (academic_year, term_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (\PDOException $e) {
    // Table may already exist, continue
}

// Auto-create the grading_scales table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS grading_scales (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            grade_level  VARCHAR(100) NOT NULL,
            letter_grade VARCHAR(10) NOT NULL,
            min_mark     DECIMAL(5,2) NOT NULL,
            max_mark     DECIMAL(5,2) NOT NULL,
            remarks_template TEXT NULL,
            UNIQUE KEY unique_grade_letter (grade_level, letter_grade)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (\PDOException $e2) {
    // Table may already exist, continue
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'GET' ? ($_GET['action'] ?? '') : ($_POST['action'] ?? '');

// ─────────────────────────────────────────────
// GET REQUESTS
// ─────────────────────────────────────────────
if ($method === 'GET') {

    // ── Fetch all term dates ──
    if ($action === 'get_term_dates') {
        try {
            $stmt  = $pdo->query("SELECT * FROM term_dates ORDER BY academic_year DESC, term_number ASC");
            $terms = $stmt->fetchAll();
            echo json_encode(['status' => 'success', 'terms' => $terms]);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── Fetch terms for a specific academic year ──
    if ($action === 'get_terms_by_year') {
        $year = trim($_GET['year'] ?? '');
        if (!$year) { echo json_encode(['status' => 'error', 'message' => 'year required']); exit; }
        try {
            $stmt = $pdo->prepare("SELECT * FROM term_dates WHERE academic_year = ? ORDER BY term_number ASC");
            $stmt->execute([$year]);
            echo json_encode(['status' => 'success', 'terms' => $stmt->fetchAll()]);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── Fetch all grading scales (grouped by grade level) ──
    if ($action === 'get_grading_scales') {
        try {
            $scales = $pdo->query("SELECT * FROM grading_scales ORDER BY grade_level ASC, min_mark DESC")->fetchAll();
            echo json_encode(['status' => 'success', 'scales' => $scales]);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Unknown GET action.']); exit;
}

// ─────────────────────────────────────────────
// POST REQUESTS
// ─────────────────────────────────────────────
if ($method === 'POST') {

    // ── Save / upsert term dates ──
    if ($action === 'save_term_dates') {
        $academic_year = trim($_POST['academic_year'] ?? '');

        // Terms are sent as indexed arrays: terms[0][term_number], terms[0][term_name] etc.
        $terms = $_POST['terms'] ?? [];

        if (!$academic_year) {
            echo json_encode(['status' => 'error', 'message' => 'Academic year is required.']); exit;
        }
        if (empty($terms)) {
            echo json_encode(['status' => 'error', 'message' => 'At least one term is required.']); exit;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO term_dates (academic_year, term_number, term_name, start_date, end_date)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    term_name  = VALUES(term_name),
                    start_date = VALUES(start_date),
                    end_date   = VALUES(end_date)
            ");

            $savedCount = 0;
            $errors     = [];
            foreach ($terms as $t) {
                $term_number = intval($t['term_number'] ?? 0);
                $term_name   = trim($t['term_name']   ?? '');
                $start_date  = trim($t['start_date']  ?? '');
                $end_date    = trim($t['end_date']    ?? '');

                if (!$term_number || !$term_name || !$start_date || !$end_date) {
                    $errors[] = "Term #{$term_number} skipped — missing fields.";
                    continue;
                }
                if ($start_date >= $end_date) {
                    $errors[] = "{$term_name}: Start date must be before end date.";
                    continue;
                }
                $stmt->execute([$academic_year, $term_number, $term_name, $start_date, $end_date]);
                $savedCount++;
            }

            $msg = "✅ {$savedCount} term(s) saved for {$academic_year}.";
            if (!empty($errors)) $msg .= ' ⚠️ ' . implode(' ', $errors);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ── Delete a specific term entry ──
    if ($action === 'delete_term') {
        $term_id = filter_input(INPUT_POST, 'term_id', FILTER_VALIDATE_INT);
        if (!$term_id) { echo json_encode(['status' => 'error', 'message' => 'term_id required.']); exit; }
        try {
            $pdo->prepare("DELETE FROM term_dates WHERE id = ?")->execute([$term_id]);
            echo json_encode(['status' => 'success', 'message' => '✅ Term entry removed.']);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── Save / upsert a grading scale entry ──
    if ($action === 'save_grading_scale') {
        $grade_level     = trim($_POST['grade_level']     ?? '');
        $letter_grade    = trim($_POST['letter_grade']    ?? '');
        $min_mark        = filter_input(INPUT_POST, 'min_mark', FILTER_VALIDATE_FLOAT);
        $max_mark        = filter_input(INPUT_POST, 'max_mark', FILTER_VALIDATE_FLOAT);
        $remarks_tpl     = trim($_POST['remarks_template'] ?? '');

        if (!$grade_level || !$letter_grade || $min_mark === false || $max_mark === false) {
            echo json_encode(['status' => 'error', 'message' => 'All grading scale fields are required.']); exit;
        }
        if ($min_mark >= $max_mark) {
            echo json_encode(['status' => 'error', 'message' => 'Min mark must be less than max mark.']); exit;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO grading_scales (grade_level, letter_grade, min_mark, max_mark, remarks_template)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    min_mark = VALUES(min_mark),
                    max_mark = VALUES(max_mark),
                    remarks_template = VALUES(remarks_template)
            ");
            $stmt->execute([$grade_level, $letter_grade, $min_mark, $max_mark, $remarks_tpl]);
            echo json_encode(['status' => 'success', 'message' => "✅ Grade boundary '{$letter_grade}' saved for {$grade_level}."]);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── Delete a grading scale entry ──
    if ($action === 'delete_grading_scale') {
        $scale_id = filter_input(INPUT_POST, 'scale_id', FILTER_VALIDATE_INT);
        if (!$scale_id) { echo json_encode(['status' => 'error', 'message' => 'scale_id required.']); exit; }
        try {
            $pdo->prepare("DELETE FROM grading_scales WHERE id = ?")->execute([$scale_id]);
            echo json_encode(['status' => 'success', 'message' => '✅ Grading entry removed.']);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Unknown POST action.']); exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
?>
