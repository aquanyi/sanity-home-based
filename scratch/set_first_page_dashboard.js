const fs = require('fs');

function setInitialTabToDashboard(file, storageKey) {
    let c = fs.readFileSync(file, 'utf8');

    // Pattern 1:
    // window.onload = () => {
    //     loadSessions();
    //     const savedTab = localStorage.getItem('accounts_dashboard_active_tab');
    // We modify this to:
    //     const savedTab = localStorage.getItem('accounts_dashboard_active_tab') || 'dashboard';
    c = c.replace(
        `const savedTab = localStorage.getItem('${storageKey}');`,
        `const savedTab = localStorage.getItem('${storageKey}') || 'dashboard';`
    );

    fs.writeFileSync(file, c, 'utf8');
    console.log(`✅ Set default onload tab to dashboard in ${file}`);
}

// 1. Accounts Dashboard
setInitialTabToDashboard('accounts_dashboard.php', 'accounts_dashboard_active_tab');

// 2. Admin Dashboard
setInitialTabToDashboard('admin_dashboard.php', 'admin_dashboard_active_tab');

// 3. Parent Portal (it currently lacks a localStorage load - let's add one)
let parent = fs.readFileSync('parent_portal.php', 'utf8');
const parentOnload = `window.onload = () => {
    loadDashboardStats();
};`;
const parentOnloadWithTab = `window.onload = () => {
    loadDashboardStats();
    const savedTab = localStorage.getItem('parent_portal_active_tab') || 'dashboard';
    switchTab(savedTab);
};`;
if (parent.includes(parentOnload)) {
    parent = parent.replace(parentOnload, parentOnloadWithTab);
}
// Add localStorage save inside parent switchTab(id)
parent = parent.replace(
    'function switchTab(id) {',
    "function switchTab(id) {\n    localStorage.setItem('parent_portal_active_tab', id);"
);
fs.writeFileSync('parent_portal.php', parent, 'utf8');
console.log('✅ Set default onload tab and save action in parent_portal.php');

// 4. Teacher Portal (it already has it but let's confirm default is 'dashboard')
let teacher = fs.readFileSync('teacher_portal.php', 'utf8');
teacher = teacher.replace(
    "function switchTab(id) {",
    "function switchTab(id) {\n    localStorage.setItem('teacher_portal_active_tab', id);"
);
fs.writeFileSync('teacher_portal.php', teacher, 'utf8');
console.log('✅ Added localStorage save action in teacher_portal.php');
