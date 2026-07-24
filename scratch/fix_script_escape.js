const fs = require('fs');

let admin = fs.readFileSync('admin_dashboard.php', 'utf8');

// Use regex to locate the end of the template string and escape the </script> tag
const regex = /<\/script>\s*<\/body>\s*<\/html>\s*`\s*;/g;

if (regex.test(admin)) {
    // Reset regex index
    regex.lastIndex = 0;
    admin = admin.replace(regex, (match) => {
        return match.replace('</script>', '<\\/script>');
    });
    console.log('✅ Successfully matched and escaped the closing script tag inside the template string!');
} else {
    console.log('❌ Could not match the template end pattern.');
}

fs.writeFileSync('admin_dashboard.php', admin, 'utf8');
