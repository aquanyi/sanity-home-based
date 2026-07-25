<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../security.php';
start_secure_session();
require_once '../db_connect.php';
require_once '../mail_helper.php';
require_once '../admission_helper.php';

ensure_schema_updated($pdo);

$role = $_SESSION['user_role'] ?? '';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($role !== 'admin' && $role !== 'timetabler')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token($_POST['csrf_token'] ?? '', true);
$lead_id = filter_input(INPUT_POST, 'lead_id', FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';

    if (!$lead_id) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Lead ID.']);
        exit;
    }

    // --- HANDLE PERMANENT REJECTION / DELETION ---
    if ($action === 'reject') {
        try {
            $stmt = $pdo->prepare("DELETE FROM enrollment_inquiries WHERE id = ?");
            $stmt->execute([$lead_id]);
            echo json_encode(['status' => 'success', 'message' => 'Lead permanently deleted from the system.']);
        } catch (\PDOException $e) {
            error_log('[SHTA DELETE LEAD ERROR] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Error deleting lead.']);
        }
        exit;
    }

    // --- HANDLE APPROVAL ---
    $custom_email_body = $_POST['email_body'] ?? '';
    if (empty($custom_email_body)) {
        echo json_encode(['status' => 'error', 'message' => 'Email Body is required for approval.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM enrollment_inquiries WHERE id = ? AND status = 'pending'");
        $stmt->execute([$lead_id]);
        $lead = $stmt->fetch();

        if (!$lead) {
            echo json_encode(['status' => 'error', 'message' => 'Lead not found or already processed.']);
            $pdo->rollBack();
            exit;
        }

        $defaultPassword = '12345';
        $hashedPassword  = password_hash($defaultPassword, PASSWORD_DEFAULT);

        // Check if parent account exists or create
        $checkParent = $pdo->prepare("SELECT id, staff_id FROM parents WHERE email = ?");
        $checkParent->execute([$lead['parent_email']]);
        $existingParent = $checkParent->fetch();

        if ($existingParent) {
            $parentId = $existingParent['id'];
            $parentStaffId = $existingParent['staff_id'];
        } else {
            $parentStaffId = 'PRN-' . date('Y') . '-' . sprintf('%03d', rand(100, 999));
            $userStmt = $pdo->prepare("INSERT INTO parents (staff_id, name, email, phone, nationality, password, must_change_password) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $userStmt->execute([$parentStaffId, $lead['parent_name'], $lead['parent_email'], $lead['parent_phone'], $lead['parent_nationality'] ?? null, $hashedPassword]);
            $parentId = $pdo->lastInsertId();
        }

        // Decode student list or use lead default
        $studentsList = [];
        if (!empty($lead['students_json'])) {
            $decoded = json_decode($lead['students_json'], true);
            if (is_array($decoded) && count($decoded) > 0) {
                $studentsList = $decoded;
            }
        }
        if (empty($studentsList)) {
            $studentsList[] = [
                'name' => $lead['student_name'],
                'grade' => $lead['student_grade'],
                'nationality' => null,
                'dob' => null,
                'first_language' => null
            ];
        }

        $createdStudents = [];

        foreach ($studentsList as $idx => $st) {
            $stName  = $st['name'];
            $stGrade = $st['grade'];
            $stNat   = $st['nationality'] ?? null;
            $stDob   = !empty($st['dob']) ? $st['dob'] : null;
            $stLang  = $st['first_language'] ?? null;

            $studentStaffId = 'STD-' . date('Y') . '-' . sprintf('%03d', rand(100, 999));
            $admissionNo    = generate_unique_admission_no($pdo);
            // Generate clean internal email using student name + admission no
            $cleanFirst     = strtolower(preg_replace('/[^a-zA-Z]/', '', explode(' ', $stName)[0]));
            $cleanAdm       = strtolower(preg_replace('/[^a-z0-9]/i', '', $admissionNo));
            $studentEmail   = $cleanFirst . '.' . $cleanAdm . '@students.shta';
            $studentPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

            $studentInsert = $pdo->prepare("INSERT INTO students (staff_id, admission_no, name, email, phone, password, must_change_password) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $studentInsert->execute([$studentStaffId, $admissionNo, $stName, $studentEmail, $lead['parent_phone'], $studentPassword]);
            $studentUserId = $pdo->lastInsertId();

            $studentStmt = $pdo->prepare("
                INSERT INTO student_profiles (user_id, parent_id, grade_level, dob, nationality, first_language, learning_notes, loc_place, loc_estate, loc_link, curriculum_id, study_type)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $studentStmt->execute([
                $studentUserId,
                $parentId,
                $stGrade,
                $stDob,
                $stNat,
                $stLang,
                $lead['learning_needs'] ?? null,
                $lead['loc_place'],
                $lead['loc_estate'],
                $lead['loc_link'],
                $lead['curriculum_id'] ?? null,
                $lead['study_type'] ?? 'tuition'
            ]);
            $profileId = $pdo->lastInsertId();

            $stSubjects = $st['subjects'] ?? [];
            if (!empty($stSubjects)) {
                save_student_subjects($pdo, $profileId, $stSubjects);
            }

            $createdStudents[] = [
                'name' => $stName,
                'admission_no' => $admissionNo
            ];
        }

        // Mark lead approved
        $updateStmt = $pdo->prepare("UPDATE enrollment_inquiries SET status = 'approved' WHERE id = ?");
        $updateStmt->execute([$lead_id]);

        $pdo->commit();
        
        // Dispatch the approval email
        $approvalSubject = "Account Approved & Provisioned - " . MAIL_SCHOOL_NAME;
        $approvalBody = "
            <h2>Account Activation</h2>
            <p>Dear {$lead['parent_name']},</p>
            <p>" . nl2br(htmlspecialchars($custom_email_body)) . "</p>
            <p><strong>Your login details:</strong></p>
            <ul>
                <li><strong>Portal URL:</strong> <a href='https://sanityeducation.com/portal.html'>https://sanityeducation.com/portal.html</a></li>
                <li><strong>Email:</strong> {$lead['parent_email']}</li>
                <li><strong>Password:</strong> 12345 (You will be prompted to change this on first login)</li>
            </ul>
        ";
        sendMail($lead['parent_email'], $approvalSubject, $approvalBody);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Enrollment lead successfully approved and accounts provisioned!'
        ]);
    } catch (\PDOException $e) {
        $pdo->rollBack();
        error_log('[SHTA APPROVE LEAD ERROR] ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Error approving lead: ' . $e->getMessage()]);
    }
}
?>
