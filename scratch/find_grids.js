const fs = require('fs');

['accounts_dashboard.php', 'admin_dashboard.php'].forEach(file => {
    const lines = fs.readFileSync(file, 'utf8').split('\n');
    console.log('\n=== ' + file + ' ===');
    lines.forEach((line, i) => {
        if (line.includes('grid-template-columns') || line.includes('display:grid') || line.includes('display: grid')) {
            console.log((i + 1) + ': ' + line.trim().substring(0, 120));
        }
    });
});
