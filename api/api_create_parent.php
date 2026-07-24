<?php
/**
 * api_create_parent.php
 * Directly provisions parent and student account(s) from Admin Role Management.
 * Supports multiple students per parent with S000A format admission numbers,
 * parent nationality, student nationality, DOB, and first language.
 */
header('Content-Type: application/json; charset=utf-8');
require_once '../security.php';
start_secure_session();
require_once '../db_connect.php';
require_once '../admission_helper.php';
require_once '../mail_helper.php';

ensure_schema_updated($pdo);

// Auth Guard - Admin or Timetabler only
$userRole = $_SESSION['user_role'] ?? '';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !in_array($userRole, ['admin', 'timetabler'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access. Admin privileges required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['csrf_token'])) {
        validate_csrf_token($_POST['csrf_token'], true);
    }

    $parent_name        = filter_input(INPUT_POST, 'parent_name', FILTER_SANITIZE_SPECIAL_CHARS);
    $parent_phone       = filter_input(INPUT_POST, 'parent_phone', FILTER_SANITIZE_SPECIAL_CHARS);
    $parent_email       = filter_input(INPUT_POST, 'parent_email', FILTER_VALIDATE_EMAIL);
    $parent_nationality = filter_input(INPUT_POST, 'parent_nationality', FILTER_SANITIZE_SPECIAL_CHARS);

    $study_type        = filter_input(INPUT_POST, 'study_type', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'tuition';
    $venue_preference = filter_input(INPUT_POST, 'venue_preference', FILTER_SANITIZE_SPECIAL_CHARS);
    if ($venue_preference === 'home') $venue_preference = 'home_visit';

    $loc_place  = isset($_POST['loc_place'])  ? filter_input(INPUT_POST, 'loc_place', FILTER_SANITIZE_SPECIAL_CHARS) : null;
    $loc_estate = isset($_POST['loc_estate']) ? filter_input(INPUT_POST, 'loc_estate', FILTER_SANITIZE_SPECIAL_CHARS) : null;
    $loc_link   = isset($_POST['loc_link'])   ? filter_input(INPUT_POST, 'loc_link', FILTER_VALIDATE_URL) : null;

    $curriculum_id     = isset($_POST['curriculum_id']) ? $_POST['curriculum_id'] : null;
    $custom_curriculum = isset($_POST['custom_curriculum']) ? trim($_POST['custom_curriculum']) : '';

    if ($study_type !== 'homeschooling') {
        $study_type = 'tuition';
    }

    if (!$parent_name || !$parent_phone || !$parent_email || !$venue_preference) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required parent fields (Name, Phone, Email, Venue Preference).']);
        exit;
    }

    // Process custom curriculum
    if ($curriculum_id === 'custom') {
        if (!empty($custom_curriculum)) {
            $stmt_check = $pdo->prepare("SELECT id FROM curriculums WHERE LOWER(name) = LOWER(?)");
            $stmt_check->execute([$custom_curriculum]);
            $existing = $stmt_check->fetch();
            if ($existing) {
                $curriculum_id = $existing['id'];
            } else {
                $stmt_ins = $pdo->prepare("INSERT INTO curriculums (name, is_approved) VALUES (?, 1)");
                $stmt_ins->execute([$custom_curriculum]);
                $curriculum_id = $pdo->lastInsertId();
            }
        } else {
            $curriculum_id = null;
        }
    } else {
        $curriculum_id = filter_var($curriculum_id, FILTER_VALIDATE_INT) ?: null;
    }

    // Parse students array or single student fallback
    $students_data = [];
    if (isset($_POST['students']) && is_array($_POST['students'])) {
        $students_data = $_POST['students'];
    } else {
        $st_name  = filter_input(INPUT_POST, 'student_name', FILTER_SANITIZE_SPECIAL_CHARS);
        $st_grade = filter_input(INPUT_POST, 'student_grade', FILTER_SANITIZE_SPECIAL_CHARS);
        $st_nat   = filter_input(INPUT_POST, 'student_nationality', FILTER_SANITIZE_SPECIAL_CHARS);
        $st_dob   = filter_input(INPUT_POST, 'student_dob', FILTER_SANITIZE_SPECIAL_CHARS);
        $st_lang  = filter_input(INPUT_POST, 'student_first_language', FILTER_SANITIZE_SPECIAL_CHARS);

        if ($st_name && $st_grade) {
            $students_data[] = [
                'name'           => $st_name,
                'grade'          => $st_grade,
                'nationality'    => $st_nat,
                'dob'            => $st_dob,
                'first_language' => $st_lang
            ];
        }
    }

    if (empty($students_data)) {
        echo json_encode(['status' => 'error', 'message' => 'At least one student must be provided with Name and Grade.']);
        exit;
    }

    // Check parent email uniqueness
    $checkParent = $pdo->prepare("SELECT id FROM parents WHERE email = ?");
    $checkParent->execute([$parent_email]);
    if ($checkParent->fetch()) {
        echo json_encode(['status' => 'error', 'message' => "A parent account with email '{$parent_email}' already exists."]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $defaultPassword = '12345';
        $hashedPassword  = password_hash($defaultPassword, PASSWORD_DEFAULT);

        // Parent Staff ID
        $parentStaffId = 'PRN-' . date('Y') . '-' . sprintf('%03d', rand(100, 999));

        $parentStmt = $pdo->prepare("INSERT INTO parents (staff_id, name, email, phone, nationality, password, must_change_password) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $parentStmt->execute([$parentStaffId, $parent_name, $parent_email, $parent_phone, $parent_nationality, $hashedPassword]);
        $parentId = $pdo->lastInsertId();

        $createdStudents = [];

        foreach ($students_data as $idx => $sData) {
            $sName = trim($sData['name'] ?? '');
            $sGrade = trim($sData['grade'] ?? '');
            $sNat   = trim($sData['nationality'] ?? '');
            $sDob   = !empty($sData['dob']) ? $sData['dob'] : null;
            $sLang  = trim($sData['first_language'] ?? '');

            if (empty($sName) || empty($sGrade)) continue;

            $studentStaffId = 'STD-' . date('Y') . '-' . sprintf('%03d', rand(100, 999));
            $admissionNo    = generate_unique_admission_no($pdo);
            // Generate clean internal email using student name + admission no
            $cleanFirst     = strtolower(preg_replace('/[^a-zA-Z]/', '', explode(' ', $sName)[0]));
            $cleanAdm       = strtolower(preg_replace('/[^a-z0-9]/i', '', $admissionNo));
            $studentEmail   = $cleanFirst . '.' . $cleanAdm . '@students.shta';
            $studentPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

            $studentStmt = $pdo->prepare("INSERT INTO students (staff_id, admission_no, name, email, phone, password, must_change_password) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $studentStmt->execute([$studentStaffId, $admissionNo, $sName, $studentEmail, $parent_phone, $studentPassword]);
            $studentUserId = $pdo->lastInsertId();

            $profileStmt = $pdo->prepare("
                INSERT INTO student_profiles (user_id, parent_id, grade_level, dob, nationality, first_language, learning_notes, loc_place, loc_estate, loc_link, curriculum_id, study_type)
                VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?)
            ");
            $profileStmt->execute([
                $studentUserId,
                $parentId,
                $sGrade,
                $sDob,
                $sNat,
                $sLang,
                $loc_place,
                $loc_estate,
                $loc_link,
                $curriculum_id,
                $study_type
            ]);
            $profileId = $pdo->lastInsertId();

            $sSubjects = $sData['subjects'] ?? [];
            if (!empty($sSubjects)) {
                save_student_subjects($pdo, $profileId, $sSubjects);
            }

            $createdStudents[] = [
                'name' => $sName,
                'admission_no' => $admissionNo,
                'staff_id' => $studentStaffId
            ];
        }

        $pdo->commit();

        // ── Send Welcome Email to Parent ──────────────────────────────────────
        $studentListHtml = '';
        foreach ($createdStudents as $i => $s) {
            $num = $i + 1;
            $studentListHtml .= "
              <tr style='background:" . ($i % 2 === 0 ? '#F8F9FA' : '#FFFFFF') . ";'>
                <td style='padding:10px 14px;border-bottom:1px solid #E9ECEF;'><strong>{$num}</strong></td>
                <td style='padding:10px 14px;border-bottom:1px solid #E9ECEF;'>{$s['name']}</td>
                <td style='padding:10px 14px;border-bottom:1px solid #E9ECEF;font-family:monospace;'>{$s['admission_no']}</td>
              </tr>";
        }

        $portalUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                   . '://' . ($_SERVER['HTTP_HOST'] ?? 'sanityeducation.com')
                   . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/\\') . '/login.html#parent';

        $emailContent = "
<h2 style='color:#4A0E17;margin-top:0;'>Welcome to Sanity Homebased Tuition Academy!</h2>
<p>Dear <strong>{$parent_name}</strong>,</p>
<p>Your parent account has been successfully set up by our administration. You can now log in to the parent portal to track your child's progress, view reports, and stay connected with the academy.</p>

<table style='width:100%;background:#F8F9FA;border-radius:8px;border:1px solid #E9ECEF;padding:0;border-collapse:collapse;margin:20px 0;'>
  <tr><td style='padding:14px 18px;border-bottom:1px solid #E9ECEF;'><span style='color:#6C757D;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;'>Login Email</span><br><strong style='font-size:15px;'>{$parent_email}</strong></td></tr>
  <tr><td style='padding:14px 18px;'><span style='color:#6C757D;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;'>Temporary Password</span><br><strong style='font-size:15px;font-family:monospace;letter-spacing:1px;'>12345</strong></td></tr>
</table>

<p style='background:#FFF3CD;border:1px solid #FFEEBA;border-radius:6px;padding:12px 16px;color:#856404;font-size:14px;'>
  &#9888; Please log in and change your password immediately for security.
</p>

<h3 style='color:#4A0E17;margin-top:28px;margin-bottom:10px;'>Registered Student(s)</h3>
<table style='width:100%;border-collapse:collapse;'>
  <thead>
    <tr style='background:#4A0E17;'>
      <th style='padding:10px 14px;color:#E5A93B;text-align:left;font-size:13px;'>#</th>
      <th style='padding:10px 14px;color:#E5A93B;text-align:left;font-size:13px;'>Student Name</th>
      <th style='padding:10px 14px;color:#E5A93B;text-align:left;font-size:13px;'>Admission No.</th>
    </tr>
  </thead>
  <tbody>{$studentListHtml}</tbody>
</table>

<p style='margin-top:28px;'>Access the parent portal here:</p>
<p><a href='{$portalUrl}' style='display:inline-block;background:#4A0E17;color:#E5A93B;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:600;letter-spacing:0.5px;'>&#128100; Login to Parent Portal</a></p>

<p style='margin-top:24px;font-size:13px;color:#6C757D;'>If you have any questions or need assistance, please contact us at <a href='mailto:" . MAIL_CONTACT_EMAIL . "' style='color:#4A0E17;'>" . MAIL_CONTACT_EMAIL . "</a> or call " . MAIL_CONTACT_PHONE . ".</p>
";

        $emailBody  = buildEmailTemplate('Welcome to Sanity Homebased Tuition Academy', $emailContent);
        $emailSent  = sendMail(
            $parent_email,
            'Welcome to Sanity Homebased Tuition Academy – Your Account is Ready',
            $emailBody,
            MAIL_ADMIN_FROM,
            MAIL_SCHOOL_NAME,
            true
        );
        // ─────────────────────────────────────────────────────────────────────

        $successMsg = "Parent account for {$parent_name} and " . count($createdStudents) . " student(s) added successfully! Default password: 12345.";
        if ($emailSent) {
            $successMsg .= " A welcome email has been sent to {$parent_email}.";
        } else {
            $successMsg .= " (Note: welcome email could not be delivered — please inform the parent manually.)";
        }

        echo json_encode([
            'status'           => 'success',
            'message'          => $successMsg,
            'parent_staff_id'  => $parentStaffId,
            'parent_email'     => $parent_email,
            'created_students' => $createdStudents,
            'default_password' => '12345',
            'email_sent'       => $emailSent
        ]);
    } catch (\PDOException $e) {
        $pdo->rollBack();
        error_log('[SHTA CREATE PARENT ERROR] ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error creating parent/student accounts: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>
