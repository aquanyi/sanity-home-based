const fs = require('fs');

let api = fs.readFileSync('api/api_approve_teacher.php', 'utf8');

// 1. Check globally unique email across all tables:
// We can check across admins, teachers, parents, students, timetablers, accounts_officers
const oldEmailCheck = `        // Ensure email isn't already in users table
        $chkUser = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $chkUser->execute([$pending['email']]);
        if ($chkUser->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'A user with this email address already exists in the system.']);
            exit;
        }`;

const newEmailCheck = `        // Ensure email isn't already in any role table
        $tablesToCheck = ['admins', 'teachers', 'parents', 'students', 'timetablers', 'accounts_officers'];
        foreach ($tablesToCheck as $tbl) {
            $chkUser = $pdo->prepare("SELECT COUNT(*) FROM \`$tbl\` WHERE email = ?");
            $chkUser->execute([$pending['email']]);
            if ($chkUser->fetchColumn() > 0) {
                echo json_encode(['status' => 'error', 'message' => 'A user with this email address already exists in the system.']);
                exit;
            }
        }`;

if (api.includes(oldEmailCheck)) {
    api = api.replace(oldEmailCheck, newEmailCheck);
} else {
    const oldEmailCheckCRLF = oldEmailCheck.replace(/\n/g, '\r\n');
    api = api.replace(oldEmailCheckCRLF, newEmailCheck.replace(/\n/g, '\r\n'));
}

// 2. Unique Staff ID checking query
api = api.replace(
    'SELECT COUNT(*) FROM users WHERE staff_id = ?',
    'SELECT COUNT(*) FROM teachers WHERE staff_id = ?'
);

// 3. Replace inserting into users with inserting into teachers
api = api.replace(
    "INSERT INTO users (staff_id, name, email, phone, password, role, security_question, security_answer, must_change_password) VALUES (?, ?, ?, ?, ?, 'teacher', ?, ?, 0)",
    "INSERT INTO teachers (staff_id, name, email, phone, password, security_question, security_answer, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?, 0)"
);

fs.writeFileSync('api/api_approve_teacher.php', api, 'utf8');
console.log('✅ Updated api_approve_teacher.php for split tables!');
