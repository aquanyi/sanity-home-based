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

// Auth Guard - Authenticated users only
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Please log in.']);
    exit;
}

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
            // Fetch notifications applicable to this role or 'all' or sent by this user or explicitly targeted to this user ID
            $query = "
                SELECT *, UNIX_TIMESTAMP(created_at) as created_at_ts 
                FROM system_notifications 
                WHERE (
                    recipient_role = 'all' 
                    OR (recipient_role = ? AND (recipient_user_id IS NULL OR recipient_user_id = ?))
                    OR sender_id = ? 
                    OR sender_name LIKE ?
                )
            ";
            if ($unread_only) {
                $query .= " AND is_read = 0";
            }
            $query .= " ORDER BY created_at DESC LIMIT 50";

            $stmt = $pdo->prepare($query);
            $stmt->execute([$role, $userId, $userId, '%' . $userName . '%']);

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

    if ($action === 'send_notification') {
        // Validate CSRF token if provided
        if (isset($_POST['csrf_token'])) {
            validate_csrf_token($_POST['csrf_token'], true);
        }

        $sender_role = $_SESSION['user_role'] ?? '';
        $sender_id   = (int)($_SESSION['user_id'] ?? 0);
        $sender_name = ($_SESSION['user_name'] ?? 'User') . ' (' . ucfirst($sender_role) . ')';

        $recipient_role    = trim($_POST['recipient_role'] ?? 'all');
        $recipient_user_id = filter_input(INPUT_POST, 'recipient_user_id', FILTER_VALIDATE_INT) ?: null;
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
            $stmt = $pdo->prepare("
                INSERT INTO system_notifications (sender_id, sender_name, recipient_role, recipient_user_id, title, message)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$sender_id, $sender_name, $recipient_role, $recipient_user_id, $title, $message]);
            echo json_encode(['status' => 'success', 'message' => 'Message dispatched successfully!']);
        } catch (\PDOException $e) {
            error_log('[SHTA NOTIFICATION WRITE ERROR] ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Failed to dispatch message.']);
        }
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid endpoint action.']);
?>
