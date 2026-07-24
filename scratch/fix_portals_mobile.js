const fs = require('fs');

// ─────────────────────────────────────────────────────────────────────────────
// The comprehensive mobile CSS block to insert before </style> in each file
// ─────────────────────────────────────────────────────────────────────────────
const mobileCSSBlock = `
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
`;

// ─────────────────────────────────────────────────────────────────────────────
function fixPortal(filename) {
    let c = fs.readFileSync(filename, 'utf8');

    // 1. Replace the existing @media (max-width: 800px) block with new comprehensive one
    //    (both portals have the same pattern, just slightly different padding/content)
    const oldMedia800_parent = `        @media (max-width: 800px) {\r\n            body { flex-direction: column; padding-top: 65px; }\r\n            .mobile-header { display: flex; }\r\n            \r\n            /* Sidebar turns into fixed slide-out drawer from the right */\r\n            .sidebar { \r\n                width: 280px; \r\n                height: calc(100vh - 65px); \r\n                position: fixed; \r\n                top: 65px; \r\n                right: -280px; \r\n                z-index: 1050; \r\n                padding: 25px 15px; \r\n                transition: right 0.3s cubic-bezier(0.16, 1, 0.3, 1); \r\n                box-shadow: -5px 0 15px rgba(0,0,0,0.1);\r\n            }\r\n            .sidebar.active {\r\n                right: 0;\r\n            }\r\n            .sidebar-logo { display: none; } /* already in top bar */\r\n            .nav-item { padding: 12px 15px; font-size: 0.9rem; }\r\n            .main { margin-left: 0; padding: 20px 14px; }\r\n            \r\n            .info-bar { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; }\r\n        }\r\n        @media (max-width: 480px) {\r\n            .main { padding: 15px 10px; }\r\n            .panel { padding: 20px 15px; border-radius: 12px; }\r\n            .panel-header h2 { font-size: 1.05rem; }\r\n            .form-grid { gap: 12px; }\r\n            .info-date { font-size: 0.8rem; }\r\n            .info-badges { gap: 12px; }\r\n            .info-badge-item { font-size: 1.05rem; }\r\n        }`;

    const oldMedia800_teacher = `        @media (max-width: 800px) {\r\n            body { flex-direction: column; padding-top: 65px; }\r\n            .mobile-header { display: flex; }\r\n            \r\n            /* Sidebar turns into fixed slide-out drawer from the right */\r\n            .sidebar { \r\n                width: 280px; \r\n                height: calc(100vh - 65px); \r\n                position: fixed; \r\n                top: 65px; \r\n                right: -280px; \r\n                z-index: 1050; \r\n                padding: 25px 15px; \r\n                transition: right 0.3s cubic-bezier(0.16, 1, 0.3, 1); \r\n                box-shadow: -5px 0 15px rgba(0,0,0,0.1);\r\n            }\r\n            .sidebar.active {\r\n                right: 0;\r\n            }\r\n            .sidebar-logo { display: none; } /* already in top bar */\r\n            .nav-item { padding: 12px 15px; font-size: 0.9rem; }\r\n            .main { margin-left: 0; padding: 20px 14px; }\r\n            \r\n            .info-bar { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; }\r\n        }\r\n        @media (max-width: 480px) {\r\n            .main { padding: 15px 10px; }\r\n            .panel { padding: 20px 15px; border-radius: 12px; }\r\n            .panel-header h2 { font-size: 1.05rem; }\r\n            .form-grid { gap: 12px; }\r\n            .modal-box { padding: 20px 15px; }\r\n            .info-date { font-size: 0.8rem; }\r\n            .info-badges { gap: 12px; }\r\n            .info-badge-item { font-size: 1.05rem; }\r\n        }`;

    // Try replacing with CRLF version first, then LF
    if (c.includes(oldMedia800_parent)) {
        c = c.replace(oldMedia800_parent, mobileCSSBlock);
        console.log('  ✔ Replaced parent-style media block');
    } else if (c.includes(oldMedia800_teacher)) {
        c = c.replace(oldMedia800_teacher, mobileCSSBlock);
        console.log('  ✔ Replaced teacher-style media block');
    } else {
        // Fallback: insert comprehensive block before </style>
        const styleCloseIdx = c.indexOf('</style>');
        if (styleCloseIdx !== -1) {
            c = c.substring(0, styleCloseIdx) + mobileCSSBlock + '\n    </style>' + c.substring(styleCloseIdx + '</style>'.length);
            console.log('  ✔ Inserted CSS before </style> (fallback)');
        }
    }

    // 2. Add class to sidebar Sign Out wrapper
    c = c.replace(
        '<div style="margin-top: auto; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); width: 100%;">',
        '<div class="sidebar-signout-wrap" style="margin-top: auto; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); width: 100%;">'
    );

    // 3. Hide the inline top "Sign Out" button on mobile
    c = c.replace(
        '<div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">\r\n        <a href="logout.php" class="btn btn-sm btn-outline"',
        '<div class="main-signout-btn" style="display:flex; justify-content: flex-end; margin-bottom: 20px;">\r\n        <a href="logout.php" class="btn btn-sm btn-outline"'
    );
    c = c.replace(
        '<div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">\n        <a href="logout.php" class="btn btn-sm btn-outline"',
        '<div class="main-signout-btn" style="display:flex; justify-content: flex-end; margin-bottom: 20px;">\n        <a href="logout.php" class="btn btn-sm btn-outline"'
    );

    // 4. Fix table overflow inside existing table-wrap (enhance if needed)
    // Already has .table-wrap { overflow-x: auto; } - just ensure min-width on tables
    c = c.replace(
        '.table-wrap { overflow-x: auto; } table { width: 100%; border-collapse: collapse; }',
        '.table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; } .table-wrap table { min-width: 480px; } table { width: 100%; border-collapse: collapse; }'
    );

    fs.writeFileSync(filename, c, 'utf8');
    console.log('✅ Done: ' + filename);
}

console.log('\n── parent_portal.php ──');
fixPortal('parent_portal.php');

console.log('\n── teacher_portal.php ──');
fixPortal('teacher_portal.php');

console.log('\n🎉 All portal files updated!');
