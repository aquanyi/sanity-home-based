<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../security.php';
start_secure_session();
require_once '../db_connect.php';
require_once '../mail_helper.php';

// Auth Guard - Only admin may create accounts/roles
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !in_array($_SESSION['user_role'] ?? '', ['admin', 'timetabler'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Admin access required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    validate_csrf_token($_POST['csrf_token'] ?? '', true);

    $name     = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
    $email    = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $phone    = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_SPECIAL_CHARS);
    $password = $_POST['password'] ?? '';
    $role     = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_SPECIAL_CHARS);

    if (empty($name) || !$email || empty($phone) || !in_array($role, ['timetabler', 'teacher', 'admin', 'accounts'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid inputs. Name, real email, phone, and role are required.']);
        exit;
    }

    // Use provided password or auto-generate default password
    $defaultPasswordUsed = false;
    if (empty($password)) {
        $password = 'SHTA' . rand(1000, 9999);
        $defaultPasswordUsed = true;
    }

    try {
        // Generate unique Staff ID
        $prefix_map = [
            'teacher' => 'TCH',
            'timetabler' => 'TMT',
            'accounts' => 'ACC',
            'admin' => 'ADM',
        ];
        $prefix = $prefix_map[$role] ?? 'STF';
        
        $table_map = [
            'admin' => 'admins',
            'timetabler' => 'timetablers',
            'teacher' => 'teachers',
            'accounts' => 'accounts_officers'
        ];
        $table = $table_map[$role] ?? 'teachers';

        $staffId = null;
        $counter = rand(100, 999);
        while (true) {
            $candidate = $prefix . '-' . date('Y') . '-' . sprintf('%03d', $counter);
            
            // Check uniqueness in target table
            $chk = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE staff_id = ?");
            $chk->execute([$candidate]);
            if ($chk->fetchColumn() == 0) {
                $staffId = $candidate;
                break;
            }
            $counter++;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO `$table` (staff_id, name, email, phone, password, must_change_password) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$staffId, $name, $email, $phone, $hashedPassword]);
        $newUserId = $pdo->lastInsertId();

        // Save teaching subjects if the role is teacher
        if ($role === 'teacher' && isset($_POST['subject_ids']) && is_array($_POST['subject_ids'])) {
            $subStmt = $pdo->prepare("INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)");
            foreach ($_POST['subject_ids'] as $subId) {
                $subIdInt = filter_var($subId, FILTER_VALIDATE_INT);
                if ($subIdInt) {
                    $subStmt->execute([$newUserId, $subIdInt]);
                }
            }
        }

        // ── Send Welcome Email to New Staff Member ────────────────────────────
        $portalHash = $role;
        if ($role === 'timetabler') $portalHash = 'timetable';
        
        $portalUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                   . '://' . ($_SERVER['HTTP_HOST'] ?? 'sanityeducation.com')
                   . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/\\') . '/login.html#' . $portalHash;

        $roleName = ucfirst($role);
        if ($role === 'accounts') $roleName = 'Accounts Officer';
        
        $emailContent = "
<h2 style='color:#4A0E17;margin-top:0;'>Welcome to Sanity Homebased Tuition Academy!</h2>
<p>Dear <strong>{$name}</strong>,</p>
<p>An official <strong>{$roleName}</strong> account has been provisioned for you by the administration.</p>

<table style='width:100%;background:#F8F9FA;border-radius:8px;border:1px solid #E9ECEF;padding:0;border-collapse:collapse;margin:20px 0;'>
  <tr><td style='padding:14px 18px;border-bottom:1px solid #E9ECEF;'><span style='color:#6C757D;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;'>Staff ID</span><br><strong style='font-size:16px;font-family:monospace;letter-spacing:1px;color:#4A0E17;'>{$staffId}</strong></td></tr>
  <tr><td style='padding:14px 18px;border-bottom:1px solid #E9ECEF;'><span style='color:#6C757D;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;'>Login Email</span><br><strong style='font-size:15px;'>{$email}</strong></td></tr>
  <tr><td style='padding:14px 18px;'><span style='color:#6C757D;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;'>Temporary Password</span><br><strong style='font-size:15px;font-family:monospace;letter-spacing:1px;'>{$password}</strong></td></tr>
</table>

<p style='background:#FFF3CD;border:1px solid #FFEEBA;border-radius:6px;padding:12px 16px;color:#856404;font-size:14px;'>
  &#9888; Please log in and change your password immediately to secure your account.
</p>

<p style='margin-top:28px;'>Access your portal here:</p>
<p><a href='{$portalUrl}' style='display:inline-block;background:#4A0E17;color:#E5A93B;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:600;letter-spacing:0.5px;'>💼 Login to {$roleName} Portal</a></p>
";

        $emailBody = buildEmailTemplate("Your {$roleName} Account", $emailContent);
        $emailSent = sendMail(
            $email,
            "Welcome to Sanity Homebased Tuition Academy – Your {$roleName} Account",
            $emailBody,
            MAIL_ADMIN_FROM,
            MAIL_SCHOOL_NAME,
            true
        );
        // ─────────────────────────────────────────────────────────────────────

        $msg = ucfirst($role) . " account for {$name} provisioned successfully with Staff ID {$staffId}.";
        if ($emailSent) {
            $msg .= " A welcome email with credentials has been sent.";
        }

        echo json_encode([
            'status'           => 'success',
            'message'          => $msg,
            'user_id'          => $newUserId,
            'staff_id'         => $staffId,
            'default_password' => $password,
            'email_sent'       => $emailSent
        ]);
    } catch (\PDOException $e) {
        if ($e->getCode() == 23000) {
            echo json_encode(['status' => 'error', 'message' => 'Email or Staff ID already exists in the system.']);
        } else {
            error_log('[SHTA API CREATE ROLE ERROR] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'A server error occurred. Please contact support.']);
        }
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>
