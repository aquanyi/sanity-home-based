const fs = require('fs');

// ── Fix admin_dashboard.php Sign Out ────────────────────────────────────────
let admin = fs.readFileSync('admin_dashboard.php', 'utf8');

// Add .sidebar-signout-wrap class and keep margin-top:auto
admin = admin.replace(
    '<div style="margin-top: auto; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); width: 100%;">',
    '<div class="sidebar-signout-wrap" style="margin-top: auto; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); width: 100%;">'
);

// Add the mobile override CSS rule to the admin @media (max-width: 800px) block
// Find the .main-signout-btn rule we added earlier and add the sign out rule after it
const mobileSignout = '            /* Sign Out: on mobile, don\'t push to bottom — show right after nav */\n            .sidebar-signout-wrap { margin-top: 20px !important; }\n\n';
const mobileHideSignout = '            .main-signout-btn { display: none !important; }';

if (!admin.includes('sidebar-signout-wrap') || admin.includes('.sidebar-signout-wrap { margin-top')) {
    // already has the CSS rule or we just added the HTML class
}

// Add CSS override in the admin @media block
if (admin.includes(mobileHideSignout) && !admin.includes('.sidebar-signout-wrap { margin-top')) {
    admin = admin.replace(
        mobileHideSignout,
        mobileHideSignout + '\n\n' + '            /* Sign Out: on mobile, show right after nav items */' + '\n            .sidebar-signout-wrap { margin-top: 20px !important; }'
    );
}

fs.writeFileSync('admin_dashboard.php', admin, 'utf8');
console.log('✅ Admin Sign Out fixed!');

// ── Verify accounts_dashboard.php sidebar ───────────────────────────────────
const accounts = fs.readFileSync('accounts_dashboard.php', 'utf8');
const sidebarStart = accounts.indexOf('<aside class="sidebar" id="sidebar">');
const sidebarEnd = accounts.indexOf('</aside>', sidebarStart);
const sidebar = accounts.substring(sidebarStart, sidebarEnd + 8);
const items = ['sidebar-logo', 'Overview', 'Finance Operations', 'Operations', 'Sign Out', 'sidebar-signout-wrap'];
items.forEach(item => {
    console.log('accounts sidebar has "' + item + '": ' + sidebar.includes(item));
});
