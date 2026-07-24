const fs = require('fs');

function patchFile(filename) {
    let c = fs.readFileSync(filename, 'utf8');

    // ── Patch remaining inline HTML grid styles ──────────────────────────────

    // 1. Payroll/Invoice two-panel selector layouts: "280px 1fr"
    // These appear in both #pay-subtab-ledger and similar areas
    c = c.replace(
        /style="display:none; display:grid; grid-template-columns: 280px 1fr; gap: 20px; align-items: start;"/g,
        'style="display:none;" class="resp-two-col"'
    );
    c = c.replace(
        /style="display:grid; grid-template-columns: 280px 1fr; gap: 20px; align-items: start;"/g,
        'class="resp-two-col"'
    );
    c = c.replace(
        /style="display:grid;grid-template-columns: 280px 1fr; gap: 20px; align-items: start;"/g,
        'class="resp-two-col"'
    );
    c = c.replace(
        /style="display:grid;grid-template-columns:300px 1fr;gap:24px;align-items:start;"/g,
        'class="resp-two-col"'
    );

    // 2. Three-column summary grids (invoice/payroll stats row)
    c = c.replace(
        /style="display:grid;grid-template-columns:repeat\(3,1fr\); gap: 15px; margin-top:20px; border-top: 1\.5px solid var\(--gray-200\); padding-top:20px;"/g,
        'style="border-top:1.5px solid var(--gray-200);padding-top:20px;margin-top:20px;" class="resp-stats-row"'
    );

    // 3. Invoice billed-to / student two-col (inside print preview)
    c = c.replace(
        /style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;"/g,
        'style="gap:16px;margin-bottom:20px;" class="resp-two-col-sm"'
    );

    // 4. the 1fr 1fr compact info grid
    c = c.replace(
        /style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:0.88rem;"/g,
        'style="gap:8px;font-size:0.88rem;" class="resp-two-col-sm"'
    );

    // ── Add .resp-stats-row and .resp-two-col-sm to the new CSS block ────────
    const extraCSS = `
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
    `;

    // Insert before the SECOND </style> tag (after the first one we already patched)
    // Actually insert before ANY </style> in <head> which is the main one
    // We already inserted before the first </style> so now add to our responsive block
    // Find our RESPONSIVE UTILITY CLASSES block and append to it
    const marker = '/* ═══ RESPONSIVE UTILITY CLASSES ═══ */';
    if (c.includes(marker)) {
        c = c.replace(marker, marker + extraCSS);
    }

    fs.writeFileSync(filename, c, 'utf8');
    console.log('✅ Patched: ' + filename);
}

patchFile('accounts_dashboard.php');
patchFile('admin_dashboard.php');
console.log('Done!');
