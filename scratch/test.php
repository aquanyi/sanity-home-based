<?php
require 'db_connect.php';
try {
    $totalUsers = 
        $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn() +
        $pdo->query("SELECT COUNT(*) FROM timetablers")->fetchColumn() +
        $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn() +
        $pdo->query("SELECT COUNT(*) FROM parents")->fetchColumn() +
        $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn() +
        $pdo->query("SELECT COUNT(*) FROM accounts_officers")->fetchColumn();
    echo "Total Users: $totalUsers\n";
    $usersStmt = $pdo->query("
        SELECT id, name, email, phone, 'admin' AS role, created_at, NULL AS grade_level, NULL as subjects, NULL as subject_ids, NULL AS admission_no, NULL AS staff_id, NULL AS profile_id FROM admins
        UNION ALL
        SELECT id, name, email, phone, 'timetabler' AS role, created_at, NULL AS grade_level, NULL as subjects, NULL as subject_ids, NULL AS admission_no, NULL AS staff_id, NULL AS profile_id FROM timetablers
        UNION ALL
        SELECT id, name, email, phone, 'parent' AS role, created_at, NULL AS grade_level, NULL as subjects, NULL as subject_ids, NULL AS admission_no, NULL AS staff_id, NULL AS profile_id FROM parents
        UNION ALL
        SELECT id, name, email, phone, 'accounts' AS role, created_at, NULL AS grade_level, NULL as subjects, NULL as subject_ids, NULL AS admission_no, NULL AS staff_id, NULL AS profile_id FROM accounts_officers
        UNION ALL
        SELECT u.id, u.name,
               COALESCE(p.email, u.email) AS email,
               u.phone, 'student' AS role, u.created_at, sp.grade_level,
               (SELECT GROUP_CONCAT(sa.name ORDER BY sa.name ASC SEPARATOR ', ')
                FROM student_subjects ss
                JOIN subject_areas sa ON ss.subject_id = sa.id
                WHERE ss.student_id = sp.id) as subjects,
               (SELECT GROUP_CONCAT(ss.subject_id SEPARATOR ',')
                FROM student_subjects ss
                WHERE ss.student_id = sp.id) as subject_ids,
               u.admission_no, u.staff_id, sp.id AS profile_id
        FROM students u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        LEFT JOIN parents p ON sp.parent_id = p.id
        UNION ALL
        SELECT u.id, u.name, u.email, u.phone, 'teacher' AS role, u.created_at, NULL AS grade_level,
               (SELECT GROUP_CONCAT(sa.name ORDER BY sa.name ASC SEPARATOR ', ')
                FROM teacher_subjects ts
                JOIN subject_areas sa ON ts.subject_id = sa.id
                WHERE ts.teacher_id = u.id) as subjects,
               (SELECT GROUP_CONCAT(ts.subject_id SEPARATOR ',')
                FROM teacher_subjects ts
                WHERE ts.teacher_id = u.id) as subject_ids,
               NULL AS admission_no, NULL AS staff_id, NULL AS profile_id
        FROM teachers u
        ORDER BY created_at DESC
    ");
    echo "Users Query OK\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
