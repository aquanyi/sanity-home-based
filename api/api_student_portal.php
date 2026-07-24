<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../security.php';
start_secure_session();
require_once '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['logged_in'] !== true || $_SESSION['user_role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

$studentId = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'profile':
            $stmt = $pdo->prepare("
                SELECT u.name, u.email, u.phone, u.admission_no, 
                       sp.grade_level, sp.dob, sp.nationality,
                       p.name AS parent_name, p.email AS parent_email, p.phone AS parent_phone
                FROM students u
                LEFT JOIN student_profiles sp ON u.id = sp.user_id
                LEFT JOIN parents p ON sp.parent_id = p.id
                WHERE u.id = ?
            ");
            $stmt->execute([$studentId]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $profile]);
            break;

        case 'subjects':
            $stmt = $pdo->prepare("
                SELECT sa.name, sa.category 
                FROM student_subjects ss
                JOIN student_profiles sp ON ss.student_id = sp.id
                JOIN subject_areas sa ON ss.subject_id = sa.id
                WHERE sp.user_id = ?
                ORDER BY sa.name ASC
            ");
            $stmt->execute([$studentId]);
            $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $subjects]);
            break;

        case 'timetable':
            // Check if tables exist
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'timetable_slots'");
            if ($tableCheck->rowCount() === 0) {
                echo json_encode(['status' => 'success', 'data' => []]);
                break;
            }

            $stmt = $pdo->prepare("
                SELECT t.day_of_week, t.start_time, t.end_time, sa.name AS subject_name,
                       tch.name AS teacher_name, t.zoom_link
                FROM timetable_slots t
                JOIN student_profiles sp ON t.student_profile_id = sp.id
                JOIN subject_areas sa ON t.subject_id = sa.id
                JOIN teachers tch ON t.teacher_id = tch.id
                WHERE sp.user_id = ?
                ORDER BY FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), t.start_time
            ");
            $stmt->execute([$studentId]);
            $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $slots]);
            break;

        case 'results':
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'exam_results'");
            if ($tableCheck->rowCount() === 0) {
                echo json_encode(['status' => 'success', 'data' => []]);
                break;
            }
            
            $stmt = $pdo->prepare("
                SELECT r.marks, r.grade, r.remarks, sa.name AS subject_name,
                       s.exam_name, s.exam_date
                FROM exam_results r
                JOIN school_exams s ON r.exam_id = s.id
                JOIN student_profiles sp ON r.student_profile_id = sp.id
                JOIN subject_areas sa ON r.subject_id = sa.id
                WHERE sp.user_id = ?
                ORDER BY s.exam_date DESC
            ");
            $stmt->execute([$studentId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $results]);
            break;

        case 'assignments':
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'student_assignments'");
            if ($tableCheck->rowCount() === 0) {
                echo json_encode(['status' => 'success', 'data' => []]);
                break;
            }

            $stmt = $pdo->prepare("
                SELECT a.title, a.due_date, a.status, sa.name AS subject_name
                FROM student_assignments a
                JOIN student_profiles sp ON a.student_profile_id = sp.id
                JOIN subject_areas sa ON a.subject_id = sa.id
                WHERE sp.user_id = ?
                ORDER BY a.due_date DESC
            ");
            $stmt->execute([$studentId]);
            $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $assignments]);
            break;
            
        case 'reports':
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'academic_reports'");
            if ($tableCheck->rowCount() === 0) {
                echo json_encode(['status' => 'success', 'data' => []]);
                break;
            }

            $stmt = $pdo->prepare("
                SELECT r.report_title, r.term, r.year, r.file_path, r.created_at
                FROM academic_reports r
                JOIN student_profiles sp ON r.student_profile_id = sp.id
                WHERE sp.user_id = ?
                ORDER BY r.created_at DESC
            ");
            $stmt->execute([$studentId]);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $reports]);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            break;
    }
} catch (PDOException $e) {
    error_log('[API_STUDENT_PORTAL ERROR] ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred.']);
}
?>
