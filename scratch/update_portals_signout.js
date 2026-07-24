const fs = require('fs');

// 1. Fix parent_portal.php
let parent = fs.readFileSync('parent_portal.php', 'utf8');

// Add --danger: #E74C3C; to parent_portal :root variables
parent = parent.replace(
    '--success: #2ECC71; --sidebar-w: 270px;',
    '--success: #2ECC71; --danger: #E74C3C; --sidebar-w: 270px;'
);

// Apply classes & fix inline styling in parent_portal.php
parent = parent.replace(
    '<div style="margin-top: auto; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); width: 100%;">',
    '<div class="sidebar-signout-wrap" style="margin-top: auto; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); width: 100%;">'
);

parent = parent.replace(
    '<div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">\r\n        <a href="logout.php" class="btn btn-sm btn-outline"',
    '<div class="main-signout-btn" style="display:flex; justify-content: flex-end; margin-bottom: 20px;">\r\n        <a href="logout.php" class="btn btn-sm btn-outline"'
);
parent = parent.replace(
    '<div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">\n        <a href="logout.php" class="btn btn-sm btn-outline"',
    '<div class="main-signout-btn" style="display:flex; justify-content: flex-end; margin-bottom: 20px;">\n        <a href="logout.php" class="btn btn-sm btn-outline"'
);

fs.writeFileSync('parent_portal.php', parent, 'utf8');
console.log('✔ Fixed parent_portal.php sign-out buttons and styles!');


// 2. Fix teacher_portal.php
let teacher = fs.readFileSync('teacher_portal.php', 'utf8');

// Apply classes & fix inline styling in teacher_portal.php
teacher = teacher.replace(
    '<div style="margin-top: auto; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); width: 100%;">',
    '<div class="sidebar-signout-wrap" style="margin-top: auto; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); width: 100%;">'
);

teacher = teacher.replace(
    '<div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">\r\n        <a href="logout.php" class="btn btn-sm btn-outline"',
    '<div class="main-signout-btn" style="display:flex; justify-content: flex-end; margin-bottom: 20px;">\r\n        <a href="logout.php" class="btn btn-sm btn-outline"'
);
teacher = teacher.replace(
    '<div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">\n        <a href="logout.php" class="btn btn-sm btn-outline"',
    '<div class="main-signout-btn" style="display:flex; justify-content: flex-end; margin-bottom: 20px;">\n        <a href="logout.php" class="btn btn-sm btn-outline"'
);

fs.writeFileSync('teacher_portal.php', teacher, 'utf8');
console.log('✔ Fixed teacher_portal.php sign-out buttons and styles!');
