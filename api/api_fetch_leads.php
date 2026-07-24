<?php
// api_fetch_leads.php — Updated to consolidated schema
header('Content-Type: application/json; charset=utf-8');
require_once '../security.php';
start_secure_session();
require_once '../db_connect.php';

// Auth Guard — admin or timetabler only
$role = $_SESSION['user_role'] ?? '';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($role !== 'admin' && $role !== 'timetabler')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// Handle POST actions (reject lead)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    validate_csrf_token($_POST['csrf_token'] ?? '', true);

    $action  = $_POST['action'] ?? '';
    $lead_id = filter_input(INPUT_POST, 'lead_id', FILTER_VALIDATE_INT);
    if ($action === 'reject' && $lead_id) {
        $stmt = $pdo->prepare("UPDATE enrollment_inquiries SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$lead_id]);
        echo json_encode(['status' => 'success', 'message' => 'Lead rejected.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid POST action.']);
    }
    exit;
}

require_once '../admission_helper.php';
auto_assign_missing_admission_nos($pdo);

try {
    // Pending leads
    $leadsStmt = $pdo->query("
        SELECT e.*, c.name AS curriculum_name, c.is_approved AS curriculum_approved 
        FROM enrollment_inquiries e 
        LEFT JOIN curriculums c ON e.curriculum_id = c.id 
        WHERE e.status = 'pending' 
        ORDER BY e.created_at DESC
    ");
    $leads = $leadsStmt->fetchAll();

    // Metrics
    $totalUsers    = 
        $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn() +
        $pdo->query("SELECT COUNT(*) FROM timetablers")->fetchColumn() +
        $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn() +
        $pdo->query("SELECT COUNT(*) FROM parents")->fetchColumn() +
        $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn() +
        $pdo->query("SELECT COUNT(*) FROM accounts_officers")->fetchColumn();
    $totalStudents = $pdo->query("SELECT COUNT(*) FROM student_profiles")->fetchColumn();
    $totalSlots    = $pdo->query("SELECT COUNT(*) FROM timetable_slots")->fetchColumn();
    $pendingLeads  = $pdo->query("SELECT COUNT(*) FROM enrollment_inquiries WHERE status = 'pending'")->fetchColumn();

    // Users list with joined student details if student, or defaults
    $usersStmt = $pdo->query("
        SELECT id, name, email, phone, 'admin' AS role, created_at, NULL AS grade_level, NULL as subjects, NULL as subject_ids, NULL AS admission_no, NULL AS staff_id, NULL AS profile_id FROM admins
        UNION ALL
        SELECT id, name, email, phone, 'timetabler' AS role, created_at, NULL AS grade_level, NULL as subjects, NULL as subject_ids, NULL AS admission_no, NULL AS staff_id, NULL AS profile_id FROM timetablers
        UNION ALL
        SELECT id, name, email, phone, 'parent' AS role, created_at, NULL AS grade_level, NULL as subjects, NULL as subject_ids, NULL AS admission_no, NULL AS staff_id, NULL AS profile_id FROM parents
        UNION ALL
        SELECT id, name, email, phone, 'accounts' AS role, created_at, NULL AS grade_level, NULL as subjects, NULL as subject_ids, NULL AS admission_no, NULL AS staff_id, NULL AS profile_id FROM accounts_officers
        UNION ALL
        SELECT u.id, u.name,
               COALESCE(p.email, u.email) AS email,
               u.phone, 'student' AS role, u.created_at, sp.grade_level,
               (SELECT GROUP_CONCAT(sa.name ORDER BY sa.name ASC SEPARATOR ', ')
                FROM student_subjects ss
                JOIN subject_areas sa ON ss.subject_id = sa.id
                WHERE ss.student_id = sp.id) as subjects,
               (SELECT GROUP_CONCAT(ss.subject_id SEPARATOR ',')
                FROM student_subjects ss
                WHERE ss.student_id = sp.id) as subject_ids,
               u.admission_no, u.staff_id, sp.id AS profile_id
        FROM students u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        LEFT JOIN parents p ON sp.parent_id = p.id
        UNION ALL
        SELECT u.id, u.name, u.email, u.phone, 'teacher' AS role, u.created_at, NULL AS grade_level,
               (SELECT GROUP_CONCAT(sa.name ORDER BY sa.name ASC SEPARATOR ', ')
                FROM teacher_subjects ts
                JOIN subject_areas sa ON ts.subject_id = sa.id
                WHERE ts.teacher_id = u.id) as subjects,
               (SELECT GROUP_CONCAT(ts.subject_id SEPARATOR ',')
                FROM teacher_subjects ts
                WHERE ts.teacher_id = u.id) as subject_ids,
               NULL AS admission_no, NULL AS staff_id, NULL AS profile_id
        FROM teachers u
        ORDER BY created_at DESC
    ");
    $usersList = $usersStmt->fetchAll();

    echo json_encode([
        'status'  => 'success',
        'leads'   => $leads,
        'users'   => $usersList,
        'metrics' => [
            'total_users'    => $totalUsers,
            'total_students' => $totalStudents,
            'total_slots'    => $totalSlots,
            'pending_leads'  => $pendingLeads
        ]
    ]);
} catch (\PDOException $e) {
    error_log('[SHTA FETCH LEADS ERROR] ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'A server error occurred. Please try again.']);
}
?>
