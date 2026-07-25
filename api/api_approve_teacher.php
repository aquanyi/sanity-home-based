<?php
header('Content-Type: application/json; charset=utf-8');
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'PHP FATAL ERROR: ' . $error['message'] . ' in ' . basename($error['file']) . ':' . $error['line']]);
        exit;
    }
});
require_once '../security.php';
start_secure_session();
require_once '../db_connect.php';

// Only require mail_helper.php if we actually need to send an email,
// to prevent 500 errors on page load if vendor/autoload.php is missing.

// Auto-create pending_teachers table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS pending_teachers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        phone VARCHAR(30) NOT NULL,
        password VARCHAR(255) NOT NULL,
        security_question VARCHAR(255) NOT NULL,
        security_answer VARCHAR(255) NOT NULL,
        subject_ids VARCHAR(255) NOT NULL,
        custom_subjects VARCHAR(255) NULL,
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (\PDOException $e) {
    // Continue
}

// Auth Guard - Only admin may approve/decline registrations
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !in_array($_SESSION['user_role'] ?? '', ['admin', 'timetabler'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Admin access required.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

if ($action === 'get_pending_teachers') {
    try {
        // Fetch all subject areas to map IDs to names
        $subjects = $pdo->query("SELECT id, name FROM subject_areas")->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $stmt = $pdo->query("SELECT * FROM pending_teachers WHERE status = 'pending' ORDER BY created_at DESC");
        $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Map subject IDs to names for readability
        foreach ($pending as &$p) {
            $ids = array_filter(explode(',', $p['subject_ids'] ?? ''));
            $names = [];
            foreach ($ids as $id) {
                if (isset($subjects[$id])) {
                    $names[] = $subjects[$id];
                }
            }
            $p['subject_names'] = implode(', ', $names);
        }
        
        echo json_encode(['status' => 'success', 'pending' => $pending]);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch pending teachers: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'approve_teacher' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid teacher request ID.']);
        exit;
    }
    
    try {
        // Fetch pending registration
        $stmt = $pdo->prepare("SELECT * FROM pending_teachers WHERE id = ? AND status = 'pending'");
        $stmt->execute([$id]);
        $pending = $stmt->fetch();
        
        if (!$pending) {
            echo json_encode(['status' => 'error', 'message' => 'Registration request not found or already processed.']);
            exit;
        }
        
        // Ensure email isn't already in any role table
        $tablesToCheck = ['admins', 'teachers', 'parents', 'students', 'timetablers', 'accounts_officers'];
        foreach ($tablesToCheck as $tbl) {
            $chkUser = $pdo->prepare("SELECT COUNT(*) FROM `$tbl` WHERE email = ?");
            $chkUser->execute([$pending['email']]);
            if ($chkUser->fetchColumn() > 0) {
                echo json_encode(['status' => 'error', 'message' => 'A user with this email address already exists in the system.']);
                exit;
            }
        }
        
        // Generate unique Staff ID
        $prefix = 'TCH';
        $staffId = null;
        $counter = rand(100, 999);
        while (true) {
            $candidate = $prefix . '-' . date('Y') . '-' . sprintf('%03d', $counter);
            $chk = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE staff_id = ?");
            $chk->execute([$candidate]);
            if ($chk->fetchColumn() == 0) {
                $staffId = $candidate;
                break;
            }
            $counter++;
        }
        
        $pdo->beginTransaction();
        
        // Insert into users
        $insUser = $pdo->prepare("INSERT INTO teachers (staff_id, name, email, phone, password, security_question, security_answer, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
        $insUser->execute([
            $staffId,
            $pending['name'],
            $pending['email'],
            $pending['phone'],
            $pending['password'],
            $pending['security_question'],
            $pending['security_answer']
        ]);
        
        $newUserId = $pdo->lastInsertId();
        
        // Link existing and custom subjects intelligently (handles numeric IDs & text names)
        $rawSubjects = array_merge(
            !empty($pending['subject_ids']) ? explode(',', $pending['subject_ids']) : [],
            !empty($pending['custom_subjects']) ? explode(',', $pending['custom_subjects']) : []
        );
        if (!empty($rawSubjects)) {
            $linkStmt = $pdo->prepare("INSERT IGNORE INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)");
            $chkSub   = $pdo->prepare("SELECT id FROM subject_areas WHERE LOWER(name) = ? OR LOWER(name) LIKE ? LIMIT 1");
            $insSub   = $pdo->prepare("INSERT INTO subject_areas (name) VALUES (?)");

            foreach ($rawSubjects as $rawItem) {
                $rawItem = trim($rawItem);
                if (empty($rawItem)) continue;

                if (is_numeric($rawItem)) {
                    $linkStmt->execute([$newUserId, (int)$rawItem]);
                } else {
                    $chkSub->execute([strtolower($rawItem), '%' . strtolower($rawItem) . '%']);
                    $subjId = $chkSub->fetchColumn();
                    if (!$subjId) {
                        $insSub->execute([$rawItem]);
                        $subjId = $pdo->lastInsertId();
                    }
                    if ($subjId) {
                        $linkStmt->execute([$newUserId, (int)$subjId]);
                    }
                }
            }
        }
        
        // Update request status
        $updPending = $pdo->prepare("UPDATE pending_teachers SET status = 'approved' WHERE id = ?");
        $updPending->execute([$id]);
        
        $pdo->commit();

        // ── Send Approval Email to Teacher ────────────────────────────────────
        if (file_exists('../vendor/autoload.php')) {
            require_once '../mail_helper.php';
            
            $portalUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                       . '://' . ($_SERVER['HTTP_HOST'] ?? 'sanityeducation.com')
                       . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/\\') . '/login.html#teacher';

            $emailContent = "
<h2 style='color:#4A0E17;margin-top:0;'>🎉 Congratulations — Your Application Has Been Approved!</h2>
<p>Dear <strong>{$pending['name']}</strong>,</p>
<p>We are pleased to inform you that your registration as a teacher at <strong>Sanity Homebased Tuition Academy</strong> has been reviewed and <strong>approved</strong> by our administration.</p>

<table style='width:100%;background:#F8F9FA;border-radius:8px;border:1px solid #E9ECEF;padding:0;border-collapse:collapse;margin:20px 0;'>
  <tr><td style='padding:14px 18px;border-bottom:1px solid #E9ECEF;'><span style='color:#6C757D;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;'>Staff ID</span><br><strong style='font-size:16px;font-family:monospace;letter-spacing:1px;color:#4A0E17;'>{$staffId}</strong></td></tr>
  <tr><td style='padding:14px 18px;border-bottom:1px solid #E9ECEF;'><span style='color:#6C757D;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;'>Login Email</span><br><strong style='font-size:15px;'>{$pending['email']}</strong></td></tr>
  <tr><td style='padding:14px 18px;'><span style='color:#6C757D;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;'>Password</span><br><span style='font-size:14px;color:#495057;'>Use the password you set during registration.</span></td></tr>
</table>

<p style='background:#D4EDDA;border:1px solid #C3E6CB;border-radius:6px;padding:12px 16px;color:#155724;font-size:14px;'>
  ✅ Your account is now active. You can log in to the teacher portal immediately.
</p>

<p style='margin-top:24px;'>Access the teacher portal here:</p>
<p><a href='{$portalUrl}' style='display:inline-block;background:#4A0E17;color:#E5A93B;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:600;letter-spacing:0.5px;'>📚 Login to Teacher Portal</a></p>

<p style='margin-top:24px;font-size:13px;color:#6C757D;'>If you have any questions, please contact us at <a href='mailto:" . MAIL_CONTACT_EMAIL . "' style='color:#4A0E17;'>" . MAIL_CONTACT_EMAIL . "</a> or call " . MAIL_CONTACT_PHONE . ".</p>
<p style='font-size:13px;color:#6C757D;'>We look forward to working with you!</p>
";
            
            $emailBody = buildEmailTemplate('Your Teacher Application Has Been Approved', $emailContent);
            $emailSent = sendMail(
                $pending['email'],
                'Your Teacher Application Has Been Approved - Sanity Homebased Tuition Academy',
                $emailBody,
                MAIL_ADMIN_FROM,
                MAIL_SCHOOL_NAME,
                true
            );
        } else {
            $emailSent = false;
        }
        // ─────────────────────────────────────────────────────────────────────

        $msg = "Teacher account provisioned successfully! Staff ID: {$staffId}.";
        $msg .= $emailSent
            ? " An approval email has been sent to {$pending['email']}."
            : " (Note: approval email could not be delivered — please inform the teacher manually.)";

        echo json_encode([
            'status'     => 'success',
            'message'    => $msg,
            'email_sent' => $emailSent
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => 'Transaction failed: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'decline_teacher' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid teacher request ID.']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM pending_teachers WHERE id = ?");
        $stmt->execute([$id]);
        $declined = $stmt->fetch();

        $updStmt = $pdo->prepare("UPDATE pending_teachers SET status = 'declined' WHERE id = ?");
        $updStmt->execute([$id]);

        // ── Send Decline Email to Teacher ─────────────────────────────────────
        if ($declined && !empty($declined['email'])) {
            if (file_exists('../vendor/autoload.php')) {
                require_once '../mail_helper.php';
                
                $emailContent = "
<h2 style='color:#4A0E17;margin-top:0;'>Update on Your Teacher Application</h2>
<p>Dear <strong>{$declined['name']}</strong>,</p>
<p>Thank you for your interest in joining <strong>Sanity Homebased Tuition Academy</strong> as a teacher.</p>
<p>After careful review, we regret to inform you that your application has <strong style='color:#DC3545;'>not been approved</strong> at this time.</p>

<p style='background:#F8D7DA;border:1px solid #F5C6CB;border-radius:6px;padding:12px 16px;color:#721C24;font-size:14px;'>
  This may be due to current staffing requirements or qualifications matching. We encourage you to re-apply in the future.
</p>

<p style='margin-top:20px;'>If you believe this decision was made in error or would like to discuss further, please reach out to us directly:</p>
<p><strong>Email:</strong> <a href='mailto:" . MAIL_CONTACT_EMAIL . "' style='color:#4A0E17;'>" . MAIL_CONTACT_EMAIL . "</a><br>
<strong>Phone:</strong> " . MAIL_CONTACT_PHONE . "</p>

<p style='margin-top:20px;font-size:13px;color:#6C757D;'>We appreciate your interest in our academy and wish you the best in your professional journey.</p>
";
                
                $emailBody = buildEmailTemplate('Update on Your Teacher Application', $emailContent);
                sendMail(
                    $declined['email'],
                    'Update on Your Teacher Application – Sanity Homebased Tuition Academy',
                    $emailBody,
                    MAIL_ADMIN_FROM,
                    MAIL_SCHOOL_NAME,
                    true
                );
            }
        }
        // ─────────────────────────────────────────────────────────────────────

        echo json_encode(['status' => 'success', 'message' => 'Teacher registration request declined.']);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to decline request: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request action.']);
?>
