<?php
/**
 * api_accounts.php
 * Accounts Hub API — session tracking, monthly summaries, student pricing.
 * Access restricted to admin and accounts roles.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../security.php';
start_secure_session();
require_once __DIR__ . '/../db_connect.php';
// mail_helper.php intentionally not loaded here — no emails sent by this API

// Auth guard
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated.']); exit;
}
if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'accounts'])) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied.']); exit;
}
session_write_close(); // Release session lock early

// ─────────────────────────────────────────────
// GET REQUESTS
// ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    // ── Completed sessions + monthly summary ──
    if ($action === 'completed_sessions') {
        try {
            // Join timetable_slots (for schedule) with lessons (for confirmed sessions)
            // Pick parent fee from student_pricing table and teacher pay from teacher_pricing table.
            $stmt = $pdo->query("
                SELECT 
                    l.id,
                    l.lesson_date,
                    l.check_in_time,
                    l.check_out_time,
                    ts.venue_type,
                    u_student.name AS student_name,
                    u_teacher.name  AS teacher_name,
                    COALESCE(
                        CASE WHEN ts.venue_type = 'home_visit' THEN sp_price.price_home_visit
                             WHEN ts.venue_type = 'school' THEN sp_price.price_school
                             WHEN ts.venue_type = 'online_meet' THEN sp_price.price_online_meet
                             WHEN ts.venue_type = 'online_zoom' THEN sp_price.price_online_zoom
                             ELSE 0 END,
                        0
                    ) AS price,
                    COALESCE(
                        CASE WHEN ts.venue_type = 'home_visit' THEN tp_price.pay_home_visit
                             WHEN ts.venue_type = 'school' THEN tp_price.pay_school
                             WHEN ts.venue_type = 'online_meet' THEN tp_price.pay_online_meet
                             WHEN ts.venue_type = 'online_zoom' THEN tp_price.pay_online_zoom
                             ELSE 0 END,
                        0
                    ) AS teacher_pay
                FROM lessons l
                JOIN timetable_slots ts ON l.slot_id = ts.id
                JOIN student_profiles sp ON ts.student_id = sp.id
                JOIN students u_student ON sp.user_id = u_student.id
                JOIN teachers u_teacher ON ts.teacher_id = u_teacher.id
                LEFT JOIN student_pricing sp_price ON sp_price.student_id = sp.id
                LEFT JOIN teacher_pricing tp_price ON tp_price.teacher_id = ts.teacher_id
                WHERE l.session_status = 'completed'
                ORDER BY l.lesson_date DESC
            ");
            $sessions = $stmt->fetchAll();

            // Build monthly summary
            $monthly = [];
            foreach ($sessions as $s) {
                $month = date('F Y', strtotime($s['lesson_date']));
                if (!isset($monthly[$month])) {
                    $monthly[$month] = [
                        'month' => $month, 
                        'total_sessions' => 0, 
                        'online_meet_count' => 0, 
                        'online_zoom_count' => 0, 
                        'school_count' => 0, 
                        'home_visit_count' => 0, 
                        'total_revenue' => 0,
                        'total_teacher_payout' => 0
                    ];
                }
                $monthly[$month]['total_sessions']++;
                if ($s['venue_type'] === 'online_meet') $monthly[$month]['online_meet_count']++;
                elseif ($s['venue_type'] === 'online_zoom') $monthly[$month]['online_zoom_count']++;
                elseif ($s['venue_type'] === 'school') $monthly[$month]['school_count']++;
                else $monthly[$month]['home_visit_count']++;
                
                $monthly[$month]['total_revenue'] += floatval($s['price']);
                $monthly[$month]['total_teacher_payout'] += floatval($s['teacher_pay']);
            }

            echo json_encode([
                'status'           => 'success',
                'sessions'         => $sessions,
                'monthly_summary'  => array_values($monthly)
            ]);
        } catch (\PDOException $e) {
            echo json_encode([
                'status'          => 'success',
                'sessions'        => [],
                'monthly_summary' => [],
                '_note'           => 'Database query error: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    // ── Students with pricing ──
    if ($action === 'students_pricing') {
        try {
            $stmt = $pdo->query("
                SELECT 
                    sp.id,
                    u_student.name  AS student_name,
                    u_student.email AS student_email,
                    u_parent.name   AS parent_name,
                    sp.grade_level,
                    COALESCE(spr.price_online_meet, 0) AS price_online_meet,
                    COALESCE(spr.price_online_zoom, 0) AS price_online_zoom,
                    COALESCE(spr.price_school, 0) AS price_school,
                    COALESCE(spr.price_home_visit, 0) AS price_home_visit
                FROM student_profiles sp
                JOIN students u_student ON sp.user_id    = u_student.id
                LEFT JOIN parents u_parent  ON sp.parent_id = u_parent.id
                LEFT JOIN student_pricing spr ON spr.student_id = sp.id
                ORDER BY u_student.name ASC
            ");
            $students = $stmt->fetchAll();
            echo json_encode(['status' => 'success', 'students' => $students]);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── Students list for dropdown ──
    if ($action === 'students_list') {
        try {
            $stmt = $pdo->query("
                SELECT sp.id, u.name AS student_name, sp.grade_level 
                FROM student_profiles sp 
                JOIN students u ON sp.user_id = u.id 
                ORDER BY u.name ASC
            ");
            echo json_encode(['status' => 'success', 'students' => $stmt->fetchAll()]);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── Teachers list for dropdown ──
    if ($action === 'teachers_list') {
        try {
            $stmt = $pdo->query("
                SELECT id, name FROM teachers ORDER BY name ASC
            ");
            echo json_encode(['status' => 'success', 'teachers' => $stmt->fetchAll()]);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── Get detailed invoice and balance ledger for a student ──
    if ($action === 'get_invoice') {
        $student_id = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
        $month      = $_GET['month'] ?? ''; // e.g. "July 2026"
        if (!$student_id) { echo json_encode(['status' => 'error', 'message' => 'student_id required']); exit; }

        try {
            // Get student info
            $stmt = $pdo->prepare("
                SELECT sp.id, u_student.name AS student_name, u_parent.name AS parent_name, u_parent.email AS parent_email 
                FROM student_profiles sp
                JOIN students u_student ON sp.user_id = u_student.id
                JOIN parents u_parent ON sp.parent_id = u_parent.id
                WHERE sp.id = ?
            ");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch();

            if (!$student) { echo json_encode(['status' => 'error', 'message' => 'Student not found']); exit; }

            // Fetch all completed lessons for this student
            $stmt = $pdo->prepare("
                SELECT l.id, l.lesson_date, ts.venue_type, u_teacher.name AS teacher_name,
                    COALESCE(
                        CASE WHEN ts.venue_type = 'home_visit' THEN sp_price.price_home_visit
                             WHEN ts.venue_type = 'school' THEN sp_price.price_school
                             WHEN ts.venue_type = 'online_meet' THEN sp_price.price_online_meet
                             WHEN ts.venue_type = 'online_zoom' THEN sp_price.price_online_zoom
                             ELSE 0 END,
                        0
                    ) AS price
                FROM lessons l
                JOIN timetable_slots ts ON l.slot_id = ts.id
                JOIN teachers u_teacher ON ts.teacher_id = u_teacher.id
                LEFT JOIN student_pricing sp_price ON sp_price.student_id = ts.student_id
                WHERE ts.student_id = ? AND l.session_status = 'completed'
                ORDER BY l.lesson_date DESC
            ");
            $stmt->execute([$student_id]);
            $all_lessons = $stmt->fetchAll();

            // Filter lessons by chosen month
            $billed_lessons = [];
            $total_billed_all_time = 0;
            $total_billed_month = 0;

            foreach ($all_lessons as $l) {
                $total_billed_all_time += floatval($l['price']);
                $lMonth = date('F Y', strtotime($l['lesson_date']));
                if (empty($month) || $lMonth === $month) {
                    $billed_lessons[] = $l;
                    $total_billed_month += floatval($l['price']);
                }
            }

            // Fetch payment records
            $payStmt = $pdo->prepare("SELECT * FROM student_payments WHERE student_id = ? ORDER BY payment_date DESC");
            $payStmt->execute([$student_id]);
            $payments = $payStmt->fetchAll();

            $total_paid = 0;
            foreach ($payments as $p) {
                $total_paid += floatval($p['amount']);
            }

            $outstanding_balance = $total_billed_all_time - $total_paid;

            echo json_encode([
                'status'              => 'success',
                'student'             => $student,
                'lessons'             => $billed_lessons,
                'payments'            => $payments,
                'total_billed_month'  => $total_billed_month,
                'total_billed_all'    => $total_billed_all_time,
                'total_paid'          => $total_paid,
                'balance'             => $outstanding_balance
            ]);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── Get payroll rates list ──
    if ($action === 'teachers_pricing') {
        try {
            $stmt = $pdo->query("
                SELECT 
                    u.id, u.name, u.email,
                    COALESCE(tp.pay_online_meet, 0) AS pay_online_meet,
                    COALESCE(tp.pay_online_zoom, 0) AS pay_online_zoom,
                    COALESCE(tp.pay_school, 0)      AS pay_school,
                    COALESCE(tp.pay_home_visit, 0)  AS pay_home_visit
                FROM teachers u
                LEFT JOIN teacher_pricing tp ON tp.teacher_id = u.id
                ORDER BY u.name ASC
            ");
            echo json_encode(['status' => 'success', 'teachers' => $stmt->fetchAll()]);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── Get teacher pay summary and disbursements ──
    if ($action === 'get_payroll') {
        $teacher_id = filter_input(INPUT_GET, 'teacher_id', FILTER_VALIDATE_INT);
        $month      = $_GET['month'] ?? ''; // e.g. "July 2026"
        if (!$teacher_id) { echo json_encode(['status' => 'error', 'message' => 'teacher_id required']); exit; }

        try {
            // Fetch teacher info
            $stmt = $pdo->prepare("SELECT id, name, email FROM teachers WHERE id = ?");
            $stmt->execute([$teacher_id]);
            $teacher = $stmt->fetch();
            if (!$teacher) { echo json_encode(['status' => 'error', 'message' => 'Teacher not found']); exit; }

            // Fetch completed lessons taught by this teacher (include check-in/out for audit)
            $stmt = $pdo->prepare("
                SELECT l.id, l.lesson_date, ts.venue_type, u_student.name AS student_name,
                    l.check_in_time, l.check_out_time, l.topics_covered,
                    ts.start_time AS slot_start, ts.end_time AS slot_end,
                    COALESCE(
                        CASE WHEN ts.venue_type = 'home_visit' THEN tp_price.pay_home_visit
                             WHEN ts.venue_type = 'school' THEN tp_price.pay_school
                             WHEN ts.venue_type = 'online_meet' THEN tp_price.pay_online_meet
                             WHEN ts.venue_type = 'online_zoom' THEN tp_price.pay_online_zoom
                             ELSE 0 END,
                        0
                    ) AS earnings
                FROM lessons l
                JOIN timetable_slots ts ON l.slot_id = ts.id
                JOIN student_profiles sp ON ts.student_id = sp.id
                JOIN students u_student ON sp.user_id = u_student.id
                LEFT JOIN teacher_pricing tp_price ON tp_price.teacher_id = ts.teacher_id
                WHERE ts.teacher_id = ? AND l.session_status = 'completed'
                ORDER BY l.lesson_date DESC
            ");
            $stmt->execute([$teacher_id]);
            $all_lessons = $stmt->fetchAll();

            $earnings_lessons = [];
            $total_earned_all_time = 0;
            $total_earned_month = 0;

            foreach ($all_lessons as $l) {
                $total_earned_all_time += floatval($l['earnings']);
                $lMonth = date('F Y', strtotime($l['lesson_date']));
                if (empty($month) || $lMonth === $month) {
                    $earnings_lessons[] = $l;
                    $total_earned_month += floatval($l['earnings']);
                }
            }

            // Fetch disbursements (payments to teacher)
            $payStmt = $pdo->prepare("SELECT * FROM teacher_payments WHERE teacher_id = ? ORDER BY payment_date DESC");
            $payStmt->execute([$teacher_id]);
            $payments = $payStmt->fetchAll();

            $total_disbursed = 0;
            foreach ($payments as $p) {
                $total_disbursed += floatval($p['amount']);
            }

            $outstanding_payroll = $total_earned_all_time - $total_disbursed;

            echo json_encode([
                'status'              => 'success',
                'teacher'             => $teacher,
                'lessons'             => $earnings_lessons,
                'disbursements'       => $payments,
                'total_earned_month'  => $total_earned_month,
                'total_earned_all'    => $total_earned_all_time,
                'total_disbursed'     => $total_disbursed,
                'balance'             => $outstanding_payroll
            ]);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── Get Extra Expenses by category ──
    if ($action === 'get_expenses') {
        $category = $_GET['category'] ?? '';
        $month    = $_GET['month'] ?? '';
        $valid_cats = ['inventory', 'utility', 'general_repairs', 'petty_cash'];
        if ($category && !in_array($category, $valid_cats)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid category.']); exit;
        }
        try {
            $sql = "
                SELECT e.*, COALESCE(u_admin.name, u_acc.name, 'Admin') AS recorded_by_name
                FROM extra_expenses e
                LEFT JOIN admins u_admin ON u_admin.id = e.recorded_by
                LEFT JOIN accounts_officers u_acc ON u_acc.id = e.recorded_by
                WHERE 1=1
            ";
            $params = [];
            if ($category) { $sql .= " AND e.category = ?"; $params[] = $category; }
            if ($month)    { $sql .= " AND DATE_FORMAT(e.expense_date, '%M %Y') = ?"; $params[] = $month; }
            $sql .= " ORDER BY e.expense_date DESC, e.id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $expenses = $stmt->fetchAll();

            // Totals per category
            $totals_stmt = $pdo->query("SELECT category, SUM(amount) AS total FROM extra_expenses GROUP BY category");
            $totals_raw  = $totals_stmt->fetchAll();
            $totals = [];
            foreach ($totals_raw as $t) { $totals[$t['category']] = floatval($t['total']); }

            echo json_encode(['status' => 'success', 'expenses' => $expenses, 'totals' => $totals]);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── Get a single expense record for editing ──
    if ($action === 'get_expense_single') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) { echo json_encode(['status' => 'error', 'message' => 'id required']); exit; }
        try {
            $stmt = $pdo->prepare("SELECT * FROM extra_expenses WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) { echo json_encode(['status' => 'error', 'message' => 'Record not found']); exit; }
            echo json_encode(['status' => 'success', 'expense' => $row]);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── Expense Reports — filterable by date range + category ──
    if ($action === 'expense_report') {
        $category  = $_GET['category']  ?? '';   // '' = all
        $from_date = $_GET['from_date'] ?? '';   // YYYY-MM-DD
        $to_date   = $_GET['to_date']   ?? '';   // YYYY-MM-DD
        $period    = $_GET['period']    ?? '';   // today|week|month|quarter|year|custom

        // Resolve quick period into actual dates
        $today = date('Y-m-d');
        switch ($period) {
            case 'today':
                $from_date = $today; $to_date = $today; break;
            case 'week':
                $from_date = date('Y-m-d', strtotime('monday this week'));
                $to_date   = date('Y-m-d', strtotime('sunday this week')); break;
            case 'month':
                $from_date = date('Y-m-01'); $to_date = date('Y-m-t'); break;
            case 'quarter':
                $qm = ceil((int)date('n') / 3) * 3;
                $from_date = date('Y-') . str_pad($qm - 2, 2, '0', STR_PAD_LEFT) . '-01';
                $to_date   = date('Y-m-t', mktime(0,0,0,$qm,1)); break;
            case 'year':
                $from_date = date('Y-01-01'); $to_date = date('Y-12-31'); break;
        }

        $valid_cats = ['inventory','utility','general_repairs','petty_cash'];

        try {
            // Build base query
            $sql = "
                SELECT e.id, e.category, e.item_name, e.description,
                       e.amount, e.expense_date, e.reference,
                       COALESCE(u_admin.name, u_acc.name, 'Admin') AS recorded_by_name
                FROM extra_expenses e
                LEFT JOIN admins u_admin ON u_admin.id = e.recorded_by LEFT JOIN accounts_officers u_acc ON u_acc.id = e.recorded_by
                WHERE 1=1
            ";
            $params = [];
            if ($category && in_array($category, $valid_cats)) {
                $sql .= " AND e.category = ?";
                $params[] = $category;
            }
            if ($from_date) { $sql .= " AND e.expense_date >= ?"; $params[] = $from_date; }
            if ($to_date)   { $sql .= " AND e.expense_date <= ?"; $params[] = $to_date; }
            $sql .= " ORDER BY e.expense_date ASC, e.id ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $expenses = $stmt->fetchAll();

            // Category totals from filtered data
            $cat_totals = ['inventory'=>0,'utility'=>0,'general_repairs'=>0,'petty_cash'=>0];
            $grand_total = 0;
            $daily = [];

            foreach ($expenses as $e) {
                $amt = floatval($e['amount']);
                $grand_total += $amt;
                if (isset($cat_totals[$e['category']])) $cat_totals[$e['category']] += $amt;
                $day = $e['expense_date'];
                if (!isset($daily[$day])) $daily[$day] = 0;
                $daily[$day] += $amt;
            }

            // Summary row counts
            $cat_counts = ['inventory'=>0,'utility'=>0,'general_repairs'=>0,'petty_cash'=>0];
            foreach ($expenses as $e) {
                if (isset($cat_counts[$e['category']])) $cat_counts[$e['category']]++;
            }

            echo json_encode([
                'status'      => 'success',
                'expenses'    => $expenses,
                'cat_totals'  => $cat_totals,
                'cat_counts'  => $cat_counts,
                'daily'       => $daily,
                'grand_total' => $grand_total,
                'from_date'   => $from_date,
                'to_date'     => $to_date,
                'count'       => count($expenses),
            ]);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── Full School Financial Report ──
    if ($action === 'financial_report') {
        $from_date  = $_GET['from_date']  ?? '';
        $to_date    = $_GET['to_date']    ?? '';
        $period     = $_GET['period']     ?? 'custom';
        $student_id = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT) ?: 0;
        $teacher_id = filter_input(INPUT_GET, 'teacher_id', FILTER_VALIDATE_INT) ?: 0;
        $venue_type = trim($_GET['venue_type'] ?? '');

        // Resolve quick periods
        $today = date('Y-m-d');
        if ($period !== 'custom') {
            switch ($period) {
                case 'today':
                    $from_date = $today; $to_date = $today; break;
                case 'week':
                    $from_date = date('Y-m-d', strtotime('monday this week'));
                    $to_date   = date('Y-m-d', strtotime('sunday this week')); break;
                case 'month':
                    $from_date = date('Y-m-01'); $to_date = date('Y-m-t'); break;
                case 'quarter':
                    $qm = ceil((int)date('n') / 3) * 3;
                    $from_date = date('Y-') . str_pad($qm - 2, 2, '0', STR_PAD_LEFT) . '-01';
                    $to_date   = date('Y-m-t', mktime(0,0,0,$qm,1)); break;
                case 'year':
                    $from_date = date('Y-01-01'); $to_date = date('Y-12-31'); break;
            }
        }

        $fd = $from_date ?: '2000-01-01';
        $td = $to_date   ?: '2099-12-31';
        $valid_venues = ['school','home_visit','online_meet','online_zoom'];
        $venue_labels = [
            'school'      => 'School (1-on-1)',
            'home_visit'  => 'Home Visit',
            'online_meet' => 'Online (Google Meet)',
            'online_zoom' => 'Online (Zoom)',
        ];

        try {
            // ── 1. COMPLETED SESSIONS ──
            $s_sql = "
                SELECT l.id, l.lesson_date, ts.venue_type,
                       sp.id AS student_profile_id,
                       u_s.name AS student_name,
                       u_t.id AS teacher_user_id,
                       u_t.name AS teacher_name,
                       COALESCE(CASE ts.venue_type
                           WHEN 'home_visit'  THEN spr.price_home_visit
                           WHEN 'school'      THEN spr.price_school
                           WHEN 'online_meet' THEN spr.price_online_meet
                           WHEN 'online_zoom' THEN spr.price_online_zoom
                           ELSE 0 END, 0) AS billed,
                       COALESCE(CASE ts.venue_type
                           WHEN 'home_visit'  THEN tp.pay_home_visit
                           WHEN 'school'      THEN tp.pay_school
                           WHEN 'online_meet' THEN tp.pay_online_meet
                           WHEN 'online_zoom' THEN tp.pay_online_zoom
                           ELSE 0 END, 0) AS teacher_earned
                FROM lessons l
                JOIN timetable_slots ts ON l.slot_id = ts.id
                JOIN student_profiles sp ON ts.student_id = sp.id
                JOIN students u_s ON sp.user_id = u_s.id
                JOIN teachers u_t ON ts.teacher_id = u_t.id
                LEFT JOIN student_pricing spr ON spr.student_id = sp.id
                LEFT JOIN teacher_pricing tp ON tp.teacher_id = ts.teacher_id
                WHERE l.session_status = 'completed'
                  AND l.lesson_date BETWEEN ? AND ?
            ";
            $s_params = [$fd, $td];
            if ($student_id) { $s_sql .= " AND sp.id = ?";         $s_params[] = $student_id; }
            if ($teacher_id) { $s_sql .= " AND ts.teacher_id = ?"; $s_params[] = $teacher_id; }
            if ($venue_type && in_array($venue_type, $valid_venues)) {
                $s_sql .= " AND ts.venue_type = ?"; $s_params[] = $venue_type;
            }
            $s_sql .= " ORDER BY l.lesson_date ASC, u_s.name ASC";
            $s_stmt = $pdo->prepare($s_sql);
            $s_stmt->execute($s_params);
            $sessions = $s_stmt->fetchAll();

            // ── 2. STUDENT PAYMENTS collected in period ──
            $col_sql = "SELECT COALESCE(SUM(amount),0) FROM student_payments WHERE payment_date BETWEEN ? AND ?";
            $col_params = [$fd, $td];
            if ($student_id) { $col_sql .= " AND student_id = ?"; $col_params[] = $student_id; }
            $col_stmt = $pdo->prepare($col_sql);
            $col_stmt->execute($col_params);
            $collected_in_period = floatval($col_stmt->fetchColumn());

            // ── 3. TEACHER DISBURSEMENTS paid out in period ──
            $disb_sql = "SELECT COALESCE(SUM(amount),0) FROM teacher_payments WHERE payment_date BETWEEN ? AND ?";
            $disb_params = [$fd, $td];
            if ($teacher_id) { $disb_sql .= " AND teacher_id = ?"; $disb_params[] = $teacher_id; }
            $disb_stmt = $pdo->prepare($disb_sql);
            $disb_stmt->execute($disb_params);
            $disbursed_in_period = floatval($disb_stmt->fetchColumn());

            // ── 4. EXTRA EXPENSES in period ──
            $exp_sql = "
                SELECT e.category, e.item_name, e.description,
                       e.amount, e.expense_date, e.reference,
                       COALESCE(u_admin.name, u_acc.name, 'System') AS recorded_by_name
                FROM extra_expenses e
                LEFT JOIN admins u_admin ON u_admin.id = e.recorded_by
                LEFT JOIN accounts_officers u_acc ON u_acc.id = e.recorded_by
                WHERE e.expense_date BETWEEN ? AND ?
                ORDER BY e.expense_date ASC
            ";
            $exp_stmt = $pdo->prepare($exp_sql);
            $exp_stmt->execute([$fd, $td]);
            $expenses = $exp_stmt->fetchAll();

            // ── 5. AGGREGATE ──
            $total_billed         = 0;
            $total_teacher_earned = 0;
            $by_venue   = [];
            $by_student = [];
            $by_teacher = [];

            foreach ($sessions as $s) {
                $b  = floatval($s['billed']);
                $te = floatval($s['teacher_earned']);
                $total_billed         += $b;
                $total_teacher_earned += $te;

                $vt = $s['venue_type'];
                if (!isset($by_venue[$vt]))
                    $by_venue[$vt] = ['venue'=>$vt,'label'=>$venue_labels[$vt]??$vt,'count'=>0,'amount'=>0];
                $by_venue[$vt]['count']++;
                $by_venue[$vt]['amount'] += $b;

                $sid = $s['student_profile_id'];
                if (!isset($by_student[$sid]))
                    $by_student[$sid] = ['name'=>$s['student_name'],'sessions'=>0,'billed'=>0];
                $by_student[$sid]['sessions']++;
                $by_student[$sid]['billed'] += $b;

                $tid = $s['teacher_user_id'];
                if (!isset($by_teacher[$tid]))
                    $by_teacher[$tid] = ['name'=>$s['teacher_name'],'sessions'=>0,'earned'=>0];
                $by_teacher[$tid]['sessions']++;
                $by_teacher[$tid]['earned'] += $te;
            }

            // Expense totals
            $exp_total  = 0;
            $exp_by_cat = ['inventory'=>0,'utility'=>0,'general_repairs'=>0,'petty_cash'=>0];
            foreach ($expenses as $ex) {
                $ea = floatval($ex['amount']);
                $exp_total += $ea;
                if (isset($exp_by_cat[$ex['category']])) $exp_by_cat[$ex['category']] += $ea;
            }

            echo json_encode([
                'status'               => 'success',
                'from_date'            => $fd,
                'to_date'              => $td,
                'sessions_count'       => count($sessions),
                'sessions'             => $sessions,
                'total_billed'         => $total_billed,
                'collected_in_period'  => $collected_in_period,
                'outstanding'          => $total_billed - $collected_in_period,
                'total_teacher_earned' => $total_teacher_earned,
                'disbursed_in_period'  => $disbursed_in_period,
                'by_venue'             => array_values($by_venue),
                'by_student'           => array_values($by_student),
                'by_teacher'           => array_values($by_teacher),
                'expenses'             => $expenses,
                'exp_total'            => $exp_total,
                'exp_by_cat'           => $exp_by_cat,
                'net_position'         => $collected_in_period - $exp_total,
            ]);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Unknown GET action.']);
    exit;
}

// ─────────────────────────────────────────────
// POST REQUESTS
// ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Update student pricing ──
    if ($action === 'update_prices') {
        $student_id         = filter_input(INPUT_POST, 'student_id',         FILTER_VALIDATE_INT);
        $price_online_meet  = filter_input(INPUT_POST, 'price_online_meet',  FILTER_VALIDATE_FLOAT);
        $price_online_zoom  = filter_input(INPUT_POST, 'price_online_zoom',  FILTER_VALIDATE_FLOAT);
        $price_school       = filter_input(INPUT_POST, 'price_school',       FILTER_VALIDATE_FLOAT);
        $price_home_visit   = filter_input(INPUT_POST, 'price_home_visit',   FILTER_VALIDATE_FLOAT);

        if (!$student_id || $price_online_meet === false || $price_online_zoom === false || $price_school === false || $price_home_visit === false) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid input. Please provide valid student and prices.']); exit;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO student_pricing (student_id, price_online_meet, price_online_zoom, price_school, price_home_visit)
                VALUES (:id, :meet, :zoom, :school, :home)
                ON DUPLICATE KEY UPDATE 
                    price_online_meet = :meet_update, 
                    price_online_zoom = :zoom_update, 
                    price_school = :school_update, 
                    price_home_visit = :home_update
            ");
            $stmt->execute([
                'id'            => $student_id,
                'meet'          => $price_online_meet,
                'zoom'          => $price_online_zoom,
                'school'        => $price_school,
                'home'          => $price_home_visit,
                'meet_update'   => $price_online_meet,
                'zoom_update'   => $price_online_zoom,
                'school_update' => $price_school,
                'home_update'   => $price_home_visit
            ]);
            echo json_encode(['status' => 'success', 'message' => '✅ Pricing updated successfully for this student.']);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ── Record Student Payment ──
    if ($action === 'add_student_payment') {
        $student_id   = filter_input(INPUT_POST, 'student_id',   FILTER_VALIDATE_INT);
        $amount       = filter_input(INPUT_POST, 'amount',       FILTER_VALIDATE_FLOAT);
        $payment_date = $_POST['payment_date'] ?? '';
        $reference    = trim($_POST['reference'] ?? '');

        if (!$student_id || !$amount || empty($payment_date)) {
            echo json_encode(['status' => 'error', 'message' => 'Missing student, amount, or payment date.']); exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO student_payments (student_id, amount, payment_date, reference) VALUES (?, ?, ?, ?)");
            $stmt->execute([$student_id, $amount, $payment_date, $reference]);
            
            // Get parent info for email
            $pStmt = $pdo->prepare("
                SELECT u_student.name AS student_name, u_parent.name AS parent_name, u_parent.email AS parent_email 
                FROM student_profiles sp
                JOIN students u_student ON sp.user_id = u_student.id
                JOIN parents u_parent ON sp.parent_id = u_parent.id
                WHERE sp.id = ?
            ");
            $pStmt->execute([$student_id]);
            $parent = $pStmt->fetch();
            
            if ($parent && !empty($parent['parent_email'])) {
                $receiptSubject = "Payment Receipt - " . MAIL_SCHOOL_NAME;
                $receiptBody = "
                    <h2>Payment Received</h2>
                    <p>Dear {$parent['parent_name']},</p>
                    <p>We have successfully received a payment of <strong>Ksh " . number_format($amount, 2) . "</strong> for <strong>{$parent['student_name']}</strong>.</p>
                    <table style='width:100%;max-width:400px;font-size:14px;background:#FAF7F2;padding:14px;border-radius:8px;border-collapse:collapse;margin:20px 0;'>
                        <tr><td style='padding:4px 0;'><strong>Date:</strong></td><td>{$payment_date}</td></tr>
                        <tr><td style='padding:4px 0;'><strong>Amount:</strong></td><td>Ksh " . number_format($amount, 2) . "</td></tr>
                        <tr><td style='padding:4px 0;'><strong>Reference:</strong></td><td>" . ($reference ?: 'N/A') . "</td></tr>
                    </table>
                    <p>Thank you for your prompt payment.</p>
                ";
                sendMail($parent['parent_email'], $receiptSubject, $receiptBody, MAIL_INVOICES_FROM, MAIL_SCHOOL_NAME . ' - Accounts');
            }
            
            echo json_encode(['status' => 'success', 'message' => '✅ Fee payment recorded successfully.']);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ── Update Teacher Pay Rates ──
    if ($action === 'update_teacher_prices') {
        $teacher_id       = filter_input(INPUT_POST, 'teacher_id',       FILTER_VALIDATE_INT);
        $pay_online_meet  = filter_input(INPUT_POST, 'pay_online_meet',  FILTER_VALIDATE_FLOAT);
        $pay_online_zoom  = filter_input(INPUT_POST, 'pay_online_zoom',  FILTER_VALIDATE_FLOAT);
        $pay_school       = filter_input(INPUT_POST, 'pay_school',       FILTER_VALIDATE_FLOAT);
        $pay_home_visit   = filter_input(INPUT_POST, 'pay_home_visit',   FILTER_VALIDATE_FLOAT);

        if (!$teacher_id || $pay_online_meet === false || $pay_online_zoom === false || $pay_school === false || $pay_home_visit === false) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid input. Please provide valid teacher and rates.']); exit;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO teacher_pricing (teacher_id, pay_online_meet, pay_online_zoom, pay_school, pay_home_visit)
                VALUES (:id, :meet, :zoom, :school, :home)
                ON DUPLICATE KEY UPDATE 
                    pay_online_meet = :meet_update, 
                    pay_online_zoom = :zoom_update, 
                    pay_school = :school_update, 
                    pay_home_visit = :home_update
            ");
            $stmt->execute([
                'id'            => $teacher_id,
                'meet'          => $pay_online_meet,
                'zoom'          => $pay_online_zoom,
                'school'        => $pay_school,
                'home'          => $pay_home_visit,
                'meet_update'   => $pay_online_meet,
                'zoom_update'   => $pay_online_zoom,
                'school_update' => $pay_school,
                'home_update'   => $pay_home_visit
            ]);
            echo json_encode(['status' => 'success', 'message' => '✅ Teacher pay rates updated successfully.']);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ── Disburse Teacher Payment ──
    if ($action === 'add_teacher_payment') {
        $teacher_id   = filter_input(INPUT_POST, 'teacher_id',   FILTER_VALIDATE_INT);
        $amount       = filter_input(INPUT_POST, 'amount',       FILTER_VALIDATE_FLOAT);
        $payment_date = $_POST['payment_date'] ?? '';
        $reference    = trim($_POST['reference'] ?? '');

        if (!$teacher_id || !$amount || empty($payment_date)) {
            echo json_encode(['status' => 'error', 'message' => 'Missing teacher, amount, or payment date.']); exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO teacher_payments (teacher_id, amount, payment_date, reference) VALUES (?, ?, ?, ?)");
            $stmt->execute([$teacher_id, $amount, $payment_date, $reference]);
            echo json_encode(['status' => 'success', 'message' => '✅ Teacher disbursement recorded successfully.']);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ── Email Monthly Invoice to Parent ──
    if ($action === 'email_invoice') {
        $student_id = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
        $month      = trim($_POST['month'] ?? ''); // e.g. "July 2026"

        if (!$student_id) {
            echo json_encode(['status' => 'error', 'message' => 'student_id is required.']); exit;
        }

        try {
            // Get student info
            $stmt = $pdo->prepare("
                SELECT sp.id, u_student.name AS student_name, sp.grade_level, u_parent.name AS parent_name, u_parent.email AS parent_email 
                FROM student_profiles sp
                JOIN students u_student ON sp.user_id = u_student.id
                JOIN parents u_parent ON sp.parent_id = u_parent.id
                WHERE sp.id = ?
            ");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch();

            if (!$student) {
                echo json_encode(['status' => 'error', 'message' => 'Student not found.']); exit;
            }

            if (empty($student['parent_email'])) {
                echo json_encode(['status' => 'error', 'message' => "Parent does not have a registered email address. Please update it first."]); exit;
            }

            // Fetch completed lessons for this student
            $stmt = $pdo->prepare("
                SELECT l.id, l.lesson_date, ts.venue_type, u_teacher.name AS teacher_name,
                    COALESCE(
                        CASE WHEN ts.venue_type = 'home_visit' THEN sp_price.price_home_visit
                             WHEN ts.venue_type = 'school' THEN sp_price.price_school
                             WHEN ts.venue_type = 'online_meet' THEN sp_price.price_online_meet
                             WHEN ts.venue_type = 'online_zoom' THEN sp_price.price_online_zoom
                             ELSE 0 END,
                        0
                    ) AS price
                FROM lessons l
                JOIN timetable_slots ts ON l.slot_id = ts.id
                JOIN teachers u_teacher ON ts.teacher_id = u_teacher.id
                LEFT JOIN student_pricing sp_price ON sp_price.student_id = ts.student_id
                WHERE ts.student_id = ? AND l.session_status = 'completed'
                ORDER BY l.lesson_date DESC
            ");
            $stmt->execute([$student_id]);
            $all_lessons = $stmt->fetchAll();

            $billed_lessons = [];
            $total_billed_all_time = 0;
            $total_billed_month = 0;

            foreach ($all_lessons as $l) {
                $total_billed_all_time += floatval($l['price']);
                $lMonth = date('F Y', strtotime($l['lesson_date']));
                if (empty($month) || $lMonth === $month) {
                    $billed_lessons[] = $l;
                    $total_billed_month += floatval($l['price']);
                }
            }

            // Fetch payment records
            $payStmt = $pdo->prepare("SELECT amount FROM student_payments WHERE student_id = ?");
            $payStmt->execute([$student_id]);
            $payments = $payStmt->fetchAll();
            $total_paid = 0;
            foreach ($payments as $p) {
                $total_paid += floatval($p['amount']);
            }

            $outstanding_balance = $total_billed_all_time - $total_paid;

            // Subject and month label
            $invoiceMonthName = empty($month) ? 'All History' : $month;
            $subject = "Tuition Fee Invoice: {$student['student_name']} – {$invoiceMonthName}";

            // Build lessons rows
            $lessonsRows = '';
            if (empty($billed_lessons)) {
                $lessonsRows = "<tr><td colspan='4' style='padding:8px;text-align:center;color:#6C757D;'>No lessons recorded for this period.</td></tr>";
            } else {
                foreach ($billed_lessons as $bl) {
                    $venue_map = [
                        'online_meet' => '🎥 Online (Meet)',
                        'online_zoom' => '💻 Online (Zoom)',
                        'school'      => '🏫 School (1-on-1)',
                        'home_visit'  => '🏠 Home Visit',
                    ];
                    $venueStr = $venue_map[$bl['venue_type']] ?? ucfirst($bl['venue_type']);
                    $lessonsRows .= "
                        <tr>
                            <td style='padding:8px;border-bottom:1px solid #eee;'>{$bl['lesson_date']}</td>
                            <td style='padding:8px;border-bottom:1px solid #eee;'>{$bl['teacher_name']}</td>
                            <td style='padding:8px;border-bottom:1px solid #eee;'>{$venueStr}</td>
                            <td style='padding:8px;border-bottom:1px solid #eee;text-align:right;'>KES " . number_format($bl['price'], 2) . "</td>
                        </tr>
                    ";
                }
            }

            // Build branded email body
            $emailContent = "
                <p>Dear <strong>{$student['parent_name']}</strong>,</p>
                <p>Please find below the monthly fee statement for <strong>{$student['student_name']}</strong> for the period: <strong>{$invoiceMonthName}</strong>.</p>

                <table style='width:100%;margin-bottom:20px;font-size:14px;border-collapse:collapse;'>
                    <tr>
                        <td style='padding:10px;background:#FAF7F2;border-radius:8px;'>
                            <strong>Billed To:</strong><br>{$student['parent_name']}<br>{$student['parent_email']}
                        </td>
                        <td style='padding:10px;text-align:right;background:#FAF7F2;border-radius:8px;'>
                            <strong>Student:</strong> {$student['student_name']}<br>
                            <strong>Grade:</strong> {$student['grade_level']}<br>
                            <strong>Period:</strong> {$invoiceMonthName}
                        </td>
                    </tr>
                </table>

                <h3 style='color:#4A0E17;border-bottom:2px solid #E5A93B;padding-bottom:6px;'>Lessons Taught</h3>
                <table style='width:100%;border-collapse:collapse;font-size:13px;margin-bottom:20px;'>
                    <thead>
                        <tr style='background:#4A0E17;color:white;'>
                            <th style='padding:9px;text-align:left;'>Date</th>
                            <th style='padding:9px;text-align:left;'>Tutor</th>
                            <th style='padding:9px;text-align:left;'>Format</th>
                            <th style='padding:9px;text-align:right;'>Fee (KES)</th>
                        </tr>
                    </thead>
                    <tbody>{$lessonsRows}</tbody>
                </table>

                <table style='width:100%;font-size:14px;line-height:28px;background:#FAF7F2;padding:15px;border-radius:8px;'>
                    <tr><td>Total Billed ({$invoiceMonthName}):</td><td style='text-align:right;font-weight:bold;'>KES " . number_format($total_billed_month, 2) . "</td></tr>
                    <tr><td>Total Paid (All Time):</td><td style='text-align:right;font-weight:bold;color:#10B981;'>KES " . number_format($total_paid, 2) . "</td></tr>
                    <tr style='border-top:2px solid #E5A93B;font-size:16px;'>
                        <td style='padding-top:10px;'><strong>Outstanding Balance:</strong></td>
                        <td style='text-align:right;padding-top:10px;font-weight:bold;color:" . ($outstanding_balance > 0 ? '#EF4444' : '#10B981') . ";'>KES " . number_format($outstanding_balance, 2) . "</td>
                    </tr>
                </table>

                <p style='margin-top:20px;font-size:13px;color:#6C757D;'>Thank you for choosing Sanity Homebased Tuition Academy. Please make payments promptly to avoid arrears.</p>
            ";

            // Generate PDF Invoice using DomPDF
            $pdfHtml = buildEmailTemplate($subject, $emailContent);
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($pdfHtml);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $pdfString = $dompdf->output();

            $pdfFileName = 'Invoice_' . str_replace(' ', '_', $invoiceMonthName) . '_' . preg_replace('/[^a-zA-Z0-9]/', '', $student['student_name']) . '.pdf';
            $attachments = [
                [
                    'string' => $pdfString,
                    'name' => $pdfFileName,
                    'type' => 'application/pdf'
                ]
            ];

            // Send to parent — admins are auto-BCC'd via sendMail()
            $sent = sendMail(
                $student['parent_email'],
                $subject,
                $emailContent,
                MAIL_INVOICES_FROM,
                MAIL_SCHOOL_NAME . ' — Accounts',
                true,
                $attachments
            );

            echo json_encode(['status' => 'success', 'message' => '📬 Invoice emailed to ' . $student['parent_email'] . ' and copied to all admin addresses.']);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ── Add Extra Expense ──
    if ($action === 'add_expense') {
        $category     = trim($_POST['category']     ?? '');
        $item_name    = trim($_POST['item_name']    ?? '');
        $description  = trim($_POST['description']  ?? '');
        $amount       = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
        $expense_date = trim($_POST['expense_date'] ?? '');
        $reference    = trim($_POST['reference']    ?? '');
        $recorded_by  = $_SESSION['user_id'] ?? null;

        $valid_cats = ['inventory', 'utility', 'general_repairs', 'petty_cash'];
        if (!in_array($category, $valid_cats) || empty($item_name) || $amount === false || empty($expense_date) || !$recorded_by) {
            echo json_encode(['status' => 'error', 'message' => 'Missing or invalid required fields.']); exit;
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO extra_expenses (category, item_name, description, amount, expense_date, recorded_by, reference) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$category, $item_name, $description ?: null, $amount, $expense_date, $recorded_by, $reference ?: null]);
            echo json_encode(['status' => 'success', 'message' => '✅ Expense recorded successfully.', 'id' => $pdo->lastInsertId()]);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ── Update Extra Expense ──
    if ($action === 'update_expense') {
        $id           = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $category     = trim($_POST['category']     ?? '');
        $item_name    = trim($_POST['item_name']    ?? '');
        $description  = trim($_POST['description']  ?? '');
        $amount       = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
        $expense_date = trim($_POST['expense_date'] ?? '');
        $reference    = trim($_POST['reference']    ?? '');

        $valid_cats = ['inventory', 'utility', 'general_repairs', 'petty_cash'];
        if (!$id || !in_array($category, $valid_cats) || empty($item_name) || $amount === false || empty($expense_date)) {
            echo json_encode(['status' => 'error', 'message' => 'Missing or invalid required fields.']); exit;
        }
        try {
            $stmt = $pdo->prepare("UPDATE extra_expenses SET category=?, item_name=?, description=?, amount=?, expense_date=?, reference=? WHERE id=?");
            $stmt->execute([$category, $item_name, $description ?: null, $amount, $expense_date, $reference ?: null, $id]);
            echo json_encode(['status' => 'success', 'message' => '✅ Expense updated successfully.']);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ── Delete Extra Expense ──
    if ($action === 'delete_expense') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) { echo json_encode(['status' => 'error', 'message' => 'id required']); exit; }
        try {
            $stmt = $pdo->prepare("DELETE FROM extra_expenses WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success', 'message' => '🗑️ Expense deleted.']);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Unknown POST action.']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
?>
