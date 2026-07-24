const fs = require('fs');

let api = fs.readFileSync('api/api_approve_lead.php', 'utf8');

// 1. Replace parent insertion query
api = api.replace(
    "INSERT INTO users (staff_id, name, email, phone, password, role, must_change_password) VALUES (?, ?, ?, ?, ?, 'parent', 1)",
    "INSERT INTO parents (staff_id, name, email, phone, password, must_change_password) VALUES (?, ?, ?, ?, ?, 1)"
);

// 2. Replace student insertion query
api = api.replace(
    "INSERT INTO users (staff_id, name, email, phone, password, role, must_change_password) VALUES (?, ?, ?, ?, ?, 'student', 1)",
    "INSERT INTO students (staff_id, name, email, phone, password, must_change_password) VALUES (?, ?, ?, ?, ?, 1)"
);

fs.writeFileSync('api/api_approve_lead.php', api, 'utf8');
console.log('✅ Updated api_approve_lead.php for split tables!');
