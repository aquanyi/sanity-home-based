<?php
/**
 * Module 6: Grade Reporting, Admin Moderation & Escalation Nudges
 * api_manage_reports.php
 * 
 * Handles: Teacher report compilation, Admin review queue,
 *          absolute override editing, release authorization,
 *          automated deadline nudges, and parent email notifications.
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
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Role authorization checks
$admin_actions = ['create_admin_report', 'approve_report', 'send_nudge', 'auto_nudge'];
$teacher_actions = ['submit_report', 'edit_report', 'accumulate_daily_reports'];

if (in_array($action, $admin_actions)) {
    if ($role !== 'admin' && $role !== 'timetabler') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Access denied. Admin role required.']);
        exit;
    }
} elseif (in_array($action, $teacher_actions)) {
    if (!in_array($role, ['admin', 'teacher'])) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Access denied. Admin or Teacher role required.']);
        exit;
    }
} else {
    // Read actions (student_exam_report, student_term_report, etc.) require standard active user session
    if (!in_array($role, ['admin', 'teacher', 'parent', 'student', 'timetabler', 'accounts'])) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Access denied. Invalid portal role.']);
        exit;
    }
}

// Release session lock early since subsequent operations are database queries
session_write_close();

// ─────────────────────────────────────────────────
// TEACHER: Submit a report for Admin review
// ─────────────────────────────────────────────────
if ($action === 'submit_report') {
    $student_id   = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    $teacher_id   = filter_input(INPUT_POST, 'teacher_id', FILTER_VALIDATE_INT);
    $report_type  = trim($_POST['report_type'] ?? '');
    $period       = trim($_POST['period_identifier'] ?? '');
    $topics       = trim($_POST['topics_covered'] ?? '');
    $performance  = trim($_POST['student_performance_notes'] ?? '');
    $recs         = trim($_POST['teacher_recommendations'] ?? '');

    if (!$student_id || !$teacher_id || !$report_type || !$period || !$topics || !$performance || !$recs) {
        echo json_encode(['status' => 'error', 'message' => 'All report fields are required.']); exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO academic_reports (student_id, teacher_id, report_type, period_identifier, topics_covered, student_performance_notes, teacher_recommendations, status)
            VALUES (?,?,?,?,?,?,?,'pending')
        ");
        $stmt->execute([$student_id, $teacher_id, $report_type, $period, $topics, $performance, $recs]);
        echo json_encode(['status' => 'success', 'message' => 'Report submitted for Admin moderation. It is currently hidden from parents/students.', 'report_id' => $pdo->lastInsertId()]);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────
// ADMIN: Edit report (Absolute Override)
// ─────────────────────────────────────────────────
if ($action === 'edit_report') {
    $report_id   = filter_input(INPUT_POST, 'report_id', FILTER_VALIDATE_INT);
    $topics      = trim($_POST['topics_covered'] ?? '');
    $performance = trim($_POST['student_performance_notes'] ?? '');
    $recs        = trim($_POST['teacher_recommendations'] ?? '');

    if (!$report_id || !$topics || !$performance || !$recs) {
        echo json_encode(['status' => 'error', 'message' => 'report_id and all content fields required.']); exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE academic_reports SET topics_covered=?, student_performance_notes=?, teacher_recommendations=? WHERE id=?");
        $stmt->execute([$topics, $performance, $recs, $report_id]);
        echo json_encode(['status' => 'success', 'message' => 'Report updated. Admin override applied.']);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────
// ADMIN: Create, Approve & Release report directly
// ─────────────────────────────────────────────────
if ($action === 'create_admin_report') {
    $student_id   = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    $teacher_id   = filter_input(INPUT_POST, 'teacher_id', FILTER_VALIDATE_INT);
    $report_type  = trim($_POST['report_type'] ?? '');
    $period       = trim($_POST['period_identifier'] ?? '');
    $topics       = trim($_POST['topics_covered'] ?? '');
    $performance  = trim($_POST['student_performance_notes'] ?? '');
    $recs         = trim($_POST['teacher_recommendations'] ?? '');
    $admin_id     = filter_input(INPUT_POST, 'admin_id', FILTER_VALIDATE_INT);

    if (!$student_id || !$teacher_id || !$report_type || !$period || !$topics || !$performance || !$recs || !$admin_id) {
        echo json_encode(['status' => 'error', 'message' => 'All fields including admin_id are required.']); exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO academic_reports (student_id, teacher_id, report_type, period_identifier, topics_covered, student_performance_notes, teacher_recommendations, status, approved_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'approved', ?)
        ");
        $stmt->execute([$student_id, $teacher_id, $report_type, $period, $topics, $performance, $recs, $admin_id]);
        $report_id = $pdo->lastInsertId();

        // Fetch parent details to send notification email
        $pStmt = $pdo->prepare("
            SELECT u_student.name as student_name, u_parent.name as parent_name, u_parent.email as parent_email
            FROM student_profiles sp
            JOIN students u_student ON sp.user_id = u_student.id
            JOIN parents u_parent ON sp.parent_id = u_parent.id
            WHERE sp.id = ?
        ");
        $pStmt->execute([$student_id]);
        $info = $pStmt->fetch();

        if ($info && !empty($info['parent_email'])) {
            $to      = $info['parent_email'];
            $subject = "📊 Academic Report Released – {$info['student_name']}";
            $body    = "
                <p>Dear <strong>{$info['parent_name']}</strong>,</p>
                <p>An official <strong>{$report_type} academic report</strong> for <strong>{$info['student_name']}</strong> (Period: {$period}) has been accumulated, reviewed, and released by the Administration.</p>
                
                <h3 style='color:#4A0E17;border-left:4px solid #E5A93B;padding-left:10px;'>📘 Report Highlights</h3>
                <p><strong>Topics Covered:</strong><br>" . nl2br(htmlspecialchars($topics)) . "</p>
                <p><strong>Student Performance:</strong><br>" . nl2br(htmlspecialchars($performance)) . "</p>
                <p><strong>Teacher Recommendations:</strong><br>" . nl2br(htmlspecialchars($recs)) . "</p>
                
                <p style='margin-top:20px;'>Please log into your Parent Portal to view the full formatted report card and academic trends.</p>
            ";
            sendMail($to, $subject, $body, MAIL_INFO_FROM, MAIL_SCHOOL_NAME . ' — Reports', true);
        }

        echo json_encode(['status' => 'success', 'message' => '✅ Accumulated report generated and released to parent successfully.', 'report_id' => $report_id]);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────
// ADMIN: Approve & Release report to parent
// ─────────────────────────────────────────────────
if ($action === 'approve_report') {
    $report_id  = filter_input(INPUT_POST, 'report_id', FILTER_VALIDATE_INT);
    $admin_id   = filter_input(INPUT_POST, 'admin_id', FILTER_VALIDATE_INT);

    if (!$report_id || !$admin_id) {
        echo json_encode(['status' => 'error', 'message' => 'report_id and admin_id required.']); exit;
    }

    try {
        // Fetch report + parent details
        $stmt = $pdo->prepare("
            SELECT ar.*, sp.grade_level,
                   u_student.name as student_name,
                   u_teacher.name as teacher_name,
                   u_parent.name as parent_name, u_parent.email as parent_email
            FROM academic_reports ar
            JOIN student_profiles sp ON ar.student_id = sp.id
            JOIN students u_student ON sp.user_id = u_student.id
            JOIN teachers u_teacher ON ar.teacher_id = u_teacher.id
            JOIN parents u_parent ON sp.parent_id = u_parent.id
            WHERE ar.id = ? AND ar.status = 'pending'
        ");
        $stmt->execute([$report_id]);
        $report = $stmt->fetch();

        if (!$report) {
            echo json_encode(['status' => 'error', 'message' => 'Report not found or already released.']); exit;
        }

        // Update status to approved
        $update = $pdo->prepare("UPDATE academic_reports SET status='approved', approved_by=? WHERE id=?");
        $update->execute([$admin_id, $report_id]);

        // Fire notification email to parent
        $to      = $report['parent_email'];
        $subject = "📊 Academic Report Released – {$report['student_name']}";
        $body    = "
            <p>Dear <strong>{$report['parent_name']}</strong>,</p>
            <p>The <strong>{$report['report_type']} academic report</strong> for <strong>{$report['student_name']}</strong> (Period: {$report['period_identifier']}) has been officially reviewed and released by the Administration.</p>
            
            <h3 style='color:#4A0E17;border-left:4px solid #E5A93B;padding-left:10px;'>📘 Report Highlights</h3>
            <p><strong>Topics Covered:</strong><br>{$report['topics_covered']}</p>
            <p><strong>Student Performance:</strong><br>{$report['student_performance_notes']}</p>
            <p><strong>Teacher Recommendations:</strong><br>{$report['teacher_recommendations']}</p>
            
            <p style='margin-top:20px;'>Please log into your Parent Portal to view the full formatted report card and academic trends.</p>
        ";
        sendMail($to, $subject, $body, MAIL_INFO_FROM, MAIL_SCHOOL_NAME . ' — Reports', true);

        echo json_encode(['status' => 'success', 'message' => "✅ Report approved and released. Parent ({$report['parent_email']}) has been notified.","report_released_for" => $report['student_name']]);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────
// ADMIN: Manual grading nudge — send reminder to teacher
// ─────────────────────────────────────────────────
if ($action === 'send_nudge') {
    $exam_session_id = filter_input(INPUT_POST, 'exam_session_id', FILTER_VALIDATE_INT);
    if (!$exam_session_id) { echo json_encode(['status' => 'error', 'message' => 'exam_session_id required.']); exit; }

    try {
        $stmt = $pdo->prepare("
            SELECT es.subject, es.exam_date, se.submission_deadline, se.exam_name,
                   u.name as teacher_name, u.email as teacher_email
            FROM exam_sessions es
            JOIN school_exams se ON es.exam_id = se.id
            JOIN teachers u ON es.invigilator_teacher_id = u.id
            WHERE es.id = ?
        ");
        $stmt->execute([$exam_session_id]);
        $session = $stmt->fetch();

        if (!$session) { echo json_encode(['status' => 'error', 'message' => 'Exam session not found.']); exit; }

        $to      = $session['teacher_email'];
        $subject = "🚨 URGENT: Grading Deadline Reminder – {$session['subject']}";
        $body    = "
            <p>Dear <strong>{$session['teacher_name']}</strong>,</p>
            <p>This is a reminder from the Administration of <strong>Sanity Homebased Tuition Academy</strong>.</p>
            <p>The grading deadline for the <strong>{$session['exam_name']} – {$session['subject']}</strong> exam session (held on {$session['exam_date']}) is approaching or has passed.</p>
            <p><strong>Grading Submission Deadline: <span style='color:#E74C3C;'>{$session['submission_deadline']}</span></strong></p>
            <p>Please log into the Teacher Portal and submit all pending marks immediately to ensure final reports are generated on schedule.</p>
        ";
        sendMail($to, $subject, $body, MAIL_ADMIN_FROM, MAIL_SCHOOL_NAME . ' — Administration', true);

        echo json_encode(['status' => 'success', 'message' => "🚨 Grading nudge dispatched to {$session['teacher_name']} ({$session['teacher_email']})."]);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────
// CRON-STYLE: Auto scan for overdue grading deadlines
// ─────────────────────────────────────────────────
if ($action === 'auto_nudge') {
    try {
        $stmt = $pdo->query("
            SELECT es.id as session_id, es.subject, se.exam_name, se.submission_deadline,
                   u.name as teacher_name, u.email as teacher_email
            FROM exam_sessions es
            JOIN school_exams se ON es.exam_id = se.id
            JOIN teachers u ON es.invigilator_teacher_id = u.id
            WHERE se.submission_deadline < NOW()
              AND se.automated_alerts_enabled = 1
              AND (SELECT COUNT(*) FROM exam_results er WHERE er.exam_session_id = es.id AND er.is_published = 0) = 0
        ");
        $overdue = $stmt->fetchAll();

        $notified = 0;
        foreach ($overdue as $session) {
            $to      = $session['teacher_email'];
            $subject = "🚨 AUTO-ALERT: Overdue Grading — {$session['subject']}";
            $body    = "
                <p>Dear <strong>{$session['teacher_name']}</strong>,</p>
                <p><strong>AUTOMATED SYSTEM ALERT:</strong> Marks for <strong>{$session['exam_name']} – {$session['subject']}</strong> are currently overdue.</p>
                <p>The submission deadline was: <strong>{$session['submission_deadline']}</strong>. Please log into your portal and enter the results immediately.</p>
            ";
            sendMail($to, $subject, $body, MAIL_ADMIN_FROM, MAIL_SCHOOL_NAME . ' — Automated Alert', true);
            $notified++;
        }

        echo json_encode(['status' => 'success', 'message' => "Auto-nudge scan complete. {$notified} overdue teacher(s) notified."]);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────
// ADMIN: Accumulate daily reports in a period range
// ─────────────────────────────────────────────────
if ($action === 'accumulate_daily_reports') {
    $student_id = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    $teacher_id = filter_input(INPUT_POST, 'teacher_id', FILTER_VALIDATE_INT); // 0 or empty for 'all'
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date   = trim($_POST['end_date'] ?? '');

    if (!$student_id || !$start_date || !$end_date) {
        echo json_encode(['status' => 'error', 'message' => 'student_id, start_date, and end_date are required.']); exit;
    }

    try {
        // Query completed lessons in the range
        $sql = "
            SELECT l.lesson_date, l.topics_covered, l.progress_notes, l.homework_assigned,
                   u_teacher.name as teacher_name, u_teacher.id as teacher_id
            FROM lessons l
            JOIN timetable_slots ts ON l.slot_id = ts.id
            JOIN teachers u_teacher ON ts.teacher_id = u_teacher.id
            WHERE ts.student_id = ? AND l.session_status = 'completed'
              AND l.lesson_date BETWEEN ? AND ?
        ";
        
        $params = [$student_id, $start_date, $end_date];
        if ($teacher_id > 0) {
            $sql .= " AND ts.teacher_id = ?";
            $params[] = $teacher_id;
        }
        $sql .= " ORDER BY l.lesson_date ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $lessons = $stmt->fetchAll();

        if (empty($lessons)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'No completed lessons found for this student/period range. Make sure daily check-ins/check-outs are completed.'
            ]);
            exit;
        }

        // Accumulate texts
        $topics = [];
        $performance = [];
        $recs = [];
        $last_teacher_id = $lessons[0]['teacher_id']; // fallback teacher if 'all' chosen

        foreach ($lessons as $les) {
            $dateFmt = date('d-M-Y', strtotime($les['lesson_date']));
            
            if ($les['topics_covered']) {
                $topics[] = "[{$dateFmt} by {$les['teacher_name']}]: " . trim($les['topics_covered']);
            }
            if ($les['progress_notes']) {
                $performance[] = "[{$dateFmt} by {$les['teacher_name']}]: " . trim($les['progress_notes']);
            }
            if ($les['homework_assigned']) {
                $recs[] = "[{$dateFmt} by {$les['teacher_name']}]: Assigned: " . trim($les['homework_assigned']);
            }
        }

        $topicsText = !empty($topics) ? implode("\n\n", $topics) : "No specific topics logged.";
        $performanceText = !empty($performance) ? implode("\n\n", $performance) : "No performance notes logged.";
        $recsText = !empty($recs) ? "Based on sessions in this period, continue supporting with:\n" . implode("\n", $recs) : "No recommendations logged.";

        echo json_encode([
            'status' => 'success',
            'data' => [
                'topics_covered' => $topicsText,
                'student_performance_notes' => $performanceText,
                'teacher_recommendations' => $recsText,
                'teacher_id' => ($teacher_id > 0) ? $teacher_id : $last_teacher_id
            ]
        ]);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────
// GET: Fetch all reports for Admin moderation queue
// ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $getAction = $_GET['action'] ?? '';

    // ── Admin: print-ready exam result slip for one student ──
    if ($getAction === 'student_exam_report') {
        $exam_id    = filter_input(INPUT_GET, 'exam_id', FILTER_VALIDATE_INT);
        $student_id = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
        if (!$exam_id || !$student_id) {
            echo json_encode(['status' => 'error', 'message' => 'exam_id and student_id required.']); exit;
        }
        try {
            // Exam info
            $examStmt = $pdo->prepare("SELECT * FROM school_exams WHERE id = ?");
            $examStmt->execute([$exam_id]);
            $exam = $examStmt->fetch();
            if (!$exam) { echo json_encode(['status' => 'error', 'message' => 'Exam not found.']); exit; }

            // Student info
            $stuStmt = $pdo->prepare("
                SELECT u.name as student_name, sp.grade_level, sp.curriculum_id, u_p.name as parent_name
                FROM student_profiles sp
                JOIN students u ON sp.user_id = u.id
                JOIN parents u_p ON sp.parent_id = u_p.id
                WHERE sp.id = ?
            ");
            $stuStmt->execute([$student_id]);
            $student = $stuStmt->fetch();
            if (!$student) { echo json_encode(['status' => 'error', 'message' => 'Student not found.']); exit; }

            // All results for this student in this exam
            $rStmt = $pdo->prepare("
                SELECT er.marks_obtained, er.teacher_remarks, er.is_published,
                       es.subject, es.exam_date,
                       u_t.name as teacher_name
                FROM exam_results er
                JOIN exam_sessions es ON er.exam_session_id = es.id
                JOIN teachers u_t ON es.invigilator_teacher_id = u_t.id
                WHERE es.exam_id = ? AND er.student_id = ?
                ORDER BY es.subject ASC
            ");
            $rStmt->execute([$exam_id, $student_id]);
            $subjectResults = $rStmt->fetchAll();

            // Grading scales
            $scales = $pdo->query("SELECT * FROM grading_scales ORDER BY grade_level ASC, min_mark DESC")->fetchAll();
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

            $total = 0; $count = 0;
            $allRemarks = [];
            foreach ($subjectResults as &$r) {
                $grade = $getGrade((float)$r['marks_obtained'], $student['grade_level'], $student['curriculum_id']);
                $r['grade_letter'] = $grade['letter'];
                $r['grade_remark'] = $grade['remark'];
                $total += (float)$r['marks_obtained'];
                $count++;
                if (!empty($r['teacher_remarks'])) $allRemarks[] = $r['subject'] . ': ' . $r['teacher_remarks'];
            }
            $avg = $count > 0 ? round($total / $count, 2) : 0;
            $overall = $getGrade($avg, $student['grade_level'], $student['curriculum_id']);
            $summarizedRemark = !empty($allRemarks)
                ? implode(' | ', $allRemarks)
                : ($overall['remark'] ?: 'No remarks provided.');

            echo json_encode([
                'status'            => 'success',
                'exam'              => $exam,
                'student'           => $student,
                'subjects'          => $subjectResults,
                'total'             => round($total, 2),
                'average'           => $avg,
                'overall_grade'     => $overall['letter'],
                'overall_remark'    => $overall['remark'],
                'summarized_remark' => $summarizedRemark,
            ]);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── Admin: print-ready term progress report for one student ──
    if ($getAction === 'student_term_report') {
        $student_id = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
        $academic_year = trim($_GET['academic_year'] ?? '');
        $term_identifier = trim($_GET['term_identifier'] ?? '');
        
        if (!$student_id || !$academic_year || !$term_identifier) {
            echo json_encode(['status' => 'error', 'message' => 'student_id, academic_year, and term_identifier are required.']);
            exit;
        }
        
        try {
            // 1. Fetch student info
            $stuStmt = $pdo->prepare("
                SELECT u.name as student_name, sp.grade_level, sp.curriculum_id, sp.study_type, u_p.name as parent_name, c.name as curriculum_name
                FROM student_profiles sp
                JOIN students u ON sp.user_id = u.id
                JOIN parents u_p ON sp.parent_id = u_p.id
                LEFT JOIN curriculums c ON sp.curriculum_id = c.id
                WHERE sp.id = ?
            ");
            $stuStmt->execute([$student_id]);
            $student = $stuStmt->fetch();
            if (!$student) {
                echo json_encode(['status' => 'error', 'message' => 'Student not found.']);
                exit;
            }
            
            // 2. Fetch all exams in this term
            $examsStmt = $pdo->prepare("
                SELECT id, exam_name, academic_year, term_identifier 
                FROM school_exams 
                WHERE academic_year = ? AND term_identifier = ? 
                ORDER BY created_at ASC
            ");
            $examsStmt->execute([$academic_year, $term_identifier]);
            $exams = $examsStmt->fetchAll();
            
            if (empty($exams)) {
                echo json_encode(['status' => 'error', 'message' => 'No exams found for the selected term and year.']);
                exit;
            }
            
            $examIds = array_column($exams, 'id');
            $inClause = implode(',', array_fill(0, count($examIds), '?'));
            
            // 3. Fetch results for those exams for this student
            $rStmt = $pdo->prepare("
                SELECT er.*, es.subject, se.id as exam_id, se.exam_name, u_t.name as teacher_name
                FROM exam_results er
                JOIN exam_sessions es ON er.exam_session_id = es.id
                JOIN school_exams se ON es.exam_id = se.id
                JOIN teachers u_t ON es.invigilator_teacher_id = u_t.id
                WHERE er.student_id = ? AND se.id IN ($inClause)
                ORDER BY se.created_at ASC, es.subject ASC
            ");
            $rStmt->execute(array_merge([$student_id], $examIds));
            $results = $rStmt->fetchAll();
            
            // 4. Fetch grading scales to compute points and grades
            $scales = $pdo->query("SELECT * FROM grading_scales ORDER BY grade_level ASC, min_mark DESC")->fetchAll();
            $getGradeAndPts = function($marks, $grade_level, $curriculum_id) use ($scales) {
                // Try curriculum-specific scale first
                foreach ($scales as $s) {
                    if ($s['curriculum_id'] !== null && (int)$s['curriculum_id'] === (int)$curriculum_id) {
                        if (strtolower(trim($s['grade_level'])) === 'all' || strtolower(trim($s['grade_level'])) === strtolower(trim($grade_level))) {
                            if ($marks >= $s['min_mark'] && $marks <= $s['max_mark']) {
                                $points = 0;
                                $letter = strtoupper(trim($s['letter_grade']));
                                if ($letter === 'EE1') $points = 8;
                                elseif ($letter === 'EE2') $points = 7;
                                elseif ($letter === 'ME1') $points = 6;
                                elseif ($letter === 'ME2') $points = 5;
                                elseif ($letter === 'AE2') $points = 4;
                                elseif ($letter === 'AE1') $points = 3;
                                elseif ($letter === 'BE2') $points = 2;
                                elseif ($letter === 'BE1') $points = 1;
                                elseif ($letter === 'A') $points = 12;
                                elseif ($letter === 'A-') $points = 11;
                                elseif ($letter === 'B+') $points = 10;
                                elseif ($letter === 'B') $points = 9;
                                elseif ($letter === 'B-') $points = 8;
                                elseif ($letter === 'C+') $points = 7;
                                elseif ($letter === 'C') $points = 6;
                                elseif ($letter === 'C-') $points = 5;
                                elseif ($letter === 'D+') $points = 4;
                                elseif ($letter === 'D') $points = 3;
                                elseif ($letter === 'D-') $points = 2;
                                elseif ($letter === 'E') $points = 1;
                                elseif ($letter === 'OUTSTANDING') $points = 7;
                                elseif ($letter === 'EXCELLENT') $points = 6;
                                elseif ($letter === 'HIGH') $points = 5;
                                elseif ($letter === 'GOOD') $points = 4;
                                elseif ($letter === 'ASPIRING') $points = 3;
                                elseif ($letter === 'BASIC') $points = 2;
                                elseif ($letter === 'UNGRADED') $points = 1;
                                
                                return ['letter' => $s['letter_grade'], 'remark' => $s['remarks_template'] ?? '', 'points' => $points];
                            }
                        }
                    }
                }
                // Fallback to default scale
                foreach ($scales as $s) {
                    if ($s['curriculum_id'] === null) {
                        if (strtolower(trim($s['grade_level'])) === 'all' || strtolower(trim($s['grade_level'])) === strtolower(trim($grade_level))) {
                            if ($marks >= $s['min_mark'] && $marks <= $s['max_mark']) {
                                $points = 0;
                                $letter = strtoupper(trim($s['letter_grade']));
                                if ($letter === 'A') $points = 12;
                                elseif ($letter === 'A-') $points = 11;
                                elseif ($letter === 'B+') $points = 10;
                                elseif ($letter === 'B') $points = 9;
                                elseif ($letter === 'B-') $points = 8;
                                elseif ($letter === 'C+') $points = 7;
                                elseif ($letter === 'C') $points = 6;
                                elseif ($letter === 'C-') $points = 5;
                                elseif ($letter === 'D+') $points = 4;
                                elseif ($letter === 'D') $points = 3;
                                elseif ($letter === 'D-') $points = 2;
                                elseif ($letter === 'E') $points = 1;
                                return ['letter' => $s['letter_grade'], 'remark' => $s['remarks_template'] ?? '', 'points' => $points];
                            }
                        }
                    }
                }
                return ['letter' => '–', 'remark' => '', 'points' => 0];
            };
            
            // 5. Structure data by subject
            $subjectData = [];
            foreach ($results as $r) {
                $subj = $r['subject'];
                if (!isset($subjectData[$subj])) {
                    $subjectData[$subj] = [
                        'subject' => $subj,
                        'scores' => array_fill(0, count($exams), null), // index => score
                        'remarks' => '',
                        'teacher' => $r['teacher_name']
                    ];
                }
                // Find exam index
                $examIdx = array_search($r['exam_id'], $examIds);
                if ($examIdx !== false) {
                    $subjectData[$subj]['scores'][$examIdx] = (float)$r['marks_obtained'];
                }
                if (!empty($r['teacher_remarks'])) {
                    $subjectData[$subj]['remarks'] = $r['teacher_remarks'];
                }
            }
            
            // Calculate Dev, Pts, Grade, Comments for each subject
            $finalSubjects = [];
            foreach ($subjectData as $subj => $data) {
                $scoresList = $data['scores'];
                
                // Calculate Dev
                $dev = '—';
                $validScores = array_values(array_filter($scoresList, function($v) { return $v !== null; }));
                $numScores = count($validScores);
                if ($numScores >= 2) {
                    $diff = $validScores[$numScores - 1] - $validScores[$numScores - 2];
                    $dev = ($diff >= 0 ? '+' : '') . round($diff, 1);
                }
                
                // Last exam score
                $lastScore = $numScores > 0 ? $validScores[$numScores - 1] : 0;
                
                // Grade & Points from last score
                $gradeInfo = $getGradeAndPts($lastScore, $student['grade_level'], $student['curriculum_id']);
                
                // Average score
                $avgScore = $numScores > 0 ? array_sum($validScores) / $numScores : 0;
                
                $finalSubjects[] = [
                    'subject' => $subj,
                    'scores' => $scoresList,
                    'dev' => $dev,
                    'points' => $gradeInfo['points'],
                    'grade' => $gradeInfo['letter'],
                    'comments' => $gradeInfo['remark'] ?: ($data['remarks'] ?: 'Good progress'),
                    'teacher' => $data['teacher'],
                    'average_score' => round($avgScore, 2)
                ];
            }
            
            // 6. Calculate averages for columns
            $columnAverages = [];
            foreach ($exams as $idx => $ex) {
                $sum = 0; $cnt = 0;
                foreach ($finalSubjects as $fs) {
                    if ($fs['scores'][$idx] !== null) {
                        $sum += $fs['scores'][$idx];
                        $cnt++;
                    }
                }
                $columnAverages[] = $cnt > 0 ? round($sum / $cnt, 1) : null;
            }
            
            // Calculate overall values
            $overallScores = array_values(array_filter($columnAverages, function($v) { return $v !== null; }));
            $numOverall = count($overallScores);
            $overallDev = '—';
            if ($numOverall >= 2) {
                $diff = $overallScores[$numOverall - 1] - $overallScores[$numOverall - 2];
                $overallDev = ($diff >= 0 ? '+' : '') . round($diff, 1);
            }
            
            $overallMeanScore = $numOverall > 0 ? $overallScores[$numOverall - 1] : 0;
            $overallGradeInfo = $getGradeAndPts($overallMeanScore, $student['grade_level'], $student['curriculum_id']);
            
            $totalPoints = 0; $validPtsCount = 0;
            foreach ($finalSubjects as $fs) {
                if ($fs['points'] > 0) {
                    $totalPoints += $fs['points'];
                    $validPtsCount++;
                }
            }
            $meanPoints = $validPtsCount > 0 ? round($totalPoints / $validPtsCount, 2) : 0;
            
            echo json_encode([
                'status' => 'success',
                'student' => $student,
                'exams' => $exams,
                'subjects' => $finalSubjects,
                'column_averages' => $columnAverages,
                'overall_dev' => $overallDev,
                'overall_mean_score' => $overallMeanScore,
                'overall_grade' => $overallGradeInfo['letter'],
                'overall_comment' => $overallGradeInfo['remark'] ?: 'Good trial. Focus more.',
                'mean_points' => $meanPoints
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── Default: reports moderation queue ──
    try {
        $pending = $pdo->query("
            SELECT ar.*, sp.grade_level,
                   u_student.name as student_name,
                   u_teacher.name as teacher_name,
                   u_parent.name as parent_name
            FROM academic_reports ar
            JOIN student_profiles sp ON ar.student_id = sp.id
            JOIN students u_student ON sp.user_id = u_student.id
            JOIN teachers u_teacher ON ar.teacher_id = u_teacher.id
            JOIN parents u_parent ON sp.parent_id = u_parent.id
            ORDER BY ar.status ASC, ar.created_at DESC
        ")->fetchAll();

        // Overdue exam sessions (deadline passed, no marks submitted)
        $overdue = $pdo->query("
            SELECT es.id, es.subject, se.exam_name, se.submission_deadline,
                   u.name as teacher_name, u.email as teacher_email,
                   (SELECT COUNT(*) FROM exam_results er WHERE er.exam_session_id = es.id) as marks_submitted
            FROM exam_sessions es
            JOIN school_exams se ON es.exam_id = se.id
            JOIN teachers u ON es.invigilator_teacher_id = u.id
            WHERE se.submission_deadline < NOW()
        ")->fetchAll();

        echo json_encode(['status' => 'success', 'reports' => $pending, 'overdue_sessions' => $overdue]);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
?>
