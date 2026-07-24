<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../db_connect.php';

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
    echo json_encode(['status' => 'error', 'message' => 'Database setup failed: ' . $e->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'register_teacher') {
        $name     = trim($_POST['name'] ?? '');
        $email    = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $phone    = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $question = trim($_POST['security_question'] ?? '');
        $answer   = trim($_POST['security_answer'] ?? '');
        
        $subjectIds     = trim($_POST['subject_ids'] ?? '');
        $customSubjects = trim($_POST['custom_subjects'] ?? '');
        
        if (empty($name) || !$email || empty($phone) || empty($password) || empty($question) || empty($answer)) {
            echo json_encode(['status' => 'error', 'message' => 'All basic registration fields are required.']);
            exit;
        }
        
        try {
            // Check if email already exists across role tables
            $tablesToCheck = ['admins', 'teachers', 'parents', 'students', 'timetablers', 'accounts_officers'];
            foreach ($tablesToCheck as $tbl) {
                $chkUser = $pdo->prepare("SELECT COUNT(*) FROM `$tbl` WHERE email = ?");
                $chkUser->execute([$email]);
                if ($chkUser->fetchColumn() > 0) {
                    echo json_encode(['status' => 'error', 'message' => 'This email address is already registered in the system.']);
                    exit;
                }
            }
            
            // Check if email already exists in pending_teachers table
            $chkPending = $pdo->prepare("SELECT COUNT(*) FROM pending_teachers WHERE email = ? AND status = 'pending'");
            $chkPending->execute([$email]);
            if ($chkPending->fetchColumn() > 0) {
                echo json_encode(['status' => 'error', 'message' => 'You already have a pending registration request. Please wait for admin approval.']);
                exit;
            }
            
            // Hash password and insert
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO pending_teachers (name, email, phone, password, security_question, security_answer, subject_ids, custom_subjects) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $hashedPassword, $question, $answer, $subjectIds, $customSubjects]);
            
            // Dispatch Admin System Notification
            try {
                // Fetch subject names for notification
                $subjNames = [];
                if (!empty($subjectIds)) {
                    $stmtSub = $pdo->query("SELECT id, name FROM subject_areas");
                    $subjects = $stmtSub->fetchAll(PDO::FETCH_KEY_PAIR);
                    foreach (explode(',', $subjectIds) as $sId) {
                        if (isset($subjects[$sId])) {
                            $subjNames[] = $subjects[$sId];
                        }
                    }
                }
                $subjList = implode(', ', $subjNames);
                
                $notifMsg = "Teacher " . $name . " has requested registration.\n";
                $notifMsg .= "Email: " . $email . " | Phone: " . $phone . "\n";
                if (!empty($subjList)) {
                    $notifMsg .= "Teaching Subjects: " . $subjList . "\n";
                }
                if (!empty($customSubjects)) {
                    $notifMsg .= "Suggested New Subjects: " . $customSubjects . "\n";
                }
                $notifMsg .= "Please open the 'Role Delegation' panel to review and approve.";

                $notifStmt = $pdo->prepare("INSERT INTO system_notifications (sender_name, recipient_role, title, message) VALUES ('System', 'admin', ?, ?)");
                $notifStmt->execute([
                    "New Teacher Registration: " . $name,
                    $notifMsg
                ]);
            } catch (\Exception $notifEx) {
                // Ignore notifications failure so registration still succeeds
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Registration request submitted successfully! Your account is pending admin approval.'
            ]);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Registration failed: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>
