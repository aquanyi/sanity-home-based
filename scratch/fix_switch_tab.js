const fs = require('fs');

let content = fs.readFileSync('accounts_dashboard.php', 'utf8');

// 1. Update the start of switchTab to store the active tab in localStorage
const oldStart = "function switchTab(id, navEl) {\n    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));";
const oldStartCRLF = "function switchTab(id, navEl) {\r\n    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));";

const newStart = "function switchTab(id, navEl) {\n    localStorage.setItem('accounts_dashboard_active_tab', id);\n    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));";

if (content.includes(oldStartCRLF)) {
    content = content.replace(oldStartCRLF, newStart.replace(/\n/g, '\r\n'));
} else if (content.includes(oldStart)) {
    content = content.replace(oldStart, newStart);
} else {
    // Fallback regex replacement
    content = content.replace(/function\s+switchTab\s*\(\s*id\s*,\s*navEl\s*\)\s*\{/, "function switchTab(id, navEl) {\n    localStorage.setItem('accounts_dashboard_active_tab', id);");
}

// 2. Update the conditional switch bindings inside switchTab
const oldSwitch = `    if (id === 'dashboard' || id === 'sessions' || id === 'monthly') loadSessions();
    if (id === 'pricing') loadPricing();
    if (id === 'invoices') loadInvoiceDropdowns();
    if (id === 'payroll') loadPayrollTab();`;

const newSwitch = `    if (id === 'dashboard' || id === 'sessions' || id === 'monthly') loadSessions();
    if (id === 'pricing') loadPricing();
    if (id === 'invoices') loadInvoiceDropdowns();
    if (id === 'parent-invoice') initParentInvoice();
    if (id === 'payroll') loadPayrollTab();
    {
        const sidebarWrap = document.querySelector(\`[onclick*="switchTab('parent-invoice')"]\`)?.closest('.nav-category-wrap');
        if (sidebarWrap && !sidebarWrap.classList.contains('active')) {
            document.querySelectorAll('.nav-category-wrap').forEach(w => w.classList.remove('active'));
            sidebarWrap.classList.add('active');
        }
    }
    if (id === 'expenses') initExpensesTab();
    if (id === 'expreport') initReportTab();
    if (id === 'finreport') initFinReportTab();`;

// Replace handling both CRLF and LF line endings
const oldSwitchCRLF = oldSwitch.replace(/\n/g, '\r\n');
if (content.includes(oldSwitchCRLF)) {
    content = content.replace(oldSwitchCRLF, newSwitch.replace(/\n/g, '\r\n'));
} else if (content.includes(oldSwitch)) {
    content = content.replace(oldSwitch, newSwitch);
} else {
    // Fallback: replace line by line
    content = content.replace("if (id === 'payroll') loadPayrollTab();", "if (id === 'payroll') loadPayrollTab();\n    if (id === 'parent-invoice') initParentInvoice();\n    if (id === 'expenses') initExpensesTab();\n    if (id === 'expreport') initReportTab();\n    if (id === 'finreport') initFinReportTab();");
}

fs.writeFileSync('accounts_dashboard.php', content, 'utf8');
console.log("Successfully updated switchTab with localStorage persistence and tab initialization routes!");
