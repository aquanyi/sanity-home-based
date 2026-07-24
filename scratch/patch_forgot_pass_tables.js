const fs = require('fs');

let forgot = fs.readFileSync('forgot_password.php', 'utf8');

// Patch SELECT in verify_answer
forgot = forgot.replace(
    '$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");',
    '$resetTable = $_SESSION[\'reset_user_table\'] ?? \'parents\';\n                $stmt = $pdo->prepare("SELECT * FROM `$resetTable` WHERE id = ?");'
);

// Patch UPDATE in reset_password
forgot = forgot.replace(
    '$uStmt = $pdo->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?");',
    '$resetTable = $_SESSION[\'reset_user_table\'] ?? \'parents\';\n            $uStmt = $pdo->prepare("UPDATE `$resetTable` SET password = ?, must_change_password = 0 WHERE id = ?");'
);

fs.writeFileSync('forgot_password.php', forgot, 'utf8');
console.log('✅ Updated forgot_password.php for split tables!');
