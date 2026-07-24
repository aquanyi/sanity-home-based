const fs = require('fs');

let api = fs.readFileSync('api/api_fetch_leads.php', 'utf8');

// 1. Replace total users count calculation
const oldTotalUsers = `$totalUsers    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();`;
const newTotalUsers = `$totalUsers    = 
        $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn() +
        $pdo->query("SELECT COUNT(*) FROM timetablers")->fetchColumn() +
        $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn() +
        $pdo->query("SELECT COUNT(*) FROM parents")->fetchColumn() +
        $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn() +
        $pdo->query("SELECT COUNT(*) FROM accounts_officers")->fetchColumn();`;

api = api.replace(oldTotalUsers, newTotalUsers);

// 2. Replace users listing query
const oldUsersQuery = `    // Users list with joined student details if student, or defaults
    $usersStmt = $pdo->query("
        SELECT u.id, u.name, u.email, u.phone, u.role, u.created_at, sp.grade_level,
               (SELECT GROUP_CONCAT(sa.name ORDER BY sa.name ASC SEPARATOR ', ')
                FROM teacher_subjects ts
                JOIN subject_areas sa ON ts.subject_id = sa.id
                WHERE ts.teacher_id = u.id) as subjects,
               (SELECT GROUP_CONCAT(ts.subject_id SEPARATOR ',')
                FROM teacher_subjects ts
                WHERE ts.teacher_id = u.id) as subject_ids
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        ORDER BY u.created_at DESC
    ");`;

const newUsersQuery = `    // Users list with joined student details if student, or defaults
    $usersStmt = $pdo->query("
        SELECT id, name, email, phone, 'admin' AS role, created_at, NULL AS grade_level, NULL as subjects, NULL as subject_ids FROM admins
        UNION ALL
        SELECT id, name, email, phone, 'timetabler' AS role, created_at, NULL AS grade_level, NULL as subjects, NULL as subject_ids FROM timetablers
        UNION ALL
        SELECT id, name, email, phone, 'parent' AS role, created_at, NULL AS grade_level, NULL as subjects, NULL as subject_ids FROM parents
        UNION ALL
        SELECT id, name, email, phone, 'accounts' AS role, created_at, NULL AS grade_level, NULL as subjects, NULL as subject_ids FROM accounts_officers
        UNION ALL
        SELECT u.id, u.name, u.email, u.phone, 'student' AS role, u.created_at, sp.grade_level, NULL as subjects, NULL as subject_ids 
        FROM students u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        UNION ALL
        SELECT u.id, u.name, u.email, u.phone, 'teacher' AS role, u.created_at, NULL AS grade_level,
               (SELECT GROUP_CONCAT(sa.name ORDER BY sa.name ASC SEPARATOR ', ')
                FROM teacher_subjects ts
                JOIN subject_areas sa ON ts.subject_id = sa.id
                WHERE ts.teacher_id = u.id) as subjects,
               (SELECT GROUP_CONCAT(ts.subject_id SEPARATOR ',')
                FROM teacher_subjects ts
                WHERE ts.teacher_id = u.id) as subject_ids
        FROM teachers u
        ORDER BY created_at DESC
    ");`;

if (api.includes(oldUsersQuery)) {
    api = api.replace(oldUsersQuery, newUsersQuery);
} else {
    const oldUsersQueryCRLF = oldUsersQuery.replace(/\n/g, '\r\n');
    api = api.replace(oldUsersQueryCRLF, newUsersQuery.replace(/\n/g, '\r\n'));
}

fs.writeFileSync('api/api_fetch_leads.php', api, 'utf8');
console.log('✅ Updated api_fetch_leads.php for split tables!');
