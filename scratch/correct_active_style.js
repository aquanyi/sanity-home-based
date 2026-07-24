const fs = require('fs');

function correctActiveStyle(file) {
    let c = fs.readFileSync(file, 'utf8');

    // Restore display: flex for the active state selector
    c = c.replace(
        /\.nav-category-wrap\.active\s+\.nav-category-submenu\s*\{\s*display:\s*none;\s*\}/g,
        '.nav-category-wrap.active .nav-category-submenu {\n            display: flex;\n        }'
    );

    fs.writeFileSync(file, c, 'utf8');
    console.log(`✅ Corrected active state style in ${file}`);
}

correctActiveStyle('parent_portal.php');
correctActiveStyle('teacher_portal.php');
