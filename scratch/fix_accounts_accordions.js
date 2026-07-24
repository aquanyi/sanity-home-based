const fs = require('fs');

function fixAccountsHeaders(file) {
    let c = fs.readFileSync(file, 'utf8');

    // Add onclick handler to all nav-category-header divs in accounts_dashboard.php
    c = c.replace(
        /<div class="nav-category-header"\s*>/g,
        '<div class="nav-category-header" onclick="toggleCategoryMenu(this)">'
    );

    fs.writeFileSync(file, c, 'utf8');
    console.log(`✅ Fixed collapsible menu interaction in ${file}`);
}

fixAccountsHeaders('accounts_dashboard.php');
