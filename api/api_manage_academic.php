<?php
/**
 * Module 5: Exam Management, Smart Invigilation & Assignment Board
 * api_manage_academic.php
 * 
 * Handles: Exam creation, invigilation zero-overlap & load-balance checking,
 *          30-min rest cushion enforcement, and assignment uploads.
 */
require_once '../security.php';
start_secure_session();
header('Content-Type: application/json');
require_once '../db_connect.php';

// Auth guard
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated. Please log in.']);
    exit;
}

$role = $_SESSION['user_role'] ?? '';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Role authorization checks
if (in_array($action, ['create_exam', 'schedule_exam_session'])) {
    if ($role !== 'admin' && $role !== 'timetabler') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Access denied. Admin role required.']);
        exit;
    }
} elseif (in_array($action, ['submit_marks', 'upload_assignment', 'teacher_exams', 'session_students'])) {
    if (!in_array($role, ['admin', 'teacher', 'timetabler'])) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Access denied. Admin or Teacher role required.']);
        exit;
    }
}

// Release session lock early since subsequent operations are database queries
session_write_close();

// ─────────────────────────────────────────────────
// EXAM CREATION (Admin)
// ─────────────────────────────────────────────────
if ($action === 'create_exam') {
    $curriculum_id = filter_input(INPUT_POST, 'curriculum_id', FILTER_VALIDATE_INT);
    $exam_name  = trim($_POST['exam_name'] ?? '');
    $year       = trim($_POST['academic_year'] ?? '');
    $term       = trim($_POST['term_identifier'] ?? '');
    $deadline   = trim($_POST['submission_deadline'] ?? '');
    $alerts     = isset($_POST['automated_alerts_enabled']) ? 1 : 0;

    if (!$exam_name || !$year || !$term || !$deadline) {
        echo json_encode(['status' => 'error', 'message' => 'All exam fields are required.']); exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO school_exams (curriculum_id, exam_name, academic_year, term_identifier, submission_deadline, automated_alerts_enabled) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$curriculum_id, $exam_name, $year, $term, $deadline, $alerts]);
        echo json_encode(['status' => 'success', 'message' => "Exam '{$exam_name}' created.", 'exam_id' => $pdo->lastInsertId()]);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────
// SCHEDULE EXAM SESSION (with Invigilation Engine)
// ─────────────────────────────────────────────────
if ($action === 'schedule_exam_session') {
    $exam_id        = filter_input(INPUT_POST, 'exam_id', FILTER_VALIDATE_INT);
    $subject        = trim($_POST['subject'] ?? '');
    $exam_date      = trim($_POST['exam_date'] ?? '');
    $start_time     = trim($_POST['start_time'] ?? '');
    $end_time       = trim($_POST['end_time'] ?? '');
    $room_number    = trim($_POST['room_number'] ?? '');
    $teacher_id     = filter_input(INPUT_POST, 'invigilator_teacher_id', FILTER_VALIDATE_INT);
    $student_id     = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);

    if (!$exam_id || !$subject || !$exam_date || !$start_time || !$end_time || !$room_number || !$teacher_id || !$student_id) {
        echo json_encode(['status' => 'error', 'message' => 'All session fields (including Student) are required.']); exit;
    }

    try {
        // 1. Check invigilator is not double-booked (zero-overlap rule)
        $overlap = $pdo->prepare("
            SELECT COUNT(*) FROM exam_sessions
            WHERE invigilator_teacher_id = ? AND exam_date = ?
            AND NOT (end_time <= ? OR start_time >= ?)
        ");
        $overlap->execute([$teacher_id, $exam_date, $start_time, $end_time]);
        if ($overlap->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => '❌ Invigilation Conflict: This teacher is already assigned to another exam session that overlaps with this time block.']); exit;
        }

        // 2. Check student is not double-booked (zero-overlap rule)
        $studentOverlap = $pdo->prepare("
            SELECT COUNT(*) FROM exam_sessions
            WHERE student_id = ? AND exam_date = ?
            AND NOT (end_time <= ? OR start_time >= ?)
        ");
        $studentOverlap->execute([$student_id, $exam_date, $start_time, $end_time]);
        if ($studentOverlap->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => '❌ Student Conflict: This student is already scheduled for another exam session that overlaps with this time block.']); exit;
        }

        // 3. Enforce 30-minute rest cushion between back-to-back sessions (school campus only)
        $adjacent = $pdo->prepare("
            SELECT COUNT(*) FROM exam_sessions
            WHERE invigilator_teacher_id = ? AND exam_date = ?
            AND (
                (end_time <= ? AND TIMEDIFF(?, end_time) < '00:30:00') OR
                (start_time >= ? AND TIMEDIFF(start_time, ?) < '00:30:00')
            )
        ");
        $adjacent->execute([$teacher_id, $exam_date, $start_time, $start_time, $end_time, $end_time]);
        if ($adjacent->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => '⚠️ Rest Cushion Violation: A minimum 30-minute rest period is required between back-to-back exam invigilation sessions at the school venue.']); exit;
        }

        // 4. Insert the session
        $insert = $pdo->prepare("INSERT INTO exam_sessions (exam_id, subject, exam_date, start_time, end_time, room_number, invigilator_teacher_id, student_id) VALUES (?,?,?,?,?,?,?,?)");
        $insert->execute([$exam_id, $subject, $exam_date, $start_time, $end_time, $room_number, $teacher_id, $student_id]);

        echo json_encode(['status' => 'success', 'message' => "✅ Exam session '{$subject}' scheduled. Invigilation and student constraints passed.", 'session_id' => $pdo->lastInsertId()]);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────
// SUBMIT EXAM MARKS (Teacher)
// ─────────────────────────────────────────────────
if ($action === 'submit_marks') {
    $session_id   = filter_input(INPUT_POST, 'exam_session_id', FILTER_VALIDATE_INT);
    $student_id   = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    $marks        = filter_input(INPUT_POST, 'marks_obtained', FILTER_VALIDATE_FLOAT);
    $remarks      = trim($_POST['teacher_remarks'] ?? '');
    $is_published = isset($_POST['is_published']) ? (int)$_POST['is_published'] : 0;

    if (!$session_id || !$student_id || $marks === false) {
        echo json_encode(['status' => 'error', 'message' => 'session_id, student_id, and marks are required.']); exit;
    }

    try {
        $userRole = $_SESSION['user_role'] ?? '';
        $userId   = $_SESSION['user_id'] ?? 0;

        if ($userRole === 'teacher') {
            // Check if teacher teaches this student
            $chk = $pdo->prepare("SELECT COUNT(*) FROM timetable_slots WHERE teacher_id = ? AND student_id = ?");
            $chk->execute([$userId, $student_id]);
            if ($chk->fetchColumn() == 0) {
                echo json_encode(['status' => 'error', 'message' => '❌ Unauthorized: You can only enter marks for students you teach.']); exit;
            }
            // Teacher submissions start as draft/staging (is_published = 0)
            $is_published = 0;
        }

        // Upsert: insert or update marks for this student in this session
        $stmt = $pdo->prepare("
            INSERT INTO exam_results (exam_session_id, student_id, marks_obtained, teacher_remarks, is_published)
            VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE 
                marks_obtained=VALUES(marks_obtained), 
                teacher_remarks=VALUES(teacher_remarks),
                is_published=VALUES(is_published)
        ");
        $stmt->execute([$session_id, $student_id, $marks, $remarks, $is_published]);
        
        $msg = ($userRole === 'admin') ? "Marks updated and saved successfully by Admin." : "Marks saved to staging. Pending Admin approval before parent release.";
        echo json_encode(['status' => 'success', 'message' => $msg]);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────
// ASSIGNMENT UPLOAD (Teacher)
// ─────────────────────────────────────────────────
if ($action === 'upload_assignment') {
    $student_id  = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    $teacher_id  = filter_input(INPUT_POST, 'teacher_id', FILTER_VALIDATE_INT);
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $due_date    = trim($_POST['due_date'] ?? '');

    if (!$student_id || !$teacher_id || !$title || !$description || !$due_date) {
        echo json_encode(['status' => 'error', 'message' => 'All assignment fields are required.']); exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO student_assignments (student_id, teacher_id, title, description, due_date) VALUES (?,?,?,?,?)");
        $stmt->execute([$student_id, $teacher_id, $title, $description, $due_date]);
        echo json_encode(['status' => 'success', 'message' => "Assignment '{$title}' uploaded successfully.", 'assignment_id' => $pdo->lastInsertId()]);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────
// GET: Fetch exams, sessions, assignments
// ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $getAction = $_GET['action'] ?? '';

    // ── Teacher: get exams + their sessions they can grade ──
    if ($getAction === 'teacher_exams') {
        try {
            $exams = $pdo->query("SELECT e.*, c.name AS curriculum_name FROM school_exams e LEFT JOIN curriculums c ON e.curriculum_id = c.id ORDER BY e.created_at DESC")->fetchAll();
            $sessions = $pdo->query("
                SELECT es.*, se.exam_name, se.submission_deadline,
                       u.name as teacher_name
                FROM exam_sessions es
                JOIN school_exams se ON es.exam_id = se.id
                JOIN teachers u ON es.invigilator_teacher_id = u.id
                ORDER BY es.exam_date ASC, es.start_time ASC
            ")->fetchAll();
            echo json_encode(['status' => 'success', 'exams' => $exams, 'sessions' => $sessions]);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── Teacher: get students + their existing marks for a session ──
    if ($getAction === 'session_students') {
        $session_id = filter_input(INPUT_GET, 'session_id', FILTER_VALIDATE_INT);
        if (!$session_id) { echo json_encode(['status' => 'error', 'message' => 'session_id required.']); exit; }
        try {
            $userRole = $_SESSION['user_role'] ?? '';
            $userId   = $_SESSION['user_id'] ?? 0;

            if ($userRole === 'teacher') {
                $stmt = $pdo->prepare("
                    SELECT sp.id as student_id, u.name as student_name, sp.grade_level
                    FROM student_profiles sp
                    JOIN students u ON sp.user_id = u.id
                    WHERE sp.id IN (SELECT DISTINCT student_id FROM timetable_slots WHERE teacher_id = ?)
                    ORDER BY u.name ASC
                ");
                $stmt->execute([$userId]);
                $students = $stmt->fetchAll();
            } else {
                $students = $pdo->query("
                    SELECT sp.id as student_id, u.name as student_name, sp.grade_level
                    FROM student_profiles sp
                    JOIN students u ON sp.user_id = u.id
                    ORDER BY u.name ASC
                ")->fetchAll();
            }
            // Fetch existing results for this session
            $existing = $pdo->prepare("SELECT * FROM exam_results WHERE exam_session_id = ?");
            $existing->execute([$session_id]);
            $resultsMap = [];
            foreach ($existing->fetchAll() as $r) {
                $resultsMap[$r['student_id']] = $r;
            }
            // Merge
            foreach ($students as &$s) {
                $r = $resultsMap[$s['student_id']] ?? null;
                $s['marks_obtained']  = $r ? $r['marks_obtained']  : '';
                $s['teacher_remarks'] = $r ? $r['teacher_remarks'] : '';
                $s['is_published']    = $r ? $r['is_published']    : 0;
            }
            echo json_encode(['status' => 'success', 'students' => $students]);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── Admin/Teacher: full cross-subject results for an exam ──
    if ($getAction === 'exam_results') {
        $exam_id = filter_input(INPUT_GET, 'exam_id', FILTER_VALIDATE_INT);
        if (!$exam_id) { echo json_encode(['status' => 'error', 'message' => 'exam_id required.']); exit; }
        try {
            // Get all sessions for this exam
            $sessions = $pdo->prepare("
                SELECT es.id as session_id, es.subject, es.exam_date
                FROM exam_sessions es
                WHERE es.exam_id = ?
                ORDER BY es.subject ASC
            ");
            $sessions->execute([$exam_id]);
            $sessionList = $sessions->fetchAll();
            $sessionIds = array_column($sessionList, 'session_id');

            // Get all results for those sessions
            $results = [];
            if (!empty($sessionIds)) {
                $in = implode(',', array_fill(0, count($sessionIds), '?'));
                $rStmt = $pdo->prepare("
                    SELECT er.*, es.subject, u.name as student_name, sp.grade_level, sp.curriculum_id
                    FROM exam_results er
                    JOIN exam_sessions es ON er.exam_session_id = es.id
                    JOIN student_profiles sp ON er.student_id = sp.id
                    JOIN students u ON sp.user_id = u.id
                    WHERE er.exam_session_id IN ($in)
                    ORDER BY u.name ASC, es.subject ASC
                ");
                $rStmt->execute($sessionIds);
                $results = $rStmt->fetchAll();
            }

            // Fetch grading scales
            $scales = $pdo->query("SELECT * FROM grading_scales ORDER BY grade_level ASC, min_mark DESC")->fetchAll();

            // Helper: compute grade from marks + grade_level + curriculum_id
            $getGrade = function($marks, $grade_level, $curriculum_id = null) use ($scales) {
                // Try curriculum-specific scale first
                foreach ($scales as $s) {
                    if ($s['curriculum_id'] !== null && (int)$s['curriculum_id'] === (int)$curriculum_id) {
                        if (strtolower(trim($s['grade_level'])) === 'all' || strtolower(trim($s['grade_level'])) === strtolower(trim($grade_level))) {
                            if ($marks >= $s['min_mark'] && $marks <= $s['max_mark']) {
                                return ['letter' => $s['letter_grade'], 'remark' => $s['remarks_template'] ?? ''];
                            }
                        }
                    }
                }
                // Fallback to default scale (null curriculum_id)
                foreach ($scales as $s) {
                    if ($s['curriculum_id'] === null) {
                        if (strtolower(trim($s['grade_level'])) === 'all' || strtolower(trim($s['grade_level'])) === strtolower(trim($grade_level))) {
                            if ($marks >= $s['min_mark'] && $marks <= $s['max_mark']) {
                                return ['letter' => $s['letter_grade'], 'remark' => $s['remarks_template'] ?? ''];
                            }
                        }
                    }
                }
                return ['letter' => '–', 'remark' => ''];
            };

            // Group results by student
            $byStudent = [];
            foreach ($results as $r) {
                $sid = $r['student_id'];
                if (!isset($byStudent[$sid])) {
                    $byStudent[$sid] = [
                        'student_id'   => $sid,
                        'student_name' => $r['student_name'],
                        'grade_level'  => $r['grade_level'],
                        'curriculum_id'=> $r['curriculum_id'],
                        'subjects'     => [],
                        'total'        => 0,
                        'count'        => 0,
                    ];
                }
                $grade = $getGrade((float)$r['marks_obtained'], $r['grade_level'], $r['curriculum_id']);
                $byStudent[$sid]['subjects'][] = [
                    'subject'         => $r['subject'],
                    'marks_obtained'  => $r['marks_obtained'],
                    'teacher_remarks' => $r['teacher_remarks'],
                    'grade_letter'    => $grade['letter'],
                    'grade_remark'    => $grade['remark'],
                    'is_published'    => $r['is_published'],
                ];
                $byStudent[$sid]['total'] += (float)$r['marks_obtained'];
                $byStudent[$sid]['count']++;
            }
            // Compute average grade
            foreach ($byStudent as &$stu) {
                $avg = $stu['count'] > 0 ? ($stu['total'] / $stu['count']) : 0;
                $stu['average']     = round($avg, 2);
                $stu['total']       = round($stu['total'], 2);
                $overall = $getGrade($avg, $stu['grade_level'], $stu['curriculum_id']);
                $stu['overall_grade']  = $overall['letter'];
                $stu['overall_remark'] = $overall['remark'];
            }

            echo json_encode([
                'status'   => 'success',
                'sessions' => $sessionList,
                'students' => array_values($byStudent),
            ]);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── Default: admin panel data (exams, sessions, assignments, teachers, students) ──
    try {
        $exams = $pdo->query("SELECT e.*, c.name AS curriculum_name FROM school_exams e LEFT JOIN curriculums c ON e.curriculum_id = c.id ORDER BY e.created_at DESC")->fetchAll();
        $sessions = $pdo->query("
            SELECT es.*, se.exam_name, u.name as teacher_name, u_s.name as student_name
            FROM exam_sessions es
            JOIN school_exams se ON es.exam_id = se.id
            JOIN teachers u ON es.invigilator_teacher_id = u.id
            LEFT JOIN student_profiles sp ON es.student_id = sp.id
            LEFT JOIN students u_s ON sp.user_id = u_s.id
            ORDER BY es.exam_date ASC, es.start_time ASC
        ")->fetchAll();

        // Invigilation load balance report
        $load = $pdo->query("
            SELECT u.name, COUNT(es.id) as session_count
            FROM exam_sessions es
            JOIN teachers u ON es.invigilator_teacher_id = u.id
            GROUP BY es.invigilator_teacher_id
            ORDER BY session_count DESC
        ")->fetchAll();

        $assignments = $pdo->query("
            SELECT sa.*, sp.grade_level, u_s.name as student_name, u_t.name as teacher_name
            FROM student_assignments sa
            JOIN student_profiles sp ON sa.student_id = sp.id
            JOIN students u_s ON sp.user_id = u_s.id
            JOIN teachers u_t ON sa.teacher_id = u_t.id
            ORDER BY sa.due_date ASC
        ")->fetchAll();

        require_once __DIR__ . '/../admission_helper.php';
        auto_assign_missing_admission_nos($pdo);

        $teachers = $pdo->query("SELECT id, name FROM teachers")->fetchAll();
        $students = $pdo->query("SELECT sp.id, u.name as student_name, sp.grade_level, u.admission_no, u.staff_id FROM student_profiles sp JOIN students u ON sp.user_id = u.id")->fetchAll();

        echo json_encode([
            'status'      => 'success',
            'exams'       => $exams,
            'sessions'    => $sessions,
            'invig_load'  => $load,
            'assignments' => $assignments,
            'teachers'    => $teachers,
            'students'    => $students
        ]);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
?>
