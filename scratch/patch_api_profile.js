const fs = require('fs');

let api = fs.readFileSync('api/api_profile.php', 'utf8');

// 1. Rewrite GET profile route
const oldGetProfile = `    try {
        $stmt = $pdo->prepare("SELECT id, name, email, phone, role, created_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();`;

const newGetProfile = `    $userRole = $_SESSION['user_role'] ?? '';
    $table = '';
    switch ($userRole) {
        case 'admin': $table = 'admins'; break;
        case 'timetabler': $table = 'timetablers'; break;
        case 'teacher': $table = 'teachers'; break;
        case 'parent': $table = 'parents'; break;
        case 'student': $table = 'students'; break;
        case 'accounts': $table = 'accounts_officers'; break;
    }
    try {
        $stmt = $pdo->prepare("SELECT id, name, email, phone, '$userRole' AS role, created_at FROM \`$table\` WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();`;

if (api.includes(oldGetProfile)) {
    api = api.replace(oldGetProfile, newGetProfile);
} else {
    const oldGetProfileCRLF = oldGetProfile.replace(/\n/g, '\r\n');
    api = api.replace(oldGetProfileCRLF, newGetProfile.replace(/\n/g, '\r\n'));
}

// 2. Rewrite POST update profile route (Self Update)
const oldSelfUpdate = `        // Fetch current user data to verify password if changing, and check email uniqueness
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $currentUser = $stmt->fetch();

        if (!$currentUser) {
            echo json_encode(['status' => 'error', 'message' => 'User not found.']);
            exit;
        }

        // Check if email is already in use by another user
        $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $checkEmail->execute([$email, $userId]);
        if ($checkEmail->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'The email address is already in use by another account.']);
            exit;
        }

        $passwordUpdateSql = "";
        $params = [$name, $email, $phone];`;

const newSelfUpdate = `        $userRole = $_SESSION['user_role'] ?? '';
        $table = '';
        switch ($userRole) {
            case 'admin': $table = 'admins'; break;
            case 'timetabler': $table = 'timetablers'; break;
            case 'teacher': $table = 'teachers'; break;
            case 'parent': $table = 'parents'; break;
            case 'student': $table = 'students'; break;
            case 'accounts': $table = 'accounts_officers'; break;
        }
        // Fetch current user data to verify password if changing, and check email uniqueness
        $stmt = $pdo->prepare("SELECT * FROM \`$table\` WHERE id = ?");
        $stmt->execute([$userId]);
        $currentUser = $stmt->fetch();

        if (!$currentUser) {
            echo json_encode(['status' => 'error', 'message' => 'User not found.']);
            exit;
        }

        // Check if email is already in use by another user across all tables
        $emailInUse = false;
        $tablesToCheck = ['admins', 'teachers', 'parents', 'students', 'timetablers', 'accounts_officers'];
        foreach ($tablesToCheck as $tbl) {
            $checkEmail = $pdo->prepare("SELECT id FROM \`$tbl\` WHERE email = ? AND id != ?");
            $checkEmail->execute([$email, $userId]);
            if ($checkEmail->fetch()) {
                $emailInUse = true;
                break;
            }
        }
        if ($emailInUse) {
            echo json_encode(['status' => 'error', 'message' => 'The email address is already in use by another account.']);
            exit;
        }

        $passwordUpdateSql = "";
        $params = [$name, $email, $phone];`;

if (api.includes(oldSelfUpdate)) {
    api = api.replace(oldSelfUpdate, newSelfUpdate);
} else {
    const oldSelfUpdateCRLF = oldSelfUpdate.replace(/\n/g, '\r\n');
    api = api.replace(oldSelfUpdateCRLF, newSelfUpdate.replace(/\n/g, '\r\n'));
}

// Replace self update statement
api = api.replace(
    'UPDATE users SET name = ?, email = ?, phone = ?',
    'UPDATE `$table` SET name = ?, email = ?, phone = ?'
);

// 3. Rewrite POST Admin update route
const oldAdminUpdate = `        // Check email uniqueness against other users
        $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $checkEmail->execute([$email, $targetUserId]);
        if ($checkEmail->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Email address is already in use by another account.']);
            exit;
        }

        $passwordUpdateSql = "";
        $params = [$name, $email, $phone, $role];`;

const newAdminUpdate = `        $table_map = [
            'admin' => 'admins',
            'timetabler' => 'timetablers',
            'teacher' => 'teachers',
            'parent' => 'parents',
            'student' => 'students',
            'accounts' => 'accounts_officers'
        ];
        $table = $table_map[$role] ?? 'teachers';

        // Check email uniqueness against all tables
        $emailInUse = false;
        $tablesToCheck = ['admins', 'teachers', 'parents', 'students', 'timetablers', 'accounts_officers'];
        foreach ($tablesToCheck as $tbl) {
            $checkEmail = $pdo->prepare("SELECT id FROM \`$tbl\` WHERE email = ? AND id != ?");
            $checkEmail->execute([$email, $targetUserId]);
            if ($checkEmail->fetch()) {
                $emailInUse = true;
                break;
            }
        }
        if ($emailInUse) {
            echo json_encode(['status' => 'error', 'message' => 'Email address is already in use by another account.']);
            exit;
        }

        $passwordUpdateSql = "";
        $params = [$name, $email, $phone];`;

if (api.includes(oldAdminUpdate)) {
    api = api.replace(oldAdminUpdate, newAdminUpdate);
} else {
    const oldAdminUpdateCRLF = oldAdminUpdate.replace(/\n/g, '\r\n');
    api = api.replace(oldAdminUpdateCRLF, newAdminUpdate.replace(/\n/g, '\r\n'));
}

// Replace admin update statement to omit role column
api = api.replace(
    'UPDATE users SET name = ?, email = ?, phone = ?, role = ? {$passwordUpdateSql} WHERE id = ?',
    'UPDATE `$table` SET name = ?, email = ?, phone = ? {$passwordUpdateSql} WHERE id = ?'
);

fs.writeFileSync('api/api_profile.php', api, 'utf8');
console.log('✅ Updated api_profile.php for split tables!');
