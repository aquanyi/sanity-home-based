const fs = require('fs');

let api = fs.readFileSync('api/api_manage_academic.php', 'utf8');

// Replace Students, Teachers joins
api = api.replace(/JOIN users u ON es\.invigilator_teacher_id = u\.id/g, 'JOIN teachers u ON es.invigilator_teacher_id = u.id');
api = api.replace(/JOIN users u ON sp\.user_id = u\.id/g, 'JOIN students u ON sp.user_id = u.id');
api = api.replace(/LEFT JOIN users u_s ON sp\.user_id = u_s\.id/g, 'LEFT JOIN students u_s ON sp.user_id = u_s.id');
api = api.replace(/JOIN users u_s ON sp\.user_id = u_s\.id/g, 'JOIN students u_s ON sp.user_id = u_s.id');
api = api.replace(/JOIN users u_t ON sa\.teacher_id = u_t\.id/g, 'JOIN teachers u_t ON sa.teacher_id = u_t.id');

// Replace SELECT lists
api = api.replace(
    'SELECT id, name FROM users WHERE role = \'teacher\'',
    'SELECT id, name FROM teachers'
);
api = api.replace(
    'student_profiles sp JOIN users u ON sp.user_id = u.id',
    'student_profiles sp JOIN students u ON sp.user_id = u.id'
);

fs.writeFileSync('api/api_manage_academic.php', api, 'utf8');
console.log('✅ Updated api_manage_academic.php for split tables!');
