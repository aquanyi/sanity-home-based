<?php
/**
 * Module 4: Mobile Teacher Attendance & Parent OTP Handshake
 * api_lesson_attendance.php
 *
 * Handles: OTP generation, check-in verification, check-out form logging,
 *          parent email notifications, and payroll/balance tracking.
 */
header('Content-Type: application/json; charset=utf-8');
require_once '../security.php';
start_secure_session();
require_once '../db_connect.php';
require_once '../mail_helper.php';

// Auth guard
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated. Please log in.']);
    exit;
}

$role = $_SESSION['user_role'] ?? '';

// Only Admins and Teachers can interact with attendance APIs
if (!in_array($role, ['admin', 'teacher'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Admin or Teacher role required.']);
    exit;
}

// Release session lock early since subsequent operations are database queries
session_write_close();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ─────────────────────────────────────────────────
// GET: Fetch lessons for teacher dashboard
// ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'fetch_teacher_lessons') {
    $teacher_id_raw = $_GET['teacher_id'] ?? '';
    if (empty($teacher_id_raw)) { echo json_encode(['status' => 'error', 'message' => 'teacher_id required']); exit; }

    try {
        // Auto-generate lessons for the current week from timetable slots
        $slotQuery = "SELECT id, day_of_week FROM timetable_slots";
        $slotParams = [];
        if ($teacher_id_raw !== 'all') {
            $slotQuery .= " WHERE teacher_id = ?";
            $slotParams[] = (int)$teacher_id_raw;
        }
        $slotStmt = $pdo->prepare($slotQuery);
        $slotStmt->execute($slotParams);
        $slots = $slotStmt->fetchAll();
        
        foreach ($slots as $slot) {
            $dayOfWeek = $slot['day_of_week']; // e.g., 'Monday'
            $lessonDate = date('Y-m-d', strtotime($dayOfWeek . ' this week'));
            
            $checkStmt = $pdo->prepare("SELECT id FROM lessons WHERE slot_id = ? AND lesson_date = ?");
            $checkStmt->execute([$slot['id'], $lessonDate]);
            if (!$checkStmt->fetch()) {
                $insertStmt = $pdo->prepare("INSERT INTO lessons (slot_id, lesson_date, session_status) VALUES (?, ?, 'scheduled')");
                $insertStmt->execute([$slot['id'], $lessonDate]);
            }
        }

        $query = "
            SELECT l.*, ts.day_of_week, ts.start_time, ts.end_time, ts.venue_type,
                   sp.grade_level,
                   u_student.name as student_name,
                   u_student.admission_no, u_student.staff_id,
                   (SELECT GROUP_CONCAT(DISTINCT sa.name ORDER BY sa.name ASC SEPARATOR ', ')
                    FROM student_subjects ss
                    JOIN subject_areas sa ON ss.subject_id = sa.id
                    JOIN teacher_subjects ts_subj ON ts_subj.subject_id = sa.id
                    WHERE ss.student_id = sp.id AND ts_subj.teacher_id = ts.teacher_id) AS subject_names,
                   u_parent.name as parent_name, u_parent.email as parent_email, u_parent.phone as parent_phone,
                   u_teacher.name as teacher_name
            FROM lessons l
            JOIN timetable_slots ts ON l.slot_id = ts.id
            LEFT JOIN student_profiles sp ON ts.student_id = sp.id
            LEFT JOIN students u_student ON sp.user_id = u_student.id
            LEFT JOIN parents u_parent ON sp.parent_id = u_parent.id
            LEFT JOIN teachers u_teacher ON ts.teacher_id = u_teacher.id
        ";
        $params = [];
        if ($teacher_id_raw !== 'all') {
            $query .= " WHERE ts.teacher_id = ?";
            $params[] = (int)$teacher_id_raw;
        }
        $query .= " ORDER BY l.lesson_date DESC, ts.start_time ASC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $lessons = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'lessons' => $lessons]);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']); exit;
}

// ─────────────────────────────────────────────────
// ACTION: start_lesson — generate OTP, email parent
// ─────────────────────────────────────────────────
if ($action === 'start_lesson') {
    $lesson_id = filter_input(INPUT_POST, 'lesson_id', FILTER_VALIDATE_INT);
    if (!$lesson_id) { echo json_encode(['status' => 'error', 'message' => 'lesson_id required']); exit; }

    try {
        $stmt = $pdo->prepare("
            SELECT l.*, u_parent.name as parent_name, u_parent.email as parent_email, u_parent.phone as parent_phone,
                   u_student.name as student_name, u_teacher.name as teacher_name, ts.venue_type, ts.teacher_id
            FROM lessons l
            JOIN timetable_slots ts ON l.slot_id = ts.id
            LEFT JOIN student_profiles sp ON ts.student_id = sp.id
            LEFT JOIN parents u_parent ON sp.parent_id = u_parent.id
            LEFT JOIN students u_student ON sp.user_id = u_student.id
            LEFT JOIN teachers u_teacher ON ts.teacher_id = u_teacher.id
            WHERE l.id = ? AND l.session_status = 'scheduled'
        ");
        $stmt->execute([$lesson_id]);
        $lesson = $stmt->fetch();

        if (!$lesson) {
            echo json_encode(['status' => 'error', 'message' => 'Lesson not found or already in progress.']); exit;
        }

        $checkInTime = date('Y-m-d H:i:s');

        // Immediately update lesson status to 'in_progress' and set check-in time
        $update = $pdo->prepare("UPDATE lessons SET session_status='in_progress', check_in_time=?, current_otp=NULL WHERE id=?");
        $update->execute([$checkInTime, $lesson_id]);

        // Venue label
        $venue_map = [
            'online_meet' => 'Online (Google Meet)',
            'online_zoom' => 'Online (Zoom)',
            'school'      => 'School Campus (1-on-1)',
            'home_visit'  => 'Home Visit (1-on-1)',
        ];
        $venueLabel = $venue_map[$lesson['venue_type']] ?? ucfirst($lesson['venue_type']);

        // Fetch teacher subjects
        $subjStmt = $pdo->prepare("
            SELECT GROUP_CONCAT(sa.subject SEPARATOR ' / ') as subjects
            FROM teacher_subjects ts
            JOIN subject_areas sa ON ts.subject_id = sa.id
            WHERE ts.teacher_id = ?
        ");
        $subjStmt->execute([$lesson['teacher_id']]);
        $subjRow = $subjStmt->fetch();
        $subjectName = $subjRow['subjects'] ?? 'General Tutoring';

        // Notify parent via email — lesson has commenced (Admins automatically BCC'd via sendMail)
        $parentSubject = "✅ Lesson Started – {$lesson['student_name']}";
        $parentBody = "
            <p>Dear <strong>{$lesson['parent_name']}</strong>,</p>
            <p>Teacher <strong>{$lesson['teacher_name']}</strong> has checked in and <strong>{$lesson['student_name']}</strong>'s lesson has officially commenced.</p>

            <table style='width:100%;font-size:14px;background:#FAF7F2;padding:14px;border-radius:8px;border-collapse:collapse;margin:20px 0;'>
                <tr><td style='padding:4px 0;'><strong>Check-In Time:</strong></td><td><strong style='color:#047857;'>{$checkInTime}</strong></td></tr>
                <tr><td style='padding:4px 0;'><strong>Teacher Name:</strong></td><td>{$lesson['teacher_name']}</td></tr>
                <tr><td style='padding:4px 0;'><strong>Student Name:</strong></td><td>{$lesson['student_name']}</td></tr>
                <tr><td style='padding:4px 0;'><strong>Subject:</strong></td><td>{$subjectName}</td></tr>
                <tr><td style='padding:4px 0;'><strong>Location / Format:</strong></td><td>{$venueLabel}</td></tr>
            </table>

            <p style='color:#6C757D;font-size:13px;'>You will receive another email notification with a full session report once the lesson ends.</p>
        ";
        sendMail($lesson['parent_email'], $parentSubject, $parentBody, MAIL_INFO_FROM, MAIL_SCHOOL_NAME . ' — Attendance', true);

        // Dispatch Check-In Admin System Notification
        try {
            $notifStmt = $pdo->prepare("INSERT INTO system_notifications (sender_name, recipient_role, title, message) VALUES (?, 'admin', ?, ?)");
            $notifStmt->execute([
                $lesson['teacher_name'],
                "Lesson Started: " . $lesson['student_name'],
                "Teacher " . $lesson['teacher_name'] . " started the lesson with student " . $lesson['student_name'] . " (" . $subjectName . ") at " . $checkInTime . ". Location: " . $venueLabel . "."
            ]);
        } catch (\Exception $notifEx) {
            // Ignore
        }

        // Dispatch Check-In Parent System Notification
        try {
            $notifStmt = $pdo->prepare("INSERT INTO system_notifications (sender_name, recipient_role, title, message) VALUES (?, 'parent', ?, ?)");
            $notifStmt->execute([
                $lesson['teacher_name'],
                "Lesson Started: " . $lesson['student_name'],
                "Teacher " . $lesson['teacher_name'] . " started the lesson with student " . $lesson['student_name'] . " (" . $subjectName . ") at " . $checkInTime . ". Location: " . $venueLabel . "."
            ]);
        } catch (\Exception $notifEx) {
            // Ignore
        }

        echo json_encode([
            'status'        => 'success',
            'message'       => "✅ Lesson is now IN PROGRESS. Check-in locked at {$checkInTime}.",
            'check_in_time' => $checkInTime
        ]);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────
// ACTION: verify_otp — verify OTP, lock check_in_time
// ─────────────────────────────────────────────────
if ($action === 'verify_otp') {
    $lesson_id   = filter_input(INPUT_POST, 'lesson_id', FILTER_VALIDATE_INT);
    $entered_otp = trim($_POST['otp'] ?? '');

    if (!$lesson_id || empty($entered_otp)) {
        echo json_encode(['status' => 'error', 'message' => 'lesson_id and OTP are required.']); exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT l.*, u_parent.name as parent_name, u_parent.email as parent_email,
                   u_student.name as student_name, u_teacher.name as teacher_name, ts.venue_type
            FROM lessons l
            JOIN timetable_slots ts ON l.slot_id = ts.id
            LEFT JOIN student_profiles sp ON ts.student_id = sp.id
            LEFT JOIN parents u_parent ON sp.parent_id = u_parent.id
            LEFT JOIN students u_student ON sp.user_id = u_student.id
            LEFT JOIN teachers u_teacher ON ts.teacher_id = u_teacher.id
            WHERE l.id = ? AND l.session_status = 'scheduled'
        ");
        $stmt->execute([$lesson_id]);
        $lesson = $stmt->fetch();

        if (!$lesson) {
            echo json_encode(['status' => 'error', 'message' => 'Lesson not found or already active.']); exit;
        }

        if ($lesson['current_otp'] !== $entered_otp) {
            echo json_encode(['status' => 'error', 'message' => '❌ OTP Mismatch: The code entered does not match. Please ask the parent to re-check.']); exit;
        }

        $checkInTime = date('Y-m-d H:i:s');
        $update = $pdo->prepare("UPDATE lessons SET session_status='in_progress', check_in_time=?, current_otp=NULL WHERE id=?");
        $update->execute([$checkInTime, $lesson_id]);

        // Venue label
        $venue_map = [
            'online_meet' => 'Online (Google Meet)',
            'online_zoom' => 'Online (Zoom)',
            'school'      => 'School Campus (1-on-1)',
            'home_visit'  => 'Home Visit (1-on-1)',
        ];
        $venueLabel = $venue_map[$lesson['venue_type']] ?? ucfirst($lesson['venue_type']);

        // Notify parent — lesson has commenced
        $parentSubject = "✅ Lesson In Progress – {$lesson['student_name']}";
        $parentBody = "
            <p>Dear <strong>{$lesson['parent_name']}</strong>,</p>
            <p>Teacher <strong>{$lesson['teacher_name']}</strong> has been verified and <strong>{$lesson['student_name']}</strong>'s lesson has officially commenced.</p>

            <table style='width:100%;font-size:14px;background:#FAF7F2;padding:14px;border-radius:8px;border-collapse:collapse;margin:20px 0;'>
                <tr><td style='padding:4px 0;'><strong>Check-In Time:</strong></td><td><strong style='color:#047857;'>{$checkInTime}</strong></td></tr>
                <tr><td style='padding:4px 0;'><strong>Teacher:</strong></td><td>{$lesson['teacher_name']}</td></tr>
                <tr><td style='padding:4px 0;'><strong>Student:</strong></td><td>{$lesson['student_name']}</td></tr>
                <tr><td style='padding:4px 0;'><strong>Format:</strong></td><td>{$venueLabel}</td></tr>
            </table>

            <p style='color:#6C757D;font-size:13px;'>You will receive another notification with a full session report once the lesson ends.</p>
        ";
        sendMail($lesson['parent_email'], $parentSubject, $parentBody, MAIL_INFO_FROM, MAIL_SCHOOL_NAME . ' — Attendance', true);

        // Dispatch Check-In Admin System Notification
        try {
            $notifStmt = $pdo->prepare("INSERT INTO system_notifications (sender_name, recipient_role, title, message) VALUES (?, 'admin', ?, ?)");
            $notifStmt->execute([
                $lesson['teacher_name'],
                "Lesson Started: " . $lesson['student_name'],
                "Teacher " . $lesson['teacher_name'] . " has verified the OTP and commenced lesson with student " . $lesson['student_name'] . " at " . $checkInTime . ". Format: " . $venueLabel . "."
            ]);
        } catch (\Exception $notifEx) {
            // Ignore
        }

        echo json_encode([
            'status'        => 'success',
            'message'       => "✅ OTP Verified. Lesson is now IN PROGRESS. Check-in locked at {$checkInTime}.",
            'check_in_time' => $checkInTime
        ]);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────
// ACTION: end_lesson — mandatory log form, check-out
// ─────────────────────────────────────────────────
if ($action === 'end_lesson') {
    $lesson_id         = filter_input(INPUT_POST, 'lesson_id', FILTER_VALIDATE_INT);
    $topics_covered    = trim($_POST['topics_covered'] ?? '');
    $progress_notes    = trim($_POST['progress_notes'] ?? '');
    $homework_assigned = trim($_POST['homework_assigned'] ?? '');

    if (!$lesson_id || empty($topics_covered) || empty($progress_notes) || empty($homework_assigned)) {
        echo json_encode(['status' => 'error', 'message' => 'All log fields (topics, progress notes, homework) are mandatory before ending a lesson.']); exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT l.*, u_parent.name as parent_name, u_parent.email as parent_email,
                   u_student.name as student_name, u_teacher.name as teacher_name, ts.venue_type
            FROM lessons l
            JOIN timetable_slots ts ON l.slot_id = ts.id
            LEFT JOIN student_profiles sp ON ts.student_id = sp.id
            LEFT JOIN parents u_parent ON sp.parent_id = u_parent.id
            LEFT JOIN students u_student ON sp.user_id = u_student.id
            LEFT JOIN teachers u_teacher ON ts.teacher_id = u_teacher.id
            WHERE l.id = ? AND l.session_status = 'in_progress'
        ");
        $stmt->execute([$lesson_id]);
        $lesson = $stmt->fetch();

        if (!$lesson) {
            echo json_encode(['status' => 'error', 'message' => 'Lesson not found or not currently active.']); exit;
        }

        $checkOutTime = date('Y-m-d H:i:s');
        $checkInTime  = $lesson['check_in_time'] ?? 'N/A';

        $update = $pdo->prepare("
            UPDATE lessons SET session_status='completed', check_out_time=?, topics_covered=?, progress_notes=?, homework_assigned=? WHERE id=?
        ");
        $update->execute([$checkOutTime, $topics_covered, $progress_notes, $homework_assigned, $lesson_id]);

        // Venue label
        $venue_map = [
            'online_meet' => 'Online (Google Meet)',
            'online_zoom' => 'Online (Zoom)',
            'school'      => 'School Campus (1-on-1)',
            'home_visit'  => 'Home Visit (1-on-1)',
        ];
        $venueLabel = $venue_map[$lesson['venue_type']] ?? ucfirst($lesson['venue_type']);

        // Session summary email to parent — admins auto-BCC'd
        $parentSubject = "📘 Session Report – {$lesson['student_name']}'s Lesson Completed";
        $parentBody = "
            <p>Dear <strong>{$lesson['parent_name']}</strong>,</p>
            <p>Teacher <strong>{$lesson['teacher_name']}</strong> has successfully completed today's session with <strong>{$lesson['student_name']}</strong>. Here is the full session report:</p>

            <table style='width:100%;font-size:14px;background:#FAF7F2;padding:14px;border-radius:8px;border-collapse:collapse;margin:16px 0;'>
                <tr><td style='padding:4px 8px;'><strong>Student:</strong></td><td>{$lesson['student_name']}</td></tr>
                <tr><td style='padding:4px 8px;'><strong>Teacher:</strong></td><td>{$lesson['teacher_name']}</td></tr>
                <tr><td style='padding:4px 8px;'><strong>Format:</strong></td><td>{$venueLabel}</td></tr>
                <tr><td style='padding:4px 8px;'><strong>Check-In:</strong></td><td>{$checkInTime}</td></tr>
                <tr><td style='padding:4px 8px;'><strong>Check-Out:</strong></td><td><strong style='color:#047857;'>{$checkOutTime}</strong></td></tr>
            </table>

            <h3 style='color:#4A0E17;border-left:4px solid #E5A93B;padding-left:12px;'>📝 Session Log</h3>

            <p><strong>Topics Covered:</strong><br>
            <span style='background:#f8f9fa;padding:10px;display:block;border-radius:6px;margin-top:6px;'>{$topics_covered}</span></p>

            <p><strong>Student Progress Notes:</strong><br>
            <span style='background:#f8f9fa;padding:10px;display:block;border-radius:6px;margin-top:6px;'>{$progress_notes}</span></p>

            <p><strong>Homework &amp; Assignments:</strong><br>
            <span style='background:#f8f9fa;padding:10px;display:block;border-radius:6px;margin-top:6px;'>{$homework_assigned}</span></p>

            <p style='margin-top:20px;color:#6C757D;font-size:13px;'>Thank you for trusting Sanity Homebased Tuition Academy with your child's education.</p>
        ";

        sendMail($lesson['parent_email'], $parentSubject, $parentBody, MAIL_INFO_FROM, MAIL_SCHOOL_NAME . ' — Reports', true);

        // Dispatch Check-Out Admin System Notification
        try {
            $notifMsg = "Teacher " . $lesson['teacher_name'] . " completed the session with student " . $lesson['student_name'] . " at " . $checkOutTime . ".\n";
            $notifMsg .= "Checked in: " . $checkInTime . " | Checked out: " . $checkOutTime . "\n\n";
            $notifMsg .= "Topics Covered: " . $topics_covered . "\n";
            $notifMsg .= "Progress Notes: " . $progress_notes . "\n";
            $notifMsg .= "Homework: " . $homework_assigned;

            $notifStmt = $pdo->prepare("INSERT INTO system_notifications (sender_name, recipient_role, title, message) VALUES (?, 'admin', ?, ?)");
            $notifStmt->execute([
                $lesson['teacher_name'],
                "Lesson Completed: " . $lesson['student_name'],
                $notifMsg
            ]);

            // Dispatch Check-Out Parent System Notification
            $notifStmt = $pdo->prepare("INSERT INTO system_notifications (sender_name, recipient_role, title, message) VALUES (?, 'parent', ?, ?)");
            $notifStmt->execute([
                $lesson['teacher_name'],
                "Lesson Completed: " . $lesson['student_name'],
                $notifMsg
            ]);
        } catch (\Exception $notifEx) {
            // Ignore
        }

        echo json_encode([
            'status'         => 'success',
            'message'        => "✅ Session closed. Check-out locked at {$checkOutTime}. Full session report emailed to parent and all admin addresses.",
            'check_out_time' => $checkOutTime
        ]);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
?>
