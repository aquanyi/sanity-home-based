const fs = require('fs');

let api = fs.readFileSync('api/api_accounts.php', 'utf8');

// 1. General replacements for Students, Parents, Teachers
api = api.replace(/JOIN users u_student ON sp\.user_id = u_student\.id/g, 'JOIN students u_student ON sp.user_id = u_student.id');
api = api.replace(/JOIN users u_teacher ON ts\.teacher_id = u_teacher\.id/g, 'JOIN teachers u_teacher ON ts.teacher_id = u_teacher.id');
api = api.replace(/JOIN users u_parent ON sp\.parent_id = u_parent\.id/g, 'JOIN parents u_parent ON sp.parent_id = u_parent.id');
api = api.replace(/LEFT JOIN users u_parent ON sp\.parent_id = u_parent\.id/g, 'LEFT JOIN parents u_parent ON sp.parent_id = u_parent.id');
api = api.replace(/JOIN users u ON sp\.user_id = u\.id/g, 'JOIN students u ON sp.user_id = u.id');
api = api.replace(/JOIN users u_s ON sp\.user_id = u_s\.id/g, 'JOIN students u_s ON sp.user_id = u_s.id');
api = api.replace(/JOIN users u_t ON ts\.teacher_id = u_t\.id/g, 'JOIN teachers u_t ON ts.teacher_id = u_t.id');

// 2. Replacements for teacher lists
api = api.replace(
    "SELECT id, name FROM users WHERE role = 'teacher' ORDER BY name ASC",
    "SELECT id, name FROM teachers ORDER BY name ASC"
);
api = api.replace(
    "FROM users u\n                WHERE u.role = 'teacher'",
    "FROM teachers u"
);
api = api.replace(
    "SELECT id, name, email FROM users WHERE id = ? AND role = 'teacher'",
    "SELECT id, name, email FROM teachers WHERE id = ?"
);

// 3. Replacements for recorded_by (Admins/Accounts)
// Query at line 351: get_expenses
api = api.replace(
    `SELECT e.*, u.name AS recorded_by_name
                FROM extra_expenses e
                JOIN users u ON u.id = e.recorded_by`,
    `SELECT e.*, COALESCE(u_adm.name, u_acc.name, 'Admin') AS recorded_by_name
                FROM extra_expenses e
                LEFT JOIN admins u_adm ON u_adm.id = e.recorded_by
                LEFT JOIN accounts_officers u_acc ON u_acc.id = e.recorded_by`
);

// Query at line 425: get_expense_details (if any) or similar
api = api.replace(
    `SELECT e.*, u.name AS recorded_by_name FROM extra_expenses e JOIN users u ON u.id = e.recorded_by`,
    `SELECT e.*, COALESCE(u_adm.name, u_acc.name, 'Admin') AS recorded_by_name FROM extra_expenses e LEFT JOIN admins u_adm ON u_adm.id = e.recorded_by LEFT JOIN accounts_officers u_acc ON u_acc.id = e.recorded_by`
);

// Query at line 580: get_invoice_details or get_payment_details
// Let's replace any general JOIN users u ON u.id = e.recorded_by (or equivalent recorded_by joins)
api = api.replace(
    `JOIN users u ON u.id = e.recorded_by`,
    `LEFT JOIN admins u_adm ON u_adm.id = e.recorded_by LEFT JOIN accounts_officers u_acc ON u_acc.id = e.recorded_by`
);
api = api.replace(
    `u.name AS recorded_by_name`,
    `COALESCE(u_adm.name, u_acc.name, 'Admin') AS recorded_by_name`
);

fs.writeFileSync('api/api_accounts.php', api, 'utf8');
console.log('✅ Updated api_accounts.php for split tables!');
