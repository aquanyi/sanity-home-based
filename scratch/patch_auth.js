const fs = require('fs');

let login = fs.readFileSync('process_login.php', 'utf8');

// 1. Add roleToTable helper function definition
const roleToTableHelper = `
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
`;

// Insert the helper at the end of helpers section
login = login.replace('function roleToHash($role) {', roleToTableHelper + '\n\nfunction roleToHash($role) {');

// 2. Replace the SELECT * FROM users query with the role-specific lookup
const oldQueryBlock = `        } else {
            // Look up user by Email OR Admin Username shortcut ('admin')
            $stmt = $pdo->prepare("
                SELECT * FROM users 
                WHERE email = ? 
                   OR staff_id = ?
                   OR (role = 'admin' AND LOWER(?) = 'admin')
                LIMIT 1
            ");
            $stmt->execute([$identifier, $identifier, $identifier]);
            $user = $stmt->fetch();

            if (!$user) {
                record_login_failure($pdo);
                redirectWithError('Invalid credentials. Please check your email and password.', $role_hint);
            }

            if (!password_verify($password, $user['password'])) {
                record_login_failure($pdo);
                redirectWithError('Invalid credentials. Please check your email and password.', $role_hint);
            }
        }`;

const newQueryBlock = `        } else {
            $table = roleToTable($role_hint);
            if (empty($table)) {
                record_login_failure($pdo);
                redirectWithError('Invalid role type specified.', $role_hint);
            }

            // Look up user in the role-specific table
            $stmt = $pdo->prepare("
                SELECT * FROM \`$table\` 
                WHERE email = ? 
                   OR staff_id = ?
                   OR ('admin' = ? AND ? = 'admin')
                LIMIT 1
            ");
            $stmt->execute([$identifier, $identifier, $identifier, $role_hint]);
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
        }`;

if (login.includes(oldQueryBlock)) {
    login = login.replace(oldQueryBlock, newQueryBlock);
} else {
    // Try matching with CRLF line endings
    const oldQueryBlockCRLF = oldQueryBlock.replace(/\n/g, '\r\n');
    login = login.replace(oldQueryBlockCRLF, newQueryBlock.replace(/\n/g, '\r\n'));
}

fs.writeFileSync('process_login.php', login, 'utf8');
console.log('✅ Updated process_login.php for split tables!');
