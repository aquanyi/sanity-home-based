const fs = require('fs');

function fixAccordionCSS(file) {
    let c = fs.readFileSync(file, 'utf8');

    // Find and update .nav-category-submenu display rule to default to 'none' instead of 'flex'
    // Accounts and Admin dashboard CSS code blocks have this pattern:
    // .nav-category-submenu {
    //     display: flex;
    // ...
    c = c.replace(
        /\.nav-category-submenu\s*\{\s*display:\s*flex;/g,
        '.nav-category-submenu {\n            display: none;'
    );

    fs.writeFileSync(file, c, 'utf8');
    console.log(`✅ Hidden sub-menus by default in ${file}`);
}

fixAccordionCSS('accounts_dashboard.php');
fixAccordionCSS('admin_dashboard.php');
