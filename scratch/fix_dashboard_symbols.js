const fs = require('fs');

let content = fs.readFileSync('accounts_dashboard.php', 'utf8');

// Specific replacements to clean up all the mangled characters
const replacements = [
    // Comment lines
    { regex: /\/\/ A A\?.*/g, replace: '// =========================================================================' },
    { regex: /\/\/ A\?\?,A\?\?,A.*/g, replace: '// =========================================================================' },
    { regex: /\/\/ Ã¢Ã¢.*/g, replace: '// =========================================================================' },
    
    // Icon mappings
    { regex: /A,\?oA/g, replace: '📦' },
    { regex: /AA/g, replace: '⚡' },
    { regex: /A,\?\?A /g, replace: '🔧' },
    { regex: /AEo /g, replace: '☕' },
    
    // Venue labels
    { regex: /A,A\?A School/g, replace: '🏫 School' },
    { regex: /A,A\?A Home/g, replace: '🏠 Home' },
    { regex: /A,A Online \(Google Meet\)/g, replace: '💻 Online (Google Meet)' },
    { regex: /A,\'A Online \(Zoom\)/g, replace: '📹 Online (Zoom)' },
    { regex: /A,\'A/g, replace: '📹' },
    
    // Special labels
    { regex: /A,A\?A/g, replace: '🏦' }, // Bank instructions label
    { regex: /A,-A"A_A,A\? Print/g, replace: '🖨️ Print' },
    { regex: /A"... Surplus/g, replace: '🟢 Surplus' },
    { regex: /AAA_A,A\? Deficit/g, replace: '🔴 Deficit' },
    { regex: /items A,A KES/g, replace: 'items · KES' },
    
    // Punctuation
    { regex: /A,\?\?/g, replace: '—' },
    { regex: /A\?\'/g, replace: '–' },
    { regex: /A,A/g, replace: '...' }
];

for (const rep of replacements) {
    content = content.replace(rep.regex, rep.replace);
}

// Clean up any remaining UTF-8 junk if there's any
content = content.replace(/â€”/g, '—');
content = content.replace(/â€“/g, '–');
content = content.replace(/â€¦/g, '...');
content = content.replace(/â€¢/g, '•');
content = content.replace(/ðŸ «/g, '🏫');
content = content.replace(/ðŸ  /g, '🏠');
content = content.replace(/ðŸ–¨ï¸ /g, '🖨️');
content = content.replace(/ðŸ ¦/g, '🏦');
content = content.replace(/â•/g, '═');
content = content.replace(/â”€/g, '─');

fs.writeFileSync('accounts_dashboard.php', content, 'utf8');
console.log("All special characters and symbols successfully cleaned!");
