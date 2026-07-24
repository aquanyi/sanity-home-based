const fs = require('fs');

let registerApi = fs.readFileSync('api/api_teacher_register.php', 'utf8');

// Replace the old email uniqueness check on `users` table
const oldEmailCheck = `            // Check if email already exists in users table
            $chkUser = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $chkUser->execute([$email]);
            if ($chkUser->fetchColumn() > 0) {
                echo json_encode(['status' => 'error', 'message' => 'This email address is already registered in the system.']);
                exit;
            }`;

const newEmailCheck = `            // Check if email already exists across role tables
            $tablesToCheck = ['admins', 'teachers', 'parents', 'students', 'timetablers', 'accounts_officers'];
            foreach ($tablesToCheck as $tbl) {
                $chkUser = $pdo->prepare("SELECT COUNT(*) FROM \`$tbl\` WHERE email = ?");
                $chkUser->execute([$email]);
                if ($chkUser->fetchColumn() > 0) {
                    echo json_encode(['status' => 'error', 'message' => 'This email address is already registered in the system.']);
                    exit;
                }
            }`;

if (registerApi.includes(oldEmailCheck)) {
    registerApi = registerApi.replace(oldEmailCheck, newEmailCheck);
} else {
    const oldEmailCheckCRLF = oldEmailCheck.replace(/\n/g, '\r\n');
    const newEmailCheckCRLF = newEmailCheck.replace(/\n/g, '\r\n');
    registerApi = registerApi.replace(oldEmailCheckCRLF, newEmailCheckCRLF);
}

fs.writeFileSync('api/api_teacher_register.php', registerApi, 'utf8');
console.log('✅ Updated api/api_teacher_register.php for split tables!');
