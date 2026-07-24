# Read accounts_dashboard.php
$lines = [System.IO.File]::ReadAllLines("accounts_dashboard.php", [System.Text.Encoding]::UTF8)

# 1. Replace printInvoice (lines 2074 to 2099, which is index 2073 to 2098)
# Let's verify line 2074:
if ($lines[2073] -like '*function printInvoice()*') {
    $new_print_inv = @(
        'function printInvoice() {',
        '    const printContents = document.getElementById("invoice-print-area").innerHTML;',
        '    const win = window.open("", "_blank", "width=850,height=1100");',
        '    win.document.write(`',
        '        <!DOCTYPE html>',
        '        <html>',
        '        <head>',
        '            <title>Fee Invoice – Sanity Tuition</title>',
        '            <style>',
        '                body { font-family: "Segoe UI", sans-serif; padding: 40px; color: #1e293b; }',
        '                table { width:100%; border-collapse:collapse; }',
        '                th, td { padding:8px 10px; border:1px solid #e2e8f0; font-size:13px; }',
        '                th { background:#f8fafc; font-weight:700; }',
        '                h3 { color:#E8963D; margin-bottom:4px; }',
        '                @media print { body { padding:20px; } button { display:none; } }',
        '            </style>',
        '        </head>',
        '        <body>',
        '            <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #E5A93B;padding-bottom:12px;margin-bottom:20px;">',
        '                <img src="logo.png" style="height:60px;">',
        '                <div style="text-align:right;">',
        '                    <h2 style="margin:0;font-size:1.3rem;color:#4A0E17;">SANITY HOMEBASED TUITION ACADEMY</h2>',
        '                    <p style="margin:4px 0;color:#6C757D;font-size:12px;">Official Fee Invoice</p>',
        '                </div>',
        '            </div>',
        '            ${printContents}',
        '            <br>',
        '            <button onclick="window.print()" style="margin-top:20px;padding:10px 24px;background:#E8963D;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;">🖨️ Print / Save as PDF</button>',
        '        </body>',
        '        </html>',
        '    `);',
        '    win.document.close();',
        '}'
    )
    
    # Reconstruct the list of lines
    $list = New-Object System.Collections.Generic.List[string]
    for ($i = 0; $i -lt 2073; $i++) { $list.Add($lines[$i]) }
    foreach ($line in $new_print_inv) { $list.Add($line) }
    for ($i = 2099; $i -lt $lines.Count; $i++) { $list.Add($lines[$i]) }
    $lines = $list.ToArray()
    Write-Host "Successfully replaced printInvoice!"
} else {
    Write-Error "Could not find printInvoice at expected index!"
}

# 2. Replace printPayroll (lines 2348 to 2355, which is index 2347 to 2354 of the updated $lines)
# Since we replaced printInvoice, let's find the index of printPayroll dynamically:
$payroll_index = -1
for ($i = 0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -like '*function printPayroll()*') {
        $payroll_index = $i
        break
    }
}

if ($payroll_index -ge 0) {
    $new_print_pay = @(
        'function printPayroll() {',
        '    const printContents = document.getElementById("payroll-print-area").innerHTML;',
        '    const win = window.open("", "_blank", "width=900,height=1100");',
        '    win.document.write(`',
        '        <!DOCTYPE html>',
        '        <html>',
        '        <head>',
        '            <title>Payroll & Disbursement Statement</title>',
        '            <style>',
        '                body { font-family: "Segoe UI", sans-serif; padding: 40px; color: #1e293b; }',
        '                table { width:100%; border-collapse:collapse; }',
        '                th, td { padding:8px 10px; border:1px solid #e2e8f0; font-size:13px; }',
        '                th { background:#f8fafc; font-weight:700; }',
        '                h3 { color:#4A0E17; margin-bottom:4px; }',
        '                @media print { body { padding:20px; } button { display:none; } }',
        '            </style>',
        '        </head>',
        '        <body>',
        '            <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #E5A93B;padding-bottom:12px;margin-bottom:20px;">',
        '                <img src="logo.png" style="height:60px;">',
        '                <div style="text-align:right;">',
        '                    <h2 style="margin:0;font-size:1.3rem;color:#4A0E17;">SANITY HOMEBASED TUITION ACADEMY</h2>',
        '                    <p style="margin:4px 0;color:#6C757D;font-size:12px;">Tutor Payroll & Disbursement Statement</p>',
        '                </div>',
        '            </div>',
        '            ${printContents}',
        '            <br>',
        '            <button onclick="window.print()" style="margin-top:20px;padding:10px 24px;background:#4A0E17;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;">🖨️ Print / Save as PDF</button>',
        '        </body>',
        '        </html>',
        '    `);',
        '    win.document.close();',
        '}'
    )
    
    # Also find the unclosed/redundant window.onload right after it (should be around index $payroll_index + 8 after replacing)
    # The old printPayroll is 8 lines long. We will replace index $payroll_index to $payroll_index+7 with $new_print_pay,
    # and then remove the next few lines if they are window.onload.
    # Let's inspect:
    $list = New-Object System.Collections.Generic.List[string]
    for ($i = 0; $i -lt $payroll_index; $i++) { $list.Add($lines[$i]) }
    foreach ($line in $new_print_pay) { $list.Add($line) }
    
    # Skip the next 8 lines (the old printPayroll is 8 lines long)
    # Then skip the next 9 lines (which correspond to the duplicate onload at lines 2357-2364)
    # Let's see what is at $payroll_index + 8:
    $offset = $payroll_index + 8
    if ($lines[$offset] -like '*// Init*' -and $lines[$offset+1] -like '*window.onload*') {
        # Yes, skip it!
        $skip_until = $offset + 9
        Write-Host "Found duplicate window.onload at index $offset, removing it!"
    } else {
        $skip_until = $offset
    }
    
    for ($i = $skip_until; $i -lt $lines.Count; $i++) { $list.Add($lines[$i]) }
    $lines = $list.ToArray()
    Write-Host "Successfully replaced printPayroll and removed first window.onload!"
} else {
    Write-Error "Could not find printPayroll dynamically!"
}

# Save updated accounts_dashboard.php
[System.IO.File]::WriteAllLines("accounts_dashboard.php", $lines, [System.Text.Encoding]::UTF8)
Write-Host "Done!"
