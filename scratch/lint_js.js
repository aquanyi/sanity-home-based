const fs = require('fs');
const vm = require('vm');

const html = fs.readFileSync('accounts_dashboard.php', 'utf8');

// Find all script tags
const regex = /<script[^>]*>([\s\S]*?)<\/script>/gi;
let match;
let count = 0;

while ((match = regex.exec(html)) !== null) {
    count++;
    const jsCode = match[1];
    console.log(`Checking script block ${count}...`);
    try {
        new vm.Script(jsCode, { filename: `script_block_${count}.js` });
        console.log(`Script block ${count} is valid.`);
    } catch (err) {
        console.error(`Syntax error in script block ${count}:`);
        console.error(err.stack);
    }
}
