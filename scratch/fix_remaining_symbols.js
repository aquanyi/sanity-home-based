const fs = require('fs');

const lines = fs.readFileSync('accounts_dashboard.php', 'utf8').split('\n');

// Update matching line indexes (line_number - 1)
lines[1180] = lines[1180].replace(/[\uFFFD]+/g, '☕'); // petty_cash option dropdown
lines[1312] = lines[1312].replace(/[\uFFFD]+/g, '📹'); // online_zoom option dropdown
lines[1342] = lines[1342].replace(/[\uFFFD]+/g, '–');  // Net Financial Position header text
lines[1356] = lines[1356].replace(/[\uFFFD]+/g, '📈'); // Revenue Overview header emoji
lines[1366] = lines[1366].replace(/[\uFFFD]+/g, '📉'); // Expenses Overview header emoji
lines[2394] = '// ========================================================================='; // comment line
lines[2734] = lines[2734].replace(/[\uFFFD]+/g, '🖨️');  // Print / Save as PDF button
lines[2749] = '// ========================================================================='; // comment line
lines[2776] = lines[2776].replace(/[\uFFFD]+/g, '☕'); // petty_cash hint description
lines[3001] = lines[3001].replace(/[\uFFFD]+/g, '☕'); // petty_cash category meta icon
lines[3302] = lines[3302].replace(/[\uFFFD]+/g, '📹'); // online_zoom label icon
lines[3308] = lines[3308].replace(/[\uFFFD]+/g, '☕'); // petty_cash financial report category meta icon
lines[3436] = lines[3436].replace(/[\uFFFD]+/g, '–');  // financial report Period label
lines[3471] = lines[3471].replace(/[\uFFFD]+/g, '🎉'); // financial report surplus hint emoji
lines[3472] = lines[3472].replace(/[\uFFFD]+/g, '–');  // financial report period subtitle label

fs.writeFileSync('accounts_dashboard.php', lines.join('\n'), 'utf8');
console.log("Successfully cleaned up all remaining replacement character symbols!");
