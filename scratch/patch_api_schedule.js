const fs = require('fs');

let api = fs.readFileSync('api/api_schedule_lesson.php', 'utf8');

// Replace Students, Teachers joins
api = api.replace(/JOIN users u_student ON sp\.user_id = u_student\.id/g, 'JOIN students u_student ON sp.user_id = u_student.id');
api = api.replace(/JOIN users u_teacher ON ts\.teacher_id = u_teacher\.id/g, 'JOIN teachers u_teacher ON ts.teacher_id = u_teacher.id');
api = api.replace(/sp JOIN users u ON sp\.user_id = u\.id/g, 'sp JOIN students u ON sp.user_id = u.id');

// Replace SELECT list for teachers
const oldTeacherQuery = `        $teachers = $pdo->query("
            SELECT u.id, u.name, GROUP_CONCAT(ts.subject_id) as subject_ids
            FROM users u
            LEFT JOIN teacher_subjects ts ON u.id = ts.teacher_id
            WHERE u.role = 'teacher'
            GROUP BY u.id
        ")->fetchAll();`;

const newTeacherQuery = `        $teachers = $pdo->query("
            SELECT u.id, u.name, GROUP_CONCAT(ts.subject_id) as subject_ids
            FROM teachers u
            LEFT JOIN teacher_subjects ts ON u.id = ts.teacher_id
            GROUP BY u.id
        ")->fetchAll();`;

if (api.includes(oldTeacherQuery)) {
    api = api.replace(oldTeacherQuery, newTeacherQuery);
} else {
    const oldTeacherQueryCRLF = oldTeacherQuery.replace(/\n/g, '\r\n');
    api = api.replace(oldTeacherQueryCRLF, newTeacherQuery.replace(/\n/g, '\r\n'));
}

fs.writeFileSync('api/api_schedule_lesson.php', api, 'utf8');
console.log('✅ Updated api_schedule_lesson.php for split tables!');
