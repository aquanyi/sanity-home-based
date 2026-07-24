const fs = require('fs');

function win1252ToBytes(str) {
    const bytes = [];
    const win1252Map = {
        0x20AC: 128, 0x201A: 130, 0x0192: 131, 0x201E: 132, 0x2026: 133, 0x2020: 134, 0x2021: 135,
        0x02C6: 136, 0x2030: 137, 0x0160: 138, 0x2039: 139, 0x0152: 140, 0x017D: 142,
        0x2018: 145, 0x2019: 146, 0x201C: 147, 0x201D: 148, 0x2022: 149, 0x2013: 150, 0x2014: 151,
        0x02DC: 152, 0x2122: 153, 0x0161: 154, 0x203A: 155, 0x0153: 156, 0x017E: 158, 0x0178: 159
    };
    for (let i = 0; i < str.length; i++) {
        const code = str.charCodeAt(i);
        if (win1252Map[code] !== undefined) {
            bytes.push(win1252Map[code]);
        } else if (code <= 255) {
            bytes.push(code);
        } else {
            bytes.push(63); // '?'
        }
    }
    return Buffer.from(bytes);
}

// Read the file as a UTF-8 string
const content = fs.readFileSync('accounts_dashboard.php', 'utf8');

// Perform the first conversion (mangled UTF-8 -> Windows-1252 characters string)
const step1_bytes = win1252ToBytes(content);
const step1_str = step1_bytes.toString('utf8');

// Perform the second conversion (Windows-1252 characters string -> pristine UTF-8 string)
const step2_bytes = win1252ToBytes(step1_str);
const pristine_content = step2_bytes.toString('utf8');

// Save the restored file as UTF-8
fs.writeFileSync('accounts_dashboard.php', pristine_content, 'utf8');
console.log("Successfully restored accounts_dashboard.php to pristine UTF-8 encoding!");
