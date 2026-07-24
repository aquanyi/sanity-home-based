<?php
/**
 * api_parent_students.php
 * Endpoint for parents & admin to view, edit, and add students and their subjects.
 */
header('Content-Type: application/json; charset=utf-8');
require_once '../security.php';
start_secure_session();
require_once '../db_connect.php';
require_once '../admission_helper.php';

ensure_schema_updated($pdo);

$userRole = $_SESSION['user_role'] ?? '';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !in_array($userRole, ['parent', 'admin', 'timetabler'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access. Parent or Admin login required.']);
    exit;
}

$parentId = $_SESSION['user_id'];
$isAdmin  = in_array($userRole, ['admin', 'timetabler']);
$action   = $_REQUEST['action'] ?? 'list';

if ($action === 'list') {
    try {
        auto_assign_missing_admission_nos($pdo);

        if ($isAdmin && isset($_GET['profile_id'])) {
            $profileId = (int)$_GET['profile_id'];
            $stmt = $pdo->prepare("
                SELECT sp.id AS profile_id, sp.user_id, sp.parent_id, s.admission_no, s.staff_id, s.name, sp.grade_level, sp.dob, sp.nationality, sp.first_language, sp.study_type, c.name AS curriculum_name, sp.curriculum_id
                FROM student_profiles sp
                JOIN students s ON sp.user_id = s.id
                LEFT JOIN curriculums c ON sp.curriculum_id = c.id
                WHERE sp.id = ?
            ");
            $stmt->execute([$profileId]);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($isAdmin && isset($_GET['all'])) {
            $stmt = $pdo->query("
                SELECT sp.id AS profile_id, sp.user_id, sp.parent_id, s.admission_no, s.staff_id, s.name, sp.grade_level, sp.dob, sp.nationality, sp.first_language, sp.study_type, c.name AS curriculum_name, sp.curriculum_id
                FROM student_profiles sp
                JOIN students s ON sp.user_id = s.id
                LEFT JOIN curriculums c ON sp.curriculum_id = c.id
                ORDER BY s.name ASC
            ");
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->prepare("
                SELECT sp.id AS profile_id, sp.user_id, sp.parent_id, s.admission_no, s.staff_id, s.name, sp.grade_level, sp.dob, sp.nationality, sp.first_language, sp.study_type, c.name AS curriculum_name, sp.curriculum_id
                FROM student_profiles sp
                JOIN students s ON sp.user_id = s.id
                LEFT JOIN curriculums c ON sp.curriculum_id = c.id
                WHERE sp.parent_id = ?
                ORDER BY s.id ASC
            ");
            $stmt->execute([$parentId]);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Attach subjects for each student
        foreach ($students as &$st) {
            $st['subjects'] = get_student_subjects($pdo, $st['profile_id']);
        }
        unset($st);

        // Fetch parent details
        $parent = null;
        if (!$isAdmin || isset($_GET['parent_id'])) {
            $pId = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : $parentId;
            $pStmt = $pdo->prepare("SELECT name, email, phone, nationality FROM parents WHERE id = ?");
            $pStmt->execute([$pId]);
            $parent = $pStmt->fetch(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'status'   => 'success',
            'parent'   => $parent,
            'students' => $students
        ]);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to load students: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    validate_csrf_token($_POST['csrf_token'] ?? '', true);

    $sName  = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
    $sGrade = filter_input(INPUT_POST, 'grade', FILTER_SANITIZE_SPECIAL_CHARS);
    $sNat   = filter_input(INPUT_POST, 'nationality', FILTER_SANITIZE_SPECIAL_CHARS);
    $sDob   = filter_input(INPUT_POST, 'dob', FILTER_SANITIZE_SPECIAL_CHARS);
    $sLang  = filter_input(INPUT_POST, 'first_language', FILTER_SANITIZE_SPECIAL_CHARS);
    $curriculumId = filter_input(INPUT_POST, 'curriculum_id', FILTER_VALIDATE_INT) ?: null;
    $studyType    = filter_input(INPUT_POST, 'study_type', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'tuition';
    $targetParentId = ($isAdmin && !empty($_POST['parent_id'])) ? (int)$_POST['parent_id'] : $parentId;
    $subjects = $_POST['subjects'] ?? [];

    if (!$sName || !$sGrade) {
        echo json_encode(['status' => 'error', 'message' => 'Student Name and Grade are required.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $pStmt = $pdo->prepare("SELECT email, phone FROM parents WHERE id = ?");
        $pStmt->execute([$targetParentId]);
        $parent = $pStmt->fetch();

        $studentStaffId  = 'STD-' . date('Y') . '-' . sprintf('%03d', rand(100, 999));
        $admissionNo     = generate_unique_admission_no($pdo);
        $studentEmail    = 'student_' . rand(100, 999) . '_' . ($parent['email'] ?? 'parent@sanity.com');
        $defaultPassword = '12345';
        $studentPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

        $insStudent = $pdo->prepare("INSERT INTO students (staff_id, admission_no, name, email, phone, password, must_change_password) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $insStudent->execute([$studentStaffId, $admissionNo, $sName, $studentEmail, $parent['phone'] ?? '', $studentPassword]);
        $studentUserId = $pdo->lastInsertId();

        $insProfile = $pdo->prepare("
            INSERT INTO student_profiles (user_id, parent_id, grade_level, dob, nationality, first_language, curriculum_id, study_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insProfile->execute([$studentUserId, $targetParentId, $sGrade, !empty($sDob) ? $sDob : null, $sNat, $sLang, $curriculumId, $studyType]);
        $profileId = $pdo->lastInsertId();

        save_student_subjects($pdo, $profileId, $subjects);

        $pdo->commit();

        echo json_encode([
            'status'       => 'success',
            'message'      => "New student {$sName} added successfully with Admission No: {$admissionNo}!",
            'admission_no' => $admissionNo
        ]);
    } catch (\PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Failed to add student: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit') {
    validate_csrf_token($_POST['csrf_token'] ?? '', true);

    $profileId = filter_input(INPUT_POST, 'profile_id', FILTER_VALIDATE_INT);
    $sName     = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
    $sGrade    = filter_input(INPUT_POST, 'grade', FILTER_SANITIZE_SPECIAL_CHARS);
    $sNat      = filter_input(INPUT_POST, 'nationality', FILTER_SANITIZE_SPECIAL_CHARS);
    $sDob      = filter_input(INPUT_POST, 'dob', FILTER_SANITIZE_SPECIAL_CHARS);
    $sLang     = filter_input(INPUT_POST, 'first_language', FILTER_SANITIZE_SPECIAL_CHARS);
    $curriculumId = filter_input(INPUT_POST, 'curriculum_id', FILTER_VALIDATE_INT) ?: null;
    $subjects  = $_POST['subjects'] ?? null;

    if (!$profileId || !$sName || !$sGrade) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid parameters. Student Name and Grade are required.']);
        exit;
    }

    try {
        // Verify ownership or Admin access
        if ($isAdmin) {
            $chk = $pdo->prepare("SELECT user_id, parent_id FROM student_profiles WHERE id = ?");
            $chk->execute([$profileId]);
        } else {
            $chk = $pdo->prepare("SELECT user_id, parent_id FROM student_profiles WHERE id = ? AND parent_id = ?");
            $chk->execute([$profileId, $parentId]);
        }
        $row = $chk->fetch();

        if (!$row) {
            echo json_encode(['status' => 'error', 'message' => 'Student record not found or access denied.']);
            exit;
        }

        $pdo->beginTransaction();

        // Update student name in `students` table
        $upStudent = $pdo->prepare("UPDATE students SET name = ? WHERE id = ?");
        $upStudent->execute([$sName, $row['user_id']]);

        // Update student profile in `student_profiles` table
        $upProfile = $pdo->prepare("
            UPDATE student_profiles 
            SET grade_level = ?, dob = ?, nationality = ?, first_language = ?, curriculum_id = ?
            WHERE id = ?
        ");
        $upProfile->execute([$sGrade, !empty($sDob) ? $sDob : null, $sNat, $sLang, $curriculumId, $profileId]);

        if ($subjects !== null) {
            save_student_subjects($pdo, $profileId, $subjects);
        }

        $pdo->commit();

        echo json_encode(['status' => 'success', 'message' => 'Student details updated successfully!']);
    } catch (\PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Failed to update student: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'update_student_subjects' || $action === 'edit_subjects')) {
    validate_csrf_token($_POST['csrf_token'] ?? '', true);

    $profileId = filter_input(INPUT_POST, 'profile_id', FILTER_VALIDATE_INT);
    $subjects  = $_POST['subjects'] ?? [];

    if (!$profileId) {
        echo json_encode(['status' => 'error', 'message' => 'Profile ID is required.']);
        exit;
    }

    try {
        if (!$isAdmin) {
            $chk = $pdo->prepare("SELECT id FROM student_profiles WHERE id = ? AND parent_id = ?");
            $chk->execute([$profileId, $parentId]);
            if (!$chk->fetch()) {
                echo json_encode(['status' => 'error', 'message' => 'Access denied. Student record not found.']);
                exit;
            }
        }

        save_student_subjects($pdo, $profileId, $subjects);
        $updatedSubjects = get_student_subjects($pdo, $profileId);

        echo json_encode([
            'status'   => 'success',
            'message'  => 'Student subjects updated successfully!',
            'subjects' => $updatedSubjects
        ]);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update student subjects: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_parent_nationality') {
    validate_csrf_token($_POST['csrf_token'] ?? '', true);
    $pNat = filter_input(INPUT_POST, 'nationality', FILTER_SANITIZE_SPECIAL_CHARS);
    try {
        $upP = $pdo->prepare("UPDATE parents SET nationality = ? WHERE id = ?");
        $upP->execute([$pNat, $parentId]);
        echo json_encode(['status' => 'success', 'message' => 'Parent nationality updated successfully.']);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update parent nationality: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
?>
