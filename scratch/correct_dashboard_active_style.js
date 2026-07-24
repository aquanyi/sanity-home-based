const fs = require('fs');

function correctDashboardActiveStyles(file) {
    let c = fs.readFileSync(file, 'utf8');

    // Replace display: none with display: flex for the active state selector
    c = c.replace(
        /\.nav-category-wrap\.active\s+\.nav-category-submenu\s*\{\s*display:\s*none;\s*\}/g,
        '.nav-category-wrap.active .nav-category-submenu {\n            display: flex;\n        }'
    );

    fs.writeFileSync(file, c, 'utf8');
    console.log(`✅ Corrected active state style in ${file}`);
}

correctDashboardActiveStyles('accounts_dashboard.php');
correctDashboardActiveStyles('admin_dashboard.php');
