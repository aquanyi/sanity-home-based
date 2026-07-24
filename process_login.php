<?php
header('Content-Type: text/html; charset=utf-8');
/**
 * process_login.php
 * Handles login form submissions and session management for all roles.
 */
require_once 'security.php';
start_secure_session();
require_once 'db_connect.php';


// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: ' . getDashboardUrl($_SESSION['user_role']));
    exit;
}

// GET → send to login page
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Location: login.html');
    exit;
}

// POST → authenticate
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';
    $role_hint  = trim($_POST['role'] ?? '');

    if (empty($identifier) || empty($password)) {
        redirectWithError('Email/username and password are required.', $role_hint);
    }

    // ── Brute-force protection ───────────────────────────────────────────
    $lockout_seconds = check_login_attempts($pdo);
    if ($lockout_seconds > 0) {
        $mins = ceil($lockout_seconds / 60);
        redirectWithError("Too many failed attempts. Please try again in {$mins} minute(s).", $role_hint);
    }

    try {
        $table = roleToTable($role_hint);
        if (empty($table)) {
            record_login_failure($pdo);
            redirectWithError('Invalid role type specified.', $role_hint);
        }

        // Look up user in the role-specific table by email or staff_id (and admission_no for students)
        if ($table === 'students') {
            $stmt = $pdo->prepare("
                SELECT * FROM `$table` 
                WHERE email = ? 
                   OR staff_id = ?
                   OR admission_no = ?
                LIMIT 1
            ");
            $stmt->execute([$identifier, $identifier, $identifier]);
        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM `$table` 
                WHERE email = ? 
                   OR staff_id = ?
                LIMIT 1
            ");
            $stmt->execute([$identifier, $identifier]);
        }
        $user = $stmt->fetch();

        if (!$user) {
            record_login_failure($pdo);
            redirectWithError('Invalid credentials. Please check your email and password.', $role_hint);
        }

        if (!password_verify($password, $user['password'])) {
            record_login_failure($pdo);
            redirectWithError('Invalid credentials. Please check your email and password.', $role_hint);
        }
        
        // Explicitly set role since the table no longer contains the role column
        $user['role'] = $role_hint;

        // Strict matching: Only allow users to log in from the tab that matches their registered role
        if (!empty($role_hint)) {
            $allowed = false;
            
            // The hidden form 'role' values match DB roles directly:
            //   admin form sends 'admin', parent sends 'parent', teacher sends 'teacher',
            //   timetabler sends 'timetabler', accounts sends 'accounts'
            if ($user['role'] === $role_hint) {
                $allowed = true;
            }

            if (!$allowed) {
                $tabName = roleToTabName($user['role']);
                redirectWithError("Access denied: Your account is registered as '{$user['role']}'. Please log in from the {$tabName} tab instead.", $role_hint);
            }
        }

        // All good — regenerate session ID (prevents session fixation)
        session_regenerate_id(true);
        clear_login_attempts($pdo);

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['logged_in']  = true;

        // Check if user needs first-time password change or security question setup
        $mustChange = isset($user['must_change_password']) ? (int)$user['must_change_password'] : 0;
        $noSecQuestion = empty($user['security_question']);

        if ($mustChange === 1 || $noSecQuestion) {
            header('Location: first_login_setup.php');
            exit;
        }

        header('Location: ' . getDashboardUrl($user['role']));
        exit;

    } catch (\PDOException $e) {
        error_log('[SHTA LOGIN ERROR] ' . $e->getMessage());
        redirectWithError('A server error occurred. Please try again.', $role_hint);
    }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function getDashboardUrl($role) {
    switch ($role) {
        case 'admin':
        case 'timetabler':
            return 'admin_dashboard.php?fresh=1';
        case 'teacher':
            return 'teacher_portal.php?fresh=1';
        case 'parent':
            return 'parent_portal.php?fresh=1';
        case 'student':
            return 'student_portal.php?fresh=1';
        case 'accounts':
            return 'accounts_dashboard.php?fresh=1';
        default:
            return 'login.html';
    }
}

/**
 * Maps a DB role string back to the login.html hash fragment for that tab.
 * The login page form IDs are: form-admin, form-parent, form-teachers, form-timetable, form-accounts
 * So the hash values are: #admin, #parent, #teachers, #timetable, #accounts
 */

function roleToTable($role) {
    switch ($role) {
        case 'admin': return 'admins';
        case 'timetabler': return 'timetablers';
        case 'teacher': return 'teachers';
        case 'parent': return 'parents';
        case 'student': return 'students';
        case 'accounts': return 'accounts_officers';
        default: return '';
    }
}


function roleToHash($role) {
    switch ($role) {
        case 'admin':
            return 'admin';
        case 'parent':
            return 'parent';
        case 'student':
            return 'student';
        case 'teacher':
            return 'teachers';
        case 'timetabler':
            return 'timetable';
        case 'accounts':
            return 'accounts';
        default:
            return 'admin';
    }
}

function roleToTabName($role) {
    switch ($role) {
        case 'admin':
            return 'Admin';
        case 'parent':
            return 'Parent';
        case 'student':
            return 'Student';
        case 'teacher':
            return 'Teacher';
        case 'timetabler':
            return 'Academic Operations Coordinator';
        case 'accounts':
            return 'Accounts';
        default:
            return 'correct';
    }
}

function redirectWithError(string $msg, string $role): void {
    $encoded = urlencode($msg);
    // Map the form's role value back to the correct login page hash
    $hash = roleToHash($role);
    header("Location: login.html?error={$encoded}#{$hash}");
    exit;
}
?>
