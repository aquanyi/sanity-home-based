const fs = require('fs');

let api = fs.readFileSync('api/api_lesson_attendance.php', 'utf8');

// Replace Students, Parents, Teachers joins
api = api.replace(/JOIN users u_student ON sp\.user_id = u_student\.id/g, 'JOIN students u_student ON sp.user_id = u_student.id');
api = api.replace(/JOIN users u_teacher ON ts\.teacher_id = u_teacher\.id/g, 'JOIN teachers u_teacher ON ts.teacher_id = u_teacher.id');
api = api.replace(/JOIN users u_parent ON sp\.parent_id = u_parent\.id/g, 'JOIN parents u_parent ON sp.parent_id = u_parent.id');

fs.writeFileSync('api/api_lesson_attendance.php', api, 'utf8');
console.log('✅ Updated api_lesson_attendance.php for split tables!');
