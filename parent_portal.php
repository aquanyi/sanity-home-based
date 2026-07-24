<?php
/**
 * parent_portal.php
 * Portal interface for Parents to track reports, resource materials, and edit profile details.
 */
require_once 'security.php';
start_secure_session();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !in_array($_SESSION['user_role'] ?? '', ['parent', 'admin'])) {
    header('Location: login.html?error=Please+log+in+with+a+Parent+account#parent');
    exit;
}
// Generate CSRF token and save it in the session before closing the session
$csrf_token = generate_csrf_token();
session_write_close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S.H.T.A – Parent Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4A0E17; --primary-light: #6b1422; --accent: #E5A93B; --cream: #FAF7F2;
            --white: #FFFFFF; --dark: #2A080D; --gray-200: #E9ECEF; --gray-600: #6C757D;
            --success: #2ECC71; --danger: #E74C3C; --sidebar-w: 270px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Outfit', sans-serif; }
        body { display: flex; min-height: 100vh; background: var(--cream); }
        .sidebar { width: var(--sidebar-w); background: linear-gradient(180deg, var(--dark) 0%, var(--primary) 100%); display: flex; flex-direction: column; padding: 25px 15px; position: fixed; height: 100vh; }
        .sidebar-logo { text-align: center; padding-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; }
        .sidebar-logo img { height: 50px; margin-bottom: 8px; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 15px; color: rgba(255,255,255,0.75); border-radius: 10px; cursor: pointer; transition: all 0.25s; margin-bottom: 3px; font-weight: 500; }
        .nav-item:hover { background: rgba(255,255,255,0.08); color: var(--white); }
        .nav-item.active { background: rgba(229,169,59,0.15); color: var(--accent); border-left: 3px solid var(--accent); }
        .main { margin-left: var(--sidebar-w); flex: 1; padding: 30px 35px; }
        .panel { background: var(--white); border-radius: 16px; padding: 28px; border: 1px solid rgba(74,14,23,0.05); box-shadow: 0 5px 20px rgba(0,0,0,0.03); margin-bottom: 25px; }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid var(--cream); }
        .section { display: none; } .section.active { display: block; }
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; } .table-wrap table { min-width: 480px; } table { width: 100%; border-collapse: collapse; }
        th { background: var(--cream); color: var(--primary); padding: 12px; text-align: left; font-size: 0.85rem; }
        td { padding: 12px; font-size: 0.9rem; border-bottom: 1px solid var(--gray-200); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 18px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-control { width: 100%; padding: 10px 14px; border: 2px solid var(--gray-200); border-radius: 8px; font-size: 0.9rem; outline: none; }
        .btn { padding: 10px 18px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; background: var(--primary); color: white; text-decoration: none; display: inline-block; }
        .btn-outline { background: transparent; border: 2px solid var(--primary); color: var(--primary); }
        .btn-outline:hover { background: var(--primary); color: white; }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; }
        .alert { padding: 12px 18px; border-radius: 10px; margin-bottom: 20px; display: none; font-size: 0.9rem; }
        .alert-success { background: #D1FAE5; color: #065F46; } .alert-error { background: #FEE2E2; color: #991B1B; }        /* Mobile Top Bar */
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

        /* Message Center / Dashboard elements */
        .message-center-card {
            background: var(--white);
            border-radius: 16px;
            padding: 30px;
            border: 1px solid rgba(74,14,23,0.05);
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            margin-top: 15px;
        }
        .mc-header {
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 700;
            color: #2b6cb0;
            margin-bottom: 25px;
            border-bottom: 1px solid var(--gray-200);
            padding-bottom: 10px;
        }
        .mc-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
            align-items: center;
            max-width: 280px;
            margin: 0 auto;
        }
        .mc-item {
            display: flex;
            align-items: center;
            gap: 25px;
            width: 100%;
            cursor: pointer;
            padding: 12px 18px;
            border-radius: 12px;
            border: 1.5px solid var(--gray-200);
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }
        .mc-item:hover {
            border-color: var(--accent);
            background: var(--cream);
            transform: translateY(-2px);
        }
        .mc-icon-wrap {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            border: 2px solid var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: var(--primary);
            position: relative;
        }
        .mc-number {
            font-size: 1.3rem;
            font-weight: 700;
            color: #2b6cb0;
            text-decoration: underline;
        }

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
            .sidebar.active {
                right: 0;
            }
            .sidebar-logo { display: none; } /* already in top bar */
            .nav-item { padding: 12px 15px; font-size: 0.9rem; }
            .main { margin-left: 0; padding: 20px 14px; }
            
            .info-bar { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; }
        }
        @media (max-width: 480px) {
            .main { padding: 15px 10px; }
            .panel { padding: 20px 15px; border-radius: 12px; }
            .panel-header h2 { font-size: 1.05rem; }
            .form-grid { gap: 12px; }
            .info-date { font-size: 0.8rem; }
            .info-badges { gap: 12px; }
            .info-badge-item { font-size: 1.05rem; }
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
    
        /* ── GLOBAL OVERFLOW PREVENTION ── */
        *, *::before, *::after { box-sizing: border-box; }
        html, body { overflow-x: hidden; max-width: 100%; }

        /* Tables scroll inside their containers — no page sideways scroll */
        .table-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .table-wrap table, .table-wrap > table { min-width: 480px; }

        /* ── RESPONSIVE UTILITY CLASSES ── */
        .resp-two-col   { display: grid; grid-template-columns: 280px 1fr; gap: 20px; align-items: start; }
        .resp-filter-grid { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 14px; align-items: end; }
        .resp-stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
        .resp-two-col-sm { display: grid; grid-template-columns: 1fr 1fr; }

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
            .main { margin-left: 0; padding: 20px 14px; overflow-x: hidden; }

            /* Hide inline Sign Out button — only show in sidebar drawer */
            .main-signout-btn { display: none !important; }
            /* Sign Out in sidebar: don't push to bottom on mobile */
            .sidebar-signout-wrap { margin-top: 20px !important; }

            .info-bar { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
            .panel { overflow: hidden; }
            .panel-header { flex-wrap: wrap; gap: 10px; }
            .panel-header h2 { font-size: 1.05rem; }
            .btn-group { flex-wrap: wrap; gap: 8px; }
            .form-grid { grid-template-columns: 1fr !important; }
            .metrics-grid { grid-template-columns: repeat(2,1fr); gap: 12px; }
            .metric-card { min-width: 0; }
            .modal-box { width: 96vw; max-width: 96vw; padding: 22px 16px; }

            /* Responsive grids */
            .resp-two-col        { grid-template-columns: 1fr !important; }
            .resp-filter-grid    { grid-template-columns: 1fr 1fr !important; }
            .resp-filter-grid > div:last-child { grid-column: span 2; }
            .resp-stats-row      { grid-template-columns: 1fr 1fr !important; }
            .resp-two-col-sm     { grid-template-columns: 1fr !important; }
            [style*="280px 1fr"] { grid-template-columns: 1fr !important; }
            [style*="300px 1fr"] { grid-template-columns: 1fr !important; }
            [style*="repeat(3, 1fr)"],
            [style*="repeat(3,1fr)"] { grid-template-columns: 1fr 1fr !important; }
        }
        @media (max-width: 480px) {
            .main { padding: 12px 8px; }
            .panel { padding: 16px 12px; border-radius: 12px; }
            .panel-header h2 { font-size: 0.95rem; }
            .form-grid { gap: 10px; }
            .metrics-grid { grid-template-columns: 1fr; }
            .modal-box { padding: 18px 12px; }
            .info-date { font-size: 0.78rem; }
            .info-badges { gap: 10px; }
            .info-badge-item { font-size: 1rem; }
            .page-header h1 { font-size: 1.25rem; }
            .page-header p { font-size: 0.82rem; }
            th, td { padding: 7px 8px; font-size: 0.78rem; }
            .resp-filter-grid    { grid-template-columns: 1fr !important; }
            .resp-filter-grid > div:last-child { grid-column: span 1; }
            .resp-stats-row      { grid-template-columns: 1fr !important; }
        }

    </style>
</head>
<body>

<!-- Mobile Header Bar -->
<div class="mobile-header">
    <div class="mobile-logo-wrap">
        <img src="logo.png" alt="S.H.T.A Logo">
        <span>S.H.T.A Portal</span>
    </div>
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()">
        <i class="fa-solid fa-bars" id="hamburgerIcon"></i>
    </button>
</div>

<!-- Sidebar Drawer Backdrop -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <img src="logo.png" alt="S.H.T.A">
        <p style="color:var(--accent);font-size:0.8rem;font-weight:700;">PARENT PORTAL</p>
        <div style="margin-top:10px;padding:8px;background:rgba(255,255,255,0.07);border-radius:8px;font-size:0.8rem;color:white;">
            <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong>
        </div>
    </div>
    <!-- Campus Info -->
    <div class="nav-category-wrap">
        <div class="nav-category-header" onclick="toggleCategoryMenu(this)">
            <i class="fa-solid fa-grip"></i>
            <span>Campus Info</span>
            
        </div>
        <div class="nav-category-submenu">
            <a href="javascript:void(0)" onclick="switchTab('dashboard')" class="submenu-item">Home Dashboard</a>
            <a href="javascript:void(0)" onclick="switchTab('notifications')" class="submenu-item">System Notifications</a>
        </div>
    </div>

    <!-- Academics -->
    <div class="nav-category-wrap">
        <div class="nav-category-header" onclick="toggleCategoryMenu(this)">
            <i class="fa-solid fa-grip"></i>
            <span>Academics</span>
            
        </div>
        <div class="nav-category-submenu">
            <a href="javascript:void(0)" onclick="switchTab('reports')" class="submenu-item">Academic Reports</a>
        </div>
    </div>

    <!-- Library Services -->
    <div class="nav-category-wrap">
        <div class="nav-category-header" onclick="toggleCategoryMenu(this)">
            <i class="fa-solid fa-grip"></i>
            <span>Library Services</span>
            
        </div>
        <div class="nav-category-submenu">
            <a href="javascript:void(0)" onclick="switchTab('resources')" class="submenu-item">Learning Resources</a>
        </div>
    </div>

    <!-- My Profile -->
    <div class="nav-category-wrap">
        <div class="nav-category-header" onclick="toggleCategoryMenu(this)">
            <i class="fa-solid fa-grip"></i>
            <span>My Profile &amp; Students</span>
            
        </div>
        <div class="nav-category-submenu">
            <a href="javascript:void(0)" onclick="switchTab('students')" class="submenu-item">My Enrolled Students</a>
            <a href="javascript:void(0)" onclick="switchTab('profile')" class="submenu-item">My Profile Settings</a>
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
            <div class="info-badge-item" onclick="switchTab('reports')" title="Report Alerts">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span class="info-badge-count" id="badge-date-alerts">0</span>
            </div>
            <div class="info-badge-item" onclick="switchTab('resources')" title="Learning Materials">
                <i class="fa-solid fa-bell"></i>
                <span class="info-badge-count" id="badge-bell-notifs">0</span>
            </div>
            <div class="info-badge-item" onclick="switchTab('profile')" title="System Status">
                <i class="fa-solid fa-calendar-days"></i>
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

    <!-- HOME DASHBOARD -->
    <div id="section-dashboard" class="section active">
        <div class="panel">
            <div class="panel-header">
                <h2>Welcome Back, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>
            </div>
            <p style="color:var(--gray-600);margin-bottom:20px;">Access your student progress tracking dashboard, learning resources, and account updates below.</p>
            
            <!-- Kenyatta University Message Center Style Card -->
            <div class="message-center-card">
                <div class="mc-header">Message Center</div>
                <div class="mc-list">
                    <a href="javascript:void(0)" onclick="switchTab('reports')" class="mc-item">
                        <div class="mc-icon-wrap">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                        <span class="mc-number" id="mc-reports-count">0</span>
                    </a>
                    <a href="javascript:void(0)" onclick="switchTab('resources')" class="mc-item">
                        <div class="mc-icon-wrap">
                            <i class="fa-solid fa-book-open"></i>
                        </div>
                        <span class="mc-number" id="mc-resources-count">0</span>
                    </a>
                    <a href="javascript:void(0)" onclick="switchTab('profile')" class="mc-item">
                        <div class="mc-icon-wrap">
                            <i class="fa-solid fa-user-gear"></i>
                        </div>
                        <span class="mc-number">1</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- NOTIFICATIONS & MESSAGING -->
    <div id="section-notifications" class="section">
        <div class="page-header" style="margin-bottom:20px;">
            <h1>Messages &amp; Announcements</h1>
            <p>Send direct messages to the Admin or your student's assigned teachers, and view updates.</p>
        </div>

        <!-- Send Message Panel -->
        <div class="panel" style="margin-bottom:24px;">
            <div class="panel-header">
                <h2><i class="fa-solid fa-paper-plane" style="color:var(--accent);"></i> Send Message</h2>
            </div>
            <form id="form-parent-send-msg" onsubmit="sendParentMessage(event)">
                <div class="form-grid" style="grid-template-columns: 1fr 2fr; gap:20px;">
                    <div class="form-group">
                        <label style="font-weight:600; font-size:0.9rem;">Send To (Recipient) <span style="color:red;">*</span></label>
                        <div style="position:relative;">
                            <i class="fa-solid fa-user-gear" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--gray-400);"></i>
                            <select id="parent-msg-recipient" class="form-control" required style="padding-left:40px;">
                                <option value="admin">Admin (Principal)</option>
                                <optgroup label="Assigned Teachers" id="parent-assigned-teachers-optgroup">
                                    <option value="" disabled>Loading assigned teachers...</option>
                                </optgroup>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label style="font-weight:600; font-size:0.9rem;">Subject / Title <span style="color:red;">*</span></label>
                        <div style="position:relative;">
                            <i class="fa-solid fa-heading" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--gray-400);"></i>
                            <input type="text" id="parent-msg-title" name="title" class="form-control" required placeholder="e.g. Question regarding Math tuition / Schedule request" style="padding-left:40px;">
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-top:16px;">
                    <label style="font-weight:600; font-size:0.9rem;">Message Content <span style="color:red;">*</span></label>
                    <textarea id="parent-msg-body" name="message" class="form-control" rows="4" required placeholder="Write your message here..."></textarea>
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
            <div class="table-wrap">
                <table style="width:100%;">
                    <thead>
                        <tr>
                            <th style="width:180px; text-align:left;">Sender</th>
                            <th style="width:200px; text-align:left;">Subject</th>
                            <th style="text-align:left;">Details</th>
                            <th style="width:160px; text-align:left;">Date</th>
                        </tr>
                    </thead>
                    <tbody id="parent-notifications-tbody">
                        <tr><td colspan="4">Loading notifications…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ACADEMIC REPORTS -->
    <div id="section-reports" class="section">
        <div class="panel">
            <div class="panel-header"><h2>Released Academic Reports</h2></div>
            <div class="table-wrap">
                <table><thead><tr><th>Period</th><th>Student</th><th>Report Type</th><th>Performance & Notes</th><th>Recommendations</th></tr></thead>
                <tbody id="parent-reports-tbody"><tr><td colspan="5">Loading reports…</td></tr></tbody></table>
            </div>
        </div>
    </div>

    <!-- LEARNING RESOURCES -->
    <div id="section-resources" class="section">
        <div class="panel">
            <div class="panel-header">
                <h2>Learning Material Warehouse</h2>
                <div style="display:flex;align-items:center;gap:10px;">
                    <label style="font-size:0.85rem;font-weight:700;color:var(--gray-600);">Subject Filter:</label>
                    <select id="parent-subject-filter" class="form-control" style="width:auto;padding:6px 12px;" onchange="loadResources()"><option value="">All Subjects</option></select>
                </div>
            </div>
            <div class="table-wrap">
                <table><thead><tr><th>Title</th><th>Subject</th><th>Grade</th><th>Type</th><th>Download</th></tr></thead>
                <tbody id="parent-resources-tbody"><tr><td colspan="5">Loading learning materials…</td></tr></tbody></table>
            </div>
        </div>
    </div>

    <!-- MY ENROLLED STUDENTS -->
    <div id="section-students" class="section">
        <div class="panel">
            <div class="panel-header" style="display:flex; justify-content:space-between; align-items:center;">
                <h2><i class="fa-solid fa-user-graduate" style="color:var(--accent);"></i> My Enrolled Students</h2>
                <button class="btn btn-primary btn-sm" onclick="openAddStudentModal()"><i class="fa-solid fa-plus"></i> Add Another Student</button>
            </div>
            <p style="color:var(--gray-600); font-size:0.9rem; margin-bottom:20px;">
                View your registered students, automatic admission numbers (S000A), date of birth, nationality, and edit student information.
            </p>
            <div class="table-wrap">
                <table style="width:100%;">
                    <thead>
                        <tr>
                            <th>Admission No</th>
                            <th>Student Name</th>
                            <th>Grade / Level</th>
                            <th>Date of Birth</th>
                            <th>Nationality</th>
                            <th>First Language</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="parent-students-tbody">
                        <tr><td colspan="7">Loading student profiles…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PROFILE SETTINGS -->
    <div id="section-profile" class="section">
        <div class="panel">
            <div class="panel-header"><h2>Edit Parent Profile & Contact Details</h2></div>
            <form onsubmit="updateProfile(event)">
                <div class="form-grid">
                    <div class="form-group"><label>Full Name</label><input type="text" id="prof-name" class="form-control" required></div>
                    <div class="form-group"><label>Email Address (Login ID)</label><input type="email" id="prof-email" class="form-control" required></div>
                    <div class="form-group"><label>Phone Number</label><input type="tel" id="prof-phone" class="form-control" required></div>
                </div>
                <h3 style="margin-top:20px;margin-bottom:10px;color:var(--primary);font-size:1rem;">Security & Password</h3>
                <div class="form-grid">
                    <div class="form-group"><label>Current Password</label><input type="password" id="prof-curr-pass" class="form-control"></div>
                    <div class="form-group"><label>New Password</label><input type="password" id="prof-new-pass" class="form-control"></div>
                </div>
                <button type="submit" class="btn" style="margin-top:20px;"><i class="fa-solid fa-floppy-disk"></i> Update Profile</button>
            </form>
        </div>
    </div>
</main>

<script>
const CSRF_TOKEN = '<?= $csrf_token ?>';
const originalFetch = window.fetch;
window.fetch = function(url, options) {
    if (options && options.method && options.method.toUpperCase() === 'POST' && options.body instanceof FormData) {
        options.body.append('csrf_token', CSRF_TOKEN);
    }
    return originalFetch(url, options);
};

function switchTab(id) {
    localStorage.setItem('parent_portal_active_tab', id);
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
    
    if (id === 'dashboard') loadDashboardStats();
    if (id === 'reports') loadReports();
    if (id === 'resources') loadResources();
    if (id === 'students') loadParentStudentsTab();
    if (id === 'profile') loadProfile();
    if (id === 'notifications') { loadNotifications(); loadParentTeachers(); }

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

function loadDashboardStats() {
    fetch('api/api_manage_reports.php')
        .then(r => r.json())
        .then(d => {
            const count = (d.reports || []).filter(r => r.status === 'approved').length;
            const mcReports = document.getElementById('mc-reports-count');
            const badgeAlerts = document.getElementById('badge-date-alerts');
            if (mcReports) mcReports.textContent = count;
            if (badgeAlerts) badgeAlerts.textContent = count;
        });

    fetch('api/api_resources.php?action=all&subject=')
        .then(r => r.json())
        .then(d => {
            const count = (d.resources || []).length;
            const mcResources = document.getElementById('mc-resources-count');
            const badgeBell = document.getElementById('badge-bell-notifs');
            if (mcResources) mcResources.textContent = count;
            if (badgeBell) badgeBell.textContent = count;
        });
}

function showAlert(type, msg) {
    const el = document.getElementById('globalAlert');
    el.className = `alert alert-${type}`; el.innerHTML = msg; el.style.display = 'block';
    setTimeout(() => el.style.display = 'none', 6000);
}

function loadReports() {
    fetch('api/api_manage_reports.php')
        .then(r => r.json())
        .then(d => {
            const tbody = document.getElementById('parent-reports-tbody');
            tbody.innerHTML = '';
            const approved = (d.reports || []).filter(r => r.status === 'approved');
            if (!approved.length) { tbody.innerHTML = '<tr><td colspan="5">No approved reports published yet.</td></tr>'; return; }
            approved.forEach(r => {
                tbody.innerHTML += `<tr>
                    <td><strong>${r.period_identifier}</strong></td>
                    <td>${r.student_name}</td>
                    <td>${r.report_type}</td>
                    <td>${r.student_performance_notes}</td>
                    <td>${r.teacher_recommendations}</td>
                </tr>`;
            });
        });
}

function loadSubjectFilters() {
    return fetch('api/api_resources.php?action=get_subjects')
        .then(r => r.json())
        .then(d => {
            if (d.status === 'success') {
                const sel = document.getElementById('parent-subject-filter');
                if (sel) {
                    const curr = sel.value;
                    sel.innerHTML = '<option value="">All Subjects</option>';
                    (d.subjects || []).forEach(s => sel.innerHTML += `<option value="${s.name}">${s.name}</option>`);
                    sel.value = curr;
                }
            }
        });
}

function loadResources() {
    loadSubjectFilters();
    const subj = document.getElementById('parent-subject-filter')?.value || '';
    fetch('api/api_resources.php?action=all&subject=' + encodeURIComponent(subj))
        .then(r => r.json())
        .then(d => {
            const tbody = document.getElementById('parent-resources-tbody');
            tbody.innerHTML = '';
            if (!d.resources || !d.resources.length) { tbody.innerHTML = '<tr><td colspan="5">No materials available for this filter.</td></tr>'; return; }
            d.resources.forEach(res => {
                tbody.innerHTML += `<tr>
                    <td><strong>${res.title}</strong></td>
                    <td><span style="background:rgba(229,169,59,0.2);color:#B45309;padding:3px 10px;border-radius:12px;font-size:0.8rem;font-weight:700;">${res.subject}</span></td>
                    <td>${res.grade_level}</td>
                    <td>${res.material_type.replace('_', ' ')}</td>
                    <td><a href="${res.file_path}" target="_blank" class="btn" style="padding:5px 12px;font-size:0.8rem;"><i class="fa-solid fa-download"></i> Get File</a></td>
                </tr>`;
            });
        });
}

function loadProfile() {
    fetch('api/api_profile.php?action=get_profile').then(r=>r.json()).then(d => {
        if (d.user) {
            document.getElementById('prof-name').value = d.user.name;
            document.getElementById('prof-email').value = d.user.email;
            document.getElementById('prof-phone').value = d.user.phone;
        }
    });
}

function updateProfile(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('csrf_token', CSRF_TOKEN);
    fd.append('action', 'update_profile');
    fd.append('name', document.getElementById('prof-name').value);
    fd.append('email', document.getElementById('prof-email').value);
    fd.append('phone', document.getElementById('prof-phone').value);
    fd.append('current_password', document.getElementById('prof-curr-pass').value);
    fd.append('new_password', document.getElementById('prof-new-pass').value);
    fetch('api/api_profile.php', { method:'POST', body:fd })
        .then(r=>r.json()).then(d => { showAlert(d.status, d.message); });
}

function loadParentTeachers() {
    const optgroup = document.getElementById('parent-assigned-teachers-optgroup');
    if (!optgroup) return;

    fetch('api/api_notifications.php?action=get_parent_teachers')
        .then(r => r.json())
        .then(d => {
            if (d.status === 'success' && d.teachers) {
                optgroup.innerHTML = '';
                if (!d.teachers.length) {
                    optgroup.innerHTML = '<option value="" disabled>No assigned teachers found</option>';
                    return;
                }
                d.teachers.forEach(t => {
                    const opt = document.createElement('option');
                    opt.value = `teacher_${t.id}`;
                    opt.textContent = `Teacher: ${t.name} (${t.email})`;
                    optgroup.appendChild(opt);
                });
            }
        })
        .catch(err => console.error('Error loading parent teachers:', err));
}

function sendParentMessage(e) {
    e.preventDefault();
    const recipientRaw = document.getElementById('parent-msg-recipient').value;
    const title        = document.getElementById('parent-msg-title').value.trim();
    const message      = document.getElementById('parent-msg-body').value.trim();

    if (!recipientRaw || !title || !message) {
        showAlert('error', 'Please fill in all required fields.');
        return;
    }

    let recipientRole = 'admin';
    let recipientUserId = '';

    if (recipientRaw.startsWith('teacher_')) {
        recipientRole = 'teacher';
        recipientUserId = recipientRaw.replace('teacher_', '');
    } else {
        recipientRole = recipientRaw;
    }

    const fd = new FormData();
    fd.append('action', 'send_notification');
    fd.append('recipient_role', recipientRole);
    if (recipientUserId) {
        fd.append('recipient_user_id', recipientUserId);
    }
    fd.append('title', title);
    fd.append('message', message);

    fetch('api/api_notifications.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            showAlert(data.status, data.message);
            if (data.status === 'success') {
                document.getElementById('form-parent-send-msg').reset();
                loadNotifications();
            }
        })
        .catch(err => {
            console.error(err);
            showAlert('error', 'Failed to send message.');
        });
}

function loadNotifications() {
    const tbody = document.getElementById('parent-notifications-tbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:20px;color:var(--gray-500);"><i class="fa-solid fa-spinner fa-spin"></i> Loading notifications...</td></tr>';
    
    fetch('api/api_notifications.php?action=get_notifications')
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:20px;color:var(--danger);">Failed to load notifications.</td></tr>';
                return;
            }
            const list = data.notifications || [];
            if (!list.length) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:20px;color:var(--gray-400);">No notifications yet.</td></tr>';
                return;
            }
            tbody.innerHTML = list.map(n => `
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding:12px;font-weight:700;color:var(--primary);">${n.sender_name}</td>
                    <td style="padding:12px;font-weight:600;color:#1e293b;">${n.title}</td>
                    <td style="padding:12px;color:#475569;white-space:pre-line;">${n.message}</td>
                    <td style="padding:12px;color:var(--gray-500);font-size:0.8rem;">${n.created_at}</td>
                </tr>
            `).join('');
        })
        .catch(() => {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:20px;color:var(--danger);">Failed to load notifications due to connection error.</td></tr>';
        });
}

function loadParentStudentsTab() {
    const tbody = document.getElementById('parent-students-tbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:20px;color:var(--gray-500);"><i class="fa-solid fa-spinner fa-spin"></i> Loading student profiles...</td></tr>';

    fetch('api/api_parent_students.php?action=list')
        .then(r => r.json())
        .then(d => {
            if (d.status !== 'success') {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:20px;color:var(--danger);">${d.message || 'Error loading students.'}</td></tr>`;
                return;
            }
            window.parentStudentsData = d.students || [];
            if (!d.students.length) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:20px;color:var(--gray-400);">No enrolled students found.</td></tr>';
                return;
            }
            tbody.innerHTML = d.students.map(s => `
                <tr style="border-bottom:1px solid #e2e8f0;">
                    <td style="padding:12px;font-weight:700;color:var(--primary);"><span style="background:rgba(74,14,23,0.1);padding:4px 10px;border-radius:12px;">${s.admission_no || s.staff_id}</span></td>
                    <td style="padding:12px;font-weight:700;color:#1e293b;">${s.name}</td>
                    <td style="padding:12px;">${s.grade_level || '-'}</td>
                    <td style="padding:12px;">${s.dob || '-'}</td>
                    <td style="padding:12px;">${s.nationality || '-'}</td>
                    <td style="padding:12px;">${s.first_language || '-'}</td>
                    <td style="padding:12px;">
                        <button class="btn btn-outline btn-sm" onclick="openEditStudentModal(${s.profile_id})"><i class="fa-solid fa-pen-to-square"></i> Edit</button>
                    </td>
                </tr>
            `).join('');
        })
        .catch(err => {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:20px;color:var(--danger);">Connection error loading students.</td></tr>';
        });
}

function openEditStudentModal(profileId) {
    const student = (window.parentStudentsData || []).find(s => s.profile_id == profileId);
    if (!student) return;
    const newName = prompt("Edit Student Name:", student.name);
    if (newName === null) return;
    const newGrade = prompt("Edit Current Grade / Level:", student.grade_level || '');
    if (newGrade === null) return;
    const newNat = prompt("Edit Student Nationality:", student.nationality || '');
    if (newNat === null) return;
    const newLang = prompt("Edit First Language:", student.first_language || '');
    if (newLang === null) return;
    const newDob = prompt("Edit Date of Birth (YYYY-MM-DD):", student.dob || '');
    if (newDob === null) return;

    const fd = new FormData();
    fd.append('action', 'edit');
    fd.append('profile_id', profileId);
    fd.append('name', newName);
    fd.append('grade', newGrade);
    fd.append('nationality', newNat);
    fd.append('first_language', newLang);
    fd.append('dob', newDob);

    fetch('api/api_parent_students.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            showAlert(d.status, d.message);
            if (d.status === 'success') loadParentStudentsTab();
        });
}

window.onload = () => {
    loadDashboardStats();
    loadParentTeachers();
    const savedTab = (new URLSearchParams(window.location.search).get('fresh') === '1')
        ? (() => { localStorage.removeItem('parent_portal_active_tab'); return 'dashboard'; })()
        : (localStorage.getItem('parent_portal_active_tab') || 'dashboard');
    switchTab(savedTab);
};


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
