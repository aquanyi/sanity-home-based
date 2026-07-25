<?php
header('Content-Type: text/html; charset=utf-8');
/**
 * teacher_portal.php
 * Portal interface for Teachers (Module 4 & Module 5 & Module 6).
 */
require_once 'security.php';
start_secure_session();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !in_array($_SESSION['user_role'] ?? '', ['teacher', 'admin'])) {
    header('Location: login.html?error=Please+log+in+with+a+Teacher+account#teachers');
    exit;
}
// Generate CSRF token and save it in the session before closing the session
$csrf_token = generate_csrf_token();
session_write_close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S.H.T.A – Teacher Portal</title>
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
        .sidebar { width: var(--sidebar-w); background: linear-gradient(180deg, var(--dark) 0%, var(--primary) 100%); display: flex; flex-direction: column; padding: 25px 15px; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-logo { text-align: center; padding-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; }
        .sidebar-logo img { height: 50px; margin-bottom: 8px; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 15px; color: rgba(255,255,255,0.75); border-radius: 10px; cursor: pointer; transition: all 0.25s; margin-bottom: 3px; font-weight: 500; }
        .nav-item:hover { background: rgba(255,255,255,0.08); color: var(--white); }
        .nav-item.active { background: rgba(229,169,59,0.15); color: var(--accent); border-left: 3px solid var(--accent); }
        .main { margin-left: var(--sidebar-w); flex: 1; padding: 30px 35px; }
        .panel { background: var(--white); border-radius: 16px; padding: 28px; border: 1px solid rgba(74,14,23,0.05); box-shadow: 0 5px 20px rgba(0,0,0,0.03); margin-bottom: 25px; }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid var(--cream); }
        .panel-header h2 { font-size: 1.15rem; color: var(--primary); font-weight: 700; }
        .section { display: none; } .section.active { display: block; }
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; } .table-wrap table { min-width: 480px; } table { width: 100%; border-collapse: collapse; }
        th { background: var(--cream); color: var(--primary); padding: 12px; text-align: left; font-size: 0.85rem; }
        td { padding: 12px; font-size: 0.9rem; border-bottom: 1px solid var(--gray-200); vertical-align: top; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 18px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        label { font-size: 0.82rem; font-weight: 700; color: var(--primary); }
        .form-control { width: 100%; padding: 10px 14px; border: 2px solid var(--gray-200); border-radius: 8px; font-size: 0.9rem; outline: none; transition: border 0.2s; }
        .form-control:focus { border-color: var(--primary); }
        .btn { padding: 10px 18px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; background: var(--primary); color: white; font-size: 0.88rem; transition: opacity 0.2s; display: inline-flex; align-items: center; gap: 7px; }
        .btn:hover { opacity: 0.88; }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; }
        .btn-success { background: var(--success); }
        .btn-outline { background: transparent; border: 2px solid var(--primary); color: var(--primary); }
        .btn-outline:hover { background: var(--primary); color: white; }
        .btn-accent { background: var(--accent); color: var(--dark); }
        .alert { padding: 12px 18px; border-radius: 10px; margin-bottom: 20px; display: none; font-size: 0.9rem; }
        .alert-success { background: #D1FAE5; color: #065F46; } .alert-error { background: #FEE2E2; color: #991B1B; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
        .badge-published { background: #D1FAE5; color: #065F46; }
        .badge-pending-grade { background: #FEF3C7; color: #92400E; }

        /* Modal Styles */
        .modal-bg { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(5px); z-index: 200; display: flex; align-items: center; justify-content: center; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
        .modal-bg.open { opacity: 1; pointer-events: auto; }
        .modal-box { background: var(--white); border-radius: 18px; padding: 35px; width: 100%; max-width: 580px; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
        .modal-header { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid var(--cream); }
        .modal-header h3 { font-size: 1.3rem; color: var(--primary); font-weight: 700; }

        /* Timetable grid */
        .tt-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px; margin-top: 15px; }
        .tt-day-col { background: var(--cream); border-radius: 12px; padding: 12px; min-height: 250px; border: 1px solid rgba(74,14,23,0.04); }
        .tt-day-hdr { text-align: center; font-weight: 700; color: var(--primary); font-size: 0.85rem; padding-bottom: 8px; border-bottom: 1.5px solid var(--gray-200); margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        .tt-card { background: white; border-radius: 8px; padding: 8px 10px; margin-bottom: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.04); border-left: 4px solid var(--accent); font-size: 0.78rem; transition: transform 0.2s; }
        .tt-card:hover { transform: translateY(-2px); }
        .tt-time { font-weight: 800; color: var(--primary); margin-bottom: 3px; }
        .tt-details { color: var(--gray-600); line-height: 1.3; }
        @media (max-width: 1024px) { .tt-grid { grid-template-columns: 1fr; } .tt-day-col { min-height: auto; margin-bottom: 15px; } }

        /* Exam tab specifics */
        .exam-step-bar { display: flex; gap: 10px; margin-bottom: 24px; flex-wrap: wrap; }
        .exam-step { background: rgba(74,14,23,0.06); border-radius: 10px; padding: 10px 18px; font-size: 0.85rem; color: var(--primary); font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .exam-step .step-num { width: 24px; height: 24px; border-radius: 50%; background: var(--primary); color: white; font-size: 0.75rem; font-weight: 800; display: flex; align-items: center; justify-content: center; }
        .marks-input { width: 80px; padding: 6px 10px; border: 2px solid var(--gray-200); border-radius: 6px; font-size: 0.9rem; text-align: center; }
        .marks-input:focus { border-color: var(--accent); outline: none; }
        .remarks-input { width: 100%; padding: 6px 10px; border: 2px solid var(--gray-200); border-radius: 6px; font-size: 0.82rem; resize: vertical; min-height: 52px; }
        .remarks-input:focus { border-color: var(--accent); outline: none; }
        .results-table th { background: var(--primary); color: white; }
        .results-table td { font-size: 0.85rem; }
        .grade-pill { display: inline-block; padding: 2px 10px; border-radius: 12px; font-weight: 800; font-size: 0.8rem; background: rgba(74,14,23,0.1); color: var(--primary); }
        .grade-pill.A { background: #D1FAE5; color: #065F46; }
        .grade-pill.B { background: #DBEAFE; color: #1E40AF; }
        .grade-pill.C { background: #FEF3C7; color: #92400E; }
        .grade-pill.D { background: #FEE2E2; color: #991B1B; }
        .no-data-msg { text-align: center; padding: 40px; color: var(--gray-600); font-size: 0.9rem; }        /* Mobile Top Bar */
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
            .modal-box { padding: 20px 15px; }
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
        <p style="color:var(--accent);font-size:0.8rem;font-weight:700;">TEACHER PORTAL</p>
        <div style="margin-top:10px;padding:8px;background:rgba(255,255,255,0.07);border-radius:8px;font-size:0.8rem;color:white;">
            <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong>
        </div>
    </div>
    <!-- Message Center -->
    <div class="nav-category-wrap">
        <div class="nav-category-header" onclick="toggleCategoryMenu(this)">
            <i class="fa-solid fa-grip"></i>
            <span>Message Center</span>
            
        </div>
        <div class="nav-category-submenu">
            <a href="javascript:void(0)" onclick="switchTab('dashboard')" class="submenu-item">Home Dashboard</a>
            <a href="javascript:void(0)" onclick="switchTab('notifications')" class="submenu-item">Notifications <span class="nav-badge" id="badge-notifs" style="background:var(--danger);color:white;font-size:0.7rem;padding:2px 7px;border-radius:10px;margin-left:auto;font-weight:700;display:none;">0</span></a>
        </div>
    </div>

    <!-- Lessons & Timetable -->
    <div class="nav-category-wrap">
        <div class="nav-category-header" onclick="toggleCategoryMenu(this)">
            <i class="fa-solid fa-grip"></i>
            <span>Lessons &amp; Timetable</span>
            
        </div>
        <div class="nav-category-submenu">
            <a href="javascript:void(0)" onclick="switchTab('classes')" class="submenu-item">My Lessons &amp; Attendance</a>
            <a href="javascript:void(0)" onclick="switchTab('timetable')" class="submenu-item">Weekly Timetable</a>
            <a href="javascript:void(0)" onclick="switchTab('ledger')" class="submenu-item">My Session Ledger</a>
        </div>
    </div>

    <!-- Academics & Reports -->
    <div class="nav-category-wrap">
        <div class="nav-category-header" onclick="toggleCategoryMenu(this)">
            <i class="fa-solid fa-grip"></i>
            <span>Academics &amp; Reports</span>
            
        </div>
        <div class="nav-category-submenu">
            <a href="javascript:void(0)" onclick="switchTab('exams')" class="submenu-item">Exams &amp; Results</a>
            <a href="javascript:void(0)" onclick="switchTab('reports')" class="submenu-item">Submit Reports</a>
        </div>
    </div>

    <!-- My Profile -->
    <div class="nav-category-wrap">
        <div class="nav-category-header" onclick="toggleCategoryMenu(this)">
            <i class="fa-solid fa-grip"></i>
            <span>My Profile</span>
            
        </div>
        <div class="nav-category-submenu">
            <a href="javascript:void(0)" onclick="switchTab('profile')" class="submenu-item">My Profile</a>
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
            <div class="info-badge-item" onclick="switchTab('classes')" title="My Lessons">
                <i class="fa-solid fa-chalkboard-user"></i>
                <span class="info-badge-count" id="badge-lessons-count">0</span>
            </div>
            <div class="info-badge-item" onclick="switchTab('notifications')" title="Notifications">
                <i class="fa-solid fa-bell"></i>
                <span class="info-badge-count" id="badge-bell-notifs">0</span>
            </div>
            <div class="info-badge-item" onclick="switchTab('timetable')" title="Weekly Timetable">
                <i class="fa-solid fa-calendar-days"></i>
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
            <p style="color:var(--gray-600);margin-bottom:20px;">Access your lesson portals, calendar agendas, student exams, and profile details below.</p>
            
            <!-- Kenyatta University Message Center Style Card -->
            <div class="message-center-card">
                <div class="mc-header">Message Center</div>
                <div class="mc-list">
                    <a href="javascript:void(0)" onclick="switchTab('classes')" class="mc-item">
                        <div class="mc-icon-wrap">
                            <i class="fa-solid fa-chalkboard-user"></i>
                        </div>
                        <span class="mc-number" id="mc-lessons-count">0</span>
                    </a>
                    <a href="javascript:void(0)" onclick="switchTab('notifications')" class="mc-item">
                        <div class="mc-icon-wrap">
                            <i class="fa-solid fa-bell"></i>
                        </div>
                        <span class="mc-number" id="mc-notifs-count">0</span>
                    </a>
                    <a href="javascript:void(0)" onclick="switchTab('timetable')" class="mc-item">
                        <div class="mc-icon-wrap">
                            <i class="fa-solid fa-calendar-days"></i>
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
            <p>Send messages to Accounts, Academic Operations Coordinator, or Admin, and review school announcements.</p>
        </div>

        <!-- Send Message Panel -->
        <div class="panel" style="margin-bottom:24px;">
            <div class="panel-header">
                <h2><i class="fa-solid fa-paper-plane" style="color:var(--accent);"></i> Send a Message / Request</h2>
            </div>
            <form id="form-teacher-send-msg" onsubmit="sendTeacherMessage(event)">
                <div class="form-grid" style="grid-template-columns: 1fr 2fr; gap:20px;">
                    <div class="form-group">
                        <label style="font-weight:600; font-size:0.9rem;">Send To (Recipient) <span style="color:red;">*</span></label>
                        <div style="position:relative;">
                            <i class="fa-solid fa-user-gear" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--gray-400);"></i>
                            <select id="teacher-msg-recipient" name="recipient_role" class="form-control" required style="padding-left:40px;">
                                <option value="admin">Admin (Principal)</option>
                                <option value="timetabler">Academic Operations Coordinator (Scheduling)</option>
                                <option value="accounts">Accounts Officer</option>
                                <option value="all">Send to All (School-Wide)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label style="font-weight:600; font-size:0.9rem;">Subject / Title <span style="color:red;">*</span></label>
                        <div style="position:relative;">
                            <i class="fa-solid fa-heading" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--gray-400);"></i>
                            <input type="text" id="teacher-msg-title" name="title" class="form-control" required placeholder="e.g. Session Rate Query / Timetable Adjustment" style="padding-left:40px;">
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-top:16px;">
                    <label style="font-weight:600; font-size:0.9rem;">Message Content <span style="color:red;">*</span></label>
                    <textarea id="teacher-msg-body" name="message" class="form-control" rows="4" required placeholder="Write your message here..."></textarea>
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
                <div style="display:flex;gap:10px;">
                    <button class="btn btn-outline btn-sm" onclick="loadNotifications()"><i class="fa-solid fa-rotate-right"></i> Refresh Feed</button>
                    <button class="btn btn-danger btn-sm" onclick="clearAllNotifications()"><i class="fa-solid fa-trash-can"></i> Clear All</button>
                </div>
            </div>
            <div id="notif-feed">
                <div class="no-data-msg">Loading notifications…</div>
            </div>
        </div>
    </div>

    <!-- LESSONS & ATTENDANCE -->
    <div id="section-classes" class="section">
        <div class="panel">
            <div class="panel-header"><h2>Assigned Lessons &amp; Check-In</h2></div>
            <p style="color:var(--gray-600);margin-bottom:15px;font-size:0.9rem;">Click "Start Lesson" to check in and notify the parent and admin. Check out at the end of the lesson to file your session progress report.</p>
            <div class="table-wrap">
                <table><thead><tr><th>Date/Time</th><th>Student</th><th>Parent Contact</th><th>Venue</th><th>Status</th><th>Action</th></tr></thead>
                <tbody id="teacher-lessons-tbody"><tr><td colspan="6">Loading lessons…</td></tr></tbody></table>
            </div>
        </div>
    </div>

    <!-- SESSION LEDGER -->
    <div id="section-ledger" class="section">
        <div class="page-header">
            <h1>Session Ledger</h1>
            <p>View and export your completed lesson sessions and check-in history.</p>
        </div>
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fa-solid fa-book-journal-whills" style="color:var(--accent);"></i> My Session Log</h2>
                <button class="btn btn-outline btn-sm" onclick="exportLedgerCSV()"><i class="fa-solid fa-file-csv"></i> Export CSV</button>
            </div>
            
            <div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;align-items:flex-end;">
                <div class="form-group" style="min-width:180px;">
                    <label>Filter by Month</label>
                    <input type="month" id="ledger-month" class="form-control" onchange="renderSessionLedger()">
                </div>
                <div class="form-group" style="min-width:160px;">
                    <label>Date From</label>
                    <input type="date" id="ledger-from" class="form-control" onchange="renderSessionLedger()">
                </div>
                <div class="form-group" style="min-width:160px;">
                    <label>Date To</label>
                    <input type="date" id="ledger-to" class="form-control" onchange="renderSessionLedger()">
                </div>
                <div class="form-group" style="min-width:160px;">
                    <label>Status</label>
                    <select id="ledger-status" class="form-control" onchange="renderSessionLedger()">
                        <option value="">All Statuses</option>
                        <option value="completed" selected>Completed ✓</option>
                        <option value="in_progress">In Progress</option>
                        <option value="scheduled">Scheduled</option>
                    </select>
                </div>
                <button class="btn btn-outline" onclick="resetLedgerFilters()" style="margin-bottom:2px;"><i class="fa-solid fa-rotate-left"></i> Reset</button>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Student</th>
                            <th>Subject</th>
                            <th>Venue</th>
                            <th>Check-In</th>
                            <th>Check-Out</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="ledger-tbody">
                        <tr><td colspan="7" class="empty-row">Loading ledger...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- WEEKLY TIMETABLE -->
    <div id="section-timetable" class="section">
        <div class="panel">
            <div class="panel-header">
                <h2>My Weekly Timetable</h2>
                <div style="display:flex;gap:10px;align-items:center;">
                    <div style="font-size:0.85rem;color:var(--gray-600);font-weight:600;"><i class="fa-solid fa-circle-info"></i> Recurring weekly slots</div>
                    <button class="btn btn-sm btn-outline" onclick="printMyTimetable()"><i class="fa-solid fa-file-pdf"></i> Download Timetable</button>
                </div>
            </div>
            <div class="tt-grid">
                <div class="tt-day-col" data-day="Monday"><div class="tt-day-hdr">Mon</div><div class="tt-slots-container"></div></div>
                <div class="tt-day-col" data-day="Tuesday"><div class="tt-day-hdr">Tue</div><div class="tt-slots-container"></div></div>
                <div class="tt-day-col" data-day="Wednesday"><div class="tt-day-hdr">Wed</div><div class="tt-slots-container"></div></div>
                <div class="tt-day-col" data-day="Thursday"><div class="tt-day-hdr">Thu</div><div class="tt-slots-container"></div></div>
                <div class="tt-day-col" data-day="Friday"><div class="tt-day-hdr">Fri</div><div class="tt-slots-container"></div></div>
                <div class="tt-day-col" data-day="Saturday"><div class="tt-day-hdr">Sat</div><div class="tt-slots-container"></div></div>
                <div class="tt-day-col" data-day="Sunday"><div class="tt-day-hdr">Sun</div><div class="tt-slots-container"></div></div>
            </div>
        </div>
    </div>

    <!-- ═════════════════════════════════════════
         EXAMS & RESULTS TAB
    ═════════════════════════════════════════ -->
    <div id="section-exams" class="section">
        <!-- Step bar -->
        <div class="exam-step-bar">
            <div class="exam-step"><span class="step-num">1</span> Select Exam</div>
            <div style="align-self:center;color:var(--gray-600);">→</div>
            <div class="exam-step"><span class="step-num">2</span> Select Subject / Session</div>
            <div style="align-self:center;color:var(--gray-600);">→</div>
            <div class="exam-step"><span class="step-num">3</span> Enter Marks &amp; Remarks</div>
        </div>

        <!-- Step 1 & 2 selectors -->
        <div class="panel">
            <div class="panel-header"><h2><i class="fa-solid fa-file-pen" style="color:var(--accent);"></i> Enter Student Marks</h2></div>
            <div class="form-grid" style="margin-bottom:20px;">
                <div class="form-group">
                    <label>Exam Series</label>
                    <select id="te-exam" class="form-control" onchange="loadAssignedStudents()">
                        <option value="">Select exam…</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Select Student</label>
                    <select id="te-student" class="form-control" onchange="loadStudentExamSessions()">
                        <option value="">Select student…</option>
                    </select>
                </div>
            </div>

            <!-- Mark entry table -->
            <div id="marks-entry-container" style="display:none;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <h3 style="font-size:1rem;color:var(--primary);font-weight:700;" id="marks-entry-title">Student Marks</h3>
                    <button class="btn btn-success btn-sm" onclick="saveAllMarks()"><i class="fa-solid fa-floppy-disk"></i> Save All Marks</button>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Subject / Session</th>
                                <th>Exam Date</th>
                                <th>Marks (out of 100)</th>
                                <th>Teacher Remarks</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="marks-entry-tbody">
                            <tr><td colspan="6" class="no-data-msg">Select an exam and student above to load subjects.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="marks-entry-placeholder" style="text-align:center;padding:30px;color:var(--gray-600);font-size:0.9rem;">
                <i class="fa-solid fa-arrow-up" style="font-size:1.5rem;margin-bottom:10px;display:block;"></i>
                Select an exam series and subject above to start entering marks.
            </div>
        </div>

        <!-- Results view -->
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fa-solid fa-table-list" style="color:var(--accent);"></i> Exam Results Overview</h2>
                <div style="display:flex;gap:10px;align-items:center;">
                    <select id="te-results-exam" class="form-control" style="width:220px;" onchange="loadExamResults()">
                        <option value="">Select exam to view results…</option>
                    </select>
                    <button class="btn btn-sm btn-outline" onclick="printResultsView()"><i class="fa-solid fa-print"></i> Print</button>
                </div>
            </div>
            <div id="results-view-container">
                <div class="no-data-msg"><i class="fa-solid fa-chart-bar" style="font-size:2rem;margin-bottom:10px;display:block;opacity:0.3;"></i>Select an exam above to view the full results matrix.</div>
            </div>
        </div>
    </div>

    <!-- SUBMIT REPORT -->
    <div id="section-reports" class="section">
        <div class="panel">
            <div class="panel-header"><h2>Submit Academic Report</h2></div>
            <form onsubmit="submitReport(event)">
                <div class="form-grid">
                    <div class="form-group"><label>Select Student</label><select id="rep-student" class="form-control" required></select></div>
                    <div class="form-group"><label>Report Type</label><select id="rep-type" class="form-control"><option value="weekly">Weekly Report</option><option value="terminal">Monthly / Terminal</option><option value="termly">Termly Report</option><option value="yearly">Yearly Report</option></select></div>
                    <div class="form-group"><label>Period Identifier</label><input type="text" id="rep-period" class="form-control" placeholder="e.g. Term 2 Week 4" required></div>
                </div>
                <div class="form-group" style="margin-top:15px;"><label>Topics Covered</label><textarea id="rep-topics" class="form-control" rows="3" required></textarea></div>
                <div class="form-group" style="margin-top:15px;"><label>Student Performance Notes</label><textarea id="rep-perf" class="form-control" rows="3" required></textarea></div>
                <div class="form-group" style="margin-top:15px;"><label>Teacher Recommendations</label><textarea id="rep-recs" class="form-control" rows="3" required></textarea></div>
                <button type="submit" class="btn" style="margin-top:20px;"><i class="fa-solid fa-paper-plane"></i> Submit for Admin Review</button>
            </form>
        </div>
    </div>

    <!-- PROFILE -->
    <div id="section-profile" class="section">
        <div class="panel">
            <div class="panel-header"><h2>My Account Settings</h2></div>
            <form onsubmit="updateProfile(event)">
                <div class="form-grid">
                    <div class="form-group"><label>Full Name</label><input type="text" id="prof-name" class="form-control" required></div>
                    <div class="form-group"><label>Email Address</label><input type="email" id="prof-email" class="form-control" required></div>
                    <div class="form-group"><label>Phone Number</label><input type="tel" id="prof-phone" class="form-control" required></div>
                </div>
                <h3 style="margin-top:25px;margin-bottom:8px;color:var(--primary);font-size:1.05rem;"><i class="fa-solid fa-book-bookmark"></i> My Teaching Subjects</h3>
                <p style="font-size:0.88rem;color:var(--gray-600);margin-bottom:15px;">Your currently registered teaching subjects are displayed below. You can drop any subject using the <strong>✕</strong> icon, or search and select new subjects from the dropdown list below.</p>
                
                <!-- Active Subject Tag Pills Container -->
                <div style="background:var(--cream);padding:18px;border-radius:12px;border:1px solid var(--gray-200);margin-bottom:15px;">
                    <label style="display:block;font-size:0.85rem;font-weight:700;color:var(--primary);margin-bottom:10px;">Registered Teaching Subjects:</label>
                    <div id="teacher-active-subjects-tags" style="display:flex;flex-wrap:wrap;gap:8px;min-height:38px;align-items:center;">
                        <span style="color:var(--gray-600);font-size:0.85rem;font-style:italic;">Loading active subjects...</span>
                    </div>
                </div>

                <!-- Search & Add Subject Input Dropdown -->
                <div style="background:#ffffff;padding:16px;border-radius:12px;border:1px solid var(--gray-200);margin-bottom:25px;">
                    <label style="display:block;font-size:0.85rem;font-weight:700;color:var(--gray-600);margin-bottom:6px;">Add New Subject:</label>
                    <div style="position:relative;max-width:480px;">
                        <div style="display:flex;align-items:center;position:relative;">
                            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:12px;color:var(--gray-400);font-size:0.9rem;"></i>
                            <input type="text" id="subject-search-input" class="form-control" placeholder="Search system subjects (e.g. Mathematics, Biology)..." oninput="filterSubjectDropdown()" onfocus="showSubjectDropdown()" style="padding-left:36px;border-radius:8px;">
                        </div>
                        <div id="subject-search-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;max-height:220px;overflow-y:auto;background:#ffffff;border:1px solid var(--gray-300);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,0.12);z-index:999;margin-top:4px;">
                            <!-- Dynamically populated options -->
                        </div>
                    </div>
                </div>

                <h3 style="margin-top:20px;margin-bottom:10px;color:var(--primary);font-size:1rem;">Change Password</h3>
                <div class="form-grid">
                    <div class="form-group"><label>Current Password</label><input type="password" id="prof-curr-pass" class="form-control"></div>
                    <div class="form-group"><label>New Password</label><input type="password" id="prof-new-pass" class="form-control"></div>
                </div>
                <button type="submit" class="btn" style="margin-top:20px;"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
            </form>
        </div>
    </div>
</main>

<!-- MODAL: Enter OTP -->
<div class="modal-bg" id="otpModal">
    <div class="modal-box" style="max-width:400px;text-align:center;">
        <div class="modal-header"><h3>Verify Parent OTP</h3></div>
        <form onsubmit="submitOtp(event)">
            <input type="hidden" id="otp-lesson-id">
            <p style="font-size:0.88rem;color:var(--gray-600);margin-bottom:15px;">Enter the 6-digit OTP sent to the parent's registered email to check in:</p>
            <input type="text" id="otp-code" class="form-control" required placeholder="######" maxlength="6" style="font-size:1.8rem;letter-spacing:8px;text-align:center;font-weight:700;margin-bottom:20px;padding:10px;">
            <div style="display:flex;justify-content:center;gap:10px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('otpModal')">Cancel</button>
                <button type="submit" class="btn"><i class="fa-solid fa-unlock-keyhole"></i> Verify &amp; Check In</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: End Lesson (Check-out) -->
<div class="modal-bg" id="endLessonModal">
    <div class="modal-box">
        <div class="modal-header"><h3><i class="fa-solid fa-circle-check" style="color:var(--success);margin-right:8px;"></i>End Lesson &amp; Session Log</h3></div>
        <form onsubmit="submitEndLesson(event)">
            <input type="hidden" id="end-lesson-id">
            <p style="font-size:0.88rem;color:var(--gray-600);margin-bottom:15px;">Fill in the mandatory session notes to check out and complete the lesson.</p>
            <div class="form-group" style="margin-bottom:15px;">
                <label>Topics Covered</label>
                <textarea id="end-topics" class="form-control" rows="3" required placeholder="e.g. Introduction to Quadratic Equations, solving by factoring..."></textarea>
            </div>
            <div class="form-group" style="margin-bottom:15px;">
                <label>Student Progress &amp; Performance Notes</label>
                <textarea id="end-progress" class="form-control" rows="3" required placeholder="e.g. Daniel understood factoring quickly but needs practice with negative signs..."></textarea>
            </div>
            <div class="form-group" style="margin-bottom:15px;">
                <label>Homework &amp; Assignments Assigned</label>
                <textarea id="end-homework" class="form-control" rows="3" required placeholder="e.g. Page 45, Exercise 2B questions 1 to 10..."></textarea>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('endLessonModal')">Cancel</button>
                <button type="submit" class="btn btn-success"><i class="fa-solid fa-flag-checkered"></i> Complete Lesson &amp; Check-Out</button>
            </div>
        </form>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?= $csrf_token ?>';
const originalFetch = window.fetch;
window.fetch = async function(url, options) {
    if (options && options.method && options.method.toUpperCase() === 'POST' && options.body instanceof FormData) {
        options.body.append('csrf_token', CSRF_TOKEN);
    }
    
    const response = await originalFetch(url, options);
    
    if (response.status === 401) {
        window.location.href = 'login.html?error=Your+session+has+expired.+Please+log+in+again.#teachers';
        return response;
    }
    
    const clone = response.clone();
    try {
        const data = await clone.json();
        if (data && data.message === 'session_expired') {
            window.location.href = 'login.html?error=Your+session+has+expired.+Please+log+in+again.#teachers';
            return response;
        }
    } catch (e) {}
    
    return response;
};

const teacherId = <?php echo $_SESSION['user_id']; ?>;
let teacherExams = [];
let teacherSessions = [];
let currentSessionStudents = [];
let currentSessionId = null;

function switchTab(id) {
    localStorage.setItem('teacher_portal_active_tab', id);
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

    localStorage.setItem('teacher_portal_active_tab', id);

    if (id === 'dashboard') loadDashboardStats();
    if (id === 'classes') loadLessons();
    if (id === 'timetable') loadWeeklyTimetable();
    if (id === 'ledger') loadSessionLedger();
    if (id === 'exams') loadTeacherExams();
    if (id === 'reports') loadStudentsDropdown();
    if (id === 'notifications') loadNotifications();
    if (id === 'profile') loadProfile();

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

// ─────────────────────────────────────────────
// EXAMS TAB
// ─────────────────────────────────────────────
function loadTeacherExams() {
    ['te-exam', 'te-results-exam'].forEach(id => {
        const sel = document.getElementById(id);
        if (sel) sel.innerHTML = '<option value="">Loading...</option>';
    });
    fetch('api/api_manage_academic.php?action=teacher_exams')
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') throw new Error(data.message || 'Error');
            teacherExams    = data.exams    || [];
            teacherSessions = data.sessions || [];

            ['te-exam', 'te-results-exam'].forEach(id => {
                const sel = document.getElementById(id);
                if (!sel) return;
                sel.innerHTML = `<option value="">${id === 'te-exam' ? 'Select exam...' : 'Select exam to view results...'}</option>`;
                if (!teacherExams.length) sel.innerHTML = '<option value="">No exams available</option>';
                teacherExams.forEach(ex => {
                    sel.innerHTML += `<option value="${ex.id}">${ex.exam_name} (${ex.term_identifier} ${ex.academic_year})</option>`;
                });
            });
        })
        .catch(err => {
            console.error('Fetch error:', err);
            ['te-exam', 'te-results-exam'].forEach(id => {
                const sel = document.getElementById(id);
                if (sel) sel.innerHTML = '<option value="">Error loading data.</option>';
            });
        });
}

let currentSelectedStudentId = null;
let currentStudentSessions = [];

function loadAssignedStudents() {
    const examId = document.getElementById('te-exam').value;
    const sel = document.getElementById('te-student');
    sel.innerHTML = '<option value="">Loading...</option>';
// sel.innerHTML = '<option value="">Select student…</option>';
    document.getElementById('marks-entry-container').style.display = 'none';
    document.getElementById('marks-entry-placeholder').style.display = 'block';
    currentSelectedStudentId = null;
    currentStudentSessions = [];
    if (!examId) return;

    fetch('api/api_manage_academic.php?action=teacher_assigned_students')
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') { showAlert('error', data.message); return; }
            (data.students || []).forEach(s => {
                const adm = s.admission_no || s.staff_id || 'S000A';
                sel.innerHTML += `<option value="${s.student_id}">${s.student_name} (${adm} - ${s.grade_level})</option>`;
            });
        });
}

function loadStudentExamSessions() {
    const studentId = document.getElementById('te-student').value;
    const examId = document.getElementById('te-exam').value;
    if (!studentId || !examId) {
        document.getElementById('marks-entry-container').style.display = 'none';
        document.getElementById('marks-entry-placeholder').style.display = 'block';
        return;
    }
    currentSelectedStudentId = studentId;
    
    const studName = document.getElementById('te-student').options[document.getElementById('te-student').selectedIndex].text;
    document.getElementById('marks-entry-title').textContent = `Enter Marks for ${studName}`;

    fetch(`api/api_manage_academic.php?action=student_exam_sessions&student_id=${studentId}&exam_id=${examId}`)
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') { showAlert('error', data.message); return; }
            currentStudentSessions = data.sessions || [];
            renderMarksEntryTable(currentStudentSessions);
            document.getElementById('marks-entry-container').style.display = 'block';
            document.getElementById('marks-entry-placeholder').style.display = 'none';
        });
}

function renderMarksEntryTable(sessions) {
    const tbody = document.getElementById('marks-entry-tbody');
    if (!sessions.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="no-data-msg">No exam sessions scheduled for this student. Contact Admin to schedule exam sessions.</td></tr>';
        return;
    }
    tbody.innerHTML = sessions.map((s, i) => `
        <tr id="session-row-${s.id}">
            <td style="color:var(--gray-600);font-weight:700;">${i+1}</td>
            <td><strong>${s.subject}</strong><br><span style="font-size:0.75rem;color:var(--gray-500);">${s.room_number || ''}</span></td>
            <td><span style="font-size:0.8rem;color:var(--gray-600);">${s.exam_date}</span></td>
            <td>
                <input type="number" class="marks-input" id="marks-${s.id}"
                    value="${s.marks_obtained !== '' && s.marks_obtained !== null ? s.marks_obtained : ''}"
                    min="0" max="100" step="0.5" placeholder="0–100">
            </td>
            <td>
                <textarea class="remarks-input" id="remarks-${s.id}"
                    placeholder="Optional: performance feedback…">${s.teacher_remarks || ''}</textarea>
            </td>
            <td>
                ${s.is_published == 1
                    ? '<span class="badge badge-published">Approved ✓</span>'
                    : '<span class="badge badge-pending-grade">Pending Approval</span>'}
            </td>
        </tr>
    `).join('');
}

function saveAllMarks() {
    if (!currentSelectedStudentId || !currentStudentSessions.length) return;
    const promises = currentStudentSessions.map(s => {
        const marksEl   = document.getElementById(`marks-${s.id}`);
        const remarksEl = document.getElementById(`remarks-${s.id}`);
        if (!marksEl || marksEl.value === '') return Promise.resolve();

        const fd = new FormData();
        fd.append('action', 'submit_marks');
        fd.append('exam_session_id', s.id);
        fd.append('student_id', currentSelectedStudentId);
        fd.append('marks_obtained', marksEl.value);
        fd.append('teacher_remarks', remarksEl ? remarksEl.value : '');
        return fetch('api/api_manage_academic.php', { method: 'POST', body: fd }).then(r => r.json());
    });

    Promise.all(promises).then(results => {
        const errors = results.filter(r => r && r.status === 'error');
        if (errors.length) {
            showAlert('error', `${errors.length} mark(s) failed to save. Check entries and try again.`);
        } else {
            showAlert('success', '✅ Marks saved to staging successfully! Pending Admin approval.');
            loadStudentExamSessions(); // refresh to show status updates
        }
    });
}

function loadExamResults() {
    const examId = document.getElementById('te-results-exam').value;
    const container = document.getElementById('results-view-container');
    if (!examId) {
        container.innerHTML = '<div class="no-data-msg"><i class="fa-solid fa-chart-bar" style="font-size:2rem;margin-bottom:10px;display:block;opacity:0.3;"></i>Select an exam above to view the full results matrix.</div>';
        return;
    }
    container.innerHTML = '<div class="no-data-msg"><i class="fa-solid fa-spinner fa-spin"></i> Loading results…</div>';

    fetch(`api/api_manage_academic.php?action=exam_results&exam_id=${examId}`)
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') {
                container.innerHTML = `<div class="no-data-msg" style="color:var(--danger);">${data.message}</div>`;
                return;
            }
            const sessions = data.sessions || [];
            const students = data.students || [];
            if (!students.length) {
                container.innerHTML = '<div class="no-data-msg">No results entered yet for this exam.</div>';
                return;
            }

            const subjects = sessions.map(s => s.subject);
            let html = `
                <div style="overflow-x:auto;">
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Level</th>
                            ${subjects.map(s => `<th>${s}</th>`).join('')}
                            <th>Total</th>
                            <th>Average</th>
                            <th>Grade</th>
                        </tr>
                    </thead>
                    <tbody>`;

            students.forEach(stu => {
                const subjectMap = {};
                stu.subjects.forEach(sub => { subjectMap[sub.subject] = sub; });
                const adm = stu.admission_no || stu.staff_id || 'S000A';
                html += `<tr>
                    <td><strong>${stu.student_name}</strong></td>
                    <td style="font-size:0.8rem;color:var(--gray-600);">${stu.grade_level}</td>
                    ${subjects.map(sub => {
                        const r = subjectMap[sub];
                        return r
                            ? `<td>${r.marks_obtained}<br><span class="grade-pill ${r.grade_letter}">${r.grade_letter}</span></td>`
                            : `<td style="color:var(--gray-600);">—</td>`;
                    }).join('')}
                    <td><strong>${stu.total}</strong></td>
                    <td>${stu.average}</td>
                    <td><span class="grade-pill ${stu.overall_grade}" style="font-size:0.95rem;padding:4px 14px;">${stu.overall_grade}</span></td>
                </tr>`;
            });

            html += `</tbody></table></div>`;
            container.innerHTML = html;
        });
}

function printResultsView() {
    const examId = document.getElementById('te-results-exam').value;
    if (!examId) { showAlert('error', 'Select an exam first.'); return; }
    const examName = document.getElementById('te-results-exam').selectedOptions[0]?.text || 'Exam Results';
    const content  = document.getElementById('results-view-container').innerHTML;
    const win = window.open('', '_blank', 'width=1100,height=800');
    win.document.write(`<!DOCTYPE html><html><head>
        <title>Results: ${examName}</title>
        <style>
            body { font-family: 'Segoe UI', sans-serif; padding: 30px; color: #1e293b; }
            h1 { color: #4A0E17; border-bottom: 3px double #4A0E17; padding-bottom: 12px; margin-bottom: 20px; font-size: 20px; }
            table { width: 100%; border-collapse: collapse; font-size: 12px; }
            th { background: #4A0E17; color: white; padding: 8px 10px; text-align: left; }
            td { padding: 7px 10px; border-bottom: 1px solid #e2e8f0; }
            tr:nth-child(even) td { background: #FAF7F2; }
            .grade-pill { display:inline-block; padding:2px 8px; border-radius:10px; font-weight:800; font-size:0.8rem; background:#e2e8f0; }
            @media print { button { display: none; } }
        </style>
    </head><body>
        <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:3px double #4A0E17;padding-bottom:12px;margin-bottom:20px;">
            <div style="display:flex;align-items:center;gap:15px;">
                <img src="logo.png" style="height:55px;">
                <div>
                    <h2 style="margin:0;color:#4A0E17;font-size:1.3rem;">SANITY HOMEBASED TUITION ACADEMY</h2>
                    <p style="margin:4px 0;color:#6C757D;font-size:12px;">${examName} — Tutor Results Matrix</p>
                </div>
            </div>
            <div style="font-size:12px;color:#6C757D;text-align:right;">
                Printed: ${new Date().toDateString()}
            </div>
        </div>
        ${content}
        <br>
        <button onclick="window.print()" style="padding:10px 25px;background:#4A0E17;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:700;margin-top:10px;">
            <i>🖨</i> Print / Save as PDF
        </button>
    </body></html>`);
    win.document.close();
}

// ─────────────────────────────────────────────
// TIMETABLE
// ─────────────────────────────────────────────
let mySlots = [];

function loadWeeklyTimetable() {
    document.querySelectorAll('.tt-day-col .tt-slots-container').forEach(el => el.innerHTML = '');
    fetch('api/api_schedule_lesson.php?teacher_id=' + teacherId)
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') return;
            mySlots = data.slots || [];
            const slots = mySlots;

            if (slots.length === 0) {
                document.querySelectorAll('.tt-day-col .tt-slots-container').forEach(el => {
                    el.innerHTML = '<div style="color:var(--gray-600);text-align:center;font-size:0.75rem;padding:20px 0;">–</div>';
                });
                return;
            }

            slots.forEach(s => {
                const dayCol = document.querySelector(`.tt-day-col[data-day="${s.day_of_week}"] .tt-slots-container`);
                if (dayCol) {
                    let venueIcon = '🏫', venueLabel = 'School';
                    if (s.venue_type === 'home_visit') { venueIcon = '🏡'; venueLabel = 'Home Visit'; }
                    else if (s.venue_type === 'online_meet') { venueIcon = '📹'; venueLabel = 'Meet'; }
                    else if (s.venue_type === 'online_zoom') { venueIcon = '💻'; venueLabel = 'Zoom'; }
                    const startTime = s.start_time.slice(0, 5);
                    const endTime   = s.end_time.slice(0, 5);
                    const adm = s.admission_no || s.staff_id || 'S000A';
                    dayCol.innerHTML += `
                        <div class="tt-card">
                            <div class="tt-time">${startTime}–${endTime}</div>
                            <div class="tt-details">
                                <strong>${s.student_name}</strong> <span style="background:rgba(229,169,59,0.2);color:#B45309;padding:1px 6px;border-radius:8px;font-size:0.68rem;font-weight:700;">${adm}</span><br>
                                <span style="font-size:0.7rem;color:var(--primary);font-weight:700;">${venueIcon} ${venueLabel}</span>
                                ${(s.subject_name || s.subject) ? `<br><small style="color:var(--gray-600);font-size:0.68rem;">📚 ${s.subject_name || s.subject}</small>` : ''}
                                ${s.student_address ? `<br><small style="color:var(--gray-600);font-size:0.68rem;">📍 ${s.student_address}</small>` : ''}
                            </div>
                        </div>`;
                }
            });

            document.querySelectorAll('.tt-day-col .tt-slots-container').forEach(el => {
                if (el.innerHTML === '') el.innerHTML = '<div style="color:var(--gray-600);text-align:center;font-size:0.75rem;padding:20px 0;">–</div>';
            });
        });
}

function printMyTimetable() {
    const teacherName = <?php echo json_encode($_SESSION['user_name']); ?>;
    const days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    const dayAbbr = { Monday:'Monday', Tuesday:'Tuesday', Wednesday:'Wednesday', Thursday:'Thursday', Friday:'Friday', Saturday:'Saturday', Sunday:'Sunday' };

    let rows = '';
    mySlots.forEach(s => {
        const venueLabel = s.venue_type === 'home_visit' ? 'Home (1-on-1)' :
            (s.venue_type === 'online_meet' ? 'Online (Meet)' :
            (s.venue_type === 'online_zoom' ? 'Online (Zoom)' : 'School (1-on-1)'));
        rows += `<tr>
            <td>${s.day_of_week}</td>
            <td>${s.start_time?.slice(0,5)} – ${s.end_time?.slice(0,5)}</td>
            <td>${s.student_name}</td>
            <td>${s.subject_name || s.subject || '—'}</td>
            <td>${venueLabel}</td>
            <td>${s.student_address || '—'}</td>
        </tr>`;
    });

    const win = window.open('', '_blank', 'width=900,height=700');
    win.document.write(`<!DOCTYPE html><html><head>
        <title>Timetable – ${teacherName}</title>
        <style>
            body { font-family: 'Segoe UI', sans-serif; padding: 30px; color: #1e293b; }
            .hdr { text-align:center; border-bottom: 3px double #4A0E17; padding-bottom:16px; margin-bottom:22px; }
            .hdr h1 { color:#4A0E17; font-size:20px; margin:0; }
            .hdr p { color:#E5A93B; font-weight:700; font-size:12px; text-transform:uppercase; margin:5px 0 0; }
            table { width:100%; border-collapse:collapse; font-size:13px; }
            th { background:#4A0E17; color:white; padding:10px; text-align:left; }
            td { padding:9px 10px; border-bottom:1px solid #e2e8f0; }
            tr:nth-child(even) td { background:#FAF7F2; }
            .footer { margin-top:40px; display:flex; justify-content:space-between; font-size:12px; }
            .sign-line { border-top:1px solid #4A0E17; width:160px; text-align:center; padding-top:4px; }
            @media print { button { display:none; } }
        </style>
    </head><body>
        <div class="hdr">
            <img src="logo.png" style="height:60px;margin-bottom:10px;">
            <h1>SANITY HOMEBASED TUITION ACADEMY</h1>
            <p>Individual Teacher Weekly Timetable</p>
        </div>
        <table style="margin-bottom:12px;width:60%;font-size:13px;border-collapse:collapse;">
            <tr><td style="background:#FAF7F2;font-weight:700;padding:6px 10px;border:1px solid #e2e8f0;">Teacher Name:</td><td style="padding:6px 10px;border:1px solid #e2e8f0;">${teacherName}</td></tr>
            <tr><td style="background:#FAF7F2;font-weight:700;padding:6px 10px;border:1px solid #e2e8f0;">Downloaded On:</td><td style="padding:6px 10px;border:1px solid #e2e8f0;">${new Date().toDateString()}</td></tr>
        </table>
        <table>
            <thead><tr><th>Day</th><th>Time</th><th>Student</th><th>Subject</th><th>Venue</th><th>Location / Address</th></tr></thead>
            <tbody>${rows || '<tr><td colspan="6" style="text-align:center;padding:20px;color:#6C757D;">No slots scheduled.</td></tr>'}</tbody>
        </table>
        <div class="footer">
            <div><br><br><div class="sign-line">Teacher Signature</div></div>
            <div style="text-align:right;"><br><br><div class="sign-line">Director of Studies</div></div>
        </div>
        <br>
        <div style="text-align:center;">
            <button onclick="window.print()" style="padding:10px 25px;background:#4A0E17;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:700;"><i class="fa-solid fa-download"></i> Download / Save as PDF</button>
        </div>
    </body></html>`);
    win.document.close();
}

// ─────────────────────────────────────────────
// UTILITIES
// ─────────────────────────────────────────────
function showAlert(type, msg) {
    const el = document.getElementById('globalAlert');
    el.className = `alert alert-${type}`; el.innerHTML = msg; el.style.display = 'block';
    setTimeout(() => el.style.display = 'none', 6000);
}

function showGlobalAlert(msg, type = 'info') {
    showAlert(type, msg);
}
function loadLessons() {
    fetch('api/api_lesson_attendance.php?action=fetch_teacher_lessons&teacher_id=' + teacherId)
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('teacher-lessons-tbody');
            tbody.innerHTML = '';
            if (!data.lessons || !data.lessons.length) { tbody.innerHTML = '<tr><td colspan="6">No lessons scheduled.</td></tr>'; return; }
            data.lessons.forEach(l => {
                let actionBtn = '';
                if (l.session_status === 'scheduled') {
                    actionBtn = `<button class="btn btn-sm" onclick="startLesson(${l.id})"><i class="fa-solid fa-play"></i> Start Lesson</button>`;
                } else if (l.session_status === 'in_progress') {
                    actionBtn = `<button class="btn btn-success btn-sm" onclick="openEndLessonModal(${l.id})"><i class="fa-solid fa-circle-check"></i> End Lesson</button>`;
                } else { actionBtn = '<span style="color:var(--gray-600);font-weight:600;">Completed ✓</span>'; }
                let timeHtml = `${l.lesson_date} ${l.start_time.slice(0,5)}`;
                if (l.check_in_time) {
                    const cin = new Date(l.check_in_time);
                    if (!isNaN(cin.getTime())) timeHtml += `<br><small style="color:var(--success);font-weight:600;">In: ${cin.toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'})}</small>`;
                }
                if (l.check_out_time) {
                    const cout = new Date(l.check_out_time);
                    if (!isNaN(cout.getTime())) timeHtml += `<br><small style="color:var(--danger);font-weight:600;">Out: ${cout.toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'})}</small>`;
                }

                tbody.innerHTML += `<tr>
                    <td>${timeHtml}</td>
                    <td><strong>${l.student_name}</strong><br><small style="color:var(--gray-600);font-size:0.75rem;">📚 ${l.subject_name || l.subject || 'No Subject'}</small></td>
                    <td>${l.parent_phone}</td>
                    <td>${l.venue_type === 'home_visit' ? '🏡 Home' : '🏫 School'}</td>
                    <td><span class="badge badge-${l.session_status}">${l.session_status.toUpperCase()}</span></td>
                    <td>${actionBtn}</td>
                </tr>`;
            });
        });
}

function startLesson(lessonId) {
    if (!confirm('Are you ready to start this lesson? This will check you in and send immediate notifications to the parent and admin.')) return;
    
    const fd = new FormData();
    fd.append('action', 'start_lesson');
    fd.append('lesson_id', lessonId);

    fetch('api/api_lesson_attendance.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert('success', '✅ Lesson started successfully! Check-in notification sent to parent and admin.');
                loadLessons();
            } else {
                showAlert('error', data.message);
            }
        });
}

function openOtpModal(lessonId) {
    document.getElementById('otp-lesson-id').value = lessonId;
    document.getElementById('otp-code').value = '';
    document.getElementById('otpModal').classList.add('open');
}

function submitOtp(e) {
    e.preventDefault();
    const lessonId = document.getElementById('otp-lesson-id').value;
    const code = document.getElementById('otp-code').value;
    fetch(`api/api_lesson_attendance.php?action=verify_otp&lesson_id=${lessonId}&otp_code=${code}`)
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert('success', '✅ Handshake verified! Lesson is now in progress.');
                closeModal('otpModal');
                loadLessons();
            } else {
                alert('Verification Failed: ' + data.message);
            }
        });
}

function openEndLessonModal(lessonId) {
    document.getElementById('end-lesson-id').value = lessonId;
    document.getElementById('end-topics').value = '';
    document.getElementById('end-progress').value = '';
    document.getElementById('end-homework').value = '';
    document.getElementById('endLessonModal').classList.add('open');
}

function submitEndLesson(e) {
    e.preventDefault();
    const lessonId = document.getElementById('end-lesson-id').value;
    const fd = new FormData();
    fd.append('action', 'end_lesson');
    fd.append('lesson_id', lessonId);
    fd.append('topics_covered', document.getElementById('end-topics').value);
    fd.append('progress_notes', document.getElementById('end-progress').value);
    fd.append('homework_assigned', document.getElementById('end-homework').value);

    fetch('api/api_lesson_attendance.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert('success', '✅ Lesson completed successfully! Session submitted to accounts.');
                closeModal('endLessonModal');
                loadLessons();
            } else {
                showAlert('error', data.message);
            }
        });
}

function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}

function loadStudentsDropdown() {
    fetch('api/api_lesson_attendance.php?action=fetch_teacher_students&teacher_id=' + teacherId)
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('rep-student');
            if (sel) {
                sel.innerHTML = '<option value="">Loading...</option>';
// sel.innerHTML = '<option value="">Select student…</option>';
                (data.students || []).forEach(s => {
                    const adm = s.admission_no || s.staff_id || 'S000A';
                    sel.innerHTML += `<option value="${s.id}">${s.student_name} (${adm} - ${s.grade_level})</option>`;
                });
            }
        });
}

function submitReport(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action', 'submit_report');
    fd.append('student_id', document.getElementById('rep-student').value);
    fd.append('report_type', document.getElementById('rep-type').value);
    fd.append('period_identifier', document.getElementById('rep-period').value);
    fd.append('topics_covered', document.getElementById('rep-topics').value);
    fd.append('perf_notes', document.getElementById('rep-perf').value);
    fd.append('teacher_recs', document.getElementById('rep-recs').value);

    fetch('api/api_manage_academic.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert('success', '✅ Report submitted for admin review successfully!');
                e.target.reset();
            } else {
                showAlert('error', data.message);
            }
        });
}

let allSystemSubjects = [];
let teacherSelectedSubjectIds = [];

function loadProfile() {
    fetch('api/api_profile.php?action=get_profile')
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('prof-name').value = data.user.name;
                document.getElementById('prof-email').value = data.user.email;
                document.getElementById('prof-phone').value = data.user.phone || '';

                allSystemSubjects = data.all_subjects || [];
                teacherSelectedSubjectIds = (data.assigned_subject_ids || []).map(id => Number(id));

                renderTeacherSubjectsUI();
            }
        });
}

function renderTeacherSubjectsUI() {
    const tagsContainer = document.getElementById('teacher-active-subjects-tags');
    if (!tagsContainer) return;

    tagsContainer.innerHTML = '';

    const selectedSet = new Set(teacherSelectedSubjectIds);
    const activeSubjects = allSystemSubjects.filter(sub => selectedSet.has(Number(sub.id)));

    if (activeSubjects.length === 0) {
        tagsContainer.innerHTML = '<span style="color:var(--gray-600);font-size:0.85rem;font-style:italic;">No teaching subjects selected yet. Search below to add subjects.</span>';
    } else {
        activeSubjects.forEach(sub => {
            const tag = document.createElement('div');
            tag.style.cssText = 'display:inline-flex;align-items:center;gap:8px;background:var(--primary);color:#ffffff;padding:6px 14px;border-radius:20px;font-size:0.85rem;font-weight:600;box-shadow:0 2px 5px rgba(0,0,0,0.08);';
            tag.innerHTML = `<span><i class="fa-solid fa-book" style="margin-right:4px;font-size:0.8rem;opacity:0.85;"></i>${sub.name}</span>
            <button type="button" onclick="removeTeacherSubject(${sub.id})" title="Drop subject" style="background:rgba(255,255,255,0.25);border:none;color:#ffffff;border-radius:50%;width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-size:0.8rem;line-height:1;transition:background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.45)'" onmouseout="this.style.background='rgba(255,255,255,0.25)'">✕</button>`;
            tagsContainer.appendChild(tag);
        });
    }

    filterSubjectDropdown();
}

function showSubjectDropdown() {
    filterSubjectDropdown();
    const dropdown = document.getElementById('subject-search-dropdown');
    if (dropdown) dropdown.style.display = 'block';
}

function filterSubjectDropdown() {
    const dropdown = document.getElementById('subject-search-dropdown');
    const query = (document.getElementById('subject-search-input')?.value || '').toLowerCase().trim();
    if (!dropdown) return;

    const selectedSet = new Set(teacherSelectedSubjectIds);
    const availableSubjects = allSystemSubjects.filter(sub => !selectedSet.has(Number(sub.id)) && sub.name.toLowerCase().includes(query));

    dropdown.innerHTML = '';

    if (availableSubjects.length === 0) {
        dropdown.innerHTML = '<div style="padding:10px 14px;font-size:0.85rem;color:var(--gray-500);font-style:italic;">No matching subjects found.</div>';
    } else {
        availableSubjects.forEach(sub => {
            const item = document.createElement('div');
            item.style.cssText = 'padding:10px 14px;font-size:0.9rem;color:var(--primary);cursor:pointer;border-bottom:1px solid var(--gray-100);font-weight:600;display:flex;justify-content:space-between;align-items:center;transition:background 0.15s;';
            item.innerHTML = `<span><i class="fa-solid fa-plus-circle" style="color:var(--secondary);margin-right:8px;"></i>${sub.name}</span><span style="font-size:0.75rem;color:var(--gray-500);">Add</span>`;
            item.onmouseover = () => { item.style.background = 'var(--cream)'; };
            item.onmouseout  = () => { item.style.background = '#ffffff'; };
            item.onclick     = () => { addTeacherSubject(sub.id); };
            dropdown.appendChild(item);
        });
    }
}

function addTeacherSubject(subId) {
    const numericId = Number(subId);
    if (!teacherSelectedSubjectIds.includes(numericId)) {
        teacherSelectedSubjectIds.push(numericId);
        renderTeacherSubjectsUI();
    }
    const searchInput = document.getElementById('subject-search-input');
    if (searchInput) searchInput.value = '';
    const dropdown = document.getElementById('subject-search-dropdown');
    if (dropdown) dropdown.style.display = 'none';
}

function removeTeacherSubject(subId) {
    const numericId = Number(subId);
    teacherSelectedSubjectIds = teacherSelectedSubjectIds.filter(id => id !== numericId);
    renderTeacherSubjectsUI();
}

// Close search dropdown when clicking outside
document.addEventListener('click', (e) => {
    const dropdown = document.getElementById('subject-search-dropdown');
    const input = document.getElementById('subject-search-input');
    if (dropdown && input && !dropdown.contains(e.target) && !input.contains(e.target)) {
        dropdown.style.display = 'none';
    }
});

// ─────────────────────────────────────────────
// PROFILE UPDATE
// ─────────────────────────────────────────────
function updateProfile(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('csrf_token', CSRF_TOKEN);
    fd.append('action', 'update_profile');
    fd.append('name', document.getElementById('prof-name').value);
    fd.append('email', document.getElementById('prof-email').value);
    fd.append('phone', document.getElementById('prof-phone').value);

    fd.append('update_subjects', '1');
    teacherSelectedSubjectIds.forEach(sId => {
        fd.append('subject_ids[]', sId);
    });

    const curr = document.getElementById('prof-curr-pass').value;
    const newP = document.getElementById('prof-new-pass').value;
    if (curr || newP) {
        fd.append('current_password', curr);
        fd.append('new_password', newP);
    }

    fetch('api/api_profile.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                showAlert('success', '✅ Profile settings & teaching subjects updated successfully!');
                document.getElementById('prof-curr-pass').value = '';
                document.getElementById('prof-new-pass').value = '';
            } else {
                showAlert('error', data.message);
            }
        });
}

function loadDashboardStats() {
    // Load lessons count
    fetch('api/api_lesson_attendance.php?action=fetch_teacher_lessons&teacher_id=' + teacherId)
        .then(r => r.json())
        .then(d => {
            const count = (d.lessons || []).length;
            const mcLessons = document.getElementById('mc-lessons-count');
            const badgeLessons = document.getElementById('badge-lessons-count');
            if (mcLessons) mcLessons.textContent = count;
            if (badgeLessons) badgeLessons.textContent = count;
        });

    // Load notifications count
    fetch('api/api_notifications.php?action=get_notifications')
        .then(r => r.json())
        .then(d => {
            const count = (d.notifications || []).length;
            const mcNotifs = document.getElementById('mc-notifs-count');
            const badgeBell = document.getElementById('badge-bell-notifs');
            const sidebarBadge = document.getElementById('badge-notifs');
            if (mcNotifs) mcNotifs.textContent = count;
            if (badgeBell) badgeBell.textContent = count;
            if (sidebarBadge) {
                if (count > 0) {
                    sidebarBadge.textContent = count;
                    sidebarBadge.style.display = 'inline-block';
                } else {
                    sidebarBadge.style.display = 'none';
                }
            }
        });
}

window.onload = () => {
    loadDashboardStats();
    const savedTab = (new URLSearchParams(window.location.search).get('fresh') === '1')
        ? (() => { localStorage.removeItem('teacher_portal_active_tab'); return 'dashboard'; })()
        : (localStorage.getItem('teacher_portal_active_tab') || 'dashboard');
    switchTab(savedTab);
};

// ─────────────────────────────────────────────
// NOTIFICATIONS FETCH
// ─────────────────────────────────────────────
function loadNotificationBadge() {
    fetch('api/api_notifications.php?action=get_notifications')
        .then(r => r.json())
        .then(d => {
            const count = (d.notifications || []).length;
            const badge = document.getElementById('badge-notifs');
            if (badge) {
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            }
        }).catch(() => {});
}

function loadNotifications() {
    const feed = document.getElementById('notif-feed');
    if (!feed) return;
    feed.innerHTML = '<div class="no-data-msg"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</div>';
    fetch('api/api_notifications.php?action=get_notifications')
        .then(r => r.json())
        .then(d => {
            const list = d.notifications || [];
            // Reset badge since they are reading notifications now
            const badge = document.getElementById('badge-notifs');
            if (badge) badge.style.display = 'none';

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

function sendTeacherMessage(e) {
    e.preventDefault();
    const recipient = document.getElementById('teacher-msg-recipient').value;
    const title     = document.getElementById('teacher-msg-title').value.trim();
    const message   = document.getElementById('teacher-msg-body').value.trim();

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
                document.getElementById('form-teacher-send-msg').reset();
                loadNotifications();
            }
        })
        .catch(err => {
            console.error(err);
            showAlert('error', 'Failed to send message.');
        });
}

function clearAllNotifications() {
    if (!confirm('Are you sure you want to permanently clear all notifications?')) return;

    const fd = new FormData();
    fd.append('action', 'clear_all');
    
    fetch('api/api_notifications.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (typeof showAlert === 'function') {
                showAlert(data.status, data.message);
            } else {
                alert(data.message);
            }
            if (data.status === 'success') {
                if (typeof loadNotifications === 'function') loadNotifications();
                if (typeof loadBellNotifications === 'function') loadBellNotifications();
            }
        })
        .catch(() => alert('Failed to clear notifications.'));
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
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ' + loadingText;
    } else {
        btn.disabled = false;
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

let teacherLedgerData = [];

function loadSessionLedger() {
    const tbody = document.getElementById('ledger-tbody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="empty-row"><i class="fa-solid fa-spinner fa-spin"></i> Fetching records...</td></tr>';
    
    fetch('api/api_lesson_attendance.php?action=fetch_teacher_lessons&teacher_id=' + teacherId)
        .then(r => r.json())
        .then(data => {
            teacherLedgerData = data.lessons || [];
            renderSessionLedger();
        })
        .catch(() => {
            if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="empty-row" style="color:red;">Failed to load records.</td></tr>';
        });
}

function renderSessionLedger() {
    const tbody = document.getElementById('ledger-tbody');
    if (!tbody) return;

    const month = document.getElementById('ledger-month')?.value; // YYYY-MM
    const fromDate = document.getElementById('ledger-from')?.value;
    const toDate = document.getElementById('ledger-to')?.value;
    const status = document.getElementById('ledger-status')?.value;

    const filtered = teacherLedgerData.filter(l => {
        if (status && l.session_status !== status) return false;
        if (month && !l.lesson_date.startsWith(month)) return false;
        if (fromDate && l.lesson_date < fromDate) return false;
        if (toDate && l.lesson_date > toDate) return false;
        return true;
    });

    if (!filtered.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="empty-row">No sessions found for the selected filters.</td></tr>';
        return;
    }

    tbody.innerHTML = filtered.map(l => {
        let timeHtml = `${l.start_time.slice(0,5)} - ${l.end_time.slice(0,5)}`;
        let checkIn = l.check_in_time ? new Date(l.check_in_time).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'}) : '—';
        let checkOut = l.check_out_time ? new Date(l.check_out_time).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'}) : '—';
        let statusBadge = l.session_status === 'completed' ? 'badge-published' : (l.session_status === 'in_progress' ? 'badge-pending-grade' : 'badge');
        let subjectDisplay = l.subject_name || l.subject || 'N/A';
        
        return `<tr>
            <td><strong>${l.lesson_date}</strong><br><small style="color:var(--gray-600);">${timeHtml}</small></td>
            <td><strong>${l.student_name}</strong></td>
            <td><span style="background:rgba(74,14,23,0.08);color:var(--primary);padding:3px 10px;border-radius:12px;font-size:0.8rem;font-weight:700;">${subjectDisplay}</span></td>
            <td>${l.venue_type === 'home_visit' ? '🏡 Home' : '🏫 School'}</td>
            <td style="color:var(--success);font-weight:600;">${checkIn}</td>
            <td style="color:var(--danger);font-weight:600;">${checkOut}</td>
            <td><span class="badge ${statusBadge}">${l.session_status.toUpperCase()}</span></td>
        </tr>`;
    }).join('');
}

function resetLedgerFilters() {
    if(document.getElementById('ledger-month')) document.getElementById('ledger-month').value = '';
    if(document.getElementById('ledger-from')) document.getElementById('ledger-from').value = '';
    if(document.getElementById('ledger-to')) document.getElementById('ledger-to').value = '';
    if(document.getElementById('ledger-status')) document.getElementById('ledger-status').value = 'completed';
    renderSessionLedger();
}

function exportLedgerCSV() {
    const month = document.getElementById('ledger-month')?.value;
    const fromDate = document.getElementById('ledger-from')?.value;
    const toDate = document.getElementById('ledger-to')?.value;
    const status = document.getElementById('ledger-status')?.value;

    const filtered = teacherLedgerData.filter(l => {
        if (status && l.session_status !== status) return false;
        if (month && !l.lesson_date.startsWith(month)) return false;
        if (fromDate && l.lesson_date < fromDate) return false;
        if (toDate && l.lesson_date > toDate) return false;
        return true;
    });

    if (!filtered.length) {
        showAlert('error', 'No records to export.');
        return;
    }

    const rows = [['Date', 'Start Time', 'End Time', 'Student', 'Subject', 'Venue', 'Check-In', 'Check-Out', 'Status']];
    filtered.forEach(l => {
        rows.push([
            l.lesson_date,
            l.start_time.slice(0,5),
            l.end_time.slice(0,5),
            l.student_name,
            l.subject_name || l.subject || '',
            l.venue_type === 'home_visit' ? 'Home' : 'School',
            l.check_in_time || '',
            l.check_out_time || '',
            l.session_status
        ]);
    });

    const csv = rows.map(r => r.map(c => `"${String(c).replace(/"/g,'""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;
    a.download = `my_session_ledger_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}
</script>
</body>
</html>
