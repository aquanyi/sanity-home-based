<?php
/**
 * api_profile.php
 * Handles user profile management across all roles (Admin, Teacher, Parent, Academic Operations Coordinator).
 * Allows users to fetch their current profile and update their details (name, email, phone, password).
 */
header('Content-Type: application/json; charset=utf-8');
require_once '../security.php';
start_secure_session();
require_once '../db_connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Please log in.']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

// ─────────────────────────────────────────────────
// GET: Fetch current user profile details
// ─────────────────────────────────────────────────
if ($method === 'GET' || $action === 'get_profile') {
    $userRole = $_SESSION['user_role'] ?? '';
    $table = '';
    switch ($userRole) {
        case 'admin': $table = 'admins'; break;
        case 'timetabler': $table = 'timetablers'; break;
        case 'teacher': $table = 'teachers'; break;
        case 'parent': $table = 'parents'; break;
        case 'student': $table = 'students'; break;
        case 'accounts': $table = 'accounts_officers'; break;
    }
    try {
        $stmt = $pdo->prepare("SELECT id, name, email, phone, '$userRole' AS role, created_at FROM `$table` WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(['status' => 'error', 'message' => 'User profile not found.']);
            exit;
        }

        if ($userRole === 'teacher') {
            $allSubStmt = $pdo->query("SELECT id, name FROM subject_areas ORDER BY name ASC");
            $allSubjects = $allSubStmt->fetchAll(PDO::FETCH_ASSOC);

            $assignedStmt = $pdo->prepare("SELECT subject_id FROM teacher_subjects WHERE teacher_id = ?");
            $assignedStmt->execute([$userId]);
            $assignedSubjectIds = $assignedStmt->fetchAll(PDO::FETCH_COLUMN);

            // Robust Fallback: If teacher_subjects table is empty for this teacher, resolve from pending_teachers registration or notifications
            if (empty($assignedSubjectIds)) {
                try {
                    $foundIds = [];
                    $teacherEmail = strtolower(trim($user['email'] ?? ''));
                    $teacherName  = strtolower(trim($user['name'] ?? ''));

                    // 1. Search pending_teachers by email or name
                    $ptStmt = $pdo->prepare("SELECT subject_ids, custom_subjects FROM pending_teachers WHERE LOWER(email) = ? OR LOWER(name) = ? ORDER BY id DESC LIMIT 1");
                    $ptStmt->execute([$teacherEmail, $teacherName]);
                    $ptRow = $ptStmt->fetch(PDO::FETCH_ASSOC);

                    if ($ptRow) {
                        $rawItems = array_merge(
                            !empty($ptRow['subject_ids']) ? explode(',', $ptRow['subject_ids']) : [],
                            !empty($ptRow['custom_subjects']) ? explode(',', $ptRow['custom_subjects']) : []
                        );
                        foreach ($rawItems as $item) {
                            $item = trim($item);
                            if (empty($item)) continue;
                            if (is_numeric($item)) {
                                $foundIds[] = (int)$item;
                            } else {
                                // Match subject by exact or partial name
                                $sFind = $pdo->prepare("SELECT id FROM subject_areas WHERE LOWER(name) = ? OR LOWER(name) LIKE ? LIMIT 1");
                                $sFind->execute([strtolower($item), '%' . strtolower($item) . '%']);
                                $fId = $sFind->fetchColumn();
                                if ($fId) $foundIds[] = (int)$fId;
                            }
                        }
                    }

                    // 2. Search system_notifications if still empty
                    if (empty($foundIds) && !empty($teacherName)) {
                        $notifStmt = $pdo->prepare("SELECT message FROM system_notifications WHERE recipient_role = 'admin' AND LOWER(message) LIKE ? ORDER BY id DESC LIMIT 1");
                        $notifStmt->execute(['%' . strtolower($teacherName) . '%']);
                        $notifMsg = $notifStmt->fetchColumn();
                        if ($notifMsg && preg_match('/Teaching Subjects:\s*([^\n]+)/i', $notifMsg, $matches)) {
                            $subjectNames = array_map('trim', explode(',', $matches[1]));
                            foreach ($subjectNames as $sn) {
                                if (empty($sn)) continue;
                                $sFind = $pdo->prepare("SELECT id FROM subject_areas WHERE LOWER(name) = ? OR LOWER(name) LIKE ? LIMIT 1");
                                $sFind->execute([strtolower($sn), '%' . strtolower($sn) . '%']);
                                $fId = $sFind->fetchColumn();
                                if ($fId) $foundIds[] = (int)$fId;
                            }
                        }
                    }

                    if (!empty($foundIds)) {
                        $assignedSubjectIds = array_unique($foundIds);
                        // Auto-populate teacher_subjects table so preferences persist forever
                        $insStmt = $pdo->prepare("INSERT IGNORE INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)");
                        foreach ($assignedSubjectIds as $sId) {
                            if ($sId > 0) {
                                $insStmt->execute([$userId, $sId]);
                            }
                        }
                    }
                } catch (\PDOException $ex) {
                    error_log('[SHTA TEACHER SUBJECT FALLBACK ERROR] ' . $ex->getMessage());
                }
            }

            echo json_encode([
                'status'               => 'success',
                'user'                 => $user,
                'all_subjects'         => $allSubjects,
                'assigned_subject_ids' => array_values(array_unique(array_map('intval', $assignedSubjectIds)))
            ]);
            exit;
        }

        echo json_encode(['status' => 'success', 'user' => $user]);
    } catch (\PDOException $e) {
        error_log('[SHTA API PROFILE FETCH ERROR] ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve profile details.']);
    }
    exit;
}

// ─────────────────────────────────────────────────
// POST: Update profile details (Name, Email, Phone, Password)
// ─────────────────────────────────────────────────
if ($method === 'POST' && ($action === 'update_profile' || empty($action))) {
    // Validate CSRF token
    validate_csrf_token($_POST['csrf_token'] ?? '', true);

    $name             = trim($_POST['name'] ?? '');
    $email            = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $phone            = trim($_POST['phone'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';

    if (empty($name) || !$email || empty($phone)) {
        echo json_encode(['status' => 'error', 'message' => 'Name, email, and phone number are required fields.']);
        exit;
    }

    try {
        $userRole = $_SESSION['user_role'] ?? '';
        $table = '';
        switch ($userRole) {
            case 'admin': $table = 'admins'; break;
            case 'timetabler': $table = 'timetablers'; break;
            case 'teacher': $table = 'teachers'; break;
            case 'parent': $table = 'parents'; break;
            case 'student': $table = 'students'; break;
            case 'accounts': $table = 'accounts_officers'; break;
        }
        // Fetch current user data to verify password if changing, and check email uniqueness
        $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE id = ?");
        $stmt->execute([$userId]);
        $currentUser = $stmt->fetch();

        if (!$currentUser) {
            echo json_encode(['status' => 'error', 'message' => 'User not found.']);
            exit;
        }

        // Check if email is already in use by another user within the same table
        $emailInUse = false;
        $checkEmail = $pdo->prepare("SELECT id FROM `$table` WHERE email = ? AND id != ?");
        $checkEmail->execute([$email, $userId]);
        if ($checkEmail->fetch()) {
            $emailInUse = true;
        }
        if ($emailInUse) {
            echo json_encode(['status' => 'error', 'message' => 'The email address is already in use by another account.']);
            exit;
        }

        $passwordUpdateSql = "";
        $params = [$name, $email, $phone];

        // Password change logic
        if (!empty($new_password)) {
            if (empty($current_password)) {
                echo json_encode(['status' => 'error', 'message' => 'Current password is required to set a new password.']);
                exit;
            }
            if (!password_verify($current_password, $currentUser['password'])) {
                echo json_encode(['status' => 'error', 'message' => 'Incorrect current password.']);
                exit;
            }
            if (strlen($new_password) < 8) {
                echo json_encode(['status' => 'error', 'message' => 'New password must be at least 8 characters long.']);
                exit;
            }
            if (!preg_match('/[0-9]/', $new_password)) {
                echo json_encode(['status' => 'error', 'message' => 'New password must contain at least one number.']);
                exit;
            }
            $passwordUpdateSql = ", password = ?";
            $params[] = password_hash($new_password, PASSWORD_DEFAULT);
        }

        $params[] = $userId;

        $updateStmt = $pdo->prepare("UPDATE `$table` SET name = ?, email = ?, phone = ? {$passwordUpdateSql} WHERE id = ?");
        $updateStmt->execute($params);

        // Update teacher subjects if teacher
        if ($userRole === 'teacher' && (isset($_POST['update_subjects']) || isset($_POST['subject_ids']))) {
            $pdo->prepare("DELETE FROM teacher_subjects WHERE teacher_id = ?")->execute([$userId]);
            if (isset($_POST['subject_ids']) && is_array($_POST['subject_ids'])) {
                $subIns = $pdo->prepare("INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)");
                foreach ($_POST['subject_ids'] as $sId) {
                    $sIdInt = filter_var($sId, FILTER_VALIDATE_INT);
                    if ($sIdInt) {
                        $subIns->execute([$userId, $sIdInt]);
                    }
                }
            }
        }

        // Update session variables if changed
        $_SESSION['user_name']  = $name;
        $_SESSION['user_email'] = $email;

        echo json_encode([
            'status'  => 'success',
            'message' => 'Profile & subject preferences updated successfully!',
            'user'    => [
                'name'  => $name,
                'email' => $email,
                'phone' => $phone
            ]
        ]);
    } catch (\PDOException $e) {
        error_log('[SHTA API PROFILE UPDATE ERROR] ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to save profile changes.']);
    }
    exit;
}

// ─────────────────────────────────────────────────
// POST: Admin Delete User (CRUD privilege)
// ─────────────────────────────────────────────────
if ($method === 'POST' && $action === 'delete_user') {
    // Validate CSRF token
    validate_csrf_token($_POST['csrf_token'] ?? '', true);

    if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'timetabler'])) {
        echo json_encode(['status' => 'error', 'message' => 'Administrative privilege required.']);
        exit;
    }

    $targetUserId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $role         = trim($_POST['role'] ?? '');

    if (!$targetUserId || empty($role)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid parameters. User ID and role are required.']);
        exit;
    }

    // Cannot delete yourself
    if ($targetUserId === (int)($_SESSION['user_id'] ?? 0) && $role === ($_SESSION['user_role'] ?? '')) {
        echo json_encode(['status' => 'error', 'message' => 'You cannot delete your own logged-in administrative account.']);
        exit;
    }

    $table_map = [
        'admin'             => 'admins',
        'timetabler'        => 'timetablers',
        'teacher'           => 'teachers',
        'parent'            => 'parents',
        'student'           => 'students',
        'accounts'          => 'accounts_officers',
        'accounts_officer'  => 'accounts_officers',
        'accounts_officers' => 'accounts_officers',
        'accountant'        => 'accounts_officers',
        'account'           => 'accounts_officers'
    ];
    $table = $table_map[$role] ?? '';
    if (empty($table)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid role specified.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. TEACHER DELETION
        if ($role === 'teacher') {
            // Remove teacher subject mappings
            $pdo->prepare("DELETE FROM teacher_subjects WHERE teacher_id = ?")->execute([$targetUserId]);
            
            // Unassign invigilator in exam sessions
            $pdo->prepare("UPDATE exam_sessions SET invigilator_teacher_id = NULL WHERE invigilator_teacher_id = ?")->execute([$targetUserId]);

            // Find timetable slots for this teacher
            $slots = $pdo->prepare("SELECT id FROM timetable_slots WHERE teacher_id = ?");
            $slots->execute([$targetUserId]);
            $slotIds = $slots->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($slotIds)) {
                $inSlots = implode(',', array_fill(0, count($slotIds), '?'));
                $pdo->prepare("DELETE FROM lessons WHERE slot_id IN ($inSlots)")->execute($slotIds);
                $pdo->prepare("DELETE FROM timetable_slots WHERE id IN ($inSlots)")->execute($slotIds);
            }

            $pdo->prepare("DELETE FROM student_assignments WHERE teacher_id = ?")->execute([$targetUserId]);
            $pdo->prepare("DELETE FROM academic_reports WHERE teacher_id = ?")->execute([$targetUserId]);
        }

        // 2. PARENT DELETION
        if ($role === 'parent') {
            // Find all student profiles linked to this parent
            $spStmt = $pdo->prepare("SELECT id, user_id FROM student_profiles WHERE parent_id = ?");
            $spStmt->execute([$targetUserId]);
            $profiles = $spStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($profiles as $sp) {
                $spId = $sp['id'];
                $studentUserId = $sp['user_id'];

                $slots = $pdo->prepare("SELECT id FROM timetable_slots WHERE student_id = ?");
                $slots->execute([$spId]);
                $slotIds = $slots->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($slotIds)) {
                    $inSlots = implode(',', array_fill(0, count($slotIds), '?'));
                    $pdo->prepare("DELETE FROM lessons WHERE slot_id IN ($inSlots)")->execute($slotIds);
                    $pdo->prepare("DELETE FROM timetable_slots WHERE id IN ($inSlots)")->execute($slotIds);
                }

                $pdo->prepare("DELETE FROM exam_results WHERE student_id = ?")->execute([$spId]);
                $pdo->prepare("DELETE FROM student_assignments WHERE student_id = ?")->execute([$spId]);
                $pdo->prepare("DELETE FROM academic_reports WHERE student_id = ?")->execute([$spId]);
                $pdo->prepare("DELETE FROM student_pricing WHERE student_id = ?")->execute([$spId]);

                $pdo->prepare("DELETE FROM student_profiles WHERE id = ?")->execute([$spId]);
                $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$studentUserId]);
            }
        }

        // 3. STUDENT DELETION
        if ($role === 'student') {
            $spStmt = $pdo->prepare("SELECT id FROM student_profiles WHERE user_id = ?");
            $spStmt->execute([$targetUserId]);
            $profiles = $spStmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($profiles as $spId) {
                $slots = $pdo->prepare("SELECT id FROM timetable_slots WHERE student_id = ?");
                $slots->execute([$spId]);
                $slotIds = $slots->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($slotIds)) {
                    $inSlots = implode(',', array_fill(0, count($slotIds), '?'));
                    $pdo->prepare("DELETE FROM lessons WHERE slot_id IN ($inSlots)")->execute($slotIds);
                    $pdo->prepare("DELETE FROM timetable_slots WHERE id IN ($inSlots)")->execute($slotIds);
                }

                $pdo->prepare("DELETE FROM exam_results WHERE student_id = ?")->execute([$spId]);
                $pdo->prepare("DELETE FROM student_assignments WHERE student_id = ?")->execute([$spId]);
                $pdo->prepare("DELETE FROM academic_reports WHERE student_id = ?")->execute([$spId]);
                $pdo->prepare("DELETE FROM student_pricing WHERE student_id = ?")->execute([$spId]);
            }
            $pdo->prepare("DELETE FROM student_profiles WHERE user_id = ?")->execute([$targetUserId]);
        }

        // Delete from specific role table
        $stmt = $pdo->prepare("DELETE FROM `$table` WHERE id = ?");
        $stmt->execute([$targetUserId]);

        // Clean up legacy users table if present
        try {
            $chkUsers = $pdo->query("SHOW TABLES LIKE 'users'");
            if ($chkUsers && $chkUsers->fetch()) {
                $pdo->prepare("DELETE FROM users WHERE (id = ? AND role IN ('accounts','accounts_officer','accountant')) OR email IN (SELECT email FROM `$table` WHERE id = ?)")->execute([$targetUserId, $targetUserId]);
            }
        } catch (\PDOException $ex) {}

        $pdo->commit();

        echo json_encode(['status' => 'success', 'message' => 'User account and associated records successfully deleted.']);
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[SHTA API PROFILE DELETE ERROR] ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete user account: ' . $e->getMessage()]);
    }
    exit;
}

require_once __DIR__ . '/../admission_helper.php';
auto_assign_missing_admission_nos($pdo);

// ─────────────────────────────────────────────────
// POST: Admin Override Update Any User (CRUD privilege)
// ─────────────────────────────────────────────────
if ($method === 'POST' && $action === 'admin_update_user') {
    // Validate CSRF token
    validate_csrf_token($_POST['csrf_token'] ?? '', true);

    if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'timetabler'])) {
        echo json_encode(['status' => 'error', 'message' => 'Administrative privilege required.']);
        exit;
    }

    $targetUserId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $name         = trim($_POST['name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $role         = strtolower(trim($_POST['role'] ?? ''));
    $new_password = $_POST['new_password'] ?? '';
    $admission_no = trim($_POST['admission_no'] ?? '');

    $validRoles = ['admin','timetabler','teacher','parent','student','accounts','accounts_officer','accounts_officers','accountant'];
    if (!$targetUserId || empty($name) || empty($email) || empty($phone) || !in_array($role, $validRoles)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid parameters. Name, email/username, phone, and role are required.']);
        exit;
    }

    try {
        $table_map = [
            'admin'             => 'admins',
            'timetabler'        => 'timetablers',
            'teacher'           => 'teachers',
            'parent'            => 'parents',
            'student'           => 'students',
            'accounts'          => 'accounts_officers',
            'accounts_officer'  => 'accounts_officers',
            'accounts_officers' => 'accounts_officers',
            'accountant'        => 'accounts_officers',
            'account'           => 'accounts_officers'
        ];
        $table = $table_map[$role] ?? 'teachers';

        // Check email uniqueness against the current table
        $emailInUse = false;
        $checkEmail = $pdo->prepare("SELECT id FROM `$table` WHERE email = ? AND id != ?");
        $checkEmail->execute([$email, $targetUserId]);
        if ($checkEmail->fetch()) {
            $emailInUse = true;
        }
        if ($emailInUse) {
            echo json_encode(['status' => 'error', 'message' => 'Email/username is already in use by another account.']);
            exit;
        }

        $passwordUpdateSql = "";
        $params = [$name, $email, $phone];

        if (!empty($new_password)) {
            if (strlen($new_password) < 8) {
                echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters long.']);
                exit;
            }
            if (!preg_match('/[0-9]/', $new_password)) {
                echo json_encode(['status' => 'error', 'message' => 'Password must contain at least one number.']);
                exit;
            }
            // When admin resets a password: hash it, clear security question/answer,
            // and set must_change_password=1 so the user is forced through
            // first_login_setup.php on next login to set a fresh security question.
            $passwordUpdateSql = ", password = ?, security_question = NULL, security_answer = NULL, must_change_password = 1";
            $params[] = password_hash($new_password, PASSWORD_DEFAULT);
        }

        $params[] = $targetUserId;

        $stmt = $pdo->prepare("UPDATE `$table` SET name = ?, email = ?, phone = ? {$passwordUpdateSql} WHERE id = ?");
        $stmt->execute($params);

        // Update student admission number if student
        if ($role === 'student' && !empty($admission_no)) {
            try {
                $updAdm = $pdo->prepare("UPDATE students SET admission_no = ? WHERE id = ?");
                $updAdm->execute([$admission_no, $targetUserId]);
            } catch (\PDOException $ex) {}
        }

        // Update teacher subjects
        $pdo->prepare("DELETE FROM teacher_subjects WHERE teacher_id = ?")->execute([$targetUserId]);
        if ($role === 'teacher' && isset($_POST['subject_ids']) && is_array($_POST['subject_ids'])) {
            $subStmt = $pdo->prepare("INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)");
            foreach ($_POST['subject_ids'] as $subId) {
                $subIdInt = filter_var($subId, FILTER_VALIDATE_INT);
                if ($subIdInt) {
                    $subStmt->execute([$targetUserId, $subIdInt]);
                }
            }
        }

        echo json_encode(['status' => 'success', 'message' => "User account #{$targetUserId} ({$name}) updated successfully!"]);
    } catch (\PDOException $e) {
        error_log('[SHTA API PROFILE UPDATE USER ERROR] ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to update user profile.']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action or request method.']);
?>
