const fs = require('fs');

let content = fs.readFileSync('accounts_dashboard.php', 'utf8');

// Replacements ordered by descending length of target string to avoid partial replacements
const replacements = [
    { search: 'Ã¢â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•', replace: '=====================================================' },
    { search: 'â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•', replace: '=====================================================' },
    { search: 'â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€', replace: '---------------------------------------------' },
    { search: 'â€”', replace: '—' },
    { search: 'â€“', replace: '–' },
    { search: 'â€¦', replace: '...' },
    { search: 'â€¢', replace: '•' },
    { search: 'â€™', replace: "'" },
    { search: 'â€˜', replace: "'" },
    { search: 'ðŸ «', replace: '🏫' },
    { search: 'ðŸ  ', replace: '🏠' },
    { search: 'ðŸ–¨ï¸ ', replace: '🖨️' },
    { search: 'ðŸ ¦', replace: '🏦' },
    { search: 'â• ', replace: '═' },
    { search: 'â”€', replace: '─' },
    { search: 'â ', replace: ' ' }
];

for (const rep of replacements) {
    // Escape regex characters
    const escapedSearch = rep.search.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
    const regex = new RegExp(escapedSearch, 'g');
    content = content.replace(regex, rep.replace);
}

// Replace any individual remaining weird sequence like â€” if they exist
content = content.replace(/â€”/g, '—');
content = content.replace(/â€“/g, '–');
content = content.replace(/â€¦/g, '...');

fs.writeFileSync('accounts_dashboard.php', content, 'utf8');
console.log("Successfully cleaned up all encoding and special symbol issues!");
