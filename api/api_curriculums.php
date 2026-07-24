<?php
/**
 * api_curriculums.php
 * API endpoint to fetch and manage curriculums.
 */
header('Content-Type: application/json; charset=utf-8');

// Action parameter determines the behavior
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Auto-run schema alteration to ensure level_type exists and curriculum_subjects table exists
if ($action === 'get_active' || $action === 'get_all' || $action === 'add' || $action === 'edit' || $action === 'get_curriculum_subjects' || $action === 'save_curriculum_subjects' || $action === 'add_subject_and_assign' || $action === 'get_subjects_by_curriculum') {
    require_once '../db_connect.php';
    try {
        $q = $pdo->query("SHOW COLUMNS FROM curriculums LIKE 'level_type'");
        if (!$q->fetch()) {
            $pdo->exec("ALTER TABLE curriculums ADD COLUMN level_type VARCHAR(50) NOT NULL DEFAULT 'custom'");
        }
        $qSub = $pdo->query("SHOW TABLES LIKE 'curriculum_subjects'");
        if (!$qSub->fetch()) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS curriculum_subjects (
                    curriculum_id INT NOT NULL,
                    subject_id INT NOT NULL,
                    PRIMARY KEY (curriculum_id, subject_id),
                    FOREIGN KEY (curriculum_id) REFERENCES curriculums(id) ON DELETE CASCADE,
                    FOREIGN KEY (subject_id) REFERENCES subject_areas(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        }
    } catch (\PDOException $e) {
        // Continue
    }
}

if ($action === 'get_active') {
    // Public action — no auth required (used by public enrollment form)
    require_once '../db_connect.php';
    try {
        $stmt = $pdo->prepare("SELECT id, name, level_type FROM curriculums WHERE is_approved = 1 ORDER BY name ASC");
        $stmt->execute();
        $curriculums = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'curriculums' => $curriculums]);
    } catch (Exception $e) {
        error_log('[SHTA API CURRICULUMS ERROR] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch active curriculums.']);
    }
    exit;
}

if ($action === 'get_subjects_by_curriculum') {
    require_once '../db_connect.php';
    try {
        $curriculum_id = filter_input(INPUT_GET, 'curriculum_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'curriculum_id', FILTER_VALIDATE_INT);
        if (!$curriculum_id) {
            $stmt = $pdo->query("SELECT id, name FROM subject_areas ORDER BY name ASC");
            echo json_encode(['status' => 'success', 'subjects' => $stmt->fetchAll()]);
            exit;
        }
        $stmt = $pdo->prepare("
            SELECT sa.id, sa.name 
            FROM subject_areas sa
            JOIN curriculum_subjects cs ON sa.id = cs.subject_id
            WHERE cs.curriculum_id = ?
            ORDER BY sa.name ASC
        ");
        $stmt->execute([$curriculum_id]);
        $subjects = $stmt->fetchAll();

        if (empty($subjects)) {
            $stmtAll = $pdo->query("SELECT id, name FROM subject_areas ORDER BY name ASC");
            $subjects = $stmtAll->fetchAll();
        }
        echo json_encode(['status' => 'success', 'subjects' => $subjects]);
    } catch (Exception $e) {
        error_log('[SHTA API CURRICULUMS ERROR] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch curriculum subjects.']);
    }
    exit;
}

// All other actions require admin authentication and session validation
require_once '../security.php';
start_secure_session();
require_once '../db_connect.php';

$role = $_SESSION['user_role'] ?? '';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !in_array($role, ['admin', 'timetabler'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access. Admins only.']);
    exit;
}

// For modifying actions, validate CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token($_POST['csrf_token'] ?? '', true);
}

try {
    switch ($action) {
        case 'get_all':
            $stmt = $pdo->query("
                SELECT c.*, 
                    COUNT(cs.subject_id) AS subject_count,
                    GROUP_CONCAT(sa.name ORDER BY sa.name ASC SEPARATOR ', ') AS subject_names
                FROM curriculums c
                LEFT JOIN curriculum_subjects cs ON c.id = cs.curriculum_id
                LEFT JOIN subject_areas sa ON cs.subject_id = sa.id
                GROUP BY c.id
                ORDER BY c.created_at DESC
            ");
            $curriculums = $stmt->fetchAll();
            echo json_encode(['status' => 'success', 'curriculums' => $curriculums]);
            break;

        case 'add':
            $name = trim($_POST['name'] ?? '');
            $level_type = trim($_POST['level_type'] ?? 'custom');
            if (empty($name)) {
                echo json_encode(['status' => 'error', 'message' => 'Curriculum name cannot be empty.']);
                exit;
            }
            
            // Check uniqueness
            $stmtCheck = $pdo->prepare("SELECT id FROM curriculums WHERE LOWER(name) = LOWER(?)");
            $stmtCheck->execute([$name]);
            if ($stmtCheck->fetch()) {
                echo json_encode(['status' => 'error', 'message' => 'Curriculum name already exists.']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO curriculums (name, level_type, is_approved) VALUES (?, ?, 1)");
            $stmt->execute([$name, $level_type]);
            echo json_encode(['status' => 'success', 'message' => 'Curriculum added successfully.', 'id' => $pdo->lastInsertId()]);
            break;

        case 'edit':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $name = trim($_POST['name'] ?? '');
            $level_type = trim($_POST['level_type'] ?? 'custom');
            if (!$id || empty($name)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid parameters. ID and name are required.']);
                exit;
            }

            // Check uniqueness (excluding current ID)
            $stmtCheck = $pdo->prepare("SELECT id FROM curriculums WHERE LOWER(name) = LOWER(?) AND id != ?");
            $stmtCheck->execute([$name, $id]);
            if ($stmtCheck->fetch()) {
                echo json_encode(['status' => 'error', 'message' => 'Curriculum name already exists.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE curriculums SET name = ?, level_type = ? WHERE id = ?");
            $stmt->execute([$name, $level_type, $id]);
            echo json_encode(['status' => 'success', 'message' => 'Curriculum updated successfully.']);
            break;

        case 'approve':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE curriculums SET is_approved = 1 WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success', 'message' => 'Curriculum approved successfully.']);
            break;

        case 'delete':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM curriculums WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success', 'message' => 'Curriculum deleted successfully.']);
            break;

        case 'get_grading_scale':
            $curriculum_id = filter_input(INPUT_GET, 'curriculum_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'curriculum_id', FILTER_VALIDATE_INT);
            if (!$curriculum_id) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid curriculum ID.']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT * FROM grading_scales WHERE curriculum_id = ? ORDER BY min_mark DESC");
            $stmt->execute([$curriculum_id]);
            $scales = $stmt->fetchAll();
            echo json_encode(['status' => 'success', 'scales' => $scales]);
            break;

        case 'save_grading_scale':
            $curriculum_id = filter_input(INPUT_POST, 'curriculum_id', FILTER_VALIDATE_INT);
            $scalesJson = $_POST['scales'] ?? '[]';
            $scales = json_decode($scalesJson, true);

            if (!$curriculum_id) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid curriculum ID.']);
                exit;
            }

            if (!is_array($scales)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid scales format.']);
                exit;
            }

            // Start transaction
            $pdo->beginTransaction();
            try {
                // Delete existing ones
                $stmtDel = $pdo->prepare("DELETE FROM grading_scales WHERE curriculum_id = ?");
                $stmtDel->execute([$curriculum_id]);

                // Insert new ones
                $stmtIns = $pdo->prepare("INSERT INTO grading_scales (curriculum_id, grade_level, letter_grade, min_mark, max_mark, remarks_template) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($scales as $s) {
                    $grade_level = trim($s['grade_level'] ?? 'All');
                    if (empty($grade_level)) $grade_level = 'All';
                    $letter_grade = trim($s['letter_grade'] ?? '');
                    $min_mark = (float)($s['min_mark'] ?? 0);
                    $max_mark = (float)($s['max_mark'] ?? 100);
                    $remarks = trim($s['remarks_template'] ?? '');

                    if (empty($letter_grade)) {
                        throw new Exception("Grade/Level name cannot be empty.");
                    }

                    $stmtIns->execute([$curriculum_id, $grade_level, $letter_grade, $min_mark, $max_mark, $remarks]);
                }
                $pdo->commit();
                echo json_encode(['status' => 'success', 'message' => 'Grading scale saved successfully.']);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Failed to save grading scale: ' . $e->getMessage()]);
            }
            break;

        case 'get_curriculum_subjects':
            $curriculum_id = filter_input(INPUT_GET, 'curriculum_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'curriculum_id', FILTER_VALIDATE_INT);
            if (!$curriculum_id) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid curriculum ID.']);
                exit;
            }

            $stmtC = $pdo->prepare("SELECT id, name FROM curriculums WHERE id = ?");
            $stmtC->execute([$curriculum_id]);
            $curriculum = $stmtC->fetch();
            if (!$curriculum) {
                echo json_encode(['status' => 'error', 'message' => 'Curriculum not found.']);
                exit;
            }

            $stmtAssigned = $pdo->prepare("SELECT subject_id FROM curriculum_subjects WHERE curriculum_id = ?");
            $stmtAssigned->execute([$curriculum_id]);
            $assignedIds = $stmtAssigned->fetchAll(PDO::FETCH_COLUMN);

            $stmtAll = $pdo->query("SELECT id, name FROM subject_areas ORDER BY name ASC");
            $allSubjects = $stmtAll->fetchAll();

            echo json_encode([
                'status' => 'success',
                'curriculum' => $curriculum,
                'assigned_subject_ids' => $assignedIds,
                'all_subjects' => $allSubjects
            ]);
            break;

        case 'save_curriculum_subjects':
            $curriculum_id = filter_input(INPUT_POST, 'curriculum_id', FILTER_VALIDATE_INT);
            $subject_ids = $_POST['subject_ids'] ?? [];
            if (!is_array($subject_ids)) {
                $subject_ids = [];
            }
            $subject_ids = array_filter(array_map('intval', $subject_ids));

            if (!$curriculum_id) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid curriculum ID.']);
                exit;
            }

            $pdo->beginTransaction();
            try {
                $stmtDel = $pdo->prepare("DELETE FROM curriculum_subjects WHERE curriculum_id = ?");
                $stmtDel->execute([$curriculum_id]);

                if (!empty($subject_ids)) {
                    $stmtIns = $pdo->prepare("INSERT INTO curriculum_subjects (curriculum_id, subject_id) VALUES (?, ?)");
                    foreach ($subject_ids as $sid) {
                        $stmtIns->execute([$curriculum_id, $sid]);
                    }
                }
                $pdo->commit();
                echo json_encode(['status' => 'success', 'message' => 'Subjects updated for curriculum successfully.']);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Failed to save curriculum subjects: ' . $e->getMessage()]);
            }
            break;

        case 'add_subject_and_assign':
            $curriculum_id = filter_input(INPUT_POST, 'curriculum_id', FILTER_VALIDATE_INT);
            $subject_name = trim($_POST['subject_name'] ?? '');
            if (!$curriculum_id || empty($subject_name)) {
                echo json_encode(['status' => 'error', 'message' => 'Curriculum ID and Subject Name are required.']);
                exit;
            }

            $pdo->beginTransaction();
            try {
                $stmtChk = $pdo->prepare("SELECT id FROM subject_areas WHERE LOWER(name) = LOWER(?)");
                $stmtChk->execute([$subject_name]);
                $existing = $stmtChk->fetch();

                if ($existing) {
                    $subject_id = $existing['id'];
                } else {
                    $stmtInsSub = $pdo->prepare("INSERT INTO subject_areas (name) VALUES (?)");
                    $stmtInsSub->execute([$subject_name]);
                    $subject_id = $pdo->lastInsertId();
                }

                $stmtLink = $pdo->prepare("INSERT IGNORE INTO curriculum_subjects (curriculum_id, subject_id) VALUES (?, ?)");
                $stmtLink->execute([$curriculum_id, $subject_id]);

                $pdo->commit();
                echo json_encode(['status' => 'success', 'message' => "Subject '{$subject_name}' added and assigned to curriculum.", 'subject_id' => $subject_id]);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Failed to add subject: ' . $e->getMessage()]);
            }
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Unknown API action.']);
            break;
    }
} catch (Exception $e) {
    error_log('[SHTA API CURRICULUMS ERROR] ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'A database error occurred: ' . $e->getMessage()]);
}
?>
