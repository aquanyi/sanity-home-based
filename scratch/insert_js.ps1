# 1. Read Parent Invoice JS (extract lines 10 to 370 of step_389_all_chunk_3.txt)
$pi_lines = Get-Content -Path "scratch/step_389_all_chunk_3.txt"
$pi_js = ($pi_lines[10..($pi_lines.Count-4)] -join "`r`n")

# 2. Build Extra Expenses JS
# We need category meta and tab setters from step_27_all_chunk_4.txt (lines 4 to 67)
$exp_lines = Get-Content -Path "scratch/step_27_all_chunk_4.txt"
$exp_meta = ($exp_lines[3..66] -join "`r`n")
# And the load/render functions from step_81_all_chunk_4.txt (lines 1 to 79)
$exp_load = Get-Content -Path "scratch/step_81_all_chunk_4.txt" -Raw
# And the modal / save / delete functions from step_27_all_chunk_4.txt (lines 130 to 217)
$exp_modals = ($exp_lines[129..216] -join "`r`n")

$expenses_js = @"
// =============================================
// EXTRA EXPENSES MODULE
// =============================================
$exp_meta

$exp_load

$exp_modals
"@
# Replace api path in expenses_js:
$expenses_js = $expenses_js.Replace("api_accounts.php", "api/api_accounts.php")

# 3. Read Expense Reports JS (extract lines 6 to 304 of step_52_all_chunk_3.txt)
$rpt_lines = Get-Content -Path "scratch/step_52_all_chunk_3.txt"
$rpt_js = ($rpt_lines[5..303] -join "`r`n")
$rpt_js = $rpt_js.Replace("api_accounts.php", "api/api_accounts.php")

# 4. Read Financial Report JS (extract lines 6 to 405 of step_81_all_chunk_5.txt)
$fin_lines = Get-Content -Path "scratch/step_81_all_chunk_5.txt"
$fin_js = ($fin_lines[5..404] -join "`r`n")
$fin_js = $fin_js.Replace("api_accounts.php", "api/api_accounts.php")

# Combined Custom JS Modules
$combined_js = @"

// =========================================================================
// RESTORED CUSTOM MODULES (PARENT INVOICE, EXPENSES, REPORTS, FINANCIALS)
// =========================================================================

$pi_js

$expenses_js

$rpt_js

$fin_js

"@

# Load accounts_dashboard.php
$content = [System.IO.File]::ReadAllText("accounts_dashboard.php", [System.Text.Encoding]::UTF8)

# Replacement 1: printInvoice()
$old_print_inv = @"
function printInvoice() {
    const printContents = document.getElementById('invoice-print-area').innerHTML;
    const win = window.open('', '_blank', 'width=850,height=1100');
    win.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Fee Invoice – Sanity Tuition</title>
            <style>
                body { font-family: 'Segoe UI', sans-serif; padding: 40px; color: #1e293b; }
                table { width:100%; border-collapse:collapse; }
                th, td { padding:8px 10px; border:1px solid #e2e8f0; font-size:13px; }
                th { background:#f8fafc; font-weight:700; }
                h3 { color:#E8963D; margin-bottom:4px; }
                @media print { body { padding:20px; } button { display:none; } }
            </style>
        </head>
        <body>
            \${printContents}
            <br>
            <button onclick="window.print()" style="margin-top:20px;padding:10px 24px;background:#E8963D;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;">🖨️ Print / Save as PDF</button>
        </body>
        </html>
    `);
    win.document.close();
}
"@

$new_print_inv = @"
function printInvoice() {
    const printContents = document.getElementById('invoice-print-area').innerHTML;
    const win = window.open('', '_blank', 'width=850,height=1100');
    win.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Fee Invoice – Sanity Tuition</title>
            <style>
                body { font-family: 'Segoe UI', sans-serif; padding: 40px; color: #1e293b; }
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
            \${printContents}
            <br>
            <button onclick="window.print()" style="margin-top:20px;padding:10px 24px;background:#E8963D;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;">🖨️ Print / Save as PDF</button>
        </body>
        </html>
    `);
    win.document.close();
}
"@

$content = $content.Replace($old_print_inv, $new_print_inv)

# Replacement 2: printPayroll()
$old_print_pay = @"
function printPayroll() {
    const printContents = document.getElementById('payroll-print-area').innerHTML;
    const originalContents = document.body.innerHTML;
    document.body.innerHTML = `<div style="padding:40px; font-family:sans-serif;">\${printContents}</div>`;
    window.print();
    document.body.innerHTML = originalContents;
    window.location.reload(); // Reload to restore event handlers
}
"@

$new_print_pay = @"
function printPayroll() {
    const printContents = document.getElementById('payroll-print-area').innerHTML;
    const win = window.open('', '_blank', 'width=900,height=1100');
    win.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Payroll & Disbursement Statement</title>
            <style>
                body { font-family: 'Segoe UI', sans-serif; padding: 40px; color: #1e293b; }
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
            \${printContents}
            <br>
            <button onclick="window.print()" style="margin-top:20px;padding:10px 24px;background:#4A0E17;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;">🖨️ Print / Save as PDF</button>
        </body>
        </html>
    `);
    win.document.close();
}
"@

$content = $content.Replace($old_print_pay, $new_print_pay)

# Replacement 3: switchTab rules
$old_switch = @"
    if (id === 'dashboard' || id === 'sessions' || id === 'monthly') loadSessions();
    if (id === 'pricing') loadPricing();
    if (id === 'invoices') loadInvoiceDropdowns();
    if (id === 'payroll') loadPayrollTab();
"@

$new_switch = @"
    if (id === 'dashboard' || id === 'sessions' || id === 'monthly') loadSessions();
    if (id === 'pricing') loadPricing();
    if (id === 'invoices') loadInvoiceDropdowns();
    if (id === 'parent-invoice') initParentInvoice();
    if (id === 'payroll') loadPayrollTab();
    if (id === 'expenses') initExpensesTab();
    if (id === 'expreport') initReportTab();
    if (id === 'finreport') initFinReportTab();
"@

$content = $content.Replace($old_switch, $new_switch)

# Replacement 4: window.onload
$old_onload = "window.onload = () => loadSessions();"
$new_onload = @"
window.onload = () => {
    loadSessions();
    const savedTab = localStorage.getItem('accounts_dashboard_active_tab');
    if (savedTab) {
        switchTab(savedTab);
    }
};
"@

$content = $content.Replace($old_onload, $new_onload)

# Find </script> at the bottom of the file
$script_index = $content.LastIndexOf("</script>")
if ($script_index -lt 0) {
    Write-Error "Could not find </script> tag!"
    exit 1
}

# Insert new JS modules
$content_updated = $content.Insert($script_index, $combined_js)

# Write updated file
[System.IO.File]::WriteAllText("accounts_dashboard.php", $content_updated, [System.Text.Encoding]::UTF8)
Write-Host "Successfully inserted JS modules and updated event handlers!"
