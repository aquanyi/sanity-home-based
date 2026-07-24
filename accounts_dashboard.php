<?php
require_once 'security.php';
start_secure_session();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.html?error=Please+log+in+to+access+the+Accounts+Dashboard#accounts');
    exit;
}
if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'accounts'])) {
    header('Location: login.html?error=Access+Denied:+Accounts+credentials+required#accounts');
    exit;
}
// Release session lock early since this page only reads session data
session_write_close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S.H.T.A ? Accounts Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4A0E17;
            --primary-light: #6b1422;
            --accent: #E5A93B;
            --cream: #FAF7F2;
            --white: #FFFFFF;
            --dark: #2A080D;
            --gray-100: #F8F9FA;
            --gray-200: #E9ECEF;
            --gray-500: #ADB5BD;
            --gray-600: #6C757D;
            --success: #2ECC71;
            --danger: #E74C3C;
            --warning: #F39C12;
            --info: #3498DB;
            --sidebar-w: 270px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Outfit', sans-serif; }
        body { display: flex; min-height: 100vh; background: var(--cream); }

        /* SIDEBAR */
        .sidebar { width: var(--sidebar-w); background: linear-gradient(180deg, #1a2744 0%, #2d3748 60%, #4A5568 100%); display: flex; flex-direction: column; padding: 25px 15px; flex-shrink: 0; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-logo { text-align: center; padding-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; }
        .sidebar-logo img { height: 55px; margin-bottom: 8px; }
        .sidebar-logo p { color: var(--accent); font-size: 0.78rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; }
        .nav-section-label { color: rgba(255,255,255,0.35); font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; padding: 15px 12px 6px; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 15px; color: rgba(255,255,255,0.75); border-radius: 10px; cursor: pointer; transition: all 0.25s; margin-bottom: 3px; font-weight: 500; }
        .nav-item i { width: 20px; text-align: center; font-size: 1rem; }
        .nav-item:hover { background: rgba(255,255,255,0.08); color: var(--white); }
        .nav-item.active { background: rgba(229,169,59,0.15); color: var(--accent); border-left: 3px solid var(--accent); }

        /* MAIN */
        .main { margin-left: var(--sidebar-w); flex: 1; padding: 30px 35px; max-height: 100vh; overflow-y: auto; }
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 1.9rem; font-weight: 800; color: var(--primary); }
        .page-header p { color: var(--gray-600); margin-top: 4px; font-size: 0.95rem; }

        /* METRICS */
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .metric-card { background: var(--white); border-radius: 18px; padding: 24px; display: flex; align-items: center; justify-content: space-between; border: 1px solid rgba(74,14,23,0.07); box-shadow: 0 8px 24px rgba(0,0,0,0.04); transition: all 0.3s ease; }
        .metric-card:hover { transform: translateY(-5px); box-shadow: 0 14px 32px rgba(74,14,23,0.10); }
        .metric-info h4 { font-size: 0.78rem; color: var(--gray-600); font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; }
        .metric-info p { font-size: 2rem; font-weight: 800; color: var(--primary); margin-top: 4px; }
        .metric-icon { width: 54px; height: 54px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .mi-sessions .metric-icon { background: linear-gradient(135deg, rgba(52,152,219,0.15), rgba(52,152,219,0.3)); color: var(--info); }
        .mi-revenue .metric-icon  { background: linear-gradient(135deg, rgba(46,204,113,0.15), rgba(46,204,113,0.3)); color: #27AE60; }
        .mi-online .metric-icon   { background: linear-gradient(135deg, rgba(229,169,59,0.15), rgba(229,169,59,0.3)); color: #B48D1B; }
        .mi-offline .metric-icon  { background: linear-gradient(135deg, rgba(74,14,23,0.1), rgba(74,14,23,0.2)); color: var(--primary); }

        /* PANEL */
        .panel { background: var(--white); border-radius: 20px; padding: 30px; border: 1px solid rgba(74,14,23,0.06); box-shadow: 0 8px 28px rgba(0,0,0,0.03); margin-bottom: 28px; }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px; padding-bottom: 14px; border-bottom: 2px solid var(--cream); flex-wrap: wrap; gap: 12px; }
        .panel-header h2 { font-size: 1.3rem; color: var(--primary); font-weight: 800; }

        /* SECTION */
        .section { display: none; animation: fadeUp 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        .section.active { display: block; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }

        /* TABLE */
        .table-wrap { overflow-x: auto; border-radius: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #FAF7F2; color: var(--primary); font-weight: 800; font-size: 0.82rem; padding: 13px 16px; text-align: left; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 15px 16px; font-size: 0.91rem; border-bottom: 1px solid var(--gray-200); vertical-align: middle; font-weight: 500; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(250,247,242,0.6); }
        .empty-row { text-align: center; color: var(--gray-500); padding: 35px; font-style: italic; }

        /* BADGES */
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 0.76rem; font-weight: 700; display: inline-block; }
        .badge-online  { background: #DBEAFE; color: #2563EB; }
        .badge-offline { background: rgba(229,169,59,0.2); color: #B45309; }
        .badge-success { background: #D1FAE5; color: #059669; }

        /* BUTTONS */
        .btn { padding: 10px 18px; border-radius: 10px; border: none; cursor: pointer; font-weight: 700; font-size: 0.86rem; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 7px; white-space: nowrap; }
        .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, #30080E 100%); color: white; box-shadow: 0 4px 14px rgba(74,14,23,0.2); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(74,14,23,0.35); }
        .btn-accent  { background: linear-gradient(135deg, #E5A93B 0%, #C98F28 100%); color: white; }
        .btn-outline { background: transparent; border: 2px solid var(--primary); color: var(--primary); }
        .btn-outline:hover { background: var(--primary); color: white; }
        .btn-sm { padding: 6px 12px; font-size: 0.78rem; border-radius: 8px; }
        .btn-group { display: flex; gap: 7px; }

        /* FORMS */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 18px; }
        .form-group { display: flex; flex-direction: column; gap: 7px; }
        .form-group label { font-weight: 700; font-size: 0.84rem; color: var(--dark); text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control { width: 100%; padding: 12px 15px; border: 2px solid var(--gray-200); border-radius: 10px; font-size: 0.93rem; outline: none; transition: all 0.3s; background: #F7FAFC; font-weight: 500; }
        .form-control:focus { border-color: var(--accent); background: white; box-shadow: 0 0 0 3px rgba(229,169,59,0.15); }

        /* MONTH SUMMARY CARDS */
        .month-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 18px; margin-bottom: 28px; }
        .month-card { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); border-radius: 16px; padding: 22px; color: white; position: relative; overflow: hidden; }
        .month-card::before { content: ''; position: absolute; top: -30px; right: -30px; width: 120px; height: 120px; background: rgba(255,255,255,0.05); border-radius: 50%; }
        .month-card h3 { font-size: 1.05rem; font-weight: 700; margin-bottom: 14px; color: var(--accent); }
        .month-stat { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.88rem; }
        .month-stat span:first-child { color: rgba(255,255,255,0.75); }
        .month-stat span:last-child { font-weight: 700; }
        .month-total { margin-top: 14px; padding-top: 14px; border-top: 1px solid rgba(255,255,255,0.15); display: flex; justify-content: space-between; align-items: center; }
        .month-total .revenue { font-size: 1.4rem; font-weight: 800; color: var(--accent); }

        /* MODAL */
        .modal-bg { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(5px); z-index: 200; display: flex; align-items: center; justify-content: center; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
        .modal-bg.open { opacity: 1; pointer-events: auto; }
        .modal-box { background: var(--white); border-radius: 18px; padding: 35px; width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto; animation: fadeUp 0.3s ease; }
        .modal-header { margin-bottom: 22px; padding-bottom: 14px; border-bottom: 2px solid var(--cream); }
        .modal-header h3 { font-size: 1.25rem; color: var(--primary); font-weight: 700; }

        /* ALERT */
        .alert { padding: 13px 17px; border-radius: 10px; margin-bottom: 20px; font-size: 0.9rem; font-weight: 500; display: none; }
        .alert-success { background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; }
        .alert-error   { background: #FEE2E2; color: #991B1B; border: 1px solid #FCA5A5; }
        .alert-info    { background: #DBEAFE; color: #1E40AF; border: 1px solid #BFDBFE; }

        /* FILTER BAR */
        .filter-bar { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; margin-bottom: 20px; }
        .filter-bar select, .filter-bar input { padding: 9px 14px; border: 2px solid var(--gray-200); border-radius: 9px; font-size: 0.88rem; outline: none; background: #F7FAFC; min-width: 160px; }
        .filter-bar select:focus, .filter-bar input:focus { border-color: var(--accent); }

        /* Mobile Top Bar */
        .mobile-header {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 65px;
            background: var(--white);
            border-bottom: 1px solid rgba(74,14,23,0.08);
            z-index: 1200;
            padding: 0 20px;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }
        .mobile-logo-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .mobile-logo-wrap img {
            height: 38px;
            width: auto;
        }
        .mobile-logo-wrap span {
            font-weight: 800;
            color: var(--primary);
            font-size: 1.05rem;
        }
        .hamburger-btn {
            background: none;
            border: none;
            font-size: 1.4rem;
            color: var(--primary);
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: background 0.3s;
        }
        .hamburger-btn:hover {
            background: var(--cream);
        }

        /* Sidebar Drawer Overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1040;
            backdrop-filter: blur(2px);
        }
        .sidebar-overlay.active {
            display: block;
        }

        /* Info Bar */
        .info-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            background: var(--white);
            padding: 14px 20px;
            border-radius: 12px;
            border: 1px solid rgba(74,14,23,0.04);
        }
        .info-date {
            font-weight: 600;
            color: var(--gray-600);
            font-size: 0.9rem;
        }
        .info-badges {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .info-badge-item {
            position: relative;
            font-size: 1.15rem;
            color: var(--primary);
            cursor: pointer;
        }
        .info-badge-count {
            position: absolute;
            top: -6px;
            right: -8px;
            background: var(--primary);
            color: var(--white);
            font-size: 0.65rem;
            font-weight: 700;
            min-width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1.5px solid var(--white);
        }

        /* ── GLOBAL OVERFLOW PREVENTION ── */
        *, *::before, *::after { box-sizing: border-box; }
        html, body { overflow-x: hidden; max-width: 100%; }

        /* Tables scroll horizontally inside their containers — page stays still */
        .table-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 8px; }
        .table-wrap table { min-width: 560px; }

        /* Responsive styling for smaller devices */
        @media (max-width: 800px) {
            body { flex-direction: column; padding-top: 65px; }
            .mobile-header { display: flex; }

            /* Sidebar turns into fixed slide-out drawer from the right */
            .sidebar {
                width: 280px;
                height: calc(100vh - 65px);
                position: fixed;
                top: 65px;
                right: -280px;
                z-index: 1050;
                padding: 25px 15px;
                transition: right 0.3s cubic-bezier(0.16, 1, 0.3, 1);
                box-shadow: -5px 0 15px rgba(0,0,0,0.1);
            }
            .sidebar.active { right: 0; }
            .sidebar-logo { display: none; }
            .nav-item { padding: 12px 15px; font-size: 0.9rem; }
            .main { margin-left: 0; padding: 20px 14px; max-height: none; overflow-x: hidden; }

            .main-signout-btn { display: none !important; }

            /* Sign Out: on mobile, don't push to bottom — show right after nav */
            .sidebar-signout-wrap { margin-top: 20px !important; }

            .info-bar { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
            .panel-header { flex-wrap: wrap; gap: 10px; }
            .btn-group { flex-wrap: wrap; gap: 8px; }
            .metrics-grid { grid-template-columns: repeat(2,1fr); gap: 12px; }
            .metric-card { padding: 16px; border-radius: 12px; min-width: 0; }
            .form-grid { grid-template-columns: 1fr !important; }
            .panel { overflow: hidden; }

            /* All tables get a scroll wrapper — no page-level sideways scroll */
            .table-wrap table { min-width: 480px; }
            th, td { padding: 9px 10px; font-size: 0.82rem; white-space: nowrap; }
            .modal-box { width: 96vw; max-width: 96vw; padding: 22px 16px; }
        }
        @media (max-width: 480px) {
            .main { padding: 12px 8px; }
            .panel { padding: 16px 12px; border-radius: 12px; }
            .panel-header h2 { font-size: 1rem; }
            .form-grid { gap: 10px; }
            .metrics-grid { grid-template-columns: 1fr; }
            .modal-box { padding: 18px 12px; }
            .info-date { font-size: 0.78rem; }
            .info-badges { gap: 10px; }
            .info-badge-item { font-size: 1rem; }
            .page-header h1 { font-size: 1.25rem; }
            .page-header p { font-size: 0.82rem; }
            th, td { padding: 7px 8px; font-size: 0.78rem; }
        }

        /* Collapsible Category Menus (KU Portal Style) */
        .nav-category-wrap {
            margin-bottom: 8px;
            width: 100%;
        }
        .nav-category-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            color: rgba(255,255,255,0.75);
            cursor: pointer;
            font-weight: 700;
            transition: background 0.3s, color 0.3s;
            border-radius: 10px;
        }
        .nav-category-header i.fa-grip {
            color: var(--accent);
            font-size: 0.95rem;
        }
        .nav-category-header .chevron-icon {
            margin-left: auto;
            font-size: 0.8rem;
            transition: transform 0.3s ease;
        }
        .nav-category-header:hover {
            background: rgba(255, 255, 255, 0.06);
            color: var(--white);
        }
        .nav-category-submenu {
            display: none;
            flex-direction: column;
            padding: 6px 0 10px 0;
            background: transparent;
            border-radius: 10px;
            margin-top: 4px;
        }
        .nav-category-wrap.active .nav-category-submenu {
            display: flex;
        }
        .nav-category-wrap.active .nav-category-header {
            background: rgba(229, 169, 59, 0.12);
            color: var(--accent);
        }
        .nav-category-wrap.active .chevron-icon {
            transform: rotate(180deg);
        }
        .submenu-item {
            display: block;
            padding: 10px 18px 10px 42px;
            color: #63b3ed;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: underline;
            transition: color 0.2s;
        }
        .submenu-item:hover {
            color: #90cdf4;
        }
        .submenu-item.active {
            color: var(--white);
            font-weight: 700;
            text-decoration: none;
        }
    
        /* ═══ RESPONSIVE UTILITY CLASSES ═══ */
        /* Stats summary row (3 columns → 1 on mobile) */
        .resp-stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        /* Compact two-column grid (billed-to, info pairs) */
        .resp-two-col-sm {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        @media (max-width: 800px) {
            .resp-stats-row { grid-template-columns: 1fr 1fr !important; }
            .resp-two-col-sm { grid-template-columns: 1fr !important; }
        }
        @media (max-width: 480px) {
            .resp-stats-row { grid-template-columns: 1fr !important; }
        }
    

        /* Two-column layout: selector panel + content */
        .resp-two-col {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 20px;
            align-items: start;
        }

        /* Three-column filter row: FROM DATE | TO DATE | CATEGORY | button */
        .resp-filter-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 14px;
            align-items: end;
        }

        /* Three-equal-column summary cards */
        .resp-three-col {
            /* keeps existing inline styles */
        }

        /* Two-column equal grid */
        .resp-two-col-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }

        @media (max-width: 800px) {
            /* Stack two-panel layout */
            .resp-two-col {
                grid-template-columns: 1fr !important;
            }

            /* Filter row: all fields stack vertically */
            .resp-filter-grid {
                grid-template-columns: 1fr 1fr !important;
            }
            .resp-filter-grid > div:last-child {
                grid-column: span 2;
            }

            /* Three-column → two-column */
            .resp-three-col,
            [style*="repeat(3, 1fr)"],
            [style*="repeat(3,1fr)"] {
                grid-template-columns: 1fr 1fr !important;
            }

            /* Two-col grid → single column */
            .resp-two-col-grid,
            .two-col-grid {
                grid-template-columns: 1fr !important;
            }

            /* Any grid with fixed px columns on mobile */
            [style*="280px 1fr"],
            [style*="300px 1fr"],
            [style*="320px 1fr"] {
                grid-template-columns: 1fr !important;
            }
        }

        @media (max-width: 480px) {
            /* Full single column on phones */
            .resp-filter-grid {
                grid-template-columns: 1fr !important;
            }
            .resp-filter-grid > div:last-child {
                grid-column: span 1;
            }
            .resp-three-col,
            [style*="repeat(3, 1fr)"],
            [style*="repeat(3,1fr)"] {
                grid-template-columns: 1fr !important;
            }
        }
    
    </style>
</head>
<body>

<!-- Mobile Header Bar -->
<div class="mobile-header">
    <div class="mobile-logo-wrap">
        <img src="logo.png" alt="S.H.T.A Logo">
        <span>S.H.T.A Accounts</span>
    </div>
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()">
        <i class="fa-solid fa-bars" id="hamburgerIcon"></i>
    </button>
</div>

<!-- Sidebar Drawer Backdrop -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
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
            
        </div>
        <div class="nav-category-submenu">
            <a href="javascript:void(0)" onclick="switchTab('dashboard')" class="submenu-item">Dashboard</a>
            <a href="javascript:void(0)" onclick="switchTab('sessions')" class="submenu-item">Completed Sessions</a>
            <a href="javascript:void(0)" onclick="switchTab('monthly')" class="submenu-item">Monthly Summary</a>
            <a href="javascript:void(0)" onclick="switchTab('notifications')" class="submenu-item">Messages &amp; Notifs</a>
        </div>
    </div>

    <!-- Finance Operations -->
    <div class="nav-category-wrap">
        <div class="nav-category-header" onclick="toggleCategoryMenu(this)">
            <i class="fa-solid fa-grip"></i>
            <span>Finance Operations</span>
            
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
</aside>

<main class="main">
    <!-- Info bar with Date and Badge Indicators -->
    <div class="info-bar">
        <div class="info-date"><?php echo date('l, F j, Y'); ?></div>
        <div class="info-badges">
            <div class="info-badge-item" onclick="switchTab('sessions')" title="Completed Sessions">
                <i class="fa-solid fa-calendar-check"></i>
                <span class="info-badge-count" id="badge-sessions-count">0</span>
            </div>
            <div class="info-badge-item" onclick="switchTab('invoices')" title="Payments & Invoices">
                <i class="fa-solid fa-file-invoice-dollar"></i>
                <span class="info-badge-count" id="badge-invoices-count">0</span>
            </div>
            <div class="info-badge-item" onclick="switchTab('payroll')" title="Teacher Payroll">
                <i class="fa-solid fa-hand-holding-dollar"></i>
                <span class="info-badge-count">1</span>
            </div>
        </div>
    </div>

    <div class="main-signout-btn" style="display:flex; justify-content: flex-end; margin-bottom: 20px;">
        <a href="logout.php" class="btn btn-sm btn-outline" style="border-color:var(--danger);color:var(--danger);background:transparent;" onmouseover="this.style.background='var(--danger)';this.style.color='white'" onmouseout="this.style.background='transparent';this.style.color='var(--danger)'">
            <i class="fa-solid fa-right-from-bracket"></i> Sign Out
        </a>
    </div>
    <div id="globalAlert" class="alert"></div>

    <!-- NOTIFICATIONS & MESSAGING -->
    <div id="section-notifications" class="section">
        <div class="page-header" style="margin-bottom:20px;">
            <h1>Messages &amp; Announcements</h1>
            <p>Send financial notices, requests, or messages to staff, tutors, or parents.</p>
        </div>

        <!-- Send Message Panel -->
        <div class="panel" style="margin-bottom:24px;">
            <div class="panel-header">
                <h2><i class="fa-solid fa-paper-plane" style="color:var(--accent);"></i> Send a Message</h2>
            </div>
            <form id="form-accounts-send-msg" onsubmit="sendAccountsMessage(event)">
                <div class="form-grid" style="grid-template-columns: 1fr 2fr; gap:20px;">
                    <div class="form-group">
                        <label style="font-weight:600; font-size:0.9rem;">Send To (Recipient) <span style="color:red;">*</span></label>
                        <div style="position:relative;">
                            <i class="fa-solid fa-user-gear" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--gray-400);"></i>
                            <select id="accounts-msg-recipient" name="recipient_role" class="form-control" required style="padding-left:40px;">
                                <option value="all">Send to All (School-Wide)</option>
                                <option value="admin">Admin (Principal)</option>
                                <option value="timetabler">Academic Operations Coordinator (Scheduling)</option>
                                <option value="teacher">Teachers Only</option>
                                <option value="parent">Parents Only</option>
                                <option value="student">Students Only</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label style="font-weight:600; font-size:0.9rem;">Subject / Title <span style="color:red;">*</span></label>
                        <div style="position:relative;">
                            <i class="fa-solid fa-heading" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--gray-400);"></i>
                            <input type="text" id="accounts-msg-title" name="title" class="form-control" required placeholder="e.g. Monthly Tuition Fee Notice / Expense Query" style="padding-left:40px;">
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-top:16px;">
                    <label style="font-weight:600; font-size:0.9rem;">Message Content <span style="color:red;">*</span></label>
                    <textarea id="accounts-msg-body" name="message" class="form-control" rows="4" required placeholder="Write your message here..."></textarea>
                </div>

                <div style="margin-top:20px; display:flex; justify-content:flex-end;">
                    <button type="submit" class="btn btn-primary" style="padding:10px 24px;"><i class="fa-solid fa-paper-plane" style="margin-right:6px;"></i> Send Message</button>
                </div>
            </form>
        </div>

        <!-- Received & Sent Messages Feed -->
        <div class="panel">
            <div class="panel-header" style="display:flex; justify-content:space-between; align-items:center;">
                <h2><i class="fa-solid fa-envelope-open-text" style="color:var(--accent);"></i> Message Feed &amp; System Notifications</h2>
                <button class="btn btn-outline btn-sm" onclick="loadNotifications()"><i class="fa-solid fa-rotate-right"></i> Refresh Feed</button>
            </div>
            <div id="notif-feed">
                <div class="no-data-msg">Loading notifications…</div>
            </div>
        </div>
    </div>

    <!-- DASHBOARD -->
    <div id="section-dashboard" class="section active">
        <div class="page-header">
            <h1>Accounts Overview</h1>
            <p>Financial summary and session tracking for Sanity Homebased Tuition Academy.</p>
        </div>
        <div class="metrics-grid">
            <div class="metric-card mi-sessions">
                <div class="metric-info"><h4>Total Completed Sessions</h4><p id="m-total">?</p></div>
                <div class="metric-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
            </div>
            <div class="metric-card mi-revenue">
                <div class="metric-info"><h4>Total Revenue (KES)</h4><p id="m-revenue">?</p></div>
                <div class="metric-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
            </div>
            <div class="metric-card mi-online">
                <div class="metric-info"><h4>Online (Campus) Sessions</h4><p id="m-online">?</p></div>
                <div class="metric-icon"><i class="fa-solid fa-school"></i></div>
            </div>
            <div class="metric-card mi-offline">
                <div class="metric-info"><h4>Offline (Home-Visit) Sessions</h4><p id="m-offline">?</p></div>
                <div class="metric-icon"><i class="fa-solid fa-house"></i></div>
            </div>
        </div>
        <div class="panel">
            <div class="panel-header"><h2>Current Month Breakdown</h2></div>
            <div id="dash-month-breakdown">
                <p style="color:var(--gray-600);">Loading current month data...</p>
            </div>
        </div>
        <div class="panel">
            <div class="panel-header"><h2>Quick Actions</h2></div>
            <div style="display:flex;gap:14px;flex-wrap:wrap;">
                <button class="btn btn-primary" onclick="switchTab('sessions', document.querySelector('.nav-item:nth-child(4)'))"><i class="fa-solid fa-calendar-check"></i> View All Sessions</button>
                <button class="btn btn-accent" onclick="switchTab('pricing', document.querySelector('.nav-item:last-child'))"><i class="fa-solid fa-tags"></i> Manage Pricing</button>
                <button class="btn btn-outline" onclick="exportCSV()"><i class="fa-solid fa-file-csv"></i> Export Report (CSV)</button>
            </div>
        </div>
    </div>

    <!-- COMPLETED SESSIONS -->
    <div id="section-sessions" class="section">
        <div class="page-header">
            <h1>Completed Sessions Log</h1>
            <p>All verified teacher check-in / check-out sessions displaying parent fees (from student pricing) and teacher payouts (from tutor payroll rates).</p>
        </div>
        <div class="panel">
            <div class="panel-header">
                <h2>Sessions Register</h2>
                <button class="btn btn-outline btn-sm" onclick="exportCSV()"><i class="fa-solid fa-file-csv"></i> Export CSV</button>
            </div>
            <div class="filter-bar">
                <select id="filter-month" onchange="filterSessions()">
                    <option value="">All Months</option>
                </select>
                <select id="filter-mode" onchange="filterSessions()">
                    <option value="">All Modes</option>
                    <option value="online_meet">Online (Google Meet)</option>
                    <option value="online_zoom">Online (Zoom)</option>
                    <option value="school">One-on-One (School)</option>
                    <option value="home_visit">One-on-One (Home)</option>
                </select>
                <input type="text" id="filter-search" placeholder="Search student / teacher..." oninput="filterSessions()">
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Teacher</th>
                            <th>Mode</th>
                            <th>Parent Fee (Invoice Rate)</th>
                            <th>Teacher Pay (Payroll Rate)</th>
                        </tr>
                    </thead>
                    <tbody id="sessions-tbody"><tr><td colspan="6" class="empty-row">Loading sessions...</td></tr></tbody>
                </table>
            </div>
            <div id="sessions-total-row" style="display:none; background:var(--cream); border-radius:10px; padding:16px 20px; margin-top:16px; display:flex; justify-content:space-between; align-items:center;">
                <strong style="color:var(--primary);">Showing <span id="sessions-count">0</span> sessions</strong>
                <strong style="color:var(--primary); font-size:1rem;"><span id="sessions-subtotal"></span></strong>
            </div>
        </div>
    </div>

    <!-- MONTHLY SUMMARY -->
    <div id="section-monthly" class="section">
        <div class="page-header">
            <h1>Monthly Session Summary</h1>
            <p>Aggregated session counts, parent revenue, and teacher payout per calendar month.</p>
        </div>
        <div class="panel">
            <div class="panel-header"><h2>Month-by-Month Breakdown</h2></div>
            <div id="monthly-cards" class="month-grid">
                <p style="color:var(--gray-600);">Loading monthly data...</p>
            </div>
        </div>
    </div>

    <!-- SESSION PRICING -->
    <div id="section-pricing" class="section">
        <div class="page-header">
            <h1>Student Session Pricing</h1>
            <p>Set per-session fees for each student. <strong>Note:</strong> Student pricing is used strictly to generate parent invoices and calculate student billing accounts (NOT to pay teachers).</p>
        </div>
        <div class="panel">
            <div class="panel-header"><h2>Pricing Registry</h2></div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Parent</th>
                            <th>Grade</th>
                            <th>Online (Meet)</th>
                            <th>Online (Zoom)</th>
                            <th>School Rate</th>
                            <th>Home Rate</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="pricing-tbody"><tr><td colspan="8" class="empty-row">Loading pricing data...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- INVOICES & PAYMENTS -->
    <div id="section-invoices" class="section">
        <div class="page-header">
            <h1>Student Invoices & Payments Ledger</h1>
            <p>Generate monthly invoices based on completed lessons and record fee payments from parents.</p>
        </div>
        <div class="resp-two-col">
            <!-- Left bar: Filters & Record Payment -->
            <div>
                <div class="panel">
                    <div class="panel-header"><h2>1. Selection</h2></div>
                    <div class="form-group" style="margin-bottom:12px;">
                        <label>Select Student</label>
                        <select id="inv-student-select" class="form-control" onchange="loadStudentInvoice()"></select>
                    </div>
                    <div class="form-group" style="margin-bottom:12px;">
                        <label>Select Month</label>
                        <select id="inv-month-select" class="form-control" onchange="loadStudentInvoice()">
                            <option value="">All Months</option>
                        </select>
                    </div>
                </div>
                
                <div class="panel" id="payment-panel" style="display:none;">
                    <div class="panel-header"><h2>2. Record Payment</h2></div>
                    <form onsubmit="submitStudentPayment(event)">
                        <div class="form-group" style="margin-bottom:12px;">
                            <label>Amount Paid (KES)</label>
                            <input type="number" id="pay-amount" class="form-control" required placeholder="e.g. 5000" min="1">
                        </div>
                        <div class="form-group" style="margin-bottom:12px;">
                            <label>Payment Date</label>
                            <input type="date" id="pay-date" class="form-control" required>
                        </div>
                        <div class="form-group" style="margin-bottom:15px;">
                            <label>Reference Note</label>
                            <input type="text" id="pay-ref" class="form-control" placeholder="e.g. Mpesa MP1234567, Cash...">
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;"><i class="fa-solid fa-receipt"></i> Record Payment</button>
                    </form>
                </div>
            </div>

            <!-- Right side: Invoice view & Ledger -->
            <div id="invoice-details-container" class="panel" style="display:none;">
                <div class="panel-header">
                    <h2 id="invoice-header">Monthly Invoice Details</h2>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <button class="btn btn-outline btn-sm" onclick="printInvoice()" id="btn-print-invoice"><i class="fa-solid fa-print"></i> Print Invoice</button>
                        <button class="btn btn-primary btn-sm" onclick="emailInvoice()" id="btn-email-invoice"><i class="fa-solid fa-envelope"></i> Email to Parent</button>
                    </div>
                </div>
                <div id="invoice-print-area">
                    <div style="border-bottom:2px solid var(--cream); padding-bottom:15px; margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <h3 style="color:var(--primary); font-size:1.4rem;">SANITY HOMEBASED TUITION</h3>
                            <p style="font-size:0.8rem;color:var(--gray-600);">Email: finance@sanitytuition.com | Phone: +254 7XX XXX XXX</p>
                        </div>
                        <div style="text-align:right;">
                            <h4 style="color:var(--accent); text-transform:uppercase; font-size:0.9rem; letter-spacing:1px;">Fee Invoice</h4>
                            <p style="font-size:0.82rem;font-weight:700;" id="invoice-date-label">Month: July 2026</p>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:space-between; margin-bottom:20px; font-size:0.88rem; background:#FAF7F2; padding:12px; border-radius:8px;">
                        <div>
                            <strong>Billed To:</strong><br>
                            Parent Name: <span id="inv-parent-name">-</span><br>
                            Email: <span id="inv-parent-email">-</span>
                        </div>
                        <div style="text-align:right;">
                            <strong>Student Profile:</strong><br>
                            Name: <span id="inv-student-name">-</span><br>
                            Grade: <span id="inv-student-grade">-</span>
                        </div>
                    </div>

                    <h3 style="font-size:0.95rem; color:var(--primary); margin-bottom:8px;"><i class="fa-solid fa-list-ol"></i> Billed Lessons Taught</h3>
                    <div class="table-wrap" style="margin-bottom:20px;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Tutor</th>
                                    <th>Format</th>
                                    <th>Rate (KES)</th>
                                </tr>
                            </thead>
                            <tbody id="inv-lessons-tbody">
                                <tr><td colspan="4" class="empty-row">No lessons for this selection.</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(3,1fr); gap: 15px; margin-top:20px; border-top: 1.5px solid var(--cream); padding-top:15px;" class="resp-three-col">
                        <div style="background:#FAF7F2; padding:15px; border-radius:10px; text-align:center;">
                            <span style="font-size:0.75rem; text-transform:uppercase; color:var(--gray-600); font-weight:700;">Total Billed (Month)</span>
                            <h4 style="font-size:1.15rem; color:var(--primary); margin-top:4px;" id="inv-total-month">KES 0</h4>
                        </div>
                        <div style="background:#EBFDF5; padding:15px; border-radius:10px; text-align:center;">
                            <span style="font-size:0.75rem; text-transform:uppercase; color:#065F46; font-weight:700;">Total Paid (All Time)</span>
                            <h4 style="font-size:1.15rem; color:#047857; margin-top:4px;" id="inv-total-paid">KES 0</h4>
                        </div>
                        <div style="background:#FEF2F2; padding:15px; border-radius:10px; text-align:center;">
                            <span style="font-size:0.75rem; text-transform:uppercase; color:#991B1B; font-weight:700;">Outstanding Balance</span>
                            <h4 style="font-size:1.15rem; color:#DC2626; margin-top:4px;" id="inv-balance">KES 0</h4>
                        </div>
                    </div>
                </div>

                <h3 style="font-size:0.95rem; color:var(--primary); margin-top:30px; margin-bottom:8px; border-top:1px dashed var(--gray-200); padding-top:20px;"><i class="fa-solid fa-clock-rotate-left"></i> Payment Ledger (All-Time History)</h3>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Ref / Notes</th>
                                <th>Amount (KES)</th>
                            </tr>
                        </thead>
                        <tbody id="inv-payments-tbody">
                            <tr><td colspan="3" class="empty-row">No payments recorded.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div id="invoice-empty-state" class="panel empty-row" style="padding:50px;">
                <i class="fa-solid fa-file-invoice" style="font-size:3rem;color:var(--gray-200);margin-bottom:15px;display:block;"></i>
                Please select a student and month to load billing details and invoices.
            </div>
        </div>
    </div>

    <!-- TEACHER PAYROLL -->
    <div id="section-payroll" class="section">
        <div class="page-header">
            <h1>Tutor Payroll & Rates Directory</h1>
            <p>Manage tutor pay scales for Zoom, Google Meet, School, and Home sessions. <strong>Note:</strong> Payout rates entered here are used strictly to calculate teacher session earnings and process tutor payroll disbursements.</p>
        </div>
        
        <!-- Toggle Menu -->
        <div style="display:flex;gap:10px;margin-bottom:20px;">
            <button id="pay-btn-rates" class="btn btn-primary" onclick="setPayrollSubTab('rates')"><i class="fa-solid fa-list"></i> Pay Rates Registry</button>
            <button id="pay-btn-ledger" class="btn btn-outline" onclick="setPayrollSubTab('ledger')"><i class="fa-solid fa-chart-line"></i> Monthly Payroll Reports</button>
        </div>

        <!-- SUB TAB: Rates Registry -->
        <div id="pay-subtab-rates" class="panel">
            <div class="panel-header"><h2>Tutor Rates Registry</h2></div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Teacher Name</th>
                            <th>Email</th>
                            <th>Online (Meet)</th>
                            <th>Online (Zoom)</th>
                            <th>School Rate</th>
                            <th>Home Rate</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="payroll-rates-tbody">
                        <tr><td colspan="7" class="empty-row">Loading tutor rates...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SUB TAB: Monthly Payroll reports -->
        <div id="pay-subtab-ledger" style="display:none;" class="resp-two-col">
            <div>
                <div class="panel">
                    <div class="panel-header"><h2>1. Selection</h2></div>
                    <div class="form-group" style="margin-bottom:12px;">
                        <label>Select Tutor</label>
                        <select id="pay-teacher-select" class="form-control" onchange="loadTeacherPayroll()"></select>
                    </div>
                    <div class="form-group" style="margin-bottom:12px;">
                        <label>Select Month</label>
                        <select id="pay-month-select" class="form-control" onchange="loadTeacherPayroll()">
                            <option value="">All Months</option>
                        </select>
                    </div>
                </div>
                
                <div class="panel" id="disburse-panel" style="display:none;">
                    <div class="panel-header"><h2>2. Record Pay Disbursed</h2></div>
                    <form onsubmit="submitTeacherDisbursement(event)">
                        <div class="form-group" style="margin-bottom:12px;">
                            <label>Amount Disbursed (KES)</label>
                            <input type="number" id="disb-amount" class="form-control" required placeholder="e.g. 10000" min="1">
                        </div>
                        <div class="form-group" style="margin-bottom:12px;">
                            <label>Payment Date</label>
                            <input type="date" id="disb-date" class="form-control" required>
                        </div>
                        <div class="form-group" style="margin-bottom:15px;">
                            <label>Reference Note</label>
                            <input type="text" id="disb-ref" class="form-control" placeholder="e.g. Bank Transfer, Mpesa Tx...">
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;"><i class="fa-solid fa-paper-plane"></i> Record Disbursement</button>
                    </form>
                </div>
            </div>

            <!-- Right side: Payroll Report -->
            <div id="payroll-details-container" class="panel" style="display:none;">
                <div class="panel-header">
                    <h2 id="payroll-header">Tutor Earnings Statement</h2>
                    <button class="btn btn-outline btn-sm" onclick="printPayroll()" id="btn-print-payroll"><i class="fa-solid fa-print"></i> Print Statement</button>
                </div>
                
                <div id="payroll-print-area">
                    <div style="border-bottom:2px solid var(--cream); padding-bottom:15px; margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <h3 style="color:var(--primary); font-size:1.4rem;">SANITY TUITION ACADEMY</h3>
                            <p style="font-size:0.8rem;color:var(--gray-600);">Tutor Payroll Statement</p>
                        </div>
                        <div style="text-align:right;">
                            <p style="font-size:0.82rem;font-weight:700;" id="payroll-date-label">Month: July 2026</p>
                            <p style="font-size:0.8rem;color:var(--gray-600);">Tutor: <span id="payroll-teacher-name">-</span></p>
                        </div>
                    </div>

                    <h3 style="font-size:0.95rem; color:var(--primary); margin-bottom:8px;"><i class="fa-solid fa-person-chalkboard"></i> Taught Lessons Summary</h3>
                    <div class="table-wrap" style="margin-bottom:20px;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Student</th>
                                    <th>Venue format</th>
                                    <th>Earned (KES)</th>
                                </tr>
                            </thead>
                            <tbody id="payroll-lessons-tbody">
                                <tr><td colspan="4" class="empty-row">No lessons taught.</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(3,1fr); gap: 15px; margin-top:20px; border-top: 1.5px solid var(--cream); padding-top:15px;" class="resp-three-col">
                        <div style="background:#FAF7F2; padding:15px; border-radius:10px; text-align:center;">
                            <span style="font-size:0.75rem; text-transform:uppercase; color:var(--gray-600); font-weight:700;">Earnings (Month)</span>
                            <h4 style="font-size:1.15rem; color:var(--primary); margin-top:4px;" id="payroll-total-month">KES 0</h4>
                        </div>
                        <div style="background:#EBFDF5; padding:15px; border-radius:10px; text-align:center;">
                            <span style="font-size:0.75rem; text-transform:uppercase; color:#065F46; font-weight:700;">Paid (All Time)</span>
                            <h4 style="font-size:1.15rem; color:#047857; margin-top:4px;" id="payroll-total-paid">KES 0</h4>
                        </div>
                        <div style="background:#FEF2F2; padding:15px; border-radius:10px; text-align:center;">
                            <span style="font-size:0.75rem; text-transform:uppercase; color:#991B1B; font-weight:700;">Tutor Balance</span>
                            <h4 style="font-size:1.15rem; color:#DC2626; margin-top:4px;" id="payroll-balance">KES 0</h4>
                        </div>
                    </div>
                </div>

                <h3 style="font-size:0.95rem; color:var(--primary); margin-top:30px; margin-bottom:8px; border-top:1px dashed var(--gray-200); padding-top:20px;"><i class="fa-solid fa-clock-rotate-left"></i> Disbursement Ledger (All-Time History)</h3>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Ref / Notes</th>
                                <th>Amount Disbursed (KES)</th>
                            </tr>
                        </thead>
                        <tbody id="payroll-payments-tbody">
                            <tr><td colspan="3" class="empty-row">No payroll disbursements recorded.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div id="payroll-empty-state" class="panel empty-row" style="padding:50px;">
                <i class="fa-solid fa-wallet" style="font-size:3rem;color:var(--gray-200);margin-bottom:15px;display:block;"></i>
                Please select a tutor and month to load payroll details and statement.
            </div>
        </div>
    </div>

    <!-- PARENT INVOICE GENERATOR -->
    <div id="section-parent-invoice" class="section">
        <div class="page-header">
            <h1><i class="fa-solid fa-paper-plane" style="color:var(--accent);margin-right:10px;"></i>Parent Invoice Generator</h1>
            <p>Build, edit and print a professional fee invoice for any parent — with session details, teacher names and payment instructions.</p>
        </div>

        <!-- Step 1: Select student & period -->
        <div class="resp-two-col">
            <div>
                <div class="panel">
                    <div class="panel-header"><h2><i class="fa-solid fa-sliders"></i> Build Invoice</h2></div>

                    <div class="form-group" style="margin-bottom:14px;">
                        <label>Student</label>
                        <select id="pi-student" class="form-control" onchange="piLoadSessions()"></select>
                    </div>
                    <div class="form-group" style="margin-bottom:14px;">
                        <label>Date From</label>
                        <input type="date" id="pi-from" class="form-control" onchange="piLoadSessions()">
                    </div>
                    <div class="form-group" style="margin-bottom:14px;">
                        <label>Date To</label>
                        <input type="date" id="pi-to" class="form-control" onchange="piLoadSessions()">
                    </div>
                    <div class="form-group" style="margin-bottom:18px;">
                        <label>Invoice Title / Note (optional)</label>
                        <input type="text" id="pi-note" class="form-control" placeholder="e.g. Term 2 · July 2026" oninput="piSyncNote()">
                    </div>

                    <div style="background:var(--cream);border-radius:12px;padding:16px;margin-bottom:16px;">
                        <p style="font-size:0.78rem;font-weight:800;text-transform:uppercase;color:var(--gray-600);margin-bottom:10px;">Bank / Payment Details</p>
                        <div class="form-group" style="margin-bottom:10px;">
                            <label style="font-size:0.77rem;">Bank Name</label>
                            <input type="text" id="pi-bank" class="form-control" value="Equity Bank Kenya" oninput="piSyncBank()">
                        </div>
                        <div class="form-group" style="margin-bottom:10px;">
                            <label style="font-size:0.77rem;">Account Name</label>
                            <input type="text" id="pi-accname" class="form-control" value="Sanity Homebased Tuition Academy" oninput="piSyncBank()">
                        </div>
                        <div class="form-group" style="margin-bottom:10px;">
                            <label style="font-size:0.77rem;">Account Number</label>
                            <input type="text" id="pi-accnum" class="form-control" value="0770299736484" oninput="piSyncBank()">
                        </div>
                        <div class="form-group" style="margin-bottom:10px;">
                            <label style="font-size:0.77rem;">M-Pesa Paybill / Till</label>
                            <input type="text" id="pi-mpesa" class="form-control" value="Paybill: 247247 · Acc: 0770299736484" oninput="piSyncBank()">
                        </div>
                        <div class="form-group">
                            <label style="font-size:0.77rem;">Due Date</label>
                            <input type="date" id="pi-due" class="form-control" oninput="piSyncBank()">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:14px;">
                        <label>Additional Notes for Parent</label>
                        <textarea id="pi-footer-note" class="form-control" rows="3" placeholder="e.g. Kindly pay by the due date. For queries call +254 716 942 939" oninput="piSyncFooter()" style="resize:vertical;"></textarea>
                    </div>

                    <button class="btn btn-primary" style="width:100%;justify-content:center;" onclick="piOpenPreview()">
                        <i class="fa-solid fa-eye"></i> Preview & Print Invoice
                    </button>
                </div>
            </div>

            <!-- Live Invoice Preview -->
            <div id="pi-preview-panel" class="panel" style="min-height:500px;">
                <div id="pi-empty-state" style="text-align:center;padding:80px 20px;color:var(--gray-500);">
                    <i class="fa-solid fa-file-invoice" style="font-size:3rem;opacity:0.2;display:block;margin-bottom:18px;"></i>
                    <strong>Select a student and date range</strong><br>
                    <span style="font-size:0.88rem;">Your invoice preview will appear here.</span>
                </div>

                <!-- Invoice Preview Area -->
                <div id="pi-preview-area" style="display:none;">
                    <!-- Header -->
                    <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:3px solid #E5A93B;padding-bottom:16px;margin-bottom:20px;">
                        <div style="display:flex;align-items:center;gap:16px;">
                            <img src="logo.png" style="height:64px;">
                            <div>
                                <div style="font-size:1.25rem;font-weight:900;color:#4A0E17;letter-spacing:0.3px;">SANITY HOMEBASED TUITION ACADEMY</div>
                                <div style="font-size:0.8rem;color:#6C757D;margin-top:3px;">Email: accounts@sanityeducation.com &nbsp;|&nbsp; Tel: +254 716 942 939 / +254 731 091 000</div>
                                <div style="font-size:0.78rem;color:#6C757D;">P.O. Box 12345, Nairobi, Kenya</div>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="background:linear-gradient(135deg,#4A0E17,#6b1422);color:white;padding:8px 18px;border-radius:10px;font-weight:800;font-size:1rem;letter-spacing:1px;">FEE INVOICE</div>
                            <div style="font-size:0.82rem;color:#6C757D;margin-top:6px;">Invoice No: <strong id="pi-inv-number">INV-001</strong></div>
                            <div style="font-size:0.82rem;color:#6C757D;">Date: <strong id="pi-inv-date"></strong></div>
                        </div>
                    </div>

                    <!-- Billed To / Student -->
                    <div style="gap:16px;margin-bottom:20px;" class="resp-two-col-sm">
                        <div style="background:#FAF7F2;border-radius:10px;padding:16px;border-left:4px solid #4A0E17;">
                            <p style="font-size:0.72rem;font-weight:800;text-transform:uppercase;color:#6C757D;margin-bottom:8px;">Billed To</p>
                            <div style="font-weight:800;font-size:1rem;color:#1e293b;" id="pi-parent-name-preview">—</div>
                            <div style="font-size:0.85rem;color:#6C757D;margin-top:4px;" id="pi-parent-email-preview">—</div>
                        </div>
                        <div style="background:#FAF7F2;border-radius:10px;padding:16px;border-left:4px solid #E5A93B;">
                            <p style="font-size:0.72rem;font-weight:800;text-transform:uppercase;color:#6C757D;margin-bottom:8px;">Student</p>
                            <div style="font-weight:800;font-size:1rem;color:#1e293b;" id="pi-student-name-preview">—</div>
                            <div style="font-size:0.85rem;color:#6C757D;margin-top:4px;" id="pi-student-grade-preview">—</div>
                            <div style="font-size:0.82rem;color:#6C757D;margin-top:2px;" id="pi-period-preview">—</div>
                        </div>
                    </div>

                    <!-- Note / Title -->
                    <div id="pi-note-preview" style="background:rgba(229,169,59,0.1);border:1px solid rgba(229,169,59,0.4);border-radius:8px;padding:10px 14px;margin-bottom:18px;font-weight:700;color:#4A0E17;font-size:0.92rem;display:none;"></div>

                    <!-- Sessions table (editable) -->
                    <div style="margin-bottom:6px;display:flex;justify-content:space-between;align-items:center;">
                        <h3 style="font-size:0.95rem;color:#4A0E17;font-weight:800;"><i class="fa-solid fa-list-ol"></i> Session Details</h3>
                        <button class="btn btn-outline btn-sm" onclick="piAddRow()"><i class="fa-solid fa-plus"></i> Add Row</button>
                    </div>
                    <div class="table-wrap" style="margin-bottom:18px;">
                        <table id="pi-sessions-table">
                            <thead>
                                <tr>
                                    <th style="width:50px;">#</th>
                                    <th>Date</th>
                                    <th>Tutor</th>
                                    <th>Session Type</th>
                                    <th>Amount (KES)</th>
                                    <th style="width:50px;"></th>
                                </tr>
                            </thead>
                            <tbody id="pi-sessions-tbody">
                                <tr><td colspan="6" class="empty-row">No sessions loaded.</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Totals -->
                    <div style="display:flex;justify-content:flex-end;margin-bottom:20px;">
                        <div style="min-width:280px;">
                            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:0.9rem;">
                                <span style="color:#6C757D;">Subtotal</span>
                                <span id="pi-subtotal-preview" style="font-weight:700;">KES 0.00</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:0.9rem;">
                                <span style="color:#6C757D;">Previously Paid</span>
                                <span id="pi-paid-preview" style="font-weight:700;color:#047857;">KES 0.00</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;padding:12px 0;background:linear-gradient(135deg,#4A0E17,#30080E);border-radius:8px;padding:12px 16px;margin-top:8px;">
                                <span style="color:rgba(255,255,255,0.85);font-weight:700;">AMOUNT DUE</span>
                                <span id="pi-due-preview" style="color:#E5A93B;font-weight:900;font-size:1.1rem;">KES 0.00</span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment details -->
                    <div id="pi-bank-preview" style="background:#FAF7F2;border-radius:12px;padding:16px 20px;margin-bottom:18px;border:1px solid rgba(74,14,23,0.1);">
                        <p style="font-size:0.75rem;font-weight:800;text-transform:uppercase;color:#6C757D;margin-bottom:10px;letter-spacing:0.8px;"><i class="fa-solid fa-building-columns"></i> Payment Instructions</p>
                        <div style="gap:8px;font-size:0.88rem;" class="resp-two-col-sm">
                            <div><span style="color:#6C757D;">Bank:</span> <strong id="pi-bank-name-p">Equity Bank Kenya</strong></div>
                            <div><span style="color:#6C757D;">Account Name:</span> <strong id="pi-accname-p">Sanity Homebased Tuition Academy</strong></div>
                            <div><span style="color:#6C757D;">Account No:</span> <strong id="pi-accnum-p">0770299736484</strong></div>
                            <div><span style="color:#6C757D;">M-Pesa:</span> <strong id="pi-mpesa-p">Paybill: 247247 · Acc: 0770299736484</strong></div>
                            <div><span style="color:#6C757D;">Due Date:</span> <strong id="pi-due-date-p" style="color:#DC2626;">—</strong></div>
                        </div>
                    </div>

                    <!-- Footer note -->
                    <div id="pi-footer-note-preview" style="font-size:0.85rem;color:#6C757D;font-style:italic;padding:10px 14px;border-top:1px dashed #e2e8f0;display:none;"></div>

                    <!-- Signature area -->
                    <div style="display:flex;justify-content:space-between;margin-top:30px;padding-top:16px;border-top:1px solid #e2e8f0;font-size:0.82rem;color:#6C757D;">
                        <div>Prepared by: <strong>Accounts Department</strong><br><div style="border-top:1px solid #4A0E17;width:160px;margin-top:28px;text-align:center;padding-top:4px;">Authorised Signature</div></div>
                        <div style="text-align:right;"><strong style="color:#4A0E17;">Sanity Homebased Tuition Academy</strong><br><span>Thank you for choosing us!</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Print Preview Modal -->
        <div id="pi-print-modal" class="modal-bg">
            <div class="modal-box" style="max-width:900px;">
                <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h3><i class="fa-solid fa-print"></i> Print Preview</h3>
                    <div style="display:flex;gap:10px;">
                        <button class="btn btn-primary" onclick="piDoPrint()"><i class="fa-solid fa-print"></i> Print / Save PDF</button>
                        <button class="btn btn-outline" onclick="closeModal('pi-print-modal')"><i class="fa-solid fa-xmark"></i> Close</button>
                    </div>
                </div>
                <div id="pi-modal-body" style="font-size:0.88rem;"></div>
            </div>
        </div>
    </div>
    <!-- EXTRA EXPENSES -->
    <div id="section-expenses" class="section">
        <div class="page-header">
            <h1>Extra Expenses</h1>
            <p>Track and manage all operational expenses — inventory, utilities, repairs, and petty cash.</p>
        </div>

        <!-- Category Summary Cards -->
        <div class="metrics-grid" style="margin-bottom:28px;">
            <div class="metric-card" style="border-left:4px solid #3B82F6;">
                <div class="metric-info"><h4>Inventory</h4><p id="exp-total-inventory" style="font-size:1.3rem;">KES 0</p></div>
                <div class="metric-icon" style="background:linear-gradient(135deg,rgba(59,130,246,0.12),rgba(59,130,246,0.25));color:#3B82F6;"><i class="fa-solid fa-boxes-stacked"></i></div>
            </div>
            <div class="metric-card" style="border-left:4px solid #10B981;">
                <div class="metric-info"><h4>Utilities</h4><p id="exp-total-utility" style="font-size:1.3rem;">KES 0</p></div>
                <div class="metric-icon" style="background:linear-gradient(135deg,rgba(16,185,129,0.12),rgba(16,185,129,0.25));color:#10B981;"><i class="fa-solid fa-bolt"></i></div>
            </div>
            <div class="metric-card" style="border-left:4px solid #F59E0B;">
                <div class="metric-info"><h4>General Repairs</h4><p id="exp-total-general_repairs" style="font-size:1.3rem;">KES 0</p></div>
                <div class="metric-icon" style="background:linear-gradient(135deg,rgba(245,158,11,0.12),rgba(245,158,11,0.25));color:#F59E0B;"><i class="fa-solid fa-screwdriver-wrench"></i></div>
            </div>
            <div class="metric-card" style="border-left:4px solid #8B5CF6;">
                <div class="metric-info"><h4>Petty Cash</h4><p id="exp-total-petty_cash" style="font-size:1.3rem;">KES 0</p></div>
                <div class="metric-icon" style="background:linear-gradient(135deg,rgba(139,92,246,0.12),rgba(139,92,246,0.25));color:#8B5CF6;"><i class="fa-solid fa-mug-hot"></i></div>
            </div>
        </div>

        <!-- Sub-category Tabs -->
        <div style="display:flex;gap:10px;margin-bottom:24px;flex-wrap:wrap;">
            <button id="exp-btn-inventory"       class="btn btn-primary" onclick="setExpenseTab('inventory')"><i class="fa-solid fa-boxes-stacked"></i> Inventory</button>
            <button id="exp-btn-utility"         class="btn btn-outline" onclick="setExpenseTab('utility')"><i class="fa-solid fa-bolt"></i> Utilities</button>
            <button id="exp-btn-general_repairs" class="btn btn-outline" onclick="setExpenseTab('general_repairs')"><i class="fa-solid fa-screwdriver-wrench"></i> General Repairs</button>
            <button id="exp-btn-petty_cash"      class="btn btn-outline" onclick="setExpenseTab('petty_cash')"><i class="fa-solid fa-mug-hot"></i> Petty Cash</button>
        </div>

        <!-- Active Category Panel -->
        <div class="panel">
            <div class="panel-header">
                <h2 id="exp-panel-title">Inventory Management</h2>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <div style="position:relative;">
                        <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--gray-500);font-size:0.82rem;"></i>
                        <input type="text" id="exp-search" class="form-control" placeholder="Search expenses…" style="padding-left:30px;min-width:210px;" oninput="filterExpensesSearch()">
                    </div>
                    <select id="exp-filter-month" class="form-control" style="min-width:160px;padding:8px 12px;" onchange="loadExpenses()">
                        <option value="">All Months</option>
                    </select>
                    <button class="btn btn-primary" onclick="openExpenseModal()">
                        <i class="fa-solid fa-plus"></i> Add Expense
                    </button>
                </div>

            </div>

            <!-- Category Description -->
            <div id="exp-category-hint" style="background:var(--cream);border-radius:10px;padding:12px 18px;margin-bottom:18px;font-size:0.88rem;color:var(--gray-600);border-left:4px solid var(--accent);"></div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Item / Name</th>
                            <th>Description</th>
                            <th>Reference</th>
                            <th>Recorded By</th>
                            <th>Amount (KES)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="expenses-tbody">
                        <tr><td colspan="7" class="empty-row">Loading…</td></tr>
                    </tbody>
                </table>
            </div>

            <div id="expenses-total-row" style="display:none;background:var(--cream);border-radius:10px;padding:14px 20px;margin-top:14px;display:flex;justify-content:space-between;align-items:center;">
                <strong style="color:var(--primary);">Showing <span id="expenses-count">0</span> records</strong>
                <strong style="color:var(--primary);font-size:1.1rem;">Total: KES <span id="expenses-subtotal">0.00</span></strong>
            </div>
        </div>
    </div>

    <!-- ===== EXPENSE REPORTS ===== -->
    <div id="section-expreport" class="section">
        <div class="page-header">
            <h1>Expense Reports</h1>
            <p>Generate, filter and export expense reports by period or category.</p>
        </div>

        <!-- ── Filter Panel ── -->
        <div class="panel" style="margin-bottom:24px;">
            <div class="panel-header"><h2><i class="fa-solid fa-sliders"></i> Report Filters</h2></div>

            <!-- Quick period buttons -->
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;">
                <button id="rpt-q-today"   class="btn btn-outline btn-sm" onclick="setReportPeriod('today')">Today</button>
                <button id="rpt-q-week"    class="btn btn-outline btn-sm" onclick="setReportPeriod('week')">This Week</button>
                <button id="rpt-q-month"   class="btn btn-outline btn-sm" onclick="setReportPeriod('month')">This Month</button>
                <button id="rpt-q-quarter" class="btn btn-outline btn-sm" onclick="setReportPeriod('quarter')">This Quarter</button>
                <button id="rpt-q-year"    class="btn btn-outline btn-sm" onclick="setReportPeriod('year')">This Year</button>
                <button id="rpt-q-custom"  class="btn btn-outline btn-sm" onclick="setReportPeriod('custom')">Custom Range</button>
            </div>

            <!-- Filter row -->
            <div class="resp-filter-grid">
                <div class="form-group">
                    <label>From Date</label>
                    <input type="date" id="rpt-from" class="form-control" onchange="markCustom()">
                </div>
                <div class="form-group">
                    <label>To Date</label>
                    <input type="date" id="rpt-to" class="form-control" onchange="markCustom()">
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select id="rpt-category" class="form-control">
                        <option value="">All Categories</option>
                        <option value="inventory">📦 Inventory</option>
                        <option value="utility">⚡ Utilities</option>
                        <option value="general_repairs">🔧 General Repairs</option>
                        <option value="petty_cash">☕? Petty Cash</option>
                    </select>
                </div>
                <div style="display:flex;gap:8px;">
                    <button class="btn btn-primary" onclick="generateReport()" id="rpt-gen-btn">
                        <i class="fa-solid fa-magnifying-glass-chart"></i> Generate
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Report Output (hidden until generated) ── -->
        <div id="rpt-output" style="display:none;">

            <!-- Report header bar -->
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
                <div>
                    <h2 style="font-size:1.15rem;font-weight:800;color:var(--primary);" id="rpt-title-label">Expense Report</h2>
                    <p style="font-size:0.85rem;color:var(--gray-600);" id="rpt-date-range-label"></p>
                </div>
                <div style="display:flex;gap:10px;">
                    <button class="btn btn-outline" onclick="exportReportCSV()">
                        <i class="fa-solid fa-file-csv"></i> Export CSV
                    </button>
                    <button class="btn btn-primary" onclick="printReport()">
                        <i class="fa-solid fa-print"></i> Print Report
                    </button>
                </div>
            </div>

            <!-- Summary cards per category -->
            <div class="metrics-grid" style="margin-bottom:24px;" id="rpt-summary-cards"></div>

            <!-- Grand total bar -->
            <div style="background:linear-gradient(135deg,var(--primary),#30080E);border-radius:14px;padding:20px 28px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;color:white;">
                <div>
                    <div style="font-size:0.78rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,0.65);font-weight:700;">Grand Total</div>
                    <div style="font-size:2.2rem;font-weight:800;color:var(--accent);" id="rpt-grand-total">KES 0.00</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:0.78rem;color:rgba(255,255,255,0.65);font-weight:700;">Total Records</div>
                    <div style="font-size:2rem;font-weight:800;" id="rpt-grand-count">0</div>
                </div>
            </div>

            <!-- Per-category breakdown panels -->
            <div id="rpt-cat-panels"></div>

            <!-- Full expenses table -->
            <div class="panel" id="rpt-full-table-panel">
                <div class="panel-header"><h2>All Expense Records</h2></div>
                <div class="table-wrap">
                    <table id="rpt-full-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Item / Name</th>
                                <th>Description</th>
                                <th>Reference</th>
                                <th>Recorded By</th>
                                <th style="text-align:right;">Amount (KES)</th>
                            </tr>
                        </thead>
                        <tbody id="rpt-full-tbody"></tbody>
                        <tfoot id="rpt-full-tfoot"></tfoot>
                    </table>
                </div>
            </div>

        </div>

        <!-- Empty state -->
        <div id="rpt-empty-state" class="panel" style="text-align:center;padding:60px 20px;">
            <i class="fa-solid fa-chart-pie" style="font-size:3.5rem;color:var(--gray-200);margin-bottom:18px;display:block;"></i>
            <h3 style="color:var(--gray-500);font-weight:600;">No Report Generated Yet</h3>
            <p style="color:var(--gray-500);margin-top:8px;font-size:0.9rem;">Choose a period and click <strong>Generate</strong> to view your expense report.</p>
        </div>
    </div>

    <!-- ===== FULL SCHOOL FINANCIAL REPORT ===== -->
    <div id="section-finreport" class="section">
        <div class="page-header">
            <h1>Full School Financial Report</h1>
            <p>Generate comprehensive financial summaries — revenue, expenses, students, teachers and lesson types.</p>
        </div>

        <!-- Filter Panel -->
        <div class="panel" style="margin-bottom:24px;">
            <div class="panel-header">
                <h2><i class="fa-solid fa-sliders"></i> Report Filters</h2>
                <button class="btn btn-outline btn-sm" onclick="resetFinFilters()"><i class="fa-solid fa-rotate-left"></i> Reset</button>
            </div>
            <!-- Quick periods -->
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;">
                <button id="fin-q-today"   class="btn btn-outline btn-sm" onclick="setFinPeriod('today')">Today</button>
                <button id="fin-q-week"    class="btn btn-outline btn-sm" onclick="setFinPeriod('week')">This Week</button>
                <button id="fin-q-month"   class="btn btn-primary btn-sm" onclick="setFinPeriod('month')">This Month</button>
                <button id="fin-q-quarter" class="btn btn-outline btn-sm" onclick="setFinPeriod('quarter')">This Quarter</button>
                <button id="fin-q-year"    class="btn btn-outline btn-sm" onclick="setFinPeriod('year')">This Year</button>
                <button id="fin-q-custom"  class="btn btn-outline btn-sm" onclick="setFinPeriod('custom')">Custom Range</button>
            </div>
            <!-- Filter grid -->
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-bottom:20px;">
                <div class="form-group">
                    <label>From Date</label>
                    <input type="date" id="fin-from" class="form-control" onchange="markFinCustom()">
                </div>
                <div class="form-group">
                    <label>To Date</label>
                    <input type="date" id="fin-to" class="form-control" onchange="markFinCustom()">
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-user-graduate"></i> Student</label>
                    <select id="fin-student" class="form-control">
                        <option value="">All Students</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-chalkboard-user"></i> Teacher / Tutor</label>
                    <select id="fin-teacher" class="form-control">
                        <option value="">All Teachers</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-location-dot"></i> Lesson Type</label>
                    <select id="fin-venue" class="form-control">
                        <option value="">All Lesson Types</option>
                        <option value="school">🏫 School (1-on-1)</option>
                        <option value="home_visit">🏠 Home Visit</option>
                        <option value="online_meet">🎥 Online (Google Meet)</option>
                        <option value="online_zoom">📹'📹 Online (Zoom)</option>
                    </select>
                </div>
            </div>
            <button class="btn btn-primary" onclick="generateFinReport()" id="fin-gen-btn" style="min-width:190px;">
                <i class="fa-solid fa-chart-column"></i> Generate Report
            </button>
        </div>

        <!-- Report Output -->
        <div id="finrpt-output" style="display:none;">

            <!-- Header bar -->
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:22px;flex-wrap:wrap;gap:12px;">
                <div>
                    <h2 style="font-size:1.2rem;font-weight:800;color:var(--primary);" id="finrpt-title">School Financial Report</h2>
                    <p style="font-size:0.85rem;color:var(--gray-600);margin-top:4px;" id="finrpt-subtitle"></p>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <button class="btn btn-outline" onclick="exportFinCSV()"><i class="fa-solid fa-file-csv"></i> Export CSV</button>
                    <button class="btn btn-primary" onclick="printFinReport()"><i class="fa-solid fa-print"></i> Print Report</button>
                </div>
            </div>

            <!-- 5 KPI Cards -->
            <div class="metrics-grid" style="margin-bottom:24px;" id="finrpt-kpi-cards"></div>

            <!-- Net Position Banner -->
            <div id="finrpt-net-banner" style="border-radius:16px;padding:22px 30px;margin-bottom:28px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
                <div>
                    <div style="font-size:0.74rem;text-transform:uppercase;letter-spacing:1px;font-weight:700;color:rgba(255,255,255,0.6);margin-bottom:6px;">Net Financial Position (Collected –' Expenses)</div>
                    <div style="font-size:2.4rem;font-weight:800;" id="finrpt-net-value">KES 0.00</div>
                    <div style="font-size:0.84rem;margin-top:6px;color:rgba(255,255,255,0.75);" id="finrpt-net-hint"></div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:0.74rem;color:rgba(255,255,255,0.6);font-weight:700;letter-spacing:1px;margin-bottom:6px;">REPORT PERIOD</div>
                    <div style="font-size:1.15rem;font-weight:700;" id="finrpt-period-label"></div>
                    <div style="font-size:0.85rem;color:rgba(255,255,255,0.65);margin-top:4px;" id="finrpt-sessions-label"></div>
                </div>
            </div>

            <!-- Revenue &amp; Expenses side-by-side -->
            <div class="resp-two-col-grid two-col-grid">
                <div class="panel" style="border-top:4px solid #10B981;">
                    <div class="panel-header"><h2 style="color:#10B981;">📈'📈 Revenue Overview</h2></div>
                    <table style="width:100%;border-collapse:collapse;font-size:0.9rem;">
                        <tr style="border-bottom:1px solid var(--gray-200);"><td style="padding:10px 0;color:var(--gray-600);">Total Billed (Sessions)</td><td style="padding:10px 0;text-align:right;font-weight:700;" id="fin-rev-billed">KES 0</td></tr>
                        <tr style="border-bottom:1px solid var(--gray-200);"><td style="padding:10px 0;color:var(--gray-600);">Collected in Period</td><td style="padding:10px 0;text-align:right;font-weight:700;color:#10B981;" id="fin-rev-collected">KES 0</td></tr>
                        <tr style="border-bottom:1px solid var(--gray-200);"><td style="padding:10px 0;color:var(--gray-600);">Outstanding Balance</td><td style="padding:10px 0;text-align:right;font-weight:700;color:#DC2626;" id="fin-rev-outstanding">KES 0</td></tr>
                        <tr style="border-bottom:1px solid var(--gray-200);"><td style="padding:10px 0;color:var(--gray-600);">Teacher Earnings</td><td style="padding:10px 0;text-align:right;font-weight:700;color:#6D28D9;" id="fin-rev-teacher">KES 0</td></tr>
                        <tr><td style="padding:10px 0;color:var(--gray-600);">Sessions Completed</td><td style="padding:10px 0;text-align:right;font-weight:700;" id="fin-rev-sessions">0</td></tr>
                    </table>
                </div>
                <div class="panel" style="border-top:4px solid #EF4444;">
                    <div class="panel-header"><h2 style="color:#EF4444;">📉'📉 Expenses Overview</h2></div>
                    <table style="width:100%;border-collapse:collapse;font-size:0.9rem;" id="fin-exp-overview-table"></table>
                </div>
            </div>

            <!-- Revenue Breakdown Tabs -->
            <div class="panel" style="margin-bottom:24px;">
                <div class="panel-header">
                    <h2>📊 Revenue Breakdown</h2>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button id="fin-brk-venue-btn"   class="btn btn-primary btn-sm" onclick="setFinBreakdown('venue')">By Lesson Type</button>
                        <button id="fin-brk-student-btn" class="btn btn-outline btn-sm" onclick="setFinBreakdown('student')">By Student</button>
                        <button id="fin-brk-teacher-btn" class="btn btn-outline btn-sm" onclick="setFinBreakdown('teacher')">By Teacher</button>
                    </div>
                </div>
                <div id="fin-brk-content"></div>
            </div>

            <!-- Session Log -->
            <div class="panel" style="margin-bottom:24px;">
                <div class="panel-header">
                    <h2>📋 Session Log</h2>
                    <span id="fin-sessions-badge" style="background:var(--cream);color:var(--primary);padding:4px 14px;border-radius:20px;font-size:0.82rem;font-weight:700;"></span>
                </div>
                <div class="table-wrap">
                    <table style="min-width:750px;">
                        <thead><tr>
                            <th>#</th><th>Date</th><th>Student</th><th>Teacher</th><th>Lesson Type</th>
                            <th style="text-align:right;">Billed (KES)</th><th style="text-align:right;">Teacher Earned</th>
                        </tr></thead>
                        <tbody id="fin-sessions-tbody"></tbody>
                        <tfoot id="fin-sessions-tfoot"></tfoot>
                    </table>
                </div>
            </div>

            <!-- Expenses Detail -->
            <div class="panel">
                <div class="panel-header">
                    <h2>📦 Expenses Detail</h2>
                    <span id="fin-exp-badge" style="background:#FEF2F2;color:#EF4444;padding:4px 14px;border-radius:20px;font-size:0.82rem;font-weight:700;"></span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr>
                            <th>#</th><th>Date</th><th>Category</th><th>Item</th><th>Description</th><th>Reference</th>
                            <th style="text-align:right;">Amount (KES)</th>
                        </tr></thead>
                        <tbody id="fin-exp-tbody"></tbody>
                        <tfoot id="fin-exp-tfoot"></tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div id="finrpt-empty-state" class="panel" style="text-align:center;padding:60px 20px;">
            <i class="fa-solid fa-chart-column" style="font-size:3.5rem;color:var(--gray-200);margin-bottom:18px;display:block;"></i>
            <h3 style="color:var(--gray-500);font-weight:600;">No Report Generated Yet</h3>
            <p style="color:var(--gray-500);margin-top:8px;font-size:0.9rem;">Select filters above and click <strong>Generate Report</strong> to view the full school financial summary.</p>
        </div>
    </div>

</main>

<!-- MODAL: Add / Edit Expense -->
<div class="modal-bg" id="expenseModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="expense-modal-title">Add Expense</h3>
            <p style="font-size:0.85rem;color:var(--gray-600);margin-top:4px;" id="expense-modal-subtitle">Fill in the details below.</p>
        </div>
        <form onsubmit="saveExpense(event)">
            <input type="hidden" id="exp-id">
            <input type="hidden" id="exp-category-hidden">
            <div class="form-grid" style="margin-bottom:18px;">
                <div class="form-group" style="grid-column:span 2;">
                    <label><i class="fa-solid fa-tag"></i> Item / Name <span style="color:red">*</span></label>
                    <input type="text" id="exp-item-name" class="form-control" placeholder="e.g. 10 Chairs, Electricity Bill, Paint Job, Tea & Snacks" required>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-calendar"></i> Expense Date <span style="color:red">*</span></label>
                    <input type="date" id="exp-date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-money-bill"></i> Amount (KES) <span style="color:red">*</span></label>
                    <input type="number" id="exp-amount" class="form-control" placeholder="e.g. 5000" min="0" step="0.01" required>
                </div>
                <div class="form-group" style="grid-column:span 2;">
                    <label><i class="fa-solid fa-align-left"></i> Description</label>
                    <textarea id="exp-description" class="form-control" rows="2" placeholder="Optional details about this expense…" style="resize:vertical;"></textarea>
                </div>
                <div class="form-group" style="grid-column:span 2;">
                    <label><i class="fa-solid fa-receipt"></i> Reference / Receipt No.</label>
                    <input type="text" id="exp-reference" class="form-control" placeholder="e.g. REC-2026-001, Invoice #123">
                </div>
            </div>
            <div style="display:flex;gap:12px;justify-content:flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeModal('expenseModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="exp-submit-btn"><i class="fa-solid fa-floppy-disk"></i> Save Expense</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Edit Pricing -->
<div class="modal-bg" id="pricingModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Edit Student Session Pricing (Parent Invoice Rates)</h3>
            <p style="font-size:0.85rem;color:var(--gray-600);margin-top:4px;" id="pricing-student-label">Set parent invoice rates for this student (used for parent billing).</p>
        </div>
        <form onsubmit="savePricing(event)">
            <input type="hidden" id="p-student-id">
            <div class="form-grid" style="margin-bottom:22px;">
                <div class="form-group">
                    <label><i class="fa-solid fa-video"></i> Google Meet Rate (KES)</label>
                    <input type="number" id="p-online-meet" class="form-control" placeholder="e.g. 1200" step="50" min="0" required>
                    <small style="color:var(--gray-600);font-size:0.78rem;">Parent fee for Google Meet lessons</small>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-circle-play"></i> Zoom Rate (KES)</label>
                    <input type="number" id="p-online-zoom" class="form-control" placeholder="e.g. 1300" step="50" min="0" required>
                    <small style="color:var(--gray-600);font-size:0.78rem;">Parent fee for Zoom online lessons</small>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-school"></i> One-on-One School Rate (KES)</label>
                    <input type="number" id="p-school" class="form-control" placeholder="e.g. 1500" step="50" min="0" required>
                    <small style="color:var(--gray-600);font-size:0.78rem;">Parent fee for Campus / school lessons</small>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-house"></i> One-on-One Home Rate (KES)</label>
                    <input type="number" id="p-home-visit" class="form-control" placeholder="e.g. 2000" step="50" min="0" required>
                    <small style="color:var(--gray-600);font-size:0.78rem;">Parent fee for Home-visit lessons</small>
                </div>
            </div>
            <div style="display:flex;gap:12px;justify-content:flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeModal('pricingModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Pricing</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Edit Teacher Pay Rates -->
<div class="modal-bg" id="teacherPricingModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Edit Tutor Pay Rates (Payroll)</h3>
            <p style="font-size:0.85rem;color:var(--gray-600);margin-top:4px;" id="pricing-teacher-label">Set tutor payout rates for this teacher (used for tutor payroll).</p>
        </div>
        <form onsubmit="saveTeacherPricing(event)">
            <input type="hidden" id="tp-teacher-id">
            <div class="form-grid" style="margin-bottom:22px;">
                <div class="form-group">
                    <label><i class="fa-solid fa-video"></i> Google Meet Pay (KES)</label>
                    <input type="number" id="tp-online-meet" class="form-control" placeholder="e.g. 1000" step="50" min="0" required>
                    <small style="color:var(--gray-600);font-size:0.78rem;">Payout for Google Meet lessons</small>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-circle-play"></i> Zoom Pay (KES)</label>
                    <input type="number" id="tp-online-zoom" class="form-control" placeholder="e.g. 1000" step="50" min="0" required>
                    <small style="color:var(--gray-600);font-size:0.78rem;">Payout for Zoom lessons</small>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-school"></i> School Pay (KES)</label>
                    <input type="number" id="tp-school" class="form-control" placeholder="e.g. 1200" step="50" min="0" required>
                    <small style="color:var(--gray-600);font-size:0.78rem;">Payout for school lessons</small>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-house"></i> Home Visit Pay (KES)</label>
                    <input type="number" id="tp-home-visit" class="form-control" placeholder="e.g. 1500" step="50" min="0" required>
                    <small style="color:var(--gray-600);font-size:0.78rem;">Payout for home visit lessons</small>
                </div>
            </div>
            <div style="display:flex;gap:12px;justify-content:flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeModal('teacherPricingModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Pay Rates</button>
            </div>
        </form>
    </div>
</div>

<script>
let allSessions = [];
let allMonthly = [];
let allPricing = [];
let currentInvoiceStudentId = null;
let currentInvoiceMonth = '';

// ---------------------------------------------
// NAVIGATION
// ---------------------------------------------
function switchTab(id, navEl) {
    localStorage.setItem('accounts_dashboard_active_tab', id);
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    
    const sec = document.getElementById('section-' + id);
    if (sec) sec.classList.add('active');
    
    // Manage active state of sub-menu items and auto-open category
    document.querySelectorAll('.submenu-item').forEach(item => {
        item.classList.remove('active');
        const onClickAttr = item.getAttribute('onclick') || '';
        if (onClickAttr.includes(`'${id}'`) || onClickAttr.includes(`"${id}"`)) {
            item.classList.add('active');
            
            // Expand the parent category
            const wrap = item.closest('.nav-category-wrap');
            if (wrap) {
                // Collapse other categories first
                document.querySelectorAll('.nav-category-wrap').forEach(w => w.classList.remove('active'));
                wrap.classList.add('active');
            }
        }
    });

    if (id === 'dashboard' || id === 'sessions' || id === 'monthly') loadSessions();
    if (id === 'pricing') loadPricing();
    if (id === 'invoices') loadInvoiceDropdowns();
    if (id === 'parent-invoice') initParentInvoice();
    if (id === 'payroll') loadPayrollTab();
    if (id === 'expenses') initExpensesTab();
    if (id === 'expreport') initReportTab();
    if (id === 'finreport') initFinReportTab();
    if (id === 'notifications') loadNotifications();

    // Close mobile drawer on selection
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const icon = document.getElementById('hamburgerIcon');
    if (sidebar && sidebar.classList.contains('active')) {
        sidebar.classList.remove('active');
        if (overlay) overlay.classList.remove('active');
        if (icon) icon.className = 'fa-solid fa-bars';
    }
}

function toggleCategoryMenu(headerEl) {
    const wrap = headerEl.parentElement;
    const isAlreadyActive = wrap.classList.contains('active');
    
    // Collapse other category menus first (accordion style)
    document.querySelectorAll('.nav-category-wrap').forEach(w => w.classList.remove('active'));
    
    if (!isAlreadyActive) {
        wrap.classList.add('active');
    }
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const icon = document.getElementById('hamburgerIcon');
    if (sidebar) {
        sidebar.classList.toggle('active');
        const isActive = sidebar.classList.contains('active');
        if (overlay) {
            if (isActive) overlay.classList.add('active');
            else overlay.classList.remove('active');
        }
        if (icon) {
            if (isActive) icon.className = 'fa-solid fa-xmark';
            else icon.className = 'fa-solid fa-bars';
        }
    }
}

// ---------------------------------------------
// ALERT
// ---------------------------------------------
function showAlert(type, msg) {
    const el = document.getElementById('globalAlert');
    el.className = `alert alert-${type}`;
    el.innerHTML = msg;
    el.style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
    setTimeout(() => el.style.display = 'none', 7000);
}

function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// ---------------------------------------------
// LOAD SESSIONS
// ---------------------------------------------
function loadSessions() {
    fetch('api/api_accounts.php?action=completed_sessions')
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') { showAlert('error', data.message || 'Failed to load sessions.'); return; }
            allSessions = data.sessions || [];
            allMonthly  = data.monthly_summary || [];

            // Populate month filter
            const monthSel = document.getElementById('filter-month');
            if (monthSel) {
                monthSel.innerHTML = '<option value="">All Months</option>';
                allMonthly.forEach(m => {
                    monthSel.innerHTML += `<option value="${m.month}">${m.month}</option>`;
                });
            }

            // Metrics
            let totalRevenue = 0, totalOnline = 0, totalOffline = 0;
            allSessions.forEach(s => {
                totalRevenue += parseFloat(s.price || 0);
                if (s.venue_type === 'school') totalOnline++;
                else totalOffline++;
            });
            const mTotal   = document.getElementById('m-total');
            const mRevenue = document.getElementById('m-revenue');
            const mOnline  = document.getElementById('m-online');
            const mOffline = document.getElementById('m-offline');
            if (mTotal)   mTotal.textContent   = allSessions.length;
            if (mRevenue) mRevenue.textContent  = 'KES ' + totalRevenue.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            if (mOnline)  mOnline.textContent   = totalOnline;
            if (mOffline) mOffline.textContent  = totalOffline;

            const badgeSessions = document.getElementById('badge-sessions-count');
            const badgeInvoices = document.getElementById('badge-invoices-count');
            if (badgeSessions) badgeSessions.textContent = allSessions.length;
            if (badgeInvoices) badgeInvoices.textContent = allMonthly.length;

            // Dashboard current month
            const now = new Date();
            const curMonthLabel = now.toLocaleString('default', { month: 'long' }) + ' ' + now.getFullYear();
            const curMonth = allMonthly.find(m => m.month === curMonthLabel);
            const breakdown = document.getElementById('dash-month-breakdown');
            if (breakdown) {
                if (curMonth) {
                    breakdown.innerHTML = `
                        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;">
                            <div style="background:var(--cream);border-radius:12px;padding:18px;text-align:center;">
                                <div style="font-size:1.8rem;font-weight:800;color:var(--primary);">${curMonth.total_sessions}</div>
                                <div style="font-size:0.82rem;color:var(--gray-600);font-weight:600;">Sessions This Month</div>
                            </div>
                            <div style="background:var(--cream);border-radius:12px;padding:18px;text-align:center;">
                                <div style="font-size:1.8rem;font-weight:800;color:#27AE60;">KES ${parseFloat(curMonth.total_revenue).toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}</div>
                                <div style="font-size:0.82rem;color:var(--gray-600);font-weight:600;">Revenue This Month</div>
                            </div>
                            <div style="background:var(--cream);border-radius:12px;padding:18px;text-align:center;">
                                <div style="font-size:1.8rem;font-weight:800;color:#2563EB;">${curMonth.online_count}</div>
                                <div style="font-size:0.82rem;color:var(--gray-600);font-weight:600;">Online (Campus)</div>
                            </div>
                            <div style="background:var(--cream);border-radius:12px;padding:18px;text-align:center;">
                                <div style="font-size:1.8rem;font-weight:800;color:#D97706;">${curMonth.offline_count}</div>
                                <div style="font-size:0.82rem;color:var(--gray-600);font-weight:600;">Offline (Home-Visit)</div>
                            </div>
                        </div>`;
                } else {
                    breakdown.innerHTML = `<p style="color:var(--gray-600);">No completed sessions recorded for ${curMonthLabel} yet.</p>`;
                }
            }

            // Monthly cards
            const mGrid = document.getElementById('monthly-cards');
            if (mGrid) {
                if (!allMonthly.length) {
                    mGrid.innerHTML = `<p style="color:var(--gray-600);">No monthly data available yet.</p>`;
                } else {
                    mGrid.innerHTML = allMonthly.map(m => `
                        <div class="month-card">
                            <h3><i class="fa-solid fa-calendar-days"></i> ${m.month}</h3>
                            <div class="month-stat"><span>Total Sessions</span><span>${m.total_sessions}</span></div>
                            <div class="month-stat"><span>Online (Meet)</span><span>${m.online_meet_count}</span></div>
                            <div class="month-stat"><span>Online (Zoom)</span><span>${m.online_zoom_count}</span></div>
                            <div class="month-stat"><span>School Sessions</span><span>${m.school_count}</span></div>
                            <div class="month-stat"><span>Home Sessions</span><span>${m.home_visit_count}</span></div>
                            <div class="month-total" style="display:flex;flex-direction:column;gap:4px;padding-top:10px;">
                                <div>
                                    <span style="font-size:0.8rem;color:rgba(255,255,255,0.75);">Parent Revenue: </span>
                                    <span class="revenue" style="font-size:1rem;font-weight:700;">KES ${parseFloat(m.total_revenue).toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}</span>
                                </div>
                                <div>
                                    <span style="font-size:0.8rem;color:rgba(255,255,255,0.75);">Tutor Payout: </span>
                                    <span style="font-size:1rem;font-weight:700;color:#6EE7B7;">KES ${parseFloat(m.total_teacher_payout || 0).toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}</span>
                                </div>
                            </div>
                        </div>
                    `).join('');
                }
            }

            filterSessions();
        })
        .catch(() => showAlert('error', 'Network error loading sessions.'));
}

// ---------------------------------------------
// FILTER SESSIONS TABLE
// ---------------------------------------------
function filterSessions() {
    const month  = document.getElementById('filter-month')?.value || '';
    const mode   = document.getElementById('filter-mode')?.value  || '';
    const search = (document.getElementById('filter-search')?.value || '').toLowerCase();

    const filtered = allSessions.filter(s => {
        const sessionMonth = new Date(s.lesson_date).toLocaleString('default', { month: 'long' }) + ' ' + new Date(s.lesson_date).getFullYear();
        if (month && sessionMonth !== month) return false;
        if (mode  && s.venue_type !== mode)  return false;
        if (search && !s.student_name.toLowerCase().includes(search) && !s.teacher_name.toLowerCase().includes(search)) return false;
        return true;
    });

    const tbody = document.getElementById('sessions-tbody');
    if (!tbody) return;

    let subtotalRevenue = 0;
    let subtotalPayout = 0;
    if (!filtered.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="empty-row">No sessions match the selected filters.</td></tr>`;
    } else {
        tbody.innerHTML = filtered.map(s => {
            const fee = parseFloat(s.price || 0);
            const pay = parseFloat(s.teacher_pay || 0);
            subtotalRevenue += fee;
            subtotalPayout  += pay;
            let modeLabel = s.venue_type;
            let modeBadge = 'badge-pending';
            if (s.venue_type === 'online_meet') { modeLabel = 'Online (Meet)'; modeBadge = 'badge-progress'; }
            else if (s.venue_type === 'online_zoom') { modeLabel = 'Online (Zoom)'; modeBadge = 'badge-progress'; }
            else if (s.venue_type === 'school') { modeLabel = 'School (One-on-One)'; modeBadge = 'badge-school'; }
            else if (s.venue_type === 'home_visit') { modeLabel = 'Home (One-on-One)'; modeBadge = 'badge-home'; }

            return `<tr>
                <td>${s.lesson_date}</td>
                <td><strong>${escHtml(s.student_name)}</strong></td>
                <td>${escHtml(s.teacher_name)}</td>
                <td><span class="badge ${modeBadge}">${modeLabel}</span></td>
                <td><strong>KES ${fee.toFixed(2)}</strong></td>
                <td><strong style="color:#047857;">KES ${pay.toFixed(2)}</strong></td>
            </tr>`;
        }).join('');
    }

    const countEl    = document.getElementById('sessions-count');
    const subtotalEl = document.getElementById('sessions-subtotal');
    const totalRow   = document.getElementById('sessions-total-row');
    if (countEl)    countEl.textContent  = filtered.length;
    if (subtotalEl) subtotalEl.innerHTML = `Parent Billed: KES ${subtotalRevenue.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')} &nbsp;|&nbsp; Tutor Pay: KES ${subtotalPayout.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`;
    if (totalRow)   totalRow.style.display = filtered.length ? 'flex' : 'none';
}

// ---------------------------------------------
// PRICING
// ---------------------------------------------
function loadPricing() {
    fetch('api/api_accounts.php?action=students_pricing')
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') { showAlert('error', data.message); return; }
            allPricing = data.students || [];
            const tbody = document.getElementById('pricing-tbody');
            if (!tbody) return;
            if (!allPricing.length) {
                tbody.innerHTML = `<tr><td colspan="6" class="empty-row">No enrolled students found. Enroll students through the Admin Dashboard.</td></tr>`;
                return;
            }
            tbody.innerHTML = allPricing.map(s => `
                <tr>
                    <td><strong>${s.student_name}</strong><br><small style="color:var(--gray-600);">${s.student_email}</small></td>
                    <td>${s.parent_name}</td>
                    <td>${s.grade_level}</td>
                    <td>
                        <span style="font-weight:700;color:#2563EB;">
                            ${parseFloat(s.price_online_meet) > 0 ? 'KES ' + parseFloat(s.price_online_meet).toFixed(0) : '<em style="color:var(--gray-500);">Not set</em>'}
                        </span>
                    </td>
                    <td>
                        <span style="font-weight:700;color:#10B981;">
                            ${parseFloat(s.price_online_zoom) > 0 ? 'KES ' + parseFloat(s.price_online_zoom).toFixed(0) : '<em style="color:var(--gray-500);">Not set</em>'}
                        </span>
                    </td>
                    <td>
                        <span style="font-weight:700;color:#6B7280;">
                            ${parseFloat(s.price_school) > 0 ? 'KES ' + parseFloat(s.price_school).toFixed(0) : '<em style="color:var(--gray-500);">Not set</em>'}
                        </span>
                    </td>
                    <td>
                        <span style="font-weight:700;color:#D97706;">
                            ${parseFloat(s.price_home_visit) > 0 ? 'KES ' + parseFloat(s.price_home_visit).toFixed(0) : '<em style="color:var(--gray-500);">Not set</em>'}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-primary btn-sm" onclick="openPricingModal(${s.id}, '${s.student_name.replace(/'/g,"\\'")}', ${s.price_online_meet || 0}, ${s.price_online_zoom || 0}, ${s.price_school || 0}, ${s.price_home_visit || 0})">
                            <i class="fa-solid fa-pen"></i> Edit Rates
                        </button>
                    </td>
                </tr>
            `).join('');
        });
}

function openPricingModal(id, name, meet, zoom, school, home) {
    document.getElementById('p-student-id').value = id;
    document.getElementById('p-online-meet').value = meet || '';
    document.getElementById('p-online-zoom').value = zoom || '';
    document.getElementById('p-school').value      = school || '';
    document.getElementById('p-home-visit').value  = home || '';
    document.getElementById('pricing-student-label').textContent = `Setting rates for: ${name}`;
    document.getElementById('pricingModal').classList.add('open');
}

function savePricing(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action', 'update_prices');
    fd.append('student_id',         document.getElementById('p-student-id').value);
    fd.append('price_online_meet',  document.getElementById('p-online-meet').value);
    fd.append('price_online_zoom',  document.getElementById('p-online-zoom').value);
    fd.append('price_school',       document.getElementById('p-school').value);
    fd.append('price_home_visit',   document.getElementById('p-home-visit').value);

    fetch('api/api_accounts.php', { method: 'POST', body: fd })
        .then(r => r.text())
        .then(text => {
            try {
                const data = JSON.parse(text);
                closeModal('pricingModal');
                showAlert(data.status, data.message);
                if (data.status === 'success') loadPricing();
            } catch (jsonErr) {
                closeModal('pricingModal');
                showAlert('error', 'Server error: ' + text);
            }
        })
        .catch(err => {
            closeModal('pricingModal');
            showAlert('error', 'Connection failed: ' + err.message);
        });
}

// ---------------------------------------------
// CSV EXPORT
// ---------------------------------------------
function exportCSV() {
    const month  = document.getElementById('filter-month')?.value || '';
    const mode   = document.getElementById('filter-mode')?.value  || '';
    const search = (document.getElementById('filter-search')?.value || '').toLowerCase();

    const filtered = allSessions.filter(s => {
        const sessionMonth = new Date(s.lesson_date).toLocaleString('default', { month: 'long' }) + ' ' + new Date(s.lesson_date).getFullYear();
        if (month && sessionMonth !== month) return false;
        if (mode  && s.venue_type !== mode)  return false;
        if (search && !s.student_name.toLowerCase().includes(search) && !s.teacher_name.toLowerCase().includes(search)) return false;
        return true;
    });

    if (!filtered.length) { showAlert('error', 'No sessions to export with current filters.'); return; }

    const rows = [['Date', 'Student', 'Teacher', 'Mode', 'Fee (KES)']];
    filtered.forEach(s => {
        let typeLabel = s.venue_type;
        if (s.venue_type === 'online_meet') typeLabel = 'Online (Meet)';
        else if (s.venue_type === 'online_zoom') typeLabel = 'Online (Zoom)';
        else if (s.venue_type === 'school') typeLabel = 'School (One-on-One)';
        else if (s.venue_type === 'home_visit') typeLabel = 'Home (One-on-One)';
        rows.push([s.lesson_date, s.student_name, s.teacher_name, typeLabel, parseFloat(s.price).toFixed(2)]);
    });

    const csv = rows.map(r => r.map(c => `"${c}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;
    a.download = `shta_sessions_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

// ---------------------------------------------
// STUDENT INVOICES & PAYMENTS LEDGER JS
// ---------------------------------------------
function loadInvoiceDropdowns() {
    // 1. Set payment date default to today
    const payDateInput = document.getElementById('pay-date');
    if (payDateInput) {
        payDateInput.value = new Date().toISOString().split('T')[0];
    }

    // 2. Fetch students
    fetch('api/api_accounts.php?action=students_list')
        .then(r => r.json())
        .then(d => {
            const sel = document.getElementById('inv-student-select');
            if (!sel) return;
            sel.innerHTML = '<option value="">-- Select Student --</option>';
            if (d.status === 'success') {
                (d.students || []).forEach(s => {
                    sel.innerHTML += `<option value="${s.id}">${s.student_name} (${s.grade_level})</option>`;
                });
            }
        });

    // 3. Populate Months dropdown
    const monthSel = document.getElementById('inv-month-select');
    if (monthSel) {
        monthSel.innerHTML = '<option value="">All Months</option>';
        if (allMonthly.length) {
            allMonthly.forEach(m => {
                monthSel.innerHTML += `<option value="${m.month}">${m.month}</option>`;
            });
        }
    }
}

function loadStudentInvoice() {
    const studentId = document.getElementById('inv-student-select').value;
    const month     = document.getElementById('inv-month-select').value;

    const emptyState = document.getElementById('invoice-empty-state');
    const container  = document.getElementById('invoice-details-container');
    const payPanel   = document.getElementById('payment-panel');

    if (!studentId) {
        emptyState.style.display = 'block';
        container.style.display = 'none';
        payPanel.style.display = 'none';
        return;
    }

    fetch(`api/api_accounts.php?action=get_invoice&student_id=${studentId}&month=${encodeURIComponent(month)}`)
        .then(r => r.json())
        .then(d => {
            if (d.status !== 'success') {
                showAlert('error', d.message || 'Failed to fetch invoice.');
                return;
            }

            emptyState.style.display = 'none';
            container.style.display = 'block';
            payPanel.style.display = 'block';

            // Track for email/print
            currentInvoiceStudentId = studentId;
            currentInvoiceMonth = month;

            // Populate Student metadata
            document.getElementById('inv-student-name').textContent = d.student.student_name;
            document.getElementById('inv-student-grade').textContent = d.student.grade_level || '?';
            document.getElementById('inv-parent-name').textContent  = d.student.parent_name || '?';
            document.getElementById('inv-parent-email').textContent = d.student.parent_email || '?';
            
            document.getElementById('invoice-date-label').textContent = `Month: ${month || 'All History'}`;

            // Populate Lessons
            const lessonsTbody = document.getElementById('inv-lessons-tbody');
            if (d.lessons && d.lessons.length) {
                lessonsTbody.innerHTML = d.lessons.map(l => {
                    let venueStr = l.venue_type;
                    if (l.venue_type === 'online_meet') venueStr = 'Online (Meet)';
                    else if (l.venue_type === 'online_zoom') venueStr = 'Online (Zoom)';
                    else if (l.venue_type === 'school') venueStr = 'School (1-on-1)';
                    else if (l.venue_type === 'home_visit') venueStr = 'Home Visit';

                    return `<tr>
                        <td>${l.lesson_date}</td>
                        <td>${l.teacher_name}</td>
                        <td><span style="font-size:0.82rem;font-weight:600;">${venueStr}</span></td>
                        <td><strong>KES ${parseFloat(l.price).toFixed(2)}</strong></td>
                    </tr>`;
                }).join('');
            } else {
                lessonsTbody.innerHTML = '<tr><td colspan="4" class="empty-row">No lessons taught in this period.</td></tr>';
            }

            // Populate calculations
            document.getElementById('inv-total-month').textContent = `KES ${parseFloat(d.total_billed_month).toLocaleString()}`;
            document.getElementById('inv-total-paid').textContent  = `KES ${parseFloat(d.total_paid).toLocaleString()}`;
            
            const balEl = document.getElementById('inv-balance');
            balEl.textContent = `KES ${parseFloat(d.balance).toLocaleString()}`;
            if (d.balance > 0) {
                balEl.style.color = '#DC2626';
            } else if (d.balance < 0) {
                balEl.textContent = `CR KES ${Math.abs(parseFloat(d.balance)).toLocaleString()}`;
                balEl.style.color = '#047857';
            } else {
                balEl.style.color = 'var(--primary)';
            }

            // Populate Payment ledger
            const paymentsTbody = document.getElementById('inv-payments-tbody');
            if (d.payments && d.payments.length) {
                paymentsTbody.innerHTML = d.payments.map(p => `
                    <tr>
                        <td>${p.payment_date}</td>
                        <td><span style="color:var(--gray-700);">${p.reference || '?'}</span></td>
                        <td><strong style="color:#047857;">KES ${parseFloat(p.amount).toFixed(2)}</strong></td>
                    </tr>
                `).join('');
            } else {
                paymentsTbody.innerHTML = '<tr><td colspan="3" class="empty-row">No payments recorded.</td></tr>';
            }
        });
}

function submitStudentPayment(e) {
    e.preventDefault();
    const studentId = document.getElementById('inv-student-select').value;
    const amount    = document.getElementById('pay-amount').value;
    const payDate   = document.getElementById('pay-date').value;
    const payRef    = document.getElementById('pay-ref').value;

    const fd = new FormData();
    fd.append('action', 'add_student_payment');
    fd.append('student_id', studentId);
    fd.append('amount', amount);
    fd.append('payment_date', payDate);
    fd.append('reference', payRef);

    fetch('api/api_accounts.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            showAlert(d.status, d.message);
            if (d.status === 'success') {
                document.getElementById('pay-amount').value = '';
                document.getElementById('pay-ref').value = '';
                loadStudentInvoice();
            }
        });
}

function printInvoice() {
    const printContents = document.getElementById("invoice-print-area").innerHTML;
    const win = window.open("", "_blank", "width=850,height=1100");
    win.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Fee Invoice ? Sanity Tuition</title>
            <style>
                body { font-family: "Segoe UI", sans-serif; padding: 40px; color: #1e293b; }
                table { width:100%; border-collapse:collapse; }
                th, td { padding:8px 10px; border:1px solid #e2e8f0; font-size:13px; }
                th { background:#f8fafc; font-weight:700; }
                h3 { color:#E8963D; margin-bottom:4px; }
                @media print { body { padding:20px; } button { display:none; } }
            </style>
        </head>
        <body>
            <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #E5A93B;padding-bottom:12px;margin-bottom:20px;">
                <img src="logo.png" style="height:60px;">
                <div style="text-align:right;">
                    <h2 style="margin:0;font-size:1.3rem;color:#4A0E17;">SANITY HOMEBASED TUITION ACADEMY</h2>
                    <p style="margin:4px 0;color:#6C757D;font-size:12px;">Official Fee Invoice</p>
                </div>
            </div>
            ${printContents}
            <br>
            <button onclick="window.print()" style="margin-top:20px;padding:10px 24px;background:#E8963D;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;">??? Print / Save as PDF</button>
        </body>
        </html>
    `);
    win.document.close();
}

function emailInvoice() {
    if (!currentInvoiceStudentId) {
        showAlert('error', 'Please load an invoice first by selecting a student.');
        return;
    }
    const btn = document.getElementById('btn-email-invoice');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';

    const fd = new FormData();
    fd.append('action', 'email_invoice');
    fd.append('student_id', currentInvoiceStudentId);
    fd.append('month', currentInvoiceMonth);

    fetch('api/api_accounts.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            btn.innerHTML = origHtml;
            showAlert(d.status, d.message || (d.status === 'success' ? '? Invoice emailed successfully!' : '? Failed to send email.'));
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = origHtml;
            showAlert('error', '? Network error. Please try again.');
        });
}

// ---------------------------------------------
// TUTOR PAYROLL & RATES DIRECTORY JS
// ---------------------------------------------
function setPayrollSubTab(tab) {
    document.getElementById('pay-subtab-rates').style.display  = tab === 'rates' ? 'block' : 'none';
    document.getElementById('pay-subtab-ledger').style.display = tab === 'ledger' ? 'grid' : 'none';

    const btnRates  = document.getElementById('pay-btn-rates');
    const btnLedger = document.getElementById('pay-btn-ledger');

    if (tab === 'rates') {
        btnRates.className = 'btn btn-primary';
        btnLedger.className = 'btn btn-outline';
    } else {
        btnRates.className = 'btn btn-outline';
        btnLedger.className = 'btn btn-primary';
    }
}

function loadPayrollTab() {
    // Populate rates list
    fetch('api/api_accounts.php?action=teachers_pricing')
        .then(r => r.json())
        .then(d => {
            const tbody = document.getElementById('payroll-rates-tbody');
            if (!tbody) return;
            if (d.status !== 'success') {
                tbody.innerHTML = `<tr><td colspan="7" class="empty-row">${d.message}</td></tr>`;
                return;
            }

            tbody.innerHTML = (d.teachers || []).map(t => `
                <tr>
                    <td><strong>${t.name}</strong></td>
                    <td>${t.email}</td>
                    <td><span style="font-weight:700;color:#2563EB;">KES ${parseFloat(t.pay_online_meet).toFixed(0)}</span></td>
                    <td><span style="font-weight:700;color:#10B981;">KES ${parseFloat(t.pay_online_zoom).toFixed(0)}</span></td>
                    <td><span style="font-weight:700;color:#6B7280;">KES ${parseFloat(t.pay_school).toFixed(0)}</span></td>
                    <td><span style="font-weight:700;color:#D97706;">KES ${parseFloat(t.pay_home_visit).toFixed(0)}</span></td>
                    <td>
                        <button class="btn btn-primary btn-sm" onclick="openTeacherPricingModal(${t.id}, '${t.name.replace(/'/g,"\\'")}', ${t.pay_online_meet || 0}, ${t.pay_online_zoom || 0}, ${t.pay_school || 0}, ${t.pay_home_visit || 0})">
                            <i class="fa-solid fa-pen"></i> Edit Rates
                        </button>
                    </td>
                </tr>
            `).join('');
        });

    // Populate tutors dropdown for payroll ledger
    fetch('api/api_accounts.php?action=teachers_list')
        .then(r => r.json())
        .then(d => {
            const sel = document.getElementById('pay-teacher-select');
            if (!sel) return;
            sel.innerHTML = '<option value="">-- Select Tutor --</option>';
            if (d.status === 'success') {
                (d.teachers || []).forEach(t => {
                    sel.innerHTML += `<option value="${t.id}">${t.name}</option>`;
                });
            }
        });

    // Populate Months dropdown
    const monthSel = document.getElementById('pay-month-select');
    if (monthSel) {
        monthSel.innerHTML = '<option value="">All Months</option>';
        if (allMonthly.length) {
            allMonthly.forEach(m => {
                monthSel.innerHTML += `<option value="${m.month}">${m.month}</option>`;
            });
        }
    }

    // Default disbursement date input
    const disbDateInput = document.getElementById('disb-date');
    if (disbDateInput) {
        disbDateInput.value = new Date().toISOString().split('T')[0];
    }
}

function openTeacherPricingModal(id, name, meet, zoom, school, home) {
    document.getElementById('tp-teacher-id').value = id;
    document.getElementById('tp-online-meet').value = meet || '';
    document.getElementById('tp-online-zoom').value = zoom || '';
    document.getElementById('tp-school').value      = school || '';
    document.getElementById('tp-home-visit').value  = home || '';
    document.getElementById('pricing-teacher-label').textContent = `Setting payout rates for: ${name}`;
    document.getElementById('teacherPricingModal').classList.add('open');
}

function saveTeacherPricing(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action', 'update_teacher_prices');
    fd.append('teacher_id',      document.getElementById('tp-teacher-id').value);
    fd.append('pay_online_meet', document.getElementById('tp-online-meet').value);
    fd.append('pay_online_zoom', document.getElementById('tp-online-zoom').value);
    fd.append('pay_school',      document.getElementById('tp-school').value);
    fd.append('pay_home_visit',  document.getElementById('tp-home-visit').value);

    fetch('api/api_accounts.php', { method: 'POST', body: fd })
        .then(r => r.text())
        .then(text => {
            try {
                const data = JSON.parse(text);
                closeModal('teacherPricingModal');
                showAlert(data.status, data.message);
                if (data.status === 'success') loadPayrollTab();
            } catch (jsonErr) {
                closeModal('teacherPricingModal');
                showAlert('error', 'Server error: ' + text);
            }
        })
        .catch(err => {
            closeModal('teacherPricingModal');
            showAlert('error', 'Connection failed: ' + err.message);
        });
}

function loadTeacherPayroll() {
    const teacherId = document.getElementById('pay-teacher-select').value;
    const month     = document.getElementById('pay-month-select').value;

    const emptyState = document.getElementById('payroll-empty-state');
    const container  = document.getElementById('payroll-details-container');
    const disbPanel  = document.getElementById('disburse-panel');

    if (!teacherId) {
        emptyState.style.display = 'block';
        container.style.display = 'none';
        disbPanel.style.display = 'none';
        return;
    }

    fetch(`api/api_accounts.php?action=get_payroll&teacher_id=${teacherId}&month=${encodeURIComponent(month)}`)
        .then(r => r.json())
        .then(d => {
            if (d.status !== 'success') {
                showAlert('error', d.message || 'Failed to fetch payroll details.');
                return;
            }

            emptyState.style.display = 'none';
            container.style.display = 'block';
            disbPanel.style.display = 'block';

            // Populate Metadata
            document.getElementById('payroll-teacher-name').textContent = d.teacher.name;
            document.getElementById('payroll-date-label').textContent = `Month: ${month || 'All History'}`;

            // Populate Lessons Taught
            const lessonsTbody = document.getElementById('payroll-lessons-tbody');
            if (d.lessons && d.lessons.length) {
                lessonsTbody.innerHTML = d.lessons.map(l => {
                    let venueStr = l.venue_type;
                    if (l.venue_type === 'online_meet') venueStr = 'Online (Meet)';
                    else if (l.venue_type === 'online_zoom') venueStr = 'Online (Zoom)';
                    else if (l.venue_type === 'school') venueStr = 'School (1-on-1)';
                    else if (l.venue_type === 'home_visit') venueStr = 'Home Visit';

                    return `<tr>
                        <td>${l.lesson_date}</td>
                        <td><strong>${l.student_name}</strong></td>
                        <td><span style="font-size:0.82rem;font-weight:600;">${venueStr}</span></td>
                        <td><strong>KES ${parseFloat(l.earnings).toFixed(2)}</strong></td>
                    </tr>`;
                }).join('');
            } else {
                lessonsTbody.innerHTML = '<tr><td colspan="4" class="empty-row">No lessons taught in this period.</td></tr>';
            }

            // Populate calculations
            document.getElementById('payroll-total-month').textContent = `KES ${parseFloat(d.total_earned_month).toLocaleString()}`;
            document.getElementById('payroll-total-paid').textContent  = `KES ${parseFloat(d.total_disbursed).toLocaleString()}`;
            
            const balEl = document.getElementById('payroll-balance');
            balEl.textContent = `KES ${parseFloat(d.balance).toLocaleString()}`;
            if (d.balance > 0) {
                balEl.style.color = '#DC2626';
            } else if (d.balance < 0) {
                balEl.textContent = `CR KES ${Math.abs(parseFloat(d.balance)).toLocaleString()}`;
                balEl.style.color = '#047857';
            } else {
                balEl.style.color = 'var(--primary)';
            }

            // Populate Disbursement history
            const disbTbody = document.getElementById('payroll-payments-tbody');
            if (d.disbursements && d.disbursements.length) {
                disbTbody.innerHTML = d.disbursements.map(p => `
                    <tr>
                        <td>${p.payment_date}</td>
                        <td><span style="color:var(--gray-700);">${p.reference || '?'}</span></td>
                        <td><strong style="color:#047857;">KES ${parseFloat(p.amount).toFixed(2)}</strong></td>
                    </tr>
                `).join('');
            } else {
                disbTbody.innerHTML = '<tr><td colspan="3" class="empty-row">No payroll disbursements recorded.</td></tr>';
            }
        });
}

function submitTeacherDisbursement(e) {
    e.preventDefault();
    const teacherId = document.getElementById('pay-teacher-select').value;
    const amount    = document.getElementById('disb-amount').value;
    const payDate   = document.getElementById('disb-date').value;
    const payRef    = document.getElementById('disb-ref').value;

    const fd = new FormData();
    fd.append('action', 'add_teacher_payment');
    fd.append('teacher_id', teacherId);
    fd.append('amount', amount);
    fd.append('payment_date', payDate);
    fd.append('reference', payRef);

    fetch('api/api_accounts.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            showAlert(d.status, d.message);
            if (d.status === 'success') {
                document.getElementById('disb-amount').value = '';
                document.getElementById('disb-ref').value = '';
                loadTeacherPayroll();
            }
        });
}

function printPayroll() {
    const printContents = document.getElementById("payroll-print-area").innerHTML;
    const win = window.open("", "_blank", "width=900,height=1100");
    win.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Payroll & Disbursement Statement</title>
            <style>
                body { font-family: "Segoe UI", sans-serif; padding: 40px; color: #1e293b; }
                table { width:100%; border-collapse:collapse; }
                th, td { padding:8px 10px; border:1px solid #e2e8f0; font-size:13px; }
                th { background:#f8fafc; font-weight:700; }
                h3 { color:#4A0E17; margin-bottom:4px; }
                @media print { body { padding:20px; } button { display:none; } }
            </style>
        </head>
        <body>
            <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #E5A93B;padding-bottom:12px;margin-bottom:20px;">
                <img src="logo.png" style="height:60px;">
                <div style="text-align:right;">
                    <h2 style="margin:0;font-size:1.3rem;color:#4A0E17;">SANITY HOMEBASED TUITION ACADEMY</h2>
                    <p style="margin:4px 0;color:#6C757D;font-size:12px;">Tutor Payroll & Disbursement Statement</p>
                </div>
            </div>
            ${printContents}
            <br>
            <button onclick="window.print()" style="margin-top:20px;padding:10px 24px;background:#4A0E17;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;">??? Print / Save as PDF</button>
        </body>
        </html>
    `);
    win.document.close();
}


// =========================================================================
// RESTORED CUSTOM MODULES (PARENT INVOICE, EXPENSES, REPORTS, FINANCIALS)
// =========================================================================

// PARENT INVOICE GENERATOR MODULE
// =========================================================================
let piData = null;      // current invoice data from API
let piInvoiceRows = []; // editable rows array {date, tutor, type, amount}
let piStudentInfo = {}; // {parent_name, parent_email, student_name, grade_level}
let piPaidTotal = 0;    // previously paid amount for this student
let piInvoiceCounter = 1;

function initParentInvoice() {
    // Set today's date defaults
    const today = new Date().toISOString().split('T')[0];
    const firstOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
    const piFrom = document.getElementById('pi-from');
    const piTo   = document.getElementById('pi-to');
    const piDue  = document.getElementById('pi-due');
    if (piFrom && !piFrom.value) piFrom.value = firstOfMonth;
    if (piTo   && !piTo.value)   piTo.value   = today;
    if (piDue  && !piDue.value) {
        const due = new Date(); due.setDate(due.getDate() + 7);
        piDue.value = due.toISOString().split('T')[0];
    }

    // Set invoice date & number
    document.getElementById('pi-inv-date').textContent = new Date().toLocaleDateString('en-GB', { day:'numeric', month:'long', year:'numeric' });
    document.getElementById('pi-inv-number').textContent = 'INV-' + String(piInvoiceCounter).padStart(3,'0');

    // Sync bank fields to preview
    piSyncBank();

    // Load students dropdown
    fetch('api/api_accounts.php?action=students_list')
        .then(r => r.json())
        .then(d => {
            const sel = document.getElementById('pi-student');
            if (!sel) return;
            sel.innerHTML = '<option value="">-- Select Student --</option>';
            (d.students || []).forEach(s => {
                sel.innerHTML += `<option value="${s.id}">${s.student_name} (${s.grade_level})</option>`;
            });
        });
}

function piLoadSessions() {
    const studentId = document.getElementById('pi-student').value;
    const fromDate  = document.getElementById('pi-from').value;
    const toDate    = document.getElementById('pi-to').value;
    if (!studentId) return;

    // Show loading state
    document.getElementById('pi-empty-state').style.display = 'none';
    document.getElementById('pi-preview-area').style.display = 'none';
    document.getElementById('pi-sessions-tbody').innerHTML = '<tr><td colspan="6" class="empty-row"><i class="fa-solid fa-spinner fa-spin"></i> Loading sessions…</td></tr>';

    const url = `api/api_accounts.php?action=get_invoice&student_id=${studentId}&month=`;
    fetch(url)
        .then(r => r.json())
        .then(d => {
            if (d.status !== 'success') { showAlert('error', d.message); return; }
            piData = d;

            // Store student info
            piStudentInfo = {
                parent_name:   d.student.parent_name  || '—',
                parent_email:  d.student.parent_email || '—',
                student_name:  d.student.student_name || '—',
                grade_level:   d.student.grade_level  || '—'
            };

            // Previously paid
            piPaidTotal = parseFloat(d.total_paid) || 0;

            // Filter lessons by date range
            let lessons = d.lessons || [];
            if (fromDate) lessons = lessons.filter(l => l.lesson_date >= fromDate);
            if (toDate)   lessons = lessons.filter(l => l.lesson_date <= toDate);

            // Build editable rows
            piInvoiceRows = lessons.map(l => ({
                date:   l.lesson_date,
                tutor:  l.teacher_name,
                type:   piVenueLabel(l.venue_type),
                amount: parseFloat(l.price) || 0
            }));

            piRenderPreview();
        })
        .catch(() => showAlert('error', 'Network error loading invoice data.'));
}

function piVenueLabel(v) {
    if (v === 'online_meet')  return 'Online (Google Meet)';
    if (v === 'online_zoom')  return 'Online (Zoom)';
    if (v === 'school')       return 'School (1-on-1)';
    if (v === 'home_visit')   return 'Home Visit';
    return v;
}

function piRenderPreview() {
    // Show preview area
    document.getElementById('pi-empty-state').style.display   = 'none';
    document.getElementById('pi-preview-area').style.display  = 'block';

    // Student / parent meta
    document.getElementById('pi-parent-name-preview').textContent  = piStudentInfo.parent_name;
    document.getElementById('pi-parent-email-preview').textContent = piStudentInfo.parent_email;
    document.getElementById('pi-student-name-preview').textContent = piStudentInfo.student_name;
    document.getElementById('pi-student-grade-preview').textContent = 'Grade: ' + piStudentInfo.grade_level;

    const from = document.getElementById('pi-from').value;
    const to   = document.getElementById('pi-to').value;
    document.getElementById('pi-period-preview').textContent = from && to ? `Period: ${from} – ${to}` : '';

    // Note
    const note = document.getElementById('pi-note').value.trim();
    const noteEl = document.getElementById('pi-note-preview');
    if (note) { noteEl.textContent = note; noteEl.style.display = 'block'; }
    else       { noteEl.style.display = 'none'; }

    // Sessions table (editable)
    const tbody = document.getElementById('pi-sessions-tbody');
    if (!piInvoiceRows.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="empty-row">No sessions found for this date range.</td></tr>';
    } else {
        tbody.innerHTML = piInvoiceRows.map((row, i) => `
            <tr id="pi-row-${i}">
                <td style="color:var(--gray-600);font-weight:700;">${i+1}</td>
                <td><input type="date" value="${row.date}" class="form-control" style="padding:6px 8px;font-size:0.85rem;" onchange="piUpdateRow(${i},'date',this.value)"></td>
                <td><input type="text" value="${row.tutor}" class="form-control" style="padding:6px 8px;font-size:0.85rem;" onchange="piUpdateRow(${i},'tutor',this.value)"></td>
                <td><input type="text" value="${row.type}" class="form-control" style="padding:6px 8px;font-size:0.85rem;" onchange="piUpdateRow(${i},'type',this.value)"></td>
                <td><input type="number" value="${row.amount.toFixed(2)}" class="form-control" style="padding:6px 8px;font-size:0.85rem;width:110px;" step="0.01" onchange="piUpdateRow(${i},'amount',parseFloat(this.value)||0)"></td>
                <td><button class="btn btn-sm" style="background:#FEE2E2;color:#DC2626;border:none;" onclick="piRemoveRow(${i})"><i class="fa-solid fa-trash"></i></button></td>
            </tr>`).join('');
    }

    piUpdateTotals();
    piSyncBank();
}

function piUpdateRow(i, field, val) {
    if (piInvoiceRows[i]) {
        piInvoiceRows[i][field] = val;
        piUpdateTotals();
    }
}

function piRemoveRow(i) {
    piInvoiceRows.splice(i, 1);
    piRenderPreview();
}

function piAddRow() {
    const today = new Date().toISOString().split('T')[0];
    piInvoiceRows.push({ date: today, tutor: '', type: 'School (1-on-1)', amount: 0 });
    piRenderPreview();
    // scroll to bottom of table
    const tbody = document.getElementById('pi-sessions-tbody');
    tbody.lastElementChild?.scrollIntoView({ behavior:'smooth', block:'nearest' });
}

function piUpdateTotals() {
    const subtotal = piInvoiceRows.reduce((sum, r) => sum + (parseFloat(r.amount)||0), 0);
    const due = Math.max(0, subtotal - piPaidTotal);
    document.getElementById('pi-subtotal-preview').textContent = 'KES ' + subtotal.toLocaleString('en-KE', {minimumFractionDigits:2, maximumFractionDigits:2});
    document.getElementById('pi-paid-preview').textContent    = 'KES ' + piPaidTotal.toLocaleString('en-KE', {minimumFractionDigits:2, maximumFractionDigits:2});
    document.getElementById('pi-due-preview').textContent     = 'KES ' + due.toLocaleString('en-KE', {minimumFractionDigits:2, maximumFractionDigits:2});
}

function piSyncNote() {
    const note = document.getElementById('pi-note').value.trim();
    const noteEl = document.getElementById('pi-note-preview');
    if (note) { noteEl.textContent = note; noteEl.style.display = 'block'; }
    else       { noteEl.style.display = 'none'; }
}

function piSyncBank() {
    const map = {
        'pi-bank':    'pi-bank-name-p',
        'pi-accname': 'pi-accname-p',
        'pi-accnum':  'pi-accnum-p',
        'pi-mpesa':   'pi-mpesa-p',
    };
    for (const [srcId, dstId] of Object.entries(map)) {
        const src = document.getElementById(srcId);
        const dst = document.getElementById(dstId);
        if (src && dst) dst.textContent = src.value;
    }
    // due date
    const dueEl = document.getElementById('pi-due');
    const duePEl = document.getElementById('pi-due-date-p');
    if (dueEl && duePEl) {
        duePEl.textContent = dueEl.value ? new Date(dueEl.value + 'T00:00:00').toLocaleDateString('en-GB', {day:'numeric',month:'long',year:'numeric'}) : '—';
    }
}

function piSyncFooter() {
    const txt = document.getElementById('pi-footer-note').value.trim();
    const el  = document.getElementById('pi-footer-note-preview');
    if (txt) { el.textContent = txt; el.style.display = 'block'; }
    else       { el.style.display = 'none'; }
}

function piOpenPreview() {
    const studentId = document.getElementById('pi-student').value;
    if (!studentId) { showAlert('error', 'Please select a student first.'); return; }
    if (!piInvoiceRows.length) { showAlert('error', 'No sessions loaded. Please load sessions first.'); return; }

    // Build print HTML
    const subtotal = piInvoiceRows.reduce((sum, r) => sum + (parseFloat(r.amount)||0), 0);
    const due      = Math.max(0, subtotal - piPaidTotal);
    const invNo    = document.getElementById('pi-inv-number').textContent;
    const invDate  = document.getElementById('pi-inv-date').textContent;
    const note     = document.getElementById('pi-note').value.trim();
    const footer   = document.getElementById('pi-footer-note').value.trim();
    const bank     = document.getElementById('pi-bank').value;
    const accName  = document.getElementById('pi-accname').value;
    const accNum   = document.getElementById('pi-accnum').value;
    const mpesa    = document.getElementById('pi-mpesa').value;
    const dueDate  = document.getElementById('pi-due-date-p').textContent;
    const from     = document.getElementById('pi-from').value;
    const to       = document.getElementById('pi-to').value;

    const rowsHtml = piInvoiceRows.map((r, i) => `
        <tr>
            <td>${i+1}</td>
            <td>${r.date}</td>
            <td>${r.tutor}</td>
            <td>${r.type}</td>
            <td style="text-align:right;"><strong>KES ${parseFloat(r.amount).toLocaleString('en-KE',{minimumFractionDigits:2})}</strong></td>
        </tr>`).join('');

    const html = `
    <!DOCTYPE html><html><head>
        <title>Fee Invoice – ${piStudentInfo.parent_name} – ${invNo}</title>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&display=swap" rel="stylesheet">
        <style>
            * { box-sizing:border-box; margin:0; padding:0; }
            body { font-family:'Outfit',sans-serif; padding:40px; color:#1e293b; background:#fff; font-size:13px; }
            .hdr { display:flex; justify-content:space-between; align-items:center; border-bottom:3px solid #E5A93B; padding-bottom:16px; margin-bottom:22px; }
            .hdr img { height:65px; }
            .hdr-school h1 { font-size:18px; font-weight:900; color:#4A0E17; }
            .hdr-school p  { font-size:11px; color:#6C757D; margin-top:3px; }
            .inv-badge { background:linear-gradient(135deg,#4A0E17,#30080E); color:#fff; padding:8px 18px; border-radius:8px; font-weight:800; font-size:14px; letter-spacing:1px; text-align:center; }
            .inv-meta  { font-size:11px; color:#6C757D; margin-top:6px; text-align:right; }
            .meta-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:18px; }
            .meta-box  { background:#FAF7F2; border-radius:8px; padding:14px 16px; }
            .meta-box .label { font-size:10px; font-weight:800; text-transform:uppercase; color:#6C757D; letter-spacing:0.6px; margin-bottom:6px; }
            .meta-box .name  { font-size:15px; font-weight:800; color:#1e293b; }
            .meta-box .sub   { font-size:11px; color:#6C757D; margin-top:3px; }
            .note-bar { background:rgba(229,169,59,0.12); border:1px solid rgba(229,169,59,0.4); border-radius:6px; padding:8px 14px; margin-bottom:16px; font-weight:700; color:#4A0E17; }
            table { width:100%; border-collapse:collapse; margin-bottom:16px; }
            thead th { background:#4A0E17; color:#fff; padding:9px 11px; text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; }
            tbody td { padding:9px 11px; border-bottom:1px solid #e2e8f0; }
            tbody tr:nth-child(even) td { background:#FAF7F2; }
            tfoot td { padding:9px 11px; font-weight:700; }
            .totals-wrap { display:flex; justify-content:flex-end; margin-bottom:18px; }
            .totals-box  { min-width:270px; }
            .tot-row     { display:flex; justify-content:space-between; padding:7px 0; border-bottom:1px solid #e2e8f0; font-size:12px; }
            .tot-row .lbl { color:#6C757D; }
            .tot-due  { display:flex; justify-content:space-between; padding:11px 14px; background:linear-gradient(135deg,#4A0E17,#30080E); border-radius:7px; margin-top:6px; }
            .tot-due .lbl { color:rgba(255,255,255,0.85); font-weight:700; }
            .tot-due .val { color:#E5A93B; font-weight:900; font-size:15px; }
            .bank-box { background:#FAF7F2; border-radius:10px; padding:14px 18px; margin-bottom:16px; border:1px solid rgba(74,14,23,0.1); }
            .bank-box .blabel { font-size:10px; font-weight:800; text-transform:uppercase; color:#6C757D; letter-spacing:0.8px; margin-bottom:10px; }
            .bank-grid { display:grid; grid-template-columns:1fr 1fr; gap:6px; font-size:12px; }
            .bank-grid .k { color:#6C757D; }
            .footer-note { font-size:11px; color:#6C757D; font-style:italic; padding:10px 0; border-top:1px dashed #e2e8f0; margin-bottom:16px; }
            .sig-row { display:flex; justify-content:space-between; padding-top:14px; border-top:1px solid #e2e8f0; font-size:11px; color:#6C757D; margin-top:10px; }
            .sig-line { border-top:1px solid #4A0E17; width:150px; text-align:center; padding-top:4px; margin-top:28px; }
            @media print { button { display:none!important; } body { padding:20px; } }
        </style>
    </head><body>
        <div class="hdr">
            <div style="display:flex;align-items:center;gap:14px;">
                <img src="../logo.png" alt="S.H.T.A">
                <div class="hdr-school">
                    <h1>SANITY HOMEBASED TUITION ACADEMY</h1>
                    <p>Email: accounts@sanityeducation.com &nbsp;|&nbsp; Tel: +254 716 942 939 / +254 731 091 000</p>
                    <p>P.O. Box 12345, Nairobi, Kenya</p>
                </div>
            </div>
            <div style="text-align:right;">
                <div class="inv-badge">FEE INVOICE</div>
                <div class="inv-meta">Invoice No: <strong>${invNo}</strong></div>
                <div class="inv-meta">Date: <strong>${invDate}</strong></div>
            </div>
        </div>

        <div class="meta-grid">
            <div class="meta-box" style="border-left:4px solid #4A0E17;">
                <div class="label">Billed To</div>
                <div class="name">${piStudentInfo.parent_name}</div>
                <div class="sub">${piStudentInfo.parent_email}</div>
            </div>
            <div class="meta-box" style="border-left:4px solid #E5A93B;">
                <div class="label">Student</div>
                <div class="name">${piStudentInfo.student_name}</div>
                <div class="sub">Grade: ${piStudentInfo.grade_level} &nbsp;|&nbsp; Period: ${from} – ${to}</div>
            </div>
        </div>

        ${note ? `<div class="note-bar">${note}</div>` : ''}

        <table>
            <thead><tr>
                <th>#</th><th>Date</th><th>Tutor</th><th>Session Type</th><th style="text-align:right;">Amount (KES)</th>
            </tr></thead>
            <tbody>${rowsHtml}</tbody>
            <tfoot><tr>
                <td colspan="4" style="text-align:right;color:#6C757D;">Subtotal</td>
                <td style="text-align:right;">KES ${subtotal.toLocaleString('en-KE',{minimumFractionDigits:2})}</td>
            </tr></tfoot>
        </table>

        <div class="totals-wrap">
            <div class="totals-box">
                <div class="tot-row"><span class="lbl">Subtotal</span><span>KES ${subtotal.toLocaleString('en-KE',{minimumFractionDigits:2})}</span></div>
                <div class="tot-row"><span class="lbl">Previously Paid</span><span style="color:#047857;">KES ${piPaidTotal.toLocaleString('en-KE',{minimumFractionDigits:2})}</span></div>
                <div class="tot-due"><span class="lbl">AMOUNT DUE</span><span class="val">KES ${due.toLocaleString('en-KE',{minimumFractionDigits:2})}</span></div>
            </div>
        </div>

        <div class="bank-box">
            <div class="blabel"><span style="margin-right:6px;">🏦</span> Payment Instructions</div>
            <div class="bank-grid">
                <div><span class="k">Bank:</span> <strong>${bank}</strong></div>
                <div><span class="k">Account Name:</span> <strong>${accName}</strong></div>
                <div><span class="k">Account No:</span> <strong>${accNum}</strong></div>
                <div><span class="k">M-Pesa:</span> <strong>${mpesa}</strong></div>
                <div style="grid-column:span 2;"><span class="k">Due Date:</span> <strong style="color:#DC2626;">${dueDate}</strong></div>
            </div>
        </div>

        ${footer ? `<div class="footer-note">${footer}</div>` : ''}

        <div class="sig-row">
            <div>Prepared by: <strong>Accounts Department</strong><div class="sig-line">Authorised Signature</div></div>
            <div style="text-align:right;"><strong style="color:#4A0E17;font-size:13px;">Sanity Homebased Tuition Academy</strong><br><span>Thank you for choosing us!</span><div class="sig-line">Parent Acknowledgement</div></div>
        </div>

        <div style="text-align:center;margin-top:24px;">
            <button onclick="window.print()" style="padding:11px 28px;background:#4A0E17;color:#fff;border:none;border-radius:8px;cursor:pointer;font-family:Outfit,sans-serif;font-weight:700;font-size:14px;">
                🖨️?🖨️️ Print / Save as PDF
            </button>
        </div>
    </body></html>`;

    // Open print window
    const win = window.open('', '_blank', 'width=1000,height=1100');
    win.document.write(html);
    win.document.close();

    // Increment invoice counter for next invoice
    piInvoiceCounter++;
    document.getElementById('pi-inv-number').textContent = 'INV-' + String(piInvoiceCounter).padStart(3,'0');
}

// =========================================================================

// =============================================
// EXTRA EXPENSES MODULE
// =============================================
let currentExpenseCategory = 'inventory';
const expCategoryMeta = {
    inventory: {
        label: 'Inventory Management',
        hint: '📦 Track furniture and equipment purchases — desks, chairs, computers, and other physical assets.',
        color: '#3B82F6',
        icon: 'fa-boxes-stacked'
    },
    utility: {
        label: 'Utility Management',
        hint: '⚡ Track recurring utility expenses — electricity, water, books, computer repairs, and internet.',
        color: '#10B981',
        icon: 'fa-bolt'
    },
    general_repairs: {
        label: 'General Repairs',
        hint: '🔧 Track maintenance and repair work — painting, plumbing, structural fixes, and building upkeep.',
        color: '#F59E0B',
        icon: 'fa-screwdriver-wrench'
    },
    petty_cash: {
        label: 'Petty Cash',
        hint: '☕? Track small daily expenses — tea, lunch, snacks, printing, and minor miscellaneous costs.',
        color: '#8B5CF6',
        icon: 'fa-mug-hot'
    }
};

function initExpensesTab() {
    // Populate month filter from allMonthly (already loaded)
    const monthSel = document.getElementById('exp-filter-month');
    if (monthSel) {
        monthSel.innerHTML = '<option value="">All Months</option>';
        allMonthly.forEach(m => {
            monthSel.innerHTML += `<option value="${m.month}">${m.month}</option>`;
        });
        // Also add months from current date if no sessions
        if (!allMonthly.length) {
            const now = new Date();
            for (let i = 0; i < 12; i++) {
                const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
                const label = d.toLocaleString('default', { month: 'long' }) + ' ' + d.getFullYear();
                monthSel.innerHTML += `<option value="${label}">${label}</option>`;
            }
        }
    }
    setExpenseTab('inventory');
}

function setExpenseTab(cat) {
    currentExpenseCategory = cat;
    // Update button styles
    ['inventory','utility','general_repairs','petty_cash'].forEach(c => {
        const btn = document.getElementById('exp-btn-' + c);
        if (btn) btn.className = c === cat ? 'btn btn-primary' : 'btn btn-outline';
    });
    // Update panel title & hint
    const meta = expCategoryMeta[cat];
    const title = document.getElementById('exp-panel-title');
    const hint  = document.getElementById('exp-category-hint');
    if (title) title.textContent = meta.label;
    if (hint)  hint.innerHTML = meta.hint;
    loadExpenses();
}

let expLastData = [];

function renderExpensesRows(expenses) {
    const cat        = currentExpenseCategory;
    const tbody      = document.getElementById('expenses-tbody');
    const totalRow   = document.getElementById('expenses-total-row');
    const countEl    = document.getElementById('expenses-count');
    const subtotalEl = document.getElementById('expenses-subtotal');
    if (!tbody) return;
    if (!expenses.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="empty-row">No results found.</td></tr>`;
        if (totalRow) totalRow.style.display = 'none';
        return;
    }
    let subtotal = 0;
    tbody.innerHTML = expenses.map(e => {
        const amt = parseFloat(e.amount); subtotal += amt;
        return `<tr>
            <td><strong>${e.expense_date}</strong></td>
            <td><strong>${escHtml(e.item_name)}</strong></td>
            <td style="max-width:200px;font-size:0.87rem;color:var(--gray-600);">${e.description ? escHtml(e.description) : '<em style="color:var(--gray-500);">—</em>'}</td>
            <td style="font-size:0.85rem;">${e.reference ? escHtml(e.reference) : '<em style="color:var(--gray-500);">—</em>'}</td>
            <td style="font-size:0.84rem;color:var(--gray-600);">${escHtml(e.recorded_by_name)}</td>
            <td><strong style="color:var(--primary);">KES ${amt.toLocaleString('en-KE', {minimumFractionDigits:2, maximumFractionDigits:2})}</strong></td>
            <td><div class="btn-group">
                <button class="btn btn-outline btn-sm" onclick="editExpense(${e.id})"><i class="fa-solid fa-pen"></i> Edit</button>
                <button class="btn btn-sm" style="background:#FEE2E2;color:#DC2626;border:none;font-weight:700;" onclick="deleteExpense(${e.id}, '${escHtml(e.item_name).replace(/'/g,"&#39;")}')"><i class="fa-solid fa-trash"></i></button>
            </div></td>
        </tr>`;
    }).join('');
    if (countEl)    countEl.textContent = expenses.length;
    if (subtotalEl) subtotalEl.textContent = subtotal.toLocaleString('en-KE', {minimumFractionDigits:2, maximumFractionDigits:2});
    if (totalRow)   totalRow.style.display = 'flex';
}

function filterExpensesSearch() {
    const q = (document.getElementById('exp-search')?.value || '').toLowerCase().trim();
    if (!q) { renderExpensesRows(expLastData); return; }
    const filtered = expLastData.filter(e =>
        (e.item_name   || '').toLowerCase().includes(q) ||
        (e.description || '').toLowerCase().includes(q) ||
        (e.reference   || '').toLowerCase().includes(q) ||
        (e.expense_date|| '').toLowerCase().includes(q)
    );
    renderExpensesRows(filtered);
}

function loadExpenses() {
    const cat   = currentExpenseCategory;
    const month = document.getElementById('exp-filter-month')?.value || '';
    const tbody = document.getElementById('expenses-tbody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="empty-row">Loading…</td></tr>';
    // clear search on reload
    const srch = document.getElementById('exp-search'); if (srch) srch.value = '';

    fetch(`api/api_accounts.php?action=get_expenses&category=${cat}&month=${encodeURIComponent(month)}`)
        .then(r => r.json())
        .then(d => {
            if (d.status !== 'success') { showAlert('error', d.message); return; }

            // Update summary cards
            ['inventory','utility','general_repairs','petty_cash'].forEach(c => {
                const el = document.getElementById('exp-total-' + c);
                if (el) el.textContent = 'KES ' + (d.totals[c] || 0).toLocaleString('en-KE', {minimumFractionDigits:2, maximumFractionDigits:2});
            });

            expLastData = d.expenses || [];

            const totalRow  = document.getElementById('expenses-total-row');
            const countEl   = document.getElementById('expenses-count');
            const subtotalEl = document.getElementById('expenses-subtotal');

            if (!tbody) return;

            if (!expLastData.length) {
                tbody.innerHTML = `<tr><td colspan="7" class="empty-row">No ${expCategoryMeta[cat].label} expenses recorded yet. Click "Add Expense" to start.</td></tr>`;
                if (totalRow) totalRow.style.display = 'none';
                return;
            }
            renderExpensesRows(expLastData);
        })
        .catch(() => showAlert('error', 'Network error loading expenses.'));
}
function openExpenseModal(id) {
    document.getElementById('exp-id').value = id || '';
    document.getElementById('exp-category-hidden').value = currentExpenseCategory;
    document.getElementById('exp-item-name').value   = '';
    document.getElementById('exp-date').value         = new Date().toISOString().split('T')[0];
    document.getElementById('exp-amount').value       = '';
    document.getElementById('exp-description').value  = '';
    document.getElementById('exp-reference').value    = '';

    const meta = expCategoryMeta[currentExpenseCategory];
    document.getElementById('expense-modal-title').textContent    = id ? 'Edit Expense' : 'Add Expense';
    document.getElementById('expense-modal-subtitle').textContent = `Category: ${meta.label}`;
    document.getElementById('exp-submit-btn').innerHTML           = id ? '<i class="fa-solid fa-floppy-disk"></i> Update Expense' : '<i class="fa-solid fa-floppy-disk"></i> Save Expense';
    document.getElementById('expenseModal').classList.add('open');
}

function editExpense(id) {
    fetch(`api/api_accounts.php?action=get_expense_single&id=${id}`)
        .then(r => r.json())
        .then(d => {
            if (d.status !== 'success') { showAlert('error', d.message); return; }
            const e = d.expense;
            document.getElementById('exp-id').value              = e.id;
            document.getElementById('exp-category-hidden').value = e.category;
            document.getElementById('exp-item-name').value       = e.item_name;
            document.getElementById('exp-date').value            = e.expense_date;
            document.getElementById('exp-amount').value          = e.amount;
            document.getElementById('exp-description').value     = e.description || '';
            document.getElementById('exp-reference').value       = e.reference || '';

            const meta = expCategoryMeta[e.category] || {label: e.category};
            document.getElementById('expense-modal-title').textContent    = 'Edit Expense';
            document.getElementById('expense-modal-subtitle').textContent = `Category: ${meta.label}`;
            document.getElementById('exp-submit-btn').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Update Expense';
            document.getElementById('expenseModal').classList.add('open');
        });
}

function saveExpense(e) {
    e.preventDefault();
    const id = document.getElementById('exp-id').value;
    const fd = new FormData();
    fd.append('action',       id ? 'update_expense' : 'add_expense');
    if (id) fd.append('id',   id);
    fd.append('category',     document.getElementById('exp-category-hidden').value);
    fd.append('item_name',    document.getElementById('exp-item-name').value);
    fd.append('expense_date', document.getElementById('exp-date').value);
    fd.append('amount',       document.getElementById('exp-amount').value);
    fd.append('description',  document.getElementById('exp-description').value);
    fd.append('reference',    document.getElementById('exp-reference').value);

    const btn = document.getElementById('exp-submit-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';

    fetch('api/api_accounts.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Expense';
            closeModal('expenseModal');
            showAlert(d.status, d.message);
            if (d.status === 'success') loadExpenses();
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Expense';
            showAlert('error', 'Network error. Please try again.');
        });
}

function deleteExpense(id, name) {
    if (!confirm(`Delete expense "${name}"? This cannot be undone.`)) return;
    const fd = new FormData();
    fd.append('action', 'delete_expense');
    fd.append('id', id);
    fetch('api/api_accounts.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            showAlert(d.status, d.message);
            if (d.status === 'success') loadExpenses();
        });
}

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ─────────────────────────────────────────────
// EXPENSE REPORTS MODULE
// ─────────────────────────────────────────────
let rptCurrentPeriod  = 'month';
let rptLastData       = null;

const rptCatMeta = {
    inventory:       { label:'Inventory',      icon:'📦', color:'#3B82F6', bg:'rgba(59,130,246,0.08)' },
    utility:         { label:'Utilities',       icon:'⚡', color:'#10B981', bg:'rgba(16,185,129,0.08)' },
    general_repairs: { label:'General Repairs', icon:'🔧', color:'#F59E0B', bg:'rgba(245,158,11,0.08)' },
    petty_cash:      { label:'Petty Cash',      icon:'☕', color:'#8B5CF6', bg:'rgba(139,92,246,0.08)' },
};

function initReportTab() {
    // Default to This Month
    setReportPeriod('month');
}

function setReportPeriod(period) {
    rptCurrentPeriod = period;
    // Update button highlights
    ['today','week','month','quarter','year','custom'].forEach(p => {
        const btn = document.getElementById('rpt-q-' + p);
        if (btn) btn.className = p === period ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm';
    });

    const today = new Date();
    const fmt   = d => d.toISOString().split('T')[0];
    const fromEl = document.getElementById('rpt-from');
    const toEl   = document.getElementById('rpt-to');

    if (period === 'today') {
        fromEl.value = fmt(today); toEl.value = fmt(today);
    } else if (period === 'week') {
        const mon = new Date(today);
        mon.setDate(today.getDate() - ((today.getDay() + 6) % 7));
        const sun = new Date(mon); sun.setDate(mon.getDate() + 6);
        fromEl.value = fmt(mon); toEl.value = fmt(sun);
    } else if (period === 'month') {
        fromEl.value = fmt(new Date(today.getFullYear(), today.getMonth(), 1));
        toEl.value   = fmt(new Date(today.getFullYear(), today.getMonth() + 1, 0));
    } else if (period === 'quarter') {
        const q = Math.floor(today.getMonth() / 3);
        fromEl.value = fmt(new Date(today.getFullYear(), q * 3, 1));
        toEl.value   = fmt(new Date(today.getFullYear(), q * 3 + 3, 0));
    } else if (period === 'year') {
        fromEl.value = today.getFullYear() + '-01-01';
        toEl.value   = today.getFullYear() + '-12-31';
    }
    // 'custom' — leave dates as-is, user sets manually
}

function markCustom() {
    setReportPeriod('custom');
    rptCurrentPeriod = 'custom';
    ['today','week','month','quarter','year'].forEach(p => {
        const btn = document.getElementById('rpt-q-' + p);
        if (btn) btn.className = 'btn btn-outline btn-sm';
    });
    const btn = document.getElementById('rpt-q-custom');
    if (btn) btn.className = 'btn btn-primary btn-sm';
}

function generateReport() {
    const from    = document.getElementById('rpt-from').value;
    const to      = document.getElementById('rpt-to').value;
    const cat     = document.getElementById('rpt-category').value;
    const genBtn  = document.getElementById('rpt-gen-btn');

    if (!from || !to) {
        showAlert('error', 'Please select both From and To dates.');
        return;
    }
    if (from > to) {
        showAlert('error', 'From Date cannot be after To Date.');
        return;
    }

    genBtn.disabled = true;
    genBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating…';

    const url = `api/api_accounts.php?action=expense_report&period=${rptCurrentPeriod}&from_date=${from}&to_date=${to}&category=${cat}`;
    fetch(url)
        .then(r => r.json())
        .then(d => {
            genBtn.disabled = false;
            genBtn.innerHTML = '<i class="fa-solid fa-magnifying-glass-chart"></i> Generate';
            if (d.status !== 'success') { showAlert('error', d.message); return; }
            rptLastData = d;
            renderReport(d, from, to, cat);
        })
        .catch(() => {
            genBtn.disabled = false;
            genBtn.innerHTML = '<i class="fa-solid fa-magnifying-glass-chart"></i> Generate';
            showAlert('error', 'Network error generating report.');
        });
}

function renderReport(d, from, to, cat) {
    document.getElementById('rpt-empty-state').style.display = 'none';
    document.getElementById('rpt-output').style.display      = 'block';

    // Header label
    const periodLabels = {
        today: 'Today', week: 'This Week', month: 'This Month',
        quarter: 'This Quarter', year: 'This Year', custom: 'Custom Range'
    };
    const catLabel = cat ? (rptCatMeta[cat]?.label || cat) : 'All Categories';
    document.getElementById('rpt-title-label').textContent = `${catLabel} — Expense Report`;
    document.getElementById('rpt-date-range-label').textContent =
        `Period: ${from} to ${to}  |  ${d.count} record${d.count !== 1 ? 's' : ''} found`;

    // Grand total
    document.getElementById('rpt-grand-total').textContent =
        'KES ' + d.grand_total.toLocaleString('en-KE', {minimumFractionDigits:2, maximumFractionDigits:2});
    document.getElementById('rpt-grand-count').textContent = d.count;

    // Summary cards
    const cats = cat ? [cat] : ['inventory','utility','general_repairs','petty_cash'];
    const cardsEl = document.getElementById('rpt-summary-cards');
    cardsEl.innerHTML = cats.map(c => {
        const m   = rptCatMeta[c];
        const tot = (d.cat_totals[c] || 0);
        const cnt = (d.cat_counts[c] || 0);
        const pct = d.grand_total > 0 ? ((tot / d.grand_total) * 100).toFixed(1) : '0.0';
        return `
        <div class="metric-card" style="border-left:4px solid ${m.color};flex-direction:column;align-items:flex-start;gap:10px;">
            <div style="display:flex;justify-content:space-between;width:100%;align-items:center;">
                <div class="metric-info">
                    <h4 style="color:${m.color};">${m.icon} ${m.label}</h4>
                    <p style="font-size:1.5rem;color:${m.color};">${tot.toLocaleString('en-KE',{minimumFractionDigits:2,maximumFractionDigits:2})}</p>
                </div>
                <div style="font-size:2rem;opacity:0.15;">${m.icon}</div>
            </div>
            <div style="width:100%;background:var(--gray-200);border-radius:6px;height:6px;">
                <div style="width:${pct}%;background:${m.color};height:6px;border-radius:6px;transition:width 0.6s;"></div>
            </div>
            <div style="display:flex;justify-content:space-between;width:100%;font-size:0.8rem;color:var(--gray-600);">
                <span>${cnt} item${cnt!==1?'s':''}</span>
                <span style="font-weight:700;color:${m.color};">${pct}%</span>
            </div>
        </div>`;
    }).join('');

    // Per-category expense tables
    const catPanelsEl = document.getElementById('rpt-cat-panels');
    if (!cat) {
        catPanelsEl.innerHTML = ['inventory','utility','general_repairs','petty_cash'].map(c => {
            const m    = rptCatMeta[c];
            const rows = d.expenses.filter(e => e.category === c);
            if (!rows.length) return '';
            let subTotal = 0;
            const trs = rows.map((e, i) => {
                const amt = parseFloat(e.amount);
                subTotal += amt;
                return `<tr>
                    <td style="color:var(--gray-600);font-size:0.82rem;">${i+1}</td>
                    <td>${e.expense_date}</td>
                    <td><strong>${escHtml(e.item_name)}</strong></td>
                    <td style="max-width:180px;font-size:0.85rem;color:var(--gray-600);">${e.description ? escHtml(e.description) : '—'}</td>
                    <td style="font-size:0.83rem;">${e.reference ? escHtml(e.reference) : '—'}</td>
                    <td style="font-size:0.83rem;color:var(--gray-600);">${escHtml(e.recorded_by_name)}</td>
                    <td style="text-align:right;"><strong>KES ${amt.toLocaleString('en-KE',{minimumFractionDigits:2,maximumFractionDigits:2})}</strong></td>
                </tr>`;
            }).join('');
            return `
            <div class="panel" style="margin-bottom:20px;border-top:4px solid ${m.color};">
                <div class="panel-header">
                    <h2 style="color:${m.color};">${m.icon} ${m.label}</h2>
                    <span style="font-weight:800;color:${m.color};font-size:1.05rem;">KES ${subTotal.toLocaleString('en-KE',{minimumFractionDigits:2,maximumFractionDigits:2})}</span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>#</th><th>Date</th><th>Item</th><th>Description</th><th>Reference</th><th>Recorded By</th><th style="text-align:right;">Amount</th></tr>
                        </thead>
                        <tbody>${trs}</tbody>
                    </table>
                </div>
            </div>`;
        }).join('');
    } else {
        catPanelsEl.innerHTML = '';
    }

    // Full expenses table (always shown)
    let grand = 0;
    const allRows = d.expenses.map((e, i) => {
        const amt = parseFloat(e.amount);
        grand += amt;
        const m = rptCatMeta[e.category];
        return `<tr>
            <td style="color:var(--gray-600);">${i+1}</td>
            <td>${e.expense_date}</td>
            <td><span style="background:${m?.bg||'#eee'};color:${m?.color||'#333'};padding:3px 10px;border-radius:20px;font-size:0.78rem;font-weight:700;">${m?.icon||''} ${m?.label||e.category}</span></td>
            <td><strong>${escHtml(e.item_name)}</strong></td>
            <td style="max-width:180px;font-size:0.85rem;color:var(--gray-600);">${e.description ? escHtml(e.description) : '—'}</td>
            <td style="font-size:0.83rem;">${e.reference ? escHtml(e.reference) : '—'}</td>
            <td style="font-size:0.83rem;color:var(--gray-600);">${escHtml(e.recorded_by_name)}</td>
            <td style="text-align:right;"><strong>KES ${amt.toLocaleString('en-KE',{minimumFractionDigits:2,maximumFractionDigits:2})}</strong></td>
        </tr>`;
    }).join('');

    document.getElementById('rpt-full-tbody').innerHTML = allRows ||
        '<tr><td colspan="8" class="empty-row">No records found for this period / filter.</td></tr>';
    document.getElementById('rpt-full-tfoot').innerHTML = `
        <tr style="background:var(--cream);">
            <td colspan="7" style="text-align:right;font-weight:800;color:var(--primary);padding:14px 16px;">
                GRAND TOTAL (${d.count} records)
            </td>
            <td style="text-align:right;font-weight:800;font-size:1.05rem;color:var(--primary);padding:14px 16px;">
                KES ${grand.toLocaleString('en-KE',{minimumFractionDigits:2,maximumFractionDigits:2})}
            </td>
        </tr>`;
}

function exportReportCSV() {
    if (!rptLastData || !rptLastData.expenses.length) {
        showAlert('error', 'No report data to export. Generate a report first.');
        return;
    }
    const from = document.getElementById('rpt-from').value;
    const to   = document.getElementById('rpt-to').value;
    const rows = [['#','Date','Category','Item','Description','Reference','Recorded By','Amount (KES)']];
    rptLastData.expenses.forEach((e, i) => {
        rows.push([
            i+1,
            e.expense_date,
            rptCatMeta[e.category]?.label || e.category,
            e.item_name,
            e.description || '',
            e.reference   || '',
            e.recorded_by_name,
            parseFloat(e.amount).toFixed(2)
        ]);
    });
    // Totals row
    rows.push([]);
    rows.push(['','','','','','','GRAND TOTAL', rptLastData.grand_total.toFixed(2)]);

    const csv  = rows.map(r => r.map(c => `"${String(c).replace(/"/g,'""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], {type:'text/csv'});
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `expense_report_${from}_to_${to}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

function printReport() {
    if (!rptLastData) { showAlert('error', 'Generate a report first.'); return; }
    const from    = document.getElementById('rpt-from').value;
    const to      = document.getElementById('rpt-to').value;
    const content = document.getElementById('rpt-output').innerHTML;
    const win = window.open('', '_blank', 'width=1100,height=850');
    win.document.write(`
        <!DOCTYPE html><html>
        <head>
            <title>Expense Report — ${from} to ${to}</title>
            <style>
                body{font-family:'Segoe UI',sans-serif;padding:30px;color:#1e293b;font-size:13px;}
                table{width:100%;border-collapse:collapse;margin-bottom:20px;}
                th,td{padding:8px 10px;border:1px solid #e2e8f0;text-align:left;}
                th{background:#f8fafc;font-weight:700;font-size:11px;text-transform:uppercase;}
                h1,h2{color:#4A0E17;} .panel{border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:16px;}
                .panel-header{display:flex;justify-content:space-between;margin-bottom:12px;}
                .metric-card,.metrics-grid{display:none;}
                button,.btn{display:none;}
                tfoot tr{background:#FAF7F2;}
                @media print{body{padding:15px;} button{display:none!important;}}
            </style>
        </head>
        <body>
            <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #E5A93B;padding-bottom:12px;margin-bottom:20px;">
                <div>
                    <h1 style="margin:0;font-size:1.4rem;">SANITY HOMEBASED TUITION ACADEMY</h1>
                    <p style="margin:4px 0;color:#6C757D;font-size:12px;">Expense Report — Generated: ${new Date().toLocaleString()}</p>
                </div>
                <div style="text-align:right;">
                    <strong>Period:</strong> ${from} to ${to}<br>
                    <strong>Records:</strong> ${rptLastData.count}
                </div>
            </div>
            ${content}
            <script>window.onload=()=>window.print();<\/script>
        </body></html>
    `);
    win.document.close();
}

// Init
window.onload = () => {
    loadSessions();
    const savedTab = (new URLSearchParams(window.location.search).get('fresh') === '1')
        ? (() => { localStorage.removeItem('accounts_dashboard_active_tab'); return 'dashboard'; })()
        : (localStorage.getItem('accounts_dashboard_active_tab') || 'dashboard');
    if (savedTab) {
        switchTab(savedTab);
    }
};

// ─────────────────────────────────────────────
// FULL SCHOOL FINANCIAL REPORT MODULE
// ─────────────────────────────────────────────
let finRptData      = null;
let finRptPeriod    = 'month';
let finRptBreakdown = 'venue';

const finVenueLabels = {
    school:      '🏫 School (1-on-1)',
    home_visit:  '🏠 Home Visit',
    online_meet: '💻 Online (Google Meet)',
    online_zoom: '📹 Online (Zoom)'
};
const finCatMeta = {
    inventory:       { label:'Inventory',      icon:'📦', color:'#3B82F6' },
    utility:         { label:'Utilities',       icon:'⚡',  color:'#10B981' },
    general_repairs: { label:'General Repairs', icon:'🔧', color:'#F59E0B' },
    petty_cash:      { label:'Petty Cash',      icon:'☕?',  color:'#8B5CF6' },
};

function fmt2(n) {
    return Number(n||0).toLocaleString('en-KE',{minimumFractionDigits:2,maximumFractionDigits:2});
}

function initFinReportTab() {
    setFinPeriod('month');
    loadFinDropdowns();
}

function loadFinDropdowns() {
    fetch('api/api_accounts.php?action=students_list')
        .then(r => r.json())
        .then(d => {
            const sel = document.getElementById('fin-student');
            if (!sel) return;
            const cur = sel.value;
            sel.innerHTML = '<option value="">All Students</option>';
            (d.students || []).forEach(s => {
                sel.innerHTML += `<option value="${s.id}">${escHtml(s.student_name)}</option>`;
            });
            if (cur) sel.value = cur;
        }).catch(()=>{});
    fetch('api/api_accounts.php?action=teachers_list')
        .then(r => r.json())
        .then(d => {
            const sel = document.getElementById('fin-teacher');
            if (!sel) return;
            const cur = sel.value;
            sel.innerHTML = '<option value="">All Teachers</option>';
            (d.teachers || []).forEach(t => {
                sel.innerHTML += `<option value="${t.id}">${escHtml(t.name)}</option>`;
            });
            if (cur) sel.value = cur;
        }).catch(()=>{});
}

function setFinPeriod(period) {
    finRptPeriod = period;
    ['today','week','month','quarter','year','custom'].forEach(p => {
        const btn = document.getElementById('fin-q-' + p);
        if (btn) btn.className = p === period ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm';
    });
    const today  = new Date();
    const fmt    = d => d.toISOString().split('T')[0];
    const fromEl = document.getElementById('fin-from');
    const toEl   = document.getElementById('fin-to');
    if (!fromEl || !toEl) return;
    if (period === 'today')   { fromEl.value = fmt(today); toEl.value = fmt(today); }
    else if (period === 'week') {
        const mon = new Date(today); mon.setDate(today.getDate() - ((today.getDay()+6)%7));
        const sun = new Date(mon);   sun.setDate(mon.getDate()+6);
        fromEl.value = fmt(mon); toEl.value = fmt(sun);
    } else if (period === 'month') {
        fromEl.value = fmt(new Date(today.getFullYear(), today.getMonth(), 1));
        toEl.value   = fmt(new Date(today.getFullYear(), today.getMonth()+1, 0));
    } else if (period === 'quarter') {
        const q = Math.floor(today.getMonth()/3);
        fromEl.value = fmt(new Date(today.getFullYear(), q*3, 1));
        toEl.value   = fmt(new Date(today.getFullYear(), q*3+3, 0));
    } else if (period === 'year') {
        fromEl.value = today.getFullYear() + '-01-01';
        toEl.value   = today.getFullYear() + '-12-31';
    }
}

function markFinCustom() {
    finRptPeriod = 'custom';
    ['today','week','month','quarter','year'].forEach(p => {
        const btn = document.getElementById('fin-q-' + p);
        if (btn) btn.className = 'btn btn-outline btn-sm';
    });
    const b = document.getElementById('fin-q-custom');
    if (b) b.className = 'btn btn-primary btn-sm';
}

function resetFinFilters() {
    document.getElementById('fin-student').value = '';
    document.getElementById('fin-teacher').value = '';
    document.getElementById('fin-venue').value   = '';
    setFinPeriod('month');
}

function generateFinReport() {
    const from      = document.getElementById('fin-from').value;
    const to        = document.getElementById('fin-to').value;
    const studentId = document.getElementById('fin-student').value;
    const teacherId = document.getElementById('fin-teacher').value;
    const venue     = document.getElementById('fin-venue').value;
    const genBtn    = document.getElementById('fin-gen-btn');
    if (!from || !to) { showAlert('error','Please select both From and To dates.'); return; }
    if (from > to)    { showAlert('error','From Date cannot be after To Date.'); return; }
    genBtn.disabled = true;
    genBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating…';
    const url = `api/api_accounts.php?action=financial_report&period=${finRptPeriod}&from_date=${from}&to_date=${to}&student_id=${studentId}&teacher_id=${teacherId}&venue_type=${venue}`;
    fetch(url)
        .then(r => r.json())
        .then(d => {
            genBtn.disabled = false;
            genBtn.innerHTML = '<i class="fa-solid fa-chart-column"></i> Generate Report';
            if (d.status !== 'success') { showAlert('error', d.message); return; }
            finRptData = d;
            renderFinReport(d, from, to);
        })
        .catch(() => {
            genBtn.disabled = false;
            genBtn.innerHTML = '<i class="fa-solid fa-chart-column"></i> Generate Report';
            showAlert('error','Network error generating report.');
        });
}

function renderFinReport(d, from, to) {
    document.getElementById('finrpt-empty-state').style.display = 'none';
    document.getElementById('finrpt-output').style.display      = 'block';

    // Title
    const sSel = document.getElementById('fin-student');
    const tSel = document.getElementById('fin-teacher');
    const vSel = document.getElementById('fin-venue');
    const activeFilters = [
        sSel.value ? sSel.options[sSel.selectedIndex].text : '',
        tSel.value ? tSel.options[tSel.selectedIndex].text : '',
        vSel.value ? vSel.options[vSel.selectedIndex].text : ''
    ].filter(Boolean);
    document.getElementById('finrpt-title').textContent = 'School Financial Report';
    document.getElementById('finrpt-subtitle').textContent =
        `Period: ${from} –' ${to}` +
        (activeFilters.length ? `  |  Filters: ${activeFilters.join(', ')}` : '') +
        `  |  ${d.sessions_count} session${d.sessions_count!==1?'s':''}`;

    // KPI cards
    const net = d.net_position;
    document.getElementById('finrpt-kpi-cards').innerHTML = `
        <div class="metric-card" style="border-left:4px solid #10B981;">
            <div class="metric-info"><h4>Revenue Billed</h4><p style="font-size:1.35rem;color:#10B981;">${fmt2(d.total_billed)}</p></div>
            <div class="metric-icon" style="background:rgba(16,185,129,0.12);color:#10B981;"><i class="fa-solid fa-file-invoice-dollar"></i></div>
        </div>
        <div class="metric-card" style="border-left:4px solid #3B82F6;">
            <div class="metric-info"><h4>Collected (Period)</h4><p style="font-size:1.35rem;color:#3B82F6;">${fmt2(d.collected_in_period)}</p></div>
            <div class="metric-icon" style="background:rgba(59,130,246,0.12);color:#3B82F6;"><i class="fa-solid fa-money-bill-wave"></i></div>
        </div>
        <div class="metric-card" style="border-left:4px solid #DC2626;">
            <div class="metric-info"><h4>Outstanding</h4><p style="font-size:1.35rem;color:#DC2626;">${fmt2(d.outstanding)}</p></div>
            <div class="metric-icon" style="background:rgba(220,38,38,0.12);color:#DC2626;"><i class="fa-solid fa-triangle-exclamation"></i></div>
        </div>
        <div class="metric-card" style="border-left:4px solid #F59E0B;">
            <div class="metric-info"><h4>Total Expenses</h4><p style="font-size:1.35rem;color:#F59E0B;">${fmt2(d.exp_total)}</p></div>
            <div class="metric-icon" style="background:rgba(245,158,11,0.12);color:#F59E0B;"><i class="fa-solid fa-cart-shopping"></i></div>
        </div>
        <div class="metric-card" style="border-left:4px solid ${net>=0?'#10B981':'#DC2626'};">
            <div class="metric-info"><h4>Net Position</h4><p style="font-size:1.35rem;color:${net>=0?'#10B981':'#DC2626'};">KES ${net<0?'-':''}${fmt2(Math.abs(net))}</p></div>
            <div class="metric-icon" style="background:${net>=0?'rgba(16,185,129,0.12)':'rgba(220,38,38,0.12)'};color:${net>=0?'#10B981':'#DC2626'};"><i class="fa-solid fa-scale-balanced"></i></div>
        </div>`;

    // Net banner
    const banner = document.getElementById('finrpt-net-banner');
    banner.style.background = net >= 0
        ? 'linear-gradient(135deg,#064E3B,#047857)'
        : 'linear-gradient(135deg,#7F1D1D,#991B1B)';
    banner.style.color = 'white';
    document.getElementById('finrpt-net-value').textContent  = (net<0?'-':'') + 'KES ' + fmt2(Math.abs(net));
    document.getElementById('finrpt-net-hint').textContent   = net>=0 ? '🎉... Surplus — Collections cover all operational expenses.' : '⚠️ Deficit — Expenses exceed revenue collected in this period.';
    document.getElementById('finrpt-period-label').textContent  = `${from} –' ${to}`;
    document.getElementById('finrpt-sessions-label').textContent = `${d.sessions_count} sessions completed`;

    // Revenue overview
    document.getElementById('fin-rev-billed').textContent      = 'KES ' + fmt2(d.total_billed);
    document.getElementById('fin-rev-collected').textContent   = 'KES ' + fmt2(d.collected_in_period);
    document.getElementById('fin-rev-outstanding').textContent = 'KES ' + fmt2(d.outstanding);
    document.getElementById('fin-rev-teacher').textContent     = 'KES ' + fmt2(d.total_teacher_earned);
    document.getElementById('fin-rev-sessions').textContent    = d.sessions_count + ' sessions';

    // Expenses overview table
    const cats = ['inventory','utility','general_repairs','petty_cash'];
    document.getElementById('fin-exp-overview-table').innerHTML =
        cats.map(c => {
            const m = finCatMeta[c];
            return `<tr style="border-bottom:1px solid var(--gray-200);"><td style="padding:9px 0;color:var(--gray-600);">${m.icon} ${m.label}</td><td style="padding:9px 0;text-align:right;font-weight:700;color:${m.color};">KES ${fmt2(d.exp_by_cat[c]||0)}</td></tr>`;
        }).join('') +
        `<tr><td style="padding:11px 0;font-weight:800;color:#EF4444;">Total Expenses</td><td style="padding:11px 0;text-align:right;font-weight:800;font-size:1.05rem;color:#EF4444;">KES ${fmt2(d.exp_total)}</td></tr>`;

    // Breakdown tabs
    setFinBreakdown('venue');

    // Session Log
    document.getElementById('fin-sessions-badge').textContent = `${d.sessions_count} sessions`;
    const sTbody = document.getElementById('fin-sessions-tbody');
    const sTfoot = document.getElementById('fin-sessions-tfoot');
    if (d.sessions.length) {
        let bTot = 0, tTot = 0;
        sTbody.innerHTML = d.sessions.map((s,i) => {
            const b = parseFloat(s.billed), t = parseFloat(s.teacher_earned);
            bTot += b; tTot += t;
            return `<tr>
                <td style="color:var(--gray-600);">${i+1}</td>
                <td>${s.lesson_date}</td>
                <td><strong>${escHtml(s.student_name)}</strong></td>
                <td>${escHtml(s.teacher_name)}</td>
                <td style="font-size:0.83rem;">${finVenueLabels[s.venue_type]||s.venue_type}</td>
                <td style="text-align:right;"><strong>KES ${fmt2(b)}</strong></td>
                <td style="text-align:right;color:#047857;">KES ${fmt2(t)}</td>
            </tr>`;
        }).join('');
        sTfoot.innerHTML = `<tr style="background:var(--cream);">
            <td colspan="5" style="text-align:right;font-weight:800;padding:12px 16px;">TOTALS (${d.sessions_count} sessions)</td>
            <td style="text-align:right;font-weight:800;padding:12px 16px;color:var(--primary);">KES ${fmt2(bTot)}</td>
            <td style="text-align:right;font-weight:800;padding:12px 16px;color:#047857;">KES ${fmt2(tTot)}</td>
        </tr>`;
    } else {
        sTbody.innerHTML = '<tr><td colspan="7" class="empty-row">No completed sessions in this period.</td></tr>';
        sTfoot.innerHTML = '';
    }

    // Expenses Detail
    document.getElementById('fin-exp-badge').textContent = `${d.expenses.length} items • KES ${fmt2(d.exp_total)}`;
    const eTbody = document.getElementById('fin-exp-tbody');
    const eTfoot = document.getElementById('fin-exp-tfoot');
    if (d.expenses.length) {
        let eTotal = 0;
        eTbody.innerHTML = d.expenses.map((e,i) => {
            const amt = parseFloat(e.amount); eTotal += amt;
            const m = finCatMeta[e.category]||{};
            return `<tr>
                <td style="color:var(--gray-600);">${i+1}</td>
                <td>${e.expense_date}</td>
                <td><span style="color:${m.color||'#333'};font-weight:700;">${m.icon||''} ${m.label||e.category}</span></td>
                <td><strong>${escHtml(e.item_name)}</strong></td>
                <td style="font-size:0.85rem;color:var(--gray-600);">${e.description?escHtml(e.description):'—'}</td>
                <td style="font-size:0.83rem;">${e.reference?escHtml(e.reference):'—'}</td>
                <td style="text-align:right;"><strong>KES ${fmt2(amt)}</strong></td>
            </tr>`;
        }).join('');
        eTfoot.innerHTML = `<tr style="background:var(--cream);">
            <td colspan="6" style="text-align:right;font-weight:800;padding:12px 16px;">TOTAL EXPENSES</td>
            <td style="text-align:right;font-weight:800;color:#EF4444;padding:12px 16px;">KES ${fmt2(eTotal)}</td>
        </tr>`;
    } else {
        eTbody.innerHTML = '<tr><td colspan="7" class="empty-row">No expenses recorded in this period.</td></tr>';
        eTfoot.innerHTML = '';
    }
}

function setFinBreakdown(type) {
    finRptBreakdown = type;
    ['venue','student','teacher'].forEach(t => {
        const btn = document.getElementById('fin-brk-' + t + '-btn');
        if (btn) btn.className = t === type ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm';
    });
    if (!finRptData) return;
    const d  = finRptData;
    const el = document.getElementById('fin-brk-content');
    if (type === 'venue') {
        if (!d.by_venue.length) { el.innerHTML = '<p class="empty-row">No sessions in this period.</p>'; return; }
        const grand = d.total_billed;
        el.innerHTML = `<div class="table-wrap"><table>
            <thead><tr><th>Lesson Type</th><th>Sessions</th><th style="text-align:right;">Revenue (KES)</th><th>Share</th></tr></thead>
            <tbody>${d.by_venue.map(v => {
                const pct = grand>0?((v.amount/grand)*100).toFixed(1):'0.0';
                return `<tr>
                    <td><strong>${finVenueLabels[v.venue]||v.venue}</strong></td>
                    <td>${v.count}</td>
                    <td style="text-align:right;font-weight:700;">KES ${fmt2(v.amount)}</td>
                    <td style="min-width:140px;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="flex:1;background:var(--gray-200);border-radius:4px;height:8px;"><div style="width:${pct}%;background:#10B981;height:8px;border-radius:4px;"></div></div>
                            <span style="font-size:0.82rem;font-weight:700;color:#10B981;">${pct}%</span>
                        </div>
                    </td>
                </tr>`;
            }).join('')}</tbody>
            <tfoot><tr style="background:var(--cream);"><td style="font-weight:800;padding:12px 16px;">TOTAL</td><td style="font-weight:800;">${d.sessions_count}</td><td style="font-weight:800;text-align:right;">KES ${fmt2(d.total_billed)}</td><td style="font-weight:800;">100%</td></tr></tfoot>
        </table></div>`;
    } else if (type === 'student') {
        if (!d.by_student.length) { el.innerHTML = '<p class="empty-row">No student data in this period.</p>'; return; }
        const sorted = [...d.by_student].sort((a,b) => b.billed-a.billed);
        el.innerHTML = `<div class="table-wrap"><table>
            <thead><tr><th>#</th><th>Student</th><th>Sessions</th><th style="text-align:right;">Billed (KES)</th></tr></thead>
            <tbody>${sorted.map((s,i) => `<tr>
                <td style="color:var(--gray-600);">${i+1}</td>
                <td><strong>${escHtml(s.name)}</strong></td>
                <td>${s.sessions}</td>
                <td style="text-align:right;font-weight:700;">KES ${fmt2(s.billed)}</td>
            </tr>`).join('')}</tbody>
            <tfoot><tr style="background:var(--cream);"><td colspan="2" style="font-weight:800;padding:12px 16px;">TOTAL</td><td style="font-weight:800;">${d.sessions_count}</td><td style="font-weight:800;text-align:right;">KES ${fmt2(d.total_billed)}</td></tr></tfoot>
        </table></div>`;
    } else if (type === 'teacher') {
        if (!d.by_teacher.length) { el.innerHTML = '<p class="empty-row">No teacher data in this period.</p>'; return; }
        const sorted = [...d.by_teacher].sort((a,b) => b.earned-a.earned);
        el.innerHTML = `<div class="table-wrap"><table>
            <thead><tr><th>#</th><th>Teacher / Tutor</th><th>Sessions</th><th style="text-align:right;">Earned (KES)</th></tr></thead>
            <tbody>${sorted.map((t,i) => `<tr>
                <td style="color:var(--gray-600);">${i+1}</td>
                <td><strong>${escHtml(t.name)}</strong></td>
                <td>${t.sessions}</td>
                <td style="text-align:right;font-weight:700;color:#047857;">KES ${fmt2(t.earned)}</td>
            </tr>`).join('')}</tbody>
            <tfoot><tr style="background:var(--cream);"><td colspan="2" style="font-weight:800;padding:12px 16px;">TOTAL</td><td style="font-weight:800;">${d.sessions_count}</td><td style="font-weight:800;text-align:right;color:#047857;">KES ${fmt2(d.total_teacher_earned)}</td></tr></tfoot>
        </table></div>`;
    }
}

function exportFinCSV() {
    if (!finRptData) { showAlert('error','Generate a report first.'); return; }
    const d    = finRptData;
    const from = document.getElementById('fin-from').value;
    const to   = document.getElementById('fin-to').value;
    const rows = [
        ['SANITY HOMEBASED TUITION ACADEMY'],
        ['Full School Financial Report'],
        [`Period: ${from} to ${to}`],
        [`Generated: ${new Date().toLocaleString()}`],
        [],
        ['=== SUMMARY ==='],
        ['Revenue Billed (Sessions)', d.total_billed.toFixed(2)],
        ['Collected in Period', d.collected_in_period.toFixed(2)],
        ['Outstanding Balance', d.outstanding.toFixed(2)],
        ['Teacher Earnings', d.total_teacher_earned.toFixed(2)],
        ['Total Expenses', d.exp_total.toFixed(2)],
        ['NET POSITION', d.net_position.toFixed(2)],
        [],
        ['=== SESSION LOG ==='],
        ['#','Date','Student','Teacher','Lesson Type','Billed (KES)','Teacher Earned (KES)'],
        ...d.sessions.map((s,i) => [i+1, s.lesson_date, s.student_name, s.teacher_name, finVenueLabels[s.venue_type]||s.venue_type, parseFloat(s.billed).toFixed(2), parseFloat(s.teacher_earned).toFixed(2)]),
        [],
        ['=== EXPENSES LOG ==='],
        ['#','Date','Category','Item','Description','Reference','Amount (KES)'],
        ...d.expenses.map((e,i) => [i+1, e.expense_date, finCatMeta[e.category]?.label||e.category, e.item_name, e.description||'', e.reference||'', parseFloat(e.amount).toFixed(2)]),
    ];
    const csv  = rows.map(r => r.map(c => `"${String(c||'').replace(/"/g,'""')}"`).join(',')).join('\n');
    const blob = new Blob([csv],{type:'text/csv'});
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = `financial_report_${from}_to_${to}.csv`; a.click();
    URL.revokeObjectURL(url);
}

function printFinReport() {
    if (!finRptData) { showAlert('error','Generate a report first.'); return; }
    const from    = document.getElementById('fin-from').value;
    const to      = document.getElementById('fin-to').value;
    const content = document.getElementById('finrpt-output').innerHTML;
    const d       = finRptData;
    const win = window.open('','_blank','width=1150,height=900');
    win.document.write(`<!DOCTYPE html><html><head>
        <title>Financial Report — ${from} to ${to}</title>
        <style>
            *{box-sizing:border-box;}
            body{font-family:'Segoe UI',Arial,sans-serif;padding:30px;color:#1e293b;font-size:13px;}
            h1,h2,h3{color:#4A0E17;margin:0 0 8px;}
            table{width:100%;border-collapse:collapse;margin-bottom:16px;}
            th,td{padding:8px 10px;border:1px solid #e2e8f0;text-align:left;}
            th{background:#f8fafc;font-weight:700;font-size:11px;text-transform:uppercase;}
            tfoot tr{background:#FAF7F2;}
            .panel{border:1px solid #e2e8f0;border-radius:8px;padding:16px 18px;margin-bottom:16px;}
            .panel-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid #f1f5f9;}
            button,.btn,#finrpt-filter-panel,#finrpt-empty-state,.btn-group{display:none!important;}
            #finrpt-net-banner{border-radius:10px;padding:16px 20px;margin-bottom:16px;color:#fff;}
            .metrics-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:14px;}
            .metric-card{border:1px solid #e2e8f0;border-radius:8px;padding:12px;font-size:12px;}
            .metric-info h4{font-size:11px;color:#64748b;text-transform:uppercase;}
            .metric-info p{font-size:1.1rem;font-weight:700;margin:4px 0;}
            .metric-icon{display:none;}
            .two-col-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
            @media print{body{padding:15px;} button{display:none!important;}}
        </style></head><body>
        <div style="display:flex;justify-content:space-between;border-bottom:3px solid #E5A93B;padding-bottom:12px;margin-bottom:18px;">
            <div>
                <h1 style="font-size:1.35rem;">SANITY HOMEBASED TUITION ACADEMY</h1>
                <p style="margin:4px 0;color:#64748b;font-size:12px;">Full School Financial Report — Generated: ${new Date().toLocaleString()}</p>
            </div>
            <div style="text-align:right;font-size:12px;">
                <strong>Period:</strong> ${from} to ${to}<br>
                <strong>Sessions:</strong> ${d.sessions_count}<br>
                <strong>Net Position:</strong> KES ${fmt2(d.net_position)}
            </div>
        </div>
        ${content}
        <script>window.onload=()=>window.print();<\/script>
    </body></html>`);
    win.document.close();
}
function sendAccountsMessage(e) {
    e.preventDefault();
    const recipient = document.getElementById('accounts-msg-recipient').value;
    const title     = document.getElementById('accounts-msg-title').value.trim();
    const message   = document.getElementById('accounts-msg-body').value.trim();

    if (!title || !message) {
        showAlert('error', 'Please enter both a title and a message.');
        return;
    }

    const fd = new FormData();
    fd.append('action', 'send_notification');
    fd.append('recipient_role', recipient);
    fd.append('title', title);
    fd.append('message', message);

    fetch('api/api_notifications.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            showAlert(data.status, data.message);
            if (data.status === 'success') {
                document.getElementById('form-accounts-send-msg').reset();
                loadNotifications();
            }
        })
        .catch(err => {
            console.error(err);
            showAlert('error', 'Failed to send message.');
        });
}

function loadNotifications() {
    const feed = document.getElementById('notif-feed');
    if (!feed) return;
    feed.innerHTML = '<div class="no-data-msg"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</div>';
    fetch('api/api_notifications.php?action=get_notifications')
        .then(r => r.json())
        .then(d => {
            const list = d.notifications || [];
            if (!list.length) {
                feed.innerHTML = '<div class="no-data-msg"><i class="fa-regular fa-bell-slash" style="font-size:2rem;display:block;margin-bottom:10px;color:var(--gray-200);"></i>No notifications yet</div>';
                return;
            }
            feed.innerHTML = list.map(n => `
                <div style="display:flex;gap:14px;align-items:flex-start;padding:16px 0;border-bottom:1px solid var(--cream);">
                    <div style="width:38px;height:38px;border-radius:10px;background:rgba(229,169,59,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fa-solid fa-bell" style="color:var(--accent);"></i>
                    </div>
                    <div style="flex:1;">
                        <div style="font-weight:700;color:var(--dark);font-size:0.92rem;">${n.title}</div>
                        <div style="color:var(--gray-600);font-size:0.85rem;line-height:1.5;margin-top:3px;">${n.message}</div>
                        <div style="font-size:0.75rem;color:var(--gray-600);margin-top:6px;display:flex;gap:12px;">
                            <span><i class="fa-regular fa-clock"></i> ${formatNotifDate(n.created_at)}</span>
                            <span><i class="fa-solid fa-user"></i> From: ${n.sender_name}</span>
                        </div>
                    </div>
                </div>
            `).join('');
        }).catch(() => {
            feed.innerHTML = '<div class="no-data-msg">Could not load notifications.</div>';
        });
}

function formatNotifDate(dt) {
    if (!dt) return '';
    const d = new Date(dt);
    const now = new Date();
    const diff = Math.floor((now - d) / 1000);
    if (diff < 60)    return 'Just now';
    if (diff < 3600)  return Math.floor(diff/60) + ' min ago';
    if (diff < 86400) return Math.floor(diff/3600) + ' hr ago';
    return d.toLocaleDateString('en-GB', { day:'numeric', month:'short', year:'numeric' });
}


// Loading state utility
function setButtonLoading(btn, isLoading, loadingText = 'Processing...') {
    if (!btn || !(btn instanceof HTMLElement)) return;
    if (isLoading) {
        if (!btn.hasAttribute('data-original-html')) {
            btn.setAttribute('data-original-html', btn.innerHTML);
        }
        btn.disabled = True;
        btn.innerHTML = <i class="fa-solid fa-spinner fa-spin"></i> ;
    } else {
        btn.disabled = False;
        if (btn.hasAttribute('data-original-html')) {
            btn.innerHTML = btn.getAttribute('data-original-html');
        }
    }
}

// Missing functions implementation
function dispatchAllOverdueAlerts(btn) {
    if (!confirm('Dispatch nudge emails to all overdue teachers?')) return;
    setButtonLoading(btn, true, 'Dispatching...');
    fetch('api/api_manage_reports.php?action=nudge_all_overdue', { method: 'POST' })
        .then(r => r.json())
        .then(d => { showAlert(d.status || 'success', d.message || 'Nudges dispatched'); })
        .catch(() => showAlert('error', 'Failed to dispatch nudges.'))
        .finally(() => setButtonLoading(btn, false));
}

function openAddStudentModal() {
    showAlert('info', 'Add student feature is under development.');
}

function piDoPrint() {
    window.print();
}
</script>
</body>
</html>
