const fs = require('fs');
const vm = require('vm');

const file = 'admin_dashboard.php';
const content = fs.readFileSync(file, 'utf8');

const scriptRegex = /<script(?![^>]*src)[^>]*>([\s\S]*?)<\/script>/gi;
let match;
let blockNum = 0;

while ((match = scriptRegex.exec(content)) !== null) {
    blockNum++;
    const scriptContent = match[1].trim();
    if (!scriptContent) continue;
    console.log(`Checking script block ${blockNum}...`);
    try {
        new vm.Script(scriptContent);
        console.log(`Script block ${blockNum} is valid.`);
    } catch (e) {
        console.error(`Syntax error in script block ${blockNum}:`);
        console.error(e.message);
        process.exit(1);
    }
}

if (blockNum === 0) console.log('No inline script blocks found.');
