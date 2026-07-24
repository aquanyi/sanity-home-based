const fs = require('fs');

// ─────────────────────────────────────────────────────────────────────────────
// HELPER: Apply all mobile fixes to a dashboard file
// ─────────────────────────────────────────────────────────────────────────────
function fixDashboard(filename, label) {
    let c = fs.readFileSync(filename, 'utf8');

    // ── 1. HIDE the inline top-of-main "Sign Out" button on mobile ──────────
    // The button sitting inside <main> (not the sidebar one) should hide on mobile.
    // We do this by wrapping it with a class and hiding it in the @media block.
    // Instead of complex replace, we add CSS to hide it.

    // ── 2. COMPREHENSIVE mobile CSS additions ────────────────────────────────
    const mobileCSS = `
        /* ── MOBILE OVERFLOW FIX ── */
        * { box-sizing: border-box; }
        html, body { overflow-x: hidden; max-width: 100%; }

        /* Tables: horizontally scrollable on mobile, not the whole page */
        .table-wrap, .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        table {
            min-width: 600px;
        }

        /* Inline sign-out button (top of main area) – hide on mobile */
        .main-signout-btn {
            display: flex;
        }
        @media (max-width: 800px) {
            .main-signout-btn { display: none !important; }
            /* Tables scroll within their container, page stays put */
            table { min-width: 500px; }
            /* Grids: collapse to 1 column on small screens */
            .form-grid { grid-template-columns: 1fr !important; }
            /* Make panels not overflow */
            .panel { overflow: hidden; }
            /* Fix any wide flex rows */
            .panel-header { flex-wrap: wrap; gap: 10px; }
            .btn-group { flex-wrap: wrap; gap: 8px; }
            /* Info bar badges responsive */
            .info-bar { flex-wrap: wrap; gap: 10px; }
            /* Prevent metric cards overflowing */
            .metric-card { min-width: 0; word-break: break-word; }
        }
        @media (max-width: 480px) {
            table { min-width: 400px; font-size: 0.78rem; }
            th, td { padding: 8px 8px; white-space: nowrap; }
            .page-header h1 { font-size: 1.3rem; }
        }
    `;

    // Insert new CSS just before </style> (first occurrence)
    c = c.replace('</style>', mobileCSS + '\n    </style>');

    // ── 3. Wrap the inline "Sign Out" button in <main> with the hide class ──
    // Accounts dashboard version
    c = c.replace(
        '<div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">\n        <a href="logout.php" class="btn btn-sm btn-outline"',
        '<div class="main-signout-btn" style="justify-content: flex-end; margin-bottom: 20px;">\n        <a href="logout.php" class="btn btn-sm btn-outline"'
    );
    c = c.replace(
        '<div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">\r\n        <a href="logout.php" class="btn btn-sm btn-outline"',
        '<div class="main-signout-btn" style="justify-content: flex-end; margin-bottom: 20px;">\r\n        <a href="logout.php" class="btn btn-sm btn-outline"'
    );

    // Admin dashboard version (different layout - button is in topbar)
    // The admin topbar already hides itself on mobile (.topbar { display: none !important; })
    // so the Sign Out in topbar is already hidden. We just add class to any inline one.

    // ── 4. Wrap every <table> not already in .table-wrap with a scroll div ──
    // Only wrap bare tables that are direct children of panels/sections
    // We'll add .table-wrap around all tables that don't already have it
    c = c.replace(/<table(?![^>]*id="[^"]*modal)/g, (match, offset) => {
        // Check if already inside table-wrap by looking backward for it
        const before = c.substring(Math.max(0, offset - 200), offset);
        if (before.includes('table-wrap') || before.includes('table-responsive')) {
            return match;
        }
        return match; // We'll handle via CSS only to avoid complex HTML manipulation
    });

    // ── 5. Ensure the sidebar Sign Out is always INSIDE the sidebar drawer ──
    // It's already there, so nothing to change there.

    fs.writeFileSync(filename, c, 'utf8');
    console.log(`✅ ${label} (${filename}) — mobile fixes applied!`);
}

fixDashboard('accounts_dashboard.php', 'Accounts Dashboard');
fixDashboard('admin_dashboard.php', 'Admin Dashboard');

console.log('\nAll done!');
