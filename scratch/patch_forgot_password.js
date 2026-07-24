const fs = require('fs');

let forgot = fs.readFileSync('forgot_password.php', 'utf8');

// 1. Add forgotPasswordTabToTable helper function
const helperFunc = `
function forgotPasswordTabToTable($tab) {
    switch ($tab) {
        case 'admin': return 'admins';
        case 'timetable': return 'timetablers';
        case 'teachers': return 'teachers';
        case 'parent': return 'parents';
        case 'accounts': return 'accounts_officers';
        default: return 'parents';
    }
}
`;

forgot = forgot.replace(
    "$valid_tabs = ['admin', 'parent', 'teachers', 'timetable', 'accounts'];",
    helperFunc + "\n\n$valid_tabs = ['admin', 'parent', 'teachers', 'timetable', 'accounts'];"
);

// 2. Patch STEP 1: Find User
const findUserOld = `            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR staff_id = ? LIMIT 1");
            $stmt->execute([$identifier, $identifier]);
            $found = $stmt->fetch();

            // Neutral message to prevent user enumeration
            if ($found && !empty($found['security_question']) && !empty($found['security_answer'])) {
                $_SESSION['reset_user_id']    = $found['id'];
                $_SESSION['reset_attempts']   = 0;  // Reset answer attempt counter
                $user = $found;
                $step = 2;`;

const findUserNew = `            $table = forgotPasswordTabToTable($tab);
            $stmt = $pdo->prepare("SELECT * FROM \`$table\` WHERE email = ? OR staff_id = ? LIMIT 1");
            $stmt->execute([$identifier, $identifier]);
            $found = $stmt->fetch();

            // Neutral message to prevent user enumeration
            if ($found && !empty($found['security_question']) && !empty($found['security_answer'])) {
                $_SESSION['reset_user_id']    = $found['id'];
                $_SESSION['reset_user_table'] = $table;
                $_SESSION['reset_attempts']   = 0;  // Reset answer attempt counter
                $user = $found;
                $step = 2;`;

if (forgot.includes(findUserOld)) {
    forgot = forgot.replace(findUserOld, findUserNew);
} else {
    const findUserOldCRLF = findUserOld.replace(/\n/g, '\r\n');
    forgot = forgot.replace(findUserOldCRLF, findUserNew.replace(/\n/g, '\r\n'));
}

// 3. Patch STEP 2: Verify Answer
const verifyAnswerOld = `                 $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                 $stmt->execute([$resetUserId]);
                 $user = $stmt->fetch();`;

const verifyAnswerNew = `                 $resetUserTable = $_SESSION['reset_user_table'] ?? '';
                 $stmt = $pdo->prepare("SELECT * FROM \`$resetUserTable\` WHERE id = ?");
                 $stmt->execute([$resetUserId]);
                 $user = $stmt->fetch();`;

if (forgot.includes(verifyAnswerOld)) {
    forgot = forgot.replace(verifyAnswerOld, verifyAnswerNew);
} else {
    const verifyAnswerOldCRLF = verifyAnswerOld.replace(/\n/g, '\r\n');
    forgot = forgot.replace(verifyAnswerOldCRLF, verifyAnswerNew.replace(/\n/g, '\r\n'));
}

// 4. Patch STEP 3: Reset Password
const resetPasswordOld = `             $hashedPass = password_hash($newPassword, PASSWORD_DEFAULT);
             $uStmt = $pdo->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?");
             $uStmt->execute([$hashedPass, $resetUserId]);
             unset($_SESSION['reset_user_id'], $_SESSION['reset_attempts']);`;

const resetPasswordNew = `             $resetUserTable = $_SESSION['reset_user_table'] ?? '';
             $hashedPass = password_hash($newPassword, PASSWORD_DEFAULT);
             $uStmt = $pdo->prepare("UPDATE \`$resetUserTable\` SET password = ?, must_change_password = 0 WHERE id = ?");
             $uStmt->execute([$hashedPass, $resetUserId]);
             unset($_SESSION['reset_user_id'], $_SESSION['reset_user_table'], $_SESSION['reset_attempts']);`;

if (forgot.includes(resetPasswordOld)) {
    forgot = forgot.replace(resetPasswordOld, resetPasswordNew);
} else {
    const resetPasswordOldCRLF = resetPasswordOld.replace(/\n/g, '\r\n');
    forgot = forgot.replace(resetPasswordOldCRLF, resetPasswordNew.replace(/\n/g, '\r\n'));
}

fs.writeFileSync('forgot_password.php', forgot, 'utf8');
console.log('✅ Updated forgot_password.php for split tables!');
