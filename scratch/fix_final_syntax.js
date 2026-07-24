const fs = require('fs');

let content = fs.readFileSync('accounts_dashboard.php', 'utf8');

// Replace the online_zoom label syntax error
content = content.replace("online_zoom: '📹'📹 Online (Zoom)'", "online_zoom: '📹 Online (Zoom)'");
content = content.replace("online_meet: '🎥 Online (Google Meet)',", "online_meet: '💻 Online (Google Meet)',");

// Replace the petty_cash icon trailing question mark
content = content.replace("icon:'☕?',", "icon:'☕',");

fs.writeFileSync('accounts_dashboard.php', content, 'utf8');
console.log("Successfully corrected final syntax and typos in Node.js!");
