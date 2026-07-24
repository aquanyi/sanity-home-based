const fs = require('fs');
const html = fs.readFileSync('accounts_dashboard.php', 'utf8');

const regex = /<script[^>]*>([\s\S]*?)<\/script>/gi;
const match = regex.exec(html);
if (!match) {
    console.log("No script tag found!");
    process.exit(1);
}

const js = match[1];
const lines = js.split('\n');

let braceCount = 0;
let lastUnbalancedLine = -1;

for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    
    // Simple brace scanner (ignoring comments/strings for now, but good enough for general trace)
    for (let char of line) {
        if (char === '{') braceCount++;
        else if (char === '}') braceCount--;
    }
    
    if (braceCount < 0) {
        console.log(`Braces went negative at line ${i + 1}: ${line.trim()}`);
        braceCount = 0; // reset
    }
}

console.log(`Final brace count (should be 0): ${braceCount}`);
