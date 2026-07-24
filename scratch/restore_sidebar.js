const fs = require('fs');

let c = fs.readFileSync('accounts_dashboard.php', 'utf8');

// Find and replace the entire broken sidebar
const startMarker = '<aside class="sidebar" id="sidebar">';
const endMarker = '</aside>';

const startIdx = c.indexOf(startMarker);
const endIdx = c.indexOf(endMarker, startIdx);

if (startIdx === -1 || endIdx === -1) {
    console.error('Could not find sidebar boundaries');
    process.exit(1);
}

const correctSidebar = `<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <img src="logo.png" alt="S.H.T.A">
        <p>Accounts Hub</p>
        <div style="margin-top:10px;padding:8px 12px;background:rgba(255,255,255,0.07);border-radius:8px;font-size:0.82rem;">
            <div style="color:var(--accent);font-weight:700;"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Accountant'); ?></div>
            <div style="color:rgba(255,255,255,0.5);font-size:0.75rem;"><?php echo strtoupper($_SESSION['user_role'] ?? 'ACCOUNTS'); ?></div>
        </div>
    </div>

    <!-- Overview -->
    <div class="nav-category-wrap">
        <div class="nav-category-header" onclick="toggleCategoryMenu(this)">
            <i class="fa-solid fa-grip"></i>
            <span>Overview</span>
            <i class="fa-solid fa-chevron-down chevron-icon"></i>
        </div>
        <div class="nav-category-submenu">
            <a href="javascript:void(0)" onclick="switchTab('dashboard')" class="submenu-item">Dashboard</a>
            <a href="javascript:void(0)" onclick="switchTab('sessions')" class="submenu-item">Completed Sessions</a>
            <a href="javascript:void(0)" onclick="switchTab('monthly')" class="submenu-item">Monthly Summary</a>
        </div>
    </div>

    <!-- Finance Operations -->
    <div class="nav-category-wrap">
        <div class="nav-category-header" onclick="toggleCategoryMenu(this)">
            <i class="fa-solid fa-grip"></i>
            <span>Finance Operations</span>
            <i class="fa-solid fa-chevron-down chevron-icon"></i>
        </div>
        <div class="nav-category-submenu">
            <a href="javascript:void(0)" onclick="switchTab('pricing')" class="submenu-item">Student Pricing</a>
            <a href="javascript:void(0)" onclick="switchTab('invoices')" class="submenu-item">Invoices &amp; Payments</a>
            <a href="javascript:void(0)" onclick="switchTab('parent-invoice')" class="submenu-item">Parent Invoice</a>
            <a href="javascript:void(0)" onclick="switchTab('payroll')" class="submenu-item">Teacher Payroll</a>
        </div>
    </div>

    <!-- Operations -->
    <div class="nav-category-wrap">
        <div class="nav-category-header" onclick="toggleCategoryMenu(this)">
            <i class="fa-solid fa-grip"></i>
            <span>Operations</span>
            <i class="fa-solid fa-chevron-down chevron-icon"></i>
        </div>
        <div class="nav-category-submenu">
            <a href="javascript:void(0)" onclick="switchTab('expenses')" class="submenu-item">Extra Expenses</a>
            <a href="javascript:void(0)" onclick="switchTab('expreport')" class="submenu-item">Expense Reports</a>
            <a href="javascript:void(0)" onclick="switchTab('finreport')" class="submenu-item">Full Financial Report</a>
        </div>
    </div>

    <!-- Sign Out -->
    <div class="sidebar-signout-wrap" style="margin-top: auto; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); width: 100%;">
        <a href="logout.php" class="nav-category-header" style="color: #FC8181; text-decoration: none; display: flex; align-items: center; gap: 12px; padding: 14px 18px;">
            <i class="fa-solid fa-right-from-bracket" style="color: #FC8181;"></i>
            <span style="font-weight: 700;">Sign Out</span>
        </a>
    </div>
</aside>`;

c = c.substring(0, startIdx) + correctSidebar + c.substring(endIdx + endMarker.length);

fs.writeFileSync('accounts_dashboard.php', c, 'utf8');
console.log('✅ Sidebar fully restored with Sign Out fix!');
