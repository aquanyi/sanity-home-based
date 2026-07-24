const fs = require('fs');

function fixPortals(file) {
    let c = fs.readFileSync(file, 'utf8');

    // 1. Correct CSS definitions to hide submenu by default and show it when active
    c = c.replace(
        /\.nav-category-submenu\s*\{\s*display:\s*flex;/g,
        '.nav-category-submenu {\n            display: none;'
    );
    
    // 2. Add onclick handler to all nav-category-header divs that don't have it
    c = c.replace(
        /<div class="nav-category-header"\s*>/g,
        '<div class="nav-category-header" onclick="toggleCategoryMenu(this)">'
    );

    fs.writeFileSync(file, c, 'utf8');
    console.log(`✅ Fixed collapsible menu interaction in ${file}`);
}

fixPortals('parent_portal.php');
fixPortals('teacher_portal.php');
