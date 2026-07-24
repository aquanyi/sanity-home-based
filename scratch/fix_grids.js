const fs = require('fs');

function fixFile(filename) {
    let c = fs.readFileSync(filename, 'utf8');

    // ── 1. Fix all inline style="display:grid;grid-template-columns:NNNpx 1fr ──
    // These are sidebar+content two-column layouts that must stack on mobile
    // Replace with a responsive utility class
    c = c.replace(
        /style="display:grid;\s*grid-template-columns:\s*(?:280px|300px|320px|260px)\s+1fr;gap:\s*\d+px;align-items:start;"/g,
        'class="resp-two-col"'
    );
    c = c.replace(
        /style="display:grid;\s*grid-template-columns:\s*(?:280px|300px|320px|260px)\s+1fr;\s*gap:\s*\d+px;\s*align-items:\s*start;"/g,
        'class="resp-two-col"'
    );

    // ── 2. Fix the 3-column filter rows (the one in the screenshot) ──────────
    // grid-template-columns:1fr 1fr 1fr auto  →  resp-filter-grid
    c = c.replace(
        /style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:\d+px;align-items:end;flex-wrap:wrap;"/g,
        'class="resp-filter-grid"'
    );
    c = c.replace(
        /style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:\d+px;align-items:end;"/g,
        'class="resp-filter-grid"'
    );

    // ── 3. Fix all repeat(3,1fr) — three-equal-column grids ─────────────────
    c = c.replace(
        /style="(display:grid;\s*)?grid-template-columns:\s*repeat\(3,\s*1fr\);([^"]*)"/g,
        (match, p1, p2) => `style="display:grid;grid-template-columns:repeat(3,1fr);${p2}" class="resp-three-col"`
    );

    // ── 4. Fix two-col grids that use 1fr 1fr inline ─────────────────────────
    // Only if they have no class already and are not inside a print template
    c = c.replace(
        /style="display:grid;grid-template-columns:1fr 1fr;gap:(\d+px);margin-bottom:(\d+px);" class="two-col-grid"/g,
        'class="resp-two-col-grid two-col-grid"'
    );

    // ── 5. Fix the fin report date range filter ──────────────────────────────
    // search for fin-from/fin-to filter rows
    c = c.replace(
        /style="display:grid;grid-template-columns:1fr 1fr 1fr;([^"]*)"/g,
        (match, rest) => `style="display:grid;grid-template-columns:1fr 1fr 1fr;${rest}" class="resp-three-filter"`
    );

    // ── 6. Add the responsive CSS classes before </style> ────────────────────
    const responsiveCSS = `
        /* ═══ RESPONSIVE UTILITY CLASSES ═══ */

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
    `;

    // Insert before the closing </style> tag (first occurrence only, in <head>)
    const firstStyleClose = c.indexOf('</style>');
    if (firstStyleClose !== -1) {
        c = c.substring(0, firstStyleClose) + responsiveCSS + '\n    </style>' + c.substring(firstStyleClose + '</style>'.length);
    }

    fs.writeFileSync(filename, c, 'utf8');
    console.log('✅ Fixed: ' + filename);
}

fixFile('accounts_dashboard.php');
fixFile('admin_dashboard.php');
console.log('\nAll responsive grid fixes applied!');
