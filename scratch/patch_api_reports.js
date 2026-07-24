const fs = require('fs');

let api = fs.readFileSync('api/api_manage_reports.php', 'utf8');

// Replace Students, Parents, Teachers joins
api = api.replace(/JOIN users u_student ON sp\.user_id = u_student\.id/g, 'JOIN students u_student ON sp.user_id = u_student.id');
api = api.replace(/JOIN users u_parent ON sp\.parent_id = u_parent\.id/g, 'JOIN parents u_parent ON sp.parent_id = u_parent.id');
api = api.replace(/JOIN users u_teacher ON ar\.teacher_id = u_teacher\.id/g, 'JOIN teachers u_teacher ON ar.teacher_id = u_teacher.id');
api = api.replace(/JOIN users u_teacher ON ts\.teacher_id = u_teacher\.id/g, 'JOIN teachers u_teacher ON ts.teacher_id = u_teacher.id');
api = api.replace(/JOIN users u ON es\.invigilator_teacher_id = u\.id/g, 'JOIN teachers u ON es.invigilator_teacher_id = u.id');
api = api.replace(/JOIN users u_t ON es\.invigilator_teacher_id = u_t\.id/g, 'JOIN teachers u_t ON es.invigilator_teacher_id = u_t.id');
api = api.replace(/JOIN users u ON sp\.user_id = u\.id/g, 'JOIN students u ON sp.user_id = u.id');
api = api.replace(/JOIN users u_p ON sp\.parent_id = u_p\.id/g, 'JOIN parents u_p ON sp.parent_id = u_p.id');

fs.writeFileSync('api/api_manage_reports.php', api, 'utf8');
console.log('✅ Updated api_manage_reports.php for split tables!');
