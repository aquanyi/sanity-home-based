const fs = require('fs');

const html = fs.readFileSync('accounts_dashboard.php', 'utf8');

const regex = /<script[^>]*>([\s\S]*?)<\/script>/gi;
const match = regex.exec(html);
const js = match[1];

// Find all function names defined as "function name("
const funcRegex = /function\s+([a-zA-Z0-9_]+)\s*\(/g;
let fMatch;
const funcNames = {};

while ((fMatch = funcRegex.exec(js)) !== null) {
    const name = fMatch[1];
    if (funcNames[name]) {
        funcNames[name]++;
    } else {
        funcNames[name] = 1;
    }
}

console.log("Duplicate function definitions:");
let duplicatesFound = false;
for (const [name, count] of Object.entries(funcNames)) {
    if (count > 1) {
        console.log(`- ${name} (defined ${count} times)`);
        duplicatesFound = true;
    }
}
if (!duplicatesFound) {
    console.log("No duplicate function definitions found.");
}
