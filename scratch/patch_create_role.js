const fs = require('fs');

let create = fs.readFileSync('api/api_create_role.php', 'utf8');

// 1. Replace unique Staff ID checking query and insertion logic
const oldInsertBlock = `        $staffId = null;
        $counter = rand(100, 999);
        while (true) {
            $candidate = $prefix . '-' . date('Y') . '-' . sprintf('%03d', $counter);
            $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE staff_id = ?");
            $chk->execute([$candidate]);
            if ($chk->fetchColumn() == 0) {
                $staffId = $candidate;
                break;
            }
            $counter++;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (staff_id, name, email, phone, password, role, must_change_password) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$staffId, $name, $email, $phone, $hashedPassword, $role]);`;

const newInsertBlock = `        $table_map = [
            'admin' => 'admins',
            'timetabler' => 'timetablers',
            'teacher' => 'teachers',
            'accounts' => 'accounts_officers'
        ];
        $table = $table_map[$role] ?? 'teachers';

        $staffId = null;
        $counter = rand(100, 999);
        while (true) {
            $candidate = $prefix . '-' . date('Y') . '-' . sprintf('%03d', $counter);
            
            // Check uniqueness in target table
            $chk = $pdo->prepare("SELECT COUNT(*) FROM \`$table\` WHERE staff_id = ?");
            $chk->execute([$candidate]);
            if ($chk->fetchColumn() == 0) {
                $staffId = $candidate;
                break;
            }
            $counter++;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO \`$table\` (staff_id, name, email, phone, password, must_change_password) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$staffId, $name, $email, $phone, $hashedPassword]);`;

if (create.includes(oldInsertBlock)) {
    create = create.replace(oldInsertBlock, newInsertBlock);
} else {
    const oldInsertBlockCRLF = oldInsertBlock.replace(/\n/g, '\r\n');
    create = create.replace(oldInsertBlockCRLF, newInsertBlock.replace(/\n/g, '\r\n'));
}

fs.writeFileSync('api/api_create_role.php', create, 'utf8');
console.log('✅ Updated api_create_role.php for split tables!');
