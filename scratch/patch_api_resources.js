const fs = require('fs');

let api = fs.readFileSync('api/api_resources.php', 'utf8');

// Replace upload join
api = api.replace(
    'LEFT JOIN users u ON lr.uploaded_by = u.id',
    'LEFT JOIN admins u ON lr.uploaded_by = u.id'
);

fs.writeFileSync('api/api_resources.php', api, 'utf8');
console.log('✅ Updated api_resources.php for split tables!');
