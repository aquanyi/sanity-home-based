const fs = require('fs');
const html = fs.readFileSync('accounts_dashboard.php', 'utf8');

const regex = /<script[^>]*>([\s\S]*?)<\/script>/gi;
const match = regex.exec(html);
const js = match[1];
const lines = js.split('\n');

let depth = 0;
let scopeStack = [];

for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    
    // Find functions or declarations
    let declMatch = line.match(/(function\s+[a-zA-Z0-9_]+|window\.onload|\bconst\s+[a-zA-Z0-9_]+\s*=\s*\([^)]*\)\s*=>)/);
    let name = declMatch ? declMatch[0] : '';
    
    let chars = [];
    // simple state to ignore string literals and comments
    let inString = null; // '"'`
    let inComment = false;
    
    for (let j = 0; j < line.length; j++) {
        let c = line[j];
        let next = line[j+1];
        
        if (inComment) {
            if (c === '*' && next === '/') {
                inComment = false;
                j++;
            }
            continue;
        }
        if (c === '/' && next === '/') {
            break; // comment till end of line
        }
        if (c === '/' && next === '*') {
            inComment = true;
            j++;
            continue;
        }
        if (inString) {
            if (c === inString && line[j-1] !== '\\') {
                inString = null;
            }
            continue;
        }
        if (c === '"' || c === "'" || c === '`') {
            inString = c;
            continue;
        }
        
        if (c === '{') {
            depth++;
            scopeStack.push({ line: i + 1, name: name || 'block' });
        } else if (c === '}') {
            depth--;
            if (scopeStack.length > 0) {
                scopeStack.pop();
            }
        }
    }
    
    if (declMatch && depth > 0) {
        // console.log(`[Line ${i+1}] Start scope ${name} (depth ${depth})`);
    }
}

console.log("Unclosed scopes left at end of file:");
console.log(JSON.stringify(scopeStack, null, 2));
