<?php
header('Content-Type: text/html; charset=utf-8');
/**
 * first_login_setup.php
 * Mandatory first-time account security configuration (Password Change & Security Questions)
 */
require_once 'security.php';
start_secure_session();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.html');
    exit;
}


$userId = $_SESSION['user_id'];
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

if (empty($table)) {
    header('Location: logout.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM `$table` WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();


if (!$user) {
    header('Location: logout.php');
    exit;
}

$mustChangePassword = (isset($user['must_change_password']) && (int)$user['must_change_password'] === 1);
$needsSecQuestion = empty($user['security_question']);

function getRoleDashboardUrl(string $role): string {
    switch ($role) {
        case 'admin':
        case 'timetabler':
            return 'admin_dashboard.php';
        case 'teacher':
            return 'teacher_portal.php';
        case 'parent':
            return 'parent_portal.php';
        case 'student':
            return 'student_portal.php';
        case 'accounts':
            return 'accounts_dashboard.php';
        default:
            return 'portal.html';
    }
}

// If already configured, send directly to dashboard
if (!$mustChangePassword && !$needsSecQuestion && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . getRoleDashboardUrl($userRole));
    exit;
}

$error = '';
$success = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token
        validate_csrf_token($_POST['csrf_token'] ?? '', false);

        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $secQuestion     = filter_input(INPUT_POST, 'security_question', FILTER_SANITIZE_SPECIAL_CHARS);
        $secAnswer       = trim($_POST['security_answer'] ?? '');

        if ($mustChangePassword) {
            if (empty($newPassword) || strlen($newPassword) < 8) {
                $error = 'Password must be at least 8 characters long.';
            } elseif (!preg_match('/[0-9]/', $newPassword)) {
                $error = 'Password must contain at least one number.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'New passwords do not match.';
            }
        }

    if (empty($error)) {
        if (empty($secQuestion) || empty($secAnswer)) {
            $error = 'Please select a security question and provide an answer.';
        }
    }

    if (empty($error)) {
        try {
            $hashedAnswer = password_hash(strtolower(trim($secAnswer)), PASSWORD_DEFAULT);
            
            if ($mustChangePassword) {
                $hashedPass = password_hash($newPassword, PASSWORD_DEFAULT);
                $uStmt = $pdo->prepare("UPDATE `$table` SET password = ?, must_change_password = 0, security_question = ?, security_answer = ? WHERE id = ?");
                $uStmt->execute([$hashedPass, $secQuestion, $hashedAnswer, $userId]);
            } else {
                $uStmt = $pdo->prepare("UPDATE `$table` SET security_question = ?, security_answer = ? WHERE id = ?");
                $uStmt->execute([$secQuestion, $hashedAnswer, $userId]);
            }

            header('Location: ' . getRoleDashboardUrl($userRole));
            exit;

        } catch (\PDOException $e) {
            $error = 'Database error updating profile: ' . $e->getMessage();
        }
    }
}

$questionsList = [
    "What was the name of your first primary school?",
    "What is your mother's maiden name?",
    "What was the name of your first pet?",
    "In what city or town was your mother born?",
    "What was your childhood nickname?",
    "What is the name of your favorite childhood teacher?",
    "What was the make and model of your first car?",
    "What is the title of your favorite book?",
    "What was your high school mascot?",
    "What is your favorite food or dish?",
    "What is the street name where you grew up?",
    "What was the first concert or event you attended?",
    "In what city did your parents meet?",
    "What is your oldest sibling's middle name?",
    "What was the destination of your first holiday trip?"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Security Setup - S.H.T.A</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4A0E17;
            --primary-dark: #2A080D;
            --accent: #E5A93B;
            --cream: #FAF7F2;
            --white: #FFFFFF;
            --dark: #121214;
            --gray: #6C757D;
            --border: #E2E8F0;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background-color: var(--cream); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .setup-card { background: var(--white); border-radius: 20px; max-width: 550px; width: 100%; padding: 40px; box-shadow: 0 15px 35px rgba(0,0,0,0.08); border: 1px solid var(--border); }
        .setup-header { text-align: center; margin-bottom: 30px; }
        .setup-header .icon-badge { width: 70px; height: 70px; background: rgba(229,169,59,0.15); color: var(--accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 15px; }
        .setup-header h2 { color: var(--primary); font-size: 1.8rem; font-weight: 800; }
        .setup-header p { color: var(--gray); font-size: 0.95rem; margin-top: 5px; }
        .alert { background: #FEE2E2; border: 1px solid #FCA5A5; color: #991B1B; padding: 12px 16px; border-radius: 10px; font-size: 0.9rem; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 700; color: var(--primary-dark); font-size: 0.85rem; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px; }
        .input-box { position: relative; }
        .input-box i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #A0AEC0; }
        .input-box input, .input-box select { width: 100%; padding: 14px 16px 14px 46px; border: 2px solid var(--border); border-radius: 10px; font-size: 0.95rem; outline: none; background: #F7FAFC; transition: all 0.3s; }
        .input-box input:focus, .input-box select:focus { border-color: var(--accent); background: #FFF; box-shadow: 0 0 0 4px rgba(229,169,59,0.15); }
        .section-divider { border-top: 1px dashed var(--border); margin: 25px 0; position: relative; }
        .section-divider span { position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: #FFF; padding: 0 12px; font-size: 0.8rem; font-weight: 700; color: var(--gray); text-transform: uppercase; }
        .btn-submit { width: 100%; padding: 15px; background: linear-gradient(135deg, var(--primary) 0%, #30080E 100%); color: white; border: none; border-radius: 12px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: all 0.3s; box-shadow: 0 8px 20px rgba(74,14,23,0.25); }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 12px 25px rgba(74,14,23,0.35); }
    </style>
</head>
<body>

<div class="setup-card">
    <div class="setup-header">
        <div class="icon-badge"><i class="fa-solid fa-user-shield"></i></div>
        <h2>Account Security Setup</h2>
        <p>Welcome, <strong><?php echo htmlspecialchars($user['name']); ?></strong>! Please complete your security profile before continuing.</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert"><i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="first_login_setup.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
        <?php if ($mustChangePassword): ?>
            <div class="form-group">
                <label>New Password</label>
                <div class="input-box">
                    <input type="password" name="new_password" required placeholder="Enter new password (min 8 chars, 1 number)">
                    <i class="fa-solid fa-lock"></i>
                </div>
            </div>
            <div class="form-group">
                <label>Confirm New Password</label>
                <div class="input-box">
                    <input type="password" name="confirm_password" required placeholder="Re-enter new password">
                    <i class="fa-solid fa-check-double"></i>
                </div>
            </div>
            <div class="section-divider"><span>Security Recovery</span></div>
        <?php endif; ?>

        <div class="form-group">
            <label>Security Question (Select One)</label>
            <div class="input-box">
                <select name="security_question" required>
                    <option value="">-- Choose a Security Question --</option>
                    <?php foreach ($questionsList as $q): ?>
                        <option value="<?php echo htmlspecialchars($q); ?>"><?php echo htmlspecialchars($q); ?></option>
                    <?php endforeach; ?>
                </select>
                <i class="fa-solid fa-question-circle"></i>
            </div>
        </div>

        <div class="form-group">
            <label>Your Security Answer</label>
            <div class="input-box">
                <input type="text" name="security_answer" required placeholder="Answer (case-insensitive for recovery)">
                <i class="fa-solid fa-key"></i>
            </div>
        </div>

        <button type="submit" class="btn-submit"><i class="fa-solid fa-shield-halved"></i> Save & Continue to Dashboard</button>
    </form>
</div>

</body>
</html>
