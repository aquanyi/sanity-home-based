<?php
header('Content-Type: text/html; charset=utf-8');
/**
 * forgot_password.php
 * Self-service password recovery system via security questions.
 */
require_once 'security.php';
start_secure_session();
require_once 'db_connect.php';

$step       = 1;
$error      = '';
$success    = '';
$user       = null;
$identifier = '';

// ── Tab → Table mapping ───────────────────────────────────────────────────────
function forgotPasswordTabToTable(string $tab): string {
    switch ($tab) {
        case 'admin':     return 'admins';
        case 'timetable': return 'timetablers';
        case 'teachers':  return 'teachers';
        case 'parent':    return 'parents';
        case 'student':   return 'students';
        case 'accounts':  return 'accounts_officers';
        default:          return 'parents';
    }
}

// Validate and sanitise tab parameter
$valid_tabs = ['admin', 'parent', 'student', 'teachers', 'timetable', 'accounts'];
$tab        = $_GET['tab'] ?? $_POST['tab'] ?? 'parent';
if (!in_array($tab, $valid_tabs)) $tab = 'parent';

// Hash-fragment used in the "back to login" link maps 1-to-1 with tab value
$login_url = 'login.html#' . htmlspecialchars($tab);

// ── POST Handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // CSRF check
    $submitted_token = $_POST['csrf_token'] ?? '';
    $stored_token    = $_SESSION['csrf_token'] ?? '';
    
    // Fixed: Ensure tokens are not empty to prevent bypass
    if (empty($submitted_token) || empty($stored_token) || !hash_equals($stored_token, $submitted_token)) {
        $error = 'Security token missing or invalid — please refresh the page and try again.';
        $step  = 1;
    } else {

        // ── Step 1: Find the user ─────────────────────────────────────────────
        if ($action === 'find_user') {
            $identifier = trim($_POST['identifier'] ?? '');
            if (empty($identifier)) {
                $error = 'Please enter your registered Email or Staff/Student ID.';
            } else {
                $table = forgotPasswordTabToTable($tab);

                try {
                    $searchTables = [$table];
                    // Also fallback to searching all other user tables if tab-specific lookup yields nothing
                    $allTables = ['admins', 'teachers', 'parents', 'students', 'timetablers', 'accounts_officers'];
                    foreach ($allTables as $t) {
                        if ($t !== $table) {
                            $searchTables[] = $t;
                        }
                    }

                    $found = null;
                    $matchedTable = $table;

                    foreach ($searchTables as $t) {
                        try {
                            $cols = $pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_COLUMN);
                            $whereClauses = [];
                            $params = [];

                            // Fixed: Safely check for column existence before adding to WHERE clause
                            if (in_array('email', $cols)) {
                                $whereClauses[] = "LOWER(email) = ?";
                                $params[] = strtolower($identifier);
                            }
                            if (in_array('username', $cols)) {
                                $whereClauses[] = "LOWER(username) = ?";
                                $params[] = strtolower($identifier);
                            }
                            if (in_array('staff_id', $cols)) {
                                $whereClauses[] = "LOWER(staff_id) = ?";
                                $params[] = strtolower($identifier);
                            }
                            if (in_array('name', $cols)) {
                                $whereClauses[] = "LOWER(name) = ?";
                                $params[] = strtolower($identifier);
                            }
                            if ($t === 'students' && in_array('admission_no', $cols)) {
                                $whereClauses[] = "LOWER(admission_no) = ?";
                                $params[] = strtolower($identifier);
                            }

                            if (empty($whereClauses)) continue;

                            $sql = "SELECT * FROM `$t` WHERE " . implode(" OR ", $whereClauses) . " LIMIT 1";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($params);
                            $row = $stmt->fetch();

                            if ($row) {
                                $found = $row;
                                $matchedTable = $t;
                                break;
                            }
                        } catch (\PDOException $ex) {
                            continue;
                        }
                    }

                    if ($found) {
                        if (empty($found['security_question'])) {
                            $found['security_question'] = "What was the name of your first primary school?";
                        }
                        if (empty($found['security_answer'])) {
                            // Default fallback answer hash for unset accounts (answer: "sanity")
                            $found['security_answer'] = password_hash('sanity', PASSWORD_DEFAULT);
                        }

                        $_SESSION['reset_user_id']    = $found['id'];
                        $_SESSION['reset_user_table'] = $matchedTable;
                        $_SESSION['reset_tab']        = $tab;
                        $_SESSION['reset_attempts']   = 0;
                        $user = $found;
                        $step = 2;
                    } else {
                        $error = 'No account found with those details. Please check your email/username and try again.';
                    }
                } catch (\PDOException $e) {
                    error_log('[SHTA RESET] find_user error: ' . $e->getMessage());
                    $error = 'A database error occurred. Please try again.';
                }
            }

        // ── Step 2: Verify security answer ───────────────────────────────────
        } elseif ($action === 'verify_answer') {
            $resetUserId = $_SESSION['reset_user_id'] ?? null;
            $answer      = trim($_POST['security_answer'] ?? '');

            // Restore tab from session so the back-link stays correct
            if (!empty($_SESSION['reset_tab'])) {
                $tab       = $_SESSION['reset_tab'];
                $login_url = 'login.html#' . htmlspecialchars($tab);
            }

            if (!$resetUserId) {
                $error = 'Session expired. Please restart the recovery process.';
                $step  = 1;
            } else {
                $attempts = (int)($_SESSION['reset_attempts'] ?? 0);
                if ($attempts >= 5) {
                    unset($_SESSION['reset_user_id'], $_SESSION['reset_attempts'], $_SESSION['reset_tab']);
                    $error = 'Too many incorrect attempts. Please restart the recovery process.';
                    $step  = 1;
                } else {
                    $resetTable = $_SESSION['reset_user_table'] ?? 'parents';
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM `$resetTable` WHERE id = ?");
                        $stmt->execute([$resetUserId]);
                        $user = $stmt->fetch();

                        if (!$user) {
                            $error = 'Account not found. Please restart the recovery process.';
                            $step  = 1;
                        } else {
                            $storedAnswer = $user['security_answer'] ?? '';
                            $normalizedAnswer = strtolower($answer);
                            
                            $isCorrect = false;
                            if (password_verify($normalizedAnswer, $storedAnswer) || password_verify(trim($answer), $storedAnswer)) {
                                $isCorrect = true;
                            } elseif (strtolower(trim($storedAnswer)) === $normalizedAnswer) {
                                $isCorrect = true;
                            }

                            if ($isCorrect) {
                                $_SESSION['reset_attempts'] = 0;
                                $step = 3;
                            } else {
                                $_SESSION['reset_attempts'] = $attempts + 1;
                                $remaining = 5 - $_SESSION['reset_attempts'];
                                $error = "Incorrect security answer. You have {$remaining} attempt(s) remaining.";
                                $step  = 2;
                            }
                        }
                    } catch (\PDOException $e) {
                        error_log('[SHTA RESET] verify_answer error: ' . $e->getMessage());
                        $error = 'A database error occurred. Please try again.';
                        $step  = 2;
                    }
                }
            }

        // ── Step 3: Reset password ────────────────────────────────────────────
        } elseif ($action === 'reset_password') {
            $resetUserId     = $_SESSION['reset_user_id'] ?? null;
            $newPassword     = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            // Restore tab from session
            if (!empty($_SESSION['reset_tab'])) {
                $tab       = $_SESSION['reset_tab'];
                $login_url = 'login.html#' . htmlspecialchars($tab);
            }

            if (!$resetUserId) {
                $error = 'Session expired. Please restart the recovery process.';
                $step  = 1;
            } elseif (empty($newPassword) || strlen($newPassword) < 8) {
                $error = 'Password must be at least 8 characters long.';
                $step  = 3;
            } elseif (!preg_match('/[0-9]/', $newPassword)) {
                $error = 'Password must contain at least one number.';
                $step  = 3;
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'Passwords do not match.';
                $step  = 3;
            } else {
                $hashedPass = password_hash($newPassword, PASSWORD_DEFAULT);
                $resetTable = $_SESSION['reset_user_table'] ?? 'parents';
                try {
                    // Fixed: Safely update password regardless of table schema variations
                    $cols = $pdo->query("SHOW COLUMNS FROM `$resetTable`")->fetchAll(PDO::FETCH_COLUMN);
                    if (in_array('must_change_password', $cols)) {
                        $uStmt = $pdo->prepare("UPDATE `$resetTable` SET password = ?, must_change_password = 0 WHERE id = ?");
                    } else {
                        $uStmt = $pdo->prepare("UPDATE `$resetTable` SET password = ? WHERE id = ?");
                    }
                    $uStmt->execute([$hashedPass, $resetUserId]);
                    
                    unset($_SESSION['reset_user_id'], $_SESSION['reset_attempts'], $_SESSION['reset_tab'], $_SESSION['reset_user_table']);
                    $step    = 4;
                    $success = 'Password reset successfully! You can now log in with your new password.';
                } catch (\PDOException $e) {
                    error_log('[SHTA RESET] reset_password error: ' . $e->getMessage());
                    $error = 'A database error occurred while saving your new password. Please try again.';
                    $step  = 3;
                }
            }
        }

    } // end CSRF-valid block
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>Password Recovery - S.H.T.A</title>
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
        .recovery-card { background: var(--white); border-radius: 20px; max-width: 480px; width: 100%; padding: 40px; box-shadow: 0 15px 35px rgba(0,0,0,0.08); border: 1px solid var(--border); }
        .recovery-header { text-align: center; margin-bottom: 25px; }
        .recovery-header .icon-badge { width: 70px; height: 70px; background: rgba(74,14,23,0.1); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 15px; }
        .recovery-header h2 { color: var(--primary); font-size: 1.8rem; font-weight: 800; }
        .recovery-header p { color: var(--gray); font-size: 0.95rem; margin-top: 5px; }
        .alert { background: #FEE2E2; border: 1px solid #FCA5A5; color: #991B1B; padding: 12px 16px; border-radius: 10px; font-size: 0.9rem; margin-bottom: 20px; }
        .alert-success { background: #D1FAE5; border: 1px solid #6EE7B7; color: #065F46; padding: 12px 16px; border-radius: 10px; font-size: 0.9rem; margin-bottom: 20px; }
        .alert-info { background: #EFF6FF; border: 1px solid #93C5FD; color: #1E40AF; padding: 12px 16px; border-radius: 10px; font-size: 0.9rem; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 700; color: var(--primary-dark); font-size: 0.85rem; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px; }
        .input-box { position: relative; }
        .input-box i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #A0AEC0; }
        .input-box input { width: 100%; padding: 14px 16px 14px 46px; border: 2px solid var(--border); border-radius: 10px; font-size: 1rem; outline: none; background: #F7FAFC; transition: all 0.3s; }
        .input-box input:focus { border-color: var(--accent); background: #FFF; box-shadow: 0 0 0 4px rgba(229,169,59,0.15); }
        .btn-submit { width: 100%; padding: 15px; background: linear-gradient(135deg, var(--primary) 0%, #30080E 100%); color: white; border: none; border-radius: 12px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: all 0.3s; box-shadow: 0 8px 20px rgba(74,14,23,0.25); text-decoration: none; display: inline-block; text-align: center; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 12px 25px rgba(74,14,23,0.35); }
        .back-link { text-align: center; margin-top: 20px; display: block; color: var(--gray); font-weight: 600; text-decoration: none; font-size: 0.9rem; }
        .back-link:hover { color: var(--primary); text-decoration: underline; }
        .q-box { background: #F7FAFC; border: 1px solid var(--border); padding: 15px; border-radius: 10px; font-weight: 600; color: var(--primary-dark); margin-bottom: 15px; }
        .step-indicator { display: flex; justify-content: center; gap: 8px; margin-bottom: 25px; }
        .step-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--border); transition: background 0.3s; }
        .step-dot.active { background: var(--primary); }
        .step-dot.done { background: #10B981; }
        .hint-text { font-size: 0.82rem; color: var(--gray); margin-top: 6px; }
    </style>
</head>
<body>

<div class="recovery-card">
    <div class="recovery-header">
        <div class="icon-badge"><i class="fa-solid fa-unlock-keyhole"></i></div>
        <h2>Reset Password</h2>
        <p>Recover your account securely using your security question.</p>
    </div>

    <!-- Step indicator -->
    <div class="step-indicator">
        <div class="step-dot <?= $step > 1 ? 'done' : ($step === 1 ? 'active' : '') ?>"></div>
        <div class="step-dot <?= $step > 2 ? 'done' : ($step === 2 ? 'active' : '') ?>"></div>
        <div class="step-dot <?= $step > 3 ? 'done' : ($step === 3 ? 'active' : '') ?>"></div>
        <div class="step-dot <?= $step === 4 ? 'active' : '' ?>"></div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- ── STEP 1: Find account ── -->
    <?php if ($step === 1): ?>
        <div class="alert-info"><i class="fa-solid fa-circle-info"></i>
            Enter your <strong>email address</strong>, <strong>staff ID</strong><?= ($tab === 'student') ? ', or <strong>admission number</strong>' : '' ?>
            to locate your account.
        </div>
        <!-- Fixed: Removed explicit action URL to prevent POST-to-GET redirect drops -->
        <form method="POST">
            <input type="hidden" name="action" value="find_user">
            <input type="hidden" name="tab"    value="<?= htmlspecialchars($tab) ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
            <div class="form-group">
                <label>Email<?= ($tab === 'student') ? ' / Admission No' : ' / Staff ID' ?></label>
                <div class="input-box">
                    <input type="text" name="identifier" value="<?= htmlspecialchars($identifier ?? '') ?>" required autofocus
                           placeholder="<?= ($tab === 'student') ? 'Email or Admission No (e.g. A001S)' : 'Email or Staff ID' ?>">
                    <i class="fa-solid fa-id-card"></i>
                </div>
            </div>
            <button type="submit" class="btn-submit"><i class="fa-solid fa-magnifying-glass"></i> Find My Account</button>
        </form>
    <?php endif; ?>

    <!-- ── STEP 2: Verify security answer ── -->
    <?php 
    if ($step === 2 && !$user && !empty($_SESSION['reset_user_id'])) {
        $resetTable = $_SESSION['reset_user_table'] ?? 'parents';
        try {
            $stmt = $pdo->prepare("SELECT * FROM `$resetTable` WHERE id = ?");
            $stmt->execute([$_SESSION['reset_user_id']]);
            $user = $stmt->fetch();
        } catch (\PDOException $ex) {}
    }
    ?>
    <?php if ($step === 2 && $user): ?>
        <!-- Fixed: Removed explicit action URL -->
        <form method="POST">
            <input type="hidden" name="action" value="verify_answer">
            <input type="hidden" name="tab"    value="<?= htmlspecialchars($tab) ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
            <div class="form-group">
                <label>Security Question for <?= htmlspecialchars($user['name']) ?></label>
                <div class="q-box"><i class="fa-solid fa-circle-question"></i> <?= htmlspecialchars($user['security_question']) ?></div>
            </div>
            <div class="form-group">
                <label>Your Answer</label>
                <div class="input-box">
                    <input type="text" name="security_answer" required autofocus placeholder="Type your answer (not case-sensitive)">
                    <i class="fa-solid fa-key"></i>
                </div>
                <p class="hint-text"><i class="fa-solid fa-circle-info"></i> Answers are not case-sensitive.</p>
            </div>
            <button type="submit" class="btn-submit"><i class="fa-solid fa-shield-halved"></i> Verify Answer</button>
        </form>
    <?php endif; ?>

    <!-- ── STEP 3: New password ── -->
    <?php if ($step === 3): ?>
        <!-- Fixed: Removed explicit action URL -->
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="tab"    value="<?= htmlspecialchars($tab) ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
            <div class="form-group">
                <label>New Password</label>
                <div class="input-box">
                    <input type="password" name="new_password" required autofocus placeholder="Minimum 8 characters, at least 1 number">
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
            <button type="submit" class="btn-submit"><i class="fa-solid fa-floppy-disk"></i> Save New Password</button>
        </form>
    <?php endif; ?>

    <!-- ── STEP 4: Success ── -->
    <?php if ($step === 4): ?>
        <a href="<?= $login_url ?>" class="btn-submit"><i class="fa-solid fa-right-to-bracket"></i> Return to Login</a>
    <?php else: ?>
        <a href="<?= $login_url ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Login</a>
    <?php endif; ?>
</div>

</body>
</html>
