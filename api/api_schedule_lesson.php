<?php
/**
 * Module 3: Smart Timetabler & Spatial Constraint Engine
 * api/api_schedule_lesson.php
 * * Validates travel-time constraints before committing a lesson slot to the DB.
 * Update: Grants the timetabler full override control over 2hr and 30min buffer rules.
 */
header('Content-Type: application/json; charset=utf-8');
require_once '../security.php';
start_secure_session();
require_once '../db_connect.php';

// Auth guard
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated. Please log in.']);
    exit;
}

$role = $_SESSION['user_role'] ?? '';

// Write operations (POST) require admin or timetabler role
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($role, ['admin', 'timetabler'])) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Access denied. Admin or Academic Operations Coordinator role required.']);
        exit;
    }
} else {
    // Read operations (GET) require admin, timetabler, or teacher role
    if (!in_array($role, ['admin', 'timetabler', 'teacher'])) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Access denied. Admin, Academic Operations Coordinator, or Teacher role required.']);
        exit;
    }
}

// Release session lock early since subsequent operations are database queries
session_write_close();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'schedule') {
        $slot_id         = filter_input(INPUT_POST, 'slot_id', FILTER_VALIDATE_INT);
        $teacher_id      = filter_input(INPUT_POST, 'teacher_id', FILTER_VALIDATE_INT);
        $student_id      = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
        $day_of_week     = filter_input(INPUT_POST, 'day_of_week', FILTER_SANITIZE_SPECIAL_CHARS);
        $start_time      = filter_input(INPUT_POST, 'start_time', FILTER_SANITIZE_SPECIAL_CHARS);
        $end_time        = filter_input(INPUT_POST, 'end_time', FILTER_SANITIZE_SPECIAL_CHARS);
        $venue_type      = filter_input(INPUT_POST, 'venue_type', FILTER_SANITIZE_SPECIAL_CHARS);
        $student_address = $_POST['student_address'] ?? null;

        if (!$teacher_id || !$student_id || !$day_of_week || !$start_time || !$end_time || !$venue_type) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required parameter fields.']);
            exit;
        }

        try {
            $new_start = strtotime($start_time);
            $new_end   = strtotime($end_time);

            if ($new_start >= $new_end) {
                echo json_encode(['status' => 'error', 'message' => 'Start time must fall before end time.']);
                exit;
            }

            // ── 1. Teacher clash check (same teacher, same day, overlapping time) ──
            $tQuery = "SELECT ts.id, ts.start_time, ts.end_time, sp.id AS sp_id,
                              (SELECT s.name FROM students s WHERE s.id = sp.user_id LIMIT 1) AS student_name
                       FROM timetable_slots ts
                       JOIN student_profiles sp ON ts.student_id = sp.id
                       WHERE ts.teacher_id = ? AND ts.day_of_week = ?";
            $tParams = [$teacher_id, $day_of_week];
            if ($slot_id) { $tQuery .= " AND ts.id != ?"; $tParams[] = $slot_id; }
            $tStmt = $pdo->prepare($tQuery);
            $tStmt->execute($tParams);
            foreach ($tStmt->fetchAll() as $slot) {
                $es = strtotime($slot['start_time']);
                $ee = strtotime($slot['end_time']);
                if ($new_start < $ee && $new_end > $es) {
                    $sName = $slot['student_name'] ? " (lesson with {$slot['student_name']})" : '';
                    echo json_encode([
                        'status'  => 'error',
                        'message' => "⚠️ Schedule Clash Detected: The selected teacher is already booked from {$slot['start_time']} to {$slot['end_time']} on {$day_of_week}{$sName}."
                    ]);
                    exit;
                }
            }

            // ── 2. Student clash check (same student, same day, overlapping time) ──
            $sQuery = "SELECT ts.id, ts.start_time, ts.end_time,
                              t.name AS teacher_name
                       FROM timetable_slots ts
                       JOIN teachers t ON ts.teacher_id = t.id
                       WHERE ts.student_id = ? AND ts.day_of_week = ?";
            $sParams = [$student_id, $day_of_week];
            if ($slot_id) { $sQuery .= " AND ts.id != ?"; $sParams[] = $slot_id; }
            $sStmt = $pdo->prepare($sQuery);
            $sStmt->execute($sParams);
            foreach ($sStmt->fetchAll() as $slot) {
                $es = strtotime($slot['start_time']);
                $ee = strtotime($slot['end_time']);
                if ($new_start < $ee && $new_end > $es) {
                    echo json_encode([
                        'status'  => 'error',
                        'message' => "⚠️ Schedule Clash Detected: The selected student is already booked from {$slot['start_time']} to {$slot['end_time']} on {$day_of_week} with {$slot['teacher_name']}."
                    ]);
                    exit;
                }
            }

            // ── Insert or Update ──
            if ($slot_id) {
                $stmt = $pdo->prepare("UPDATE timetable_slots SET teacher_id = ?, student_id = ?, day_of_week = ?, start_time = ?, end_time = ?, venue_type = ?, student_address = ? WHERE id = ?");
                $stmt->execute([$teacher_id, $student_id, $day_of_week, $start_time, $end_time, $venue_type, $student_address, $slot_id]);
                $msg = "✅ Timetable slot modified successfully.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO timetable_slots (teacher_id, student_id, day_of_week, start_time, end_time, venue_type, student_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$teacher_id, $student_id, $day_of_week, $start_time, $end_time, $venue_type, $student_address]);
                $msg = "✅ Lesson scheduled successfully.";
            }

            echo json_encode(['status' => 'success', 'message' => $msg]);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // ── Edit an existing slot (sent as action='edit_slot') ──
    if ($action === 'edit_slot') {
        $slot_id         = filter_input(INPUT_POST, 'slot_id', FILTER_VALIDATE_INT);
        $student_id      = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
        $teacher_id      = filter_input(INPUT_POST, 'teacher_id', FILTER_VALIDATE_INT);
        $day_of_week     = filter_input(INPUT_POST, 'day_of_week', FILTER_SANITIZE_SPECIAL_CHARS);
        $start_time      = filter_input(INPUT_POST, 'start_time', FILTER_SANITIZE_SPECIAL_CHARS);
        $end_time        = filter_input(INPUT_POST, 'end_time', FILTER_SANITIZE_SPECIAL_CHARS);
        $venue_type      = filter_input(INPUT_POST, 'venue_type', FILTER_SANITIZE_SPECIAL_CHARS);
        $student_address = $_POST['student_address'] ?? null;

        if (!$slot_id || !$teacher_id || !$day_of_week || !$start_time || !$end_time || !$venue_type) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields for edit.']);
            exit;
        }

        try {
            $new_start = strtotime($start_time);
            $new_end   = strtotime($end_time);
            if ($new_start >= $new_end) {
                echo json_encode(['status' => 'error', 'message' => 'Start time must be before end time.']);
                exit;
            }

            // ── 1. Teacher clash check (excluding this slot) ──
            $tStmt = $pdo->prepare("SELECT ts.id, ts.start_time, ts.end_time,
                                           (SELECT s.name FROM students s JOIN student_profiles sp ON sp.user_id = s.id WHERE sp.id = ts.student_id LIMIT 1) AS student_name
                                    FROM timetable_slots ts
                                    WHERE ts.teacher_id = ? AND ts.day_of_week = ? AND ts.id != ?");
            $tStmt->execute([$teacher_id, $day_of_week, $slot_id]);
            foreach ($tStmt->fetchAll() as $existing) {
                $es = strtotime($existing['start_time']);
                $ee = strtotime($existing['end_time']);
                if ($new_start < $ee && $new_end > $es) {
                    $sName = $existing['student_name'] ? " (lesson with {$existing['student_name']})" : '';
                    echo json_encode(['status' => 'error', 'message' => "⚠️ Schedule Clash Detected: The selected teacher is already booked from {$existing['start_time']} to {$existing['end_time']} on {$day_of_week}{$sName}."]);
                    exit;
                }
            }

            // ── 2. Student clash check (excluding this slot) ──
            if ($student_id) {
                $sStmt = $pdo->prepare("SELECT ts.id, ts.start_time, ts.end_time, t.name AS teacher_name
                                        FROM timetable_slots ts
                                        JOIN teachers t ON ts.teacher_id = t.id
                                        WHERE ts.student_id = ? AND ts.day_of_week = ? AND ts.id != ?");
                $sStmt->execute([$student_id, $day_of_week, $slot_id]);
                foreach ($sStmt->fetchAll() as $existing) {
                    $es = strtotime($existing['start_time']);
                    $ee = strtotime($existing['end_time']);
                    if ($new_start < $ee && $new_end > $es) {
                        echo json_encode(['status' => 'error', 'message' => "⚠️ Schedule Clash Detected: The selected student is already booked from {$existing['start_time']} to {$existing['end_time']} on {$day_of_week} with {$existing['teacher_name']}."]);
                        exit;
                    }
                }
            }

            // ── Update ──
            if ($student_id) {
                $stmt = $pdo->prepare("UPDATE timetable_slots SET teacher_id=?, student_id=?, day_of_week=?, start_time=?, end_time=?, venue_type=?, student_address=? WHERE id=?");
                $stmt->execute([$teacher_id, $student_id, $day_of_week, $start_time, $end_time, $venue_type, $student_address, $slot_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE timetable_slots SET teacher_id=?, day_of_week=?, start_time=?, end_time=?, venue_type=?, student_address=? WHERE id=?");
                $stmt->execute([$teacher_id, $day_of_week, $start_time, $end_time, $venue_type, $student_address, $slot_id]);
            }
            echo json_encode(['status' => 'success', 'message' => '✅ Timetable slot updated successfully.']);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ── Delete a slot — handle both 'delete' and 'delete_slot' action names ──
    if ($action === 'delete' || $action === 'delete_slot') {
        $slot_id = filter_input(INPUT_POST, 'slot_id', FILTER_VALIDATE_INT);
        if (!$slot_id) {
            echo json_encode(['status' => 'error', 'message' => 'Slot ID missing.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM timetable_slots WHERE id = ?");
            $stmt->execute([$slot_id]);
            echo json_encode(['status' => 'success', 'message' => '✅ Timetable slot deleted successfully.']);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]);
        }
        exit;
    }
}

if ($method === 'GET') {
    $teacher_id = filter_input(INPUT_GET, 'teacher_id', FILTER_VALIDATE_INT);

    // ── Load timetable slots (independent - failure doesn't block students/teachers) ──
    $slots = [];
    try {
        // Check if student_address column exists first
        $hasStudentAddress = false;
        try {
            $pdo->query("SELECT student_address FROM timetable_slots LIMIT 0");
            $hasStudentAddress = true;
        } catch (\PDOException $ex) {
            // Column doesn't exist on this server, skip it
        }

        $addrCol = $hasStudentAddress ? 'ts.student_address,' : "'' AS student_address,";

        $query = "
            SELECT ts.id, ts.teacher_id, ts.student_id, ts.day_of_week, ts.start_time, ts.end_time,
                   ts.venue_type, {$addrCol} sp.grade_level,
                   u_student.name as student_name,
                   u_student.admission_no, u_student.staff_id,
                   u_teacher.name as teacher_name,
                   (SELECT GROUP_CONCAT(DISTINCT sa.name ORDER BY sa.name ASC SEPARATOR ', ')
                    FROM teacher_subjects ts_subj
                    JOIN subject_areas sa ON ts_subj.subject_id = sa.id
                    WHERE ts_subj.teacher_id = ts.teacher_id) AS subject_names
            FROM timetable_slots ts
            JOIN student_profiles sp ON ts.student_id = sp.id
            JOIN students u_student ON sp.user_id = u_student.id
            JOIN teachers u_teacher ON ts.teacher_id = u_teacher.id
        ";
        $params = [];
        if ($teacher_id) {
            $query .= " WHERE ts.teacher_id = ?";
            $params[] = $teacher_id;
        }
        $query .= " ORDER BY FIELD(ts.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), ts.start_time";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $slots = $stmt->fetchAll();
    } catch (\PDOException $e) {
        error_log('[SHTA TIMETABLE SLOTS ERROR] ' . $e->getMessage());
        // Don't exit - still load students and teachers below
    }

    // ── Ensure student profiles exist for all students ──
    try {
        require_once __DIR__ . '/../admission_helper.php';
        auto_assign_missing_admission_nos($pdo);
    } catch (\Throwable $e) {
        error_log('[SHTA ADMISSION HELPER ERROR] ' . $e->getMessage());
    }

    // ── Load students (independent) ──
    $students = [];
    try {
        // Check if admission_no column exists
        $hasAdmNo = false;
        try { $pdo->query("SELECT admission_no FROM students LIMIT 0"); $hasAdmNo = true; } catch (\PDOException $ex) {}
        $hasStaffId = false;
        try { $pdo->query("SELECT staff_id FROM students LIMIT 0"); $hasStaffId = true; } catch (\PDOException $ex) {}

        $admCol    = $hasAdmNo    ? 'u.admission_no' : "NULL AS admission_no";
        $staffCol  = $hasStaffId  ? 'u.staff_id'     : "NULL AS staff_id";

        $students = $pdo->query("
            SELECT
                COALESCE(sp.id, u.id) AS id,
                u.id AS user_id,
                u.name AS student_name,
                COALESCE(sp.grade_level, 'Student') AS grade_level,
                {$admCol},
                {$staffCol},
                (SELECT GROUP_CONCAT(DISTINCT sa.name ORDER BY sa.name ASC SEPARATOR ', ')
                 FROM student_subjects ss
                 JOIN subject_areas sa ON ss.subject_id = sa.id
                 WHERE ss.student_id = sp.id) AS subject_names,
                (SELECT GROUP_CONCAT(DISTINCT ss.subject_id ORDER BY ss.subject_id ASC)
                 FROM student_subjects ss
                 WHERE ss.student_id = sp.id) AS subject_ids
            FROM students u
            LEFT JOIN student_profiles sp ON u.id = sp.user_id
            ORDER BY u.name ASC
        ")->fetchAll();
    } catch (\PDOException $e) {
        error_log('[SHTA TIMETABLE STUDENTS ERROR] ' . $e->getMessage());
    }

    // ── Load teachers (independent) ──
    $teachers = [];
    try {
        $teachers = $pdo->query("
            SELECT u.id, u.name,
                   (SELECT GROUP_CONCAT(ts.subject_id)
                    FROM teacher_subjects ts
                    WHERE ts.teacher_id = u.id) AS subject_ids
            FROM teachers u
            ORDER BY u.name ASC
        ")->fetchAll();
    } catch (\PDOException $e) {
        error_log('[SHTA TIMETABLE TEACHERS ERROR] ' . $e->getMessage());
    }

    echo json_encode([
        'status'   => 'success',
        'slots'    => $slots,
        'students' => $students,
        'teachers' => $teachers
    ]);
    exit;
}
?>

