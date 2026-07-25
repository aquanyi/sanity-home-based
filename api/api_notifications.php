<?php
/**
 * api_notifications.php
 * Handles system and user notifications & internal messaging across all roles.
 * Supports targeted role and specific user recipients.
 */
header('Content-Type: application/json; charset=utf-8');
require_once '../security.php';
start_secure_session();
require_once '../db_connect.php';
require_once '../mail_helper.php';

// Auth Guard - Authenticated users only
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Please log in.']);
    exit;
}
session_write_close();

// Auto-create & update system_notifications table schema
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_notifications (
            id                 INT AUTO_INCREMENT PRIMARY KEY,
            sender_id          INT NULL,
            sender_name        VARCHAR(150) NOT NULL DEFAULT 'System',
            recipient_role     VARCHAR(50) NOT NULL DEFAULT 'all',
            recipient_user_id  INT NULL,
            title              VARCHAR(255) NOT NULL,
            message            TEXT NOT NULL,
            is_read            TINYINT DEFAULT 0,
            created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $q1 = $pdo->query("SHOW COLUMNS FROM system_notifications LIKE 'recipient_user_id'");
    if (!$q1->fetch()) {
        $pdo->exec("ALTER TABLE system_notifications ADD COLUMN recipient_user_id INT NULL AFTER recipient_role");
    }
    $q2 = $pdo->query("SHOW COLUMNS FROM system_notifications LIKE 'sender_id'");
    if (!$q2->fetch()) {
        $pdo->exec("ALTER TABLE system_notifications ADD COLUMN sender_id INT NULL AFTER id");
    }
} catch (\PDOException $e) {
    // Continue
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'GET' ? ($_GET['action'] ?? '') : ($_POST['action'] ?? '');

if ($method === 'GET') {
    if ($action === 'get_notifications') {
        $role     = $_SESSION['user_role'] ?? '';
        $userId   = (int)($_SESSION['user_id'] ?? 0);
        $userName = $_SESSION['user_name'] ?? '';
        $unread_only = isset($_GET['unread']) && $_GET['unread'] == 1;

        try {
            // Fetch notifications applicable to this role or 'all', strictly enforcing target_user_id
            $query = "
                SELECT *, UNIX_TIMESTAMP(created_at) as created_at_ts 
                FROM system_notifications 
                WHERE (recipient_role = 'all' OR recipient_role = ?)
                AND (recipient_user_id IS NULL OR recipient_user_id = 0 OR recipient_user_id = ?)
            ";
            if ($unread_only) {
                $query .= " AND is_read = 0";
            }
            $query .= " ORDER BY created_at DESC LIMIT 50";

            $stmt = $pdo->prepare($query);
            $stmt->execute([$role, $userId]);

            $rows = $stmt->fetchAll();
            $notifications = [];
            foreach ($rows as $n) {
                if (isset($n['created_at_ts'])) {
                    $n['created_at'] = gmdate('Y-m-d\TH:i:s\Z', $n['created_at_ts']);
                }
                $notifications[] = $n;
            }

            echo json_encode(['status' => 'success', 'notifications' => $notifications]);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'get_users_by_role') {
        $role = $_GET['role'] ?? '';
        $table_map = [
            'admin'      => 'admins',
            'timetabler' => 'timetablers',
            'teacher'    => 'teachers',
            'parent'     => 'parents',
            'student'    => 'students',
            'accounts'   => 'accounts_officers'
        ];
        $tbl = $table_map[$role] ?? '';
        if (!$tbl) {
            echo json_encode(['status' => 'success', 'users' => []]);
            exit;
        }
        try {
            $stmt = $pdo->query("SELECT id, name, email FROM `$tbl` ORDER BY name ASC");
            echo json_encode(['status' => 'success', 'users' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'get_parent_teachers') {
        $role   = $_SESSION['user_role'] ?? '';
        $userId = (int)($_SESSION['user_id'] ?? 0);

        if ($role !== 'parent') {
            echo json_encode(['status' => 'error', 'message' => 'Only parents may fetch assigned teachers.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT DISTINCT t.id, t.name, t.email
                FROM teachers t
                JOIN timetable_slots ts ON t.id = ts.teacher_id
                JOIN student_profiles sp ON ts.student_id = sp.id
                WHERE sp.parent_id = ?
                ORDER BY t.name ASC
            ");
            $stmt->execute([$userId]);
            $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['status' => 'success', 'teachers' => $teachers]);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}

if ($method === 'POST') {
    if ($action === 'mark_as_read') {
        $role   = $_SESSION['user_role'] ?? '';
        $userId = (int)($_SESSION['user_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("
                UPDATE system_notifications
                SET is_read = 1
                WHERE (recipient_role = 'all' OR (recipient_role = ? AND (recipient_user_id IS NULL OR recipient_user_id = ?))) AND is_read = 0
            ");
            $stmt->execute([$role, $userId]);
            echo json_encode(['status' => 'success', 'message' => 'Notifications marked as read.']);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'clear_all' || $action === 'mark_all_read') {
        $role     =$_SESSION['user_role'] ?? '';
        $userId   = (int)($_SESSION['user_id'] ?? 0);
        $userName =$_SESSION['user_name'] ?? '';
        
        try {
            $stmt =$pdo->prepare("
                DELETE FROM system_notifications 
                WHERE recipient_role = 'all' 
                   OR (recipient_role = ? AND (recipient_user_id IS NULL OR recipient_user_id = ?))
                   OR sender_id = ? 
                   OR sender_name LIKE ?
            ");
            $stmt->execute([$role,$userId, $userId, '%' .$userName . '%']);
            echo json_encode(['status' => 'success', 'message' => 'All notifications permanently cleared.']);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'send_notification') {
        // Validate CSRF token if provided
        if (isset($_POST['csrf_token'])) {
            validate_csrf_token($_POST['csrf_token'], true);
        }

        $sender_role = $_SESSION['user_role'] ?? '';
        $sender_id   = (int)($_SESSION['user_id'] ?? 0);
        $sender_name = ($_SESSION['user_name'] ?? 'User') . ' (' . ucfirst($sender_role) . ')';

        $recipient_role    = trim($_POST['recipient_role'] ?? 'all');
        $target_user_id    = $_POST['target_user_id'] ?? 'all';
        $target_user_id    = ($target_user_id === 'all' || empty($target_user_id)) ? null : (int)$target_user_id;
        $recipient_user_id = $target_user_id;
        $title             = trim($_POST['title'] ?? '');
        $message           = trim($_POST['message'] ?? '');

        // Parent restriction: Parents can ONLY send to 'admin' OR to specific assigned teachers!
        if ($sender_role === 'parent') {
            if ($recipient_role !== 'admin' && $recipient_role !== 'teacher') {
                echo json_encode(['status' => 'error', 'message' => 'Parents can only send messages to Admin or their assigned teachers.']);
                exit;
            }
            if ($recipient_role === 'teacher') {
                if (!$recipient_user_id) {
                    echo json_encode(['status' => 'error', 'message' => 'Please select an assigned teacher.']);
                    exit;
                }
                // Verify teacher is assigned to parent's student
                $chkAssigned = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM timetable_slots ts
                    JOIN student_profiles sp ON ts.student_id = sp.id
                    WHERE sp.parent_id = ? AND ts.teacher_id = ?
                ");
                $chkAssigned->execute([$sender_id, $recipient_user_id]);
                if ($chkAssigned->fetchColumn() == 0) {
                    echo json_encode(['status' => 'error', 'message' => 'Selected teacher is not assigned to teach your student.']);
                    exit;
                }
            }
        }

        if (!$title || !$message) {
            echo json_encode(['status' => 'error', 'message' => 'Title and Message are required.']);
            exit;
        }

        try {
            // 1. Insert into database for portal dashboard viewing
            $stmt =$pdo->prepare("
                INSERT INTO system_notifications (sender_id, sender_name, recipient_role, recipient_user_id, title, message)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$sender_id,$sender_name, $recipient_role,$recipient_user_id, $title,$message]);

            // 2. Dispatch Email Notification(s) with Safe Exception Trapping
            try {
                $emailRecipients = [];

                if ($recipient_user_id) {
                    $table_map = [                         'admin'             => 'admins',                         'timetabler'        => 'timetablers',                         'teacher'           => 'teachers',                         'parent'            => 'parents',                         'student'           => 'students',                         'accounts'          => 'accounts_officers',                         'accounts_officer'  => 'accounts_officers'                     ];$targetTable = $table_map[$recipient_role] ?? '';
                    if ($targetTable) {
                        try {
                            $uStmt =$pdo->prepare("SELECT email, name FROM `$targetTable` WHERE id = ?");
                            $uStmt->execute([$recipient_user_id]);
                            $userRow =$uStmt->fetch(PDO::FETCH_ASSOC);
                            if ($userRow && !empty($userRow['email'])) {
                                $emailRecipients[] =$userRow;
                            }
                        } catch (\Exception $ex) {}
                    }
                    
                    // Fallback search if empty
                    if (empty($emailRecipients)) {$fallbackTables = ['admins', 'timetablers', 'teachers', 'parents', 'students', 'accounts_officers'];
                        foreach ($fallbackTables as$tbl) {
                            try {
                                $uStmt =$pdo->prepare("SELECT email, name FROM `$tbl` WHERE id = ?");
                                $uStmt->execute([$recipient_user_id]);
                                $userRow =$uStmt->fetch(PDO::FETCH_ASSOC);
                                if ($userRow && !empty($userRow['email'])) {
                                    $emailRecipients[] =$userRow;
                                    break;
                                }
                            } catch (\Exception $e2) {}
                        }
                    }
                } else {
                    // Group broadcast logic...
                    $tables = ($recipient_role === 'all') 
                        ? ['admins', 'timetablers', 'teachers', 'parents', 'students', 'accounts_officers']
                        : [$table_map[$recipient_role] ?? $recipient_role];

                    foreach ($tables as$tbl) {
                        try {
                            $allRows =$pdo->query("SELECT email, name FROM `$tbl` WHERE email IS NOT NULL AND email != ''")->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($allRows as$r) {
                                $emailRecipients[] =$r;
                            }
                        } catch (\Exception $ex) {}
                    }
                }

                // Deduplicate and send
                $uniqueEmails = [];
                foreach ($emailRecipients as$rec) {
                    $em = strtolower(trim($rec['email'] ?? ''));
                    if (!empty($em) && !isset($uniqueEmails[$em])) {$uniqueEmails[$em] =$rec['name'] ?? 'User';
                    }
                }

                foreach ($uniqueEmails as $emAddress =>$emName) {
                    if (function_exists('sendMail')) {
                        $emailBody = "<p>Dear <strong>" . htmlspecialchars($emName) . "</strong>,</p><p>You have received a new notification from <strong>" . htmlspecialchars($sender_name) . "</strong>:</p><div style='background:#FAF7F2; padding:15px; border-left:4px solid #4A0E17; margin:15px 0;'><h3>" . htmlspecialchars($title) . "</h3><p>" . nl2br(htmlspecialchars($message)) . "</p></div>";
                        @sendMail($emAddress, $title,$emailBody, MAIL_INFO_FROM, MAIL_SCHOOL_NAME . ' — Notifications', true);
                    }
                }
            } catch (\Exception $mailEx) {
                error_log('[SHTA EMAIL DISPATCH WARNING] ' . $mailEx->getMessage());
            }

            echo json_encode(['status' => 'success', 'message' => 'Message dispatched successfully to portal and email inbox!']);
        } catch (\PDOException $e) {
            error_log('[SHTA NOTIFICATION WRITE ERROR] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Failed to dispatch message.']);
        }
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid endpoint action.']);
?>
