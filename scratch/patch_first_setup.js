const fs = require('fs');

let setup = fs.readFileSync('first_login_setup.php', 'utf8');

// 1. Add table resolution logic
const tableResolution = `
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

$stmt = $pdo->prepare("SELECT * FROM \`$table\` WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
`;

setup = setup.replace(
    /^\$userId = \$_SESSION\['user_id'\];\s*\$stmt = \$pdo->prepare\("SELECT \* FROM users WHERE id = \?"\);\s*\$stmt->execute\(\[\$userId\]\);\s*\$user = \$stmt->fetch\(\);/m,
    tableResolution
);

// Fallback replacement if regex spacing is slightly different
if (setup.includes('SELECT * FROM users WHERE id = ?')) {
    setup = setup.replace(
        `$userId = $_SESSION['user_id'];\n$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");\n$stmt->execute([$userId]);\n$user = $stmt->fetch();`,
        tableResolution
    );
}

// 2. Patch dashboard redirect URLs to use $userRole instead of $user['role']
setup = setup.replace(
    "header('Location: ' . getRoleDashboardUrl($user['role']));",
    "header('Location: ' . getRoleDashboardUrl($userRole));"
);
// replace multiple occurrences if they exist
setup = setup.replace(
    "header('Location: ' . getRoleDashboardUrl($user['role']));",
    "header('Location: ' . getRoleDashboardUrl($userRole));"
);

// 3. Patch UPDATE queries to use the role table variable
setup = setup.replace(
    'UPDATE users SET password = ?, must_change_password = 0, security_question = ?, security_answer = ? WHERE id = ?',
    'UPDATE `$table` SET password = ?, must_change_password = 0, security_question = ?, security_answer = ? WHERE id = ?'
);
setup = setup.replace(
    'UPDATE users SET security_question = ?, security_answer = ? WHERE id = ?',
    'UPDATE `$table` SET security_question = ?, security_answer = ? WHERE id = ?'
);

fs.writeFileSync('first_login_setup.php', setup, 'utf8');
console.log('✅ Updated first_login_setup.php for split tables!');
