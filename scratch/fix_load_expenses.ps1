# Read accounts_dashboard.php
$lines = Get-Content -Path "accounts_dashboard.php"
$index = -1
for ($i = 0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -like '*if (!expLastData.length) {*' -and $lines[$i+8] -like '*function openExpenseModal(id) {*') {
        $index = $i
        break
    }
}

if ($index -ge 0) {
    # Replace lines from $index to $index+7
    $new_lines = New-Object System.Collections.Generic.List[string]
    for ($i = 0; $i -lt $index; $i++) { $new_lines.Add($lines[$i]) }
    
    # Add corrected closing block
    $new_lines.Add('            if (!expLastData.length) {')
    $new_lines.Add('                tbody.innerHTML = `<tr><td colspan="7" class="empty-row">No ${expCategoryMeta[cat].label} expenses recorded yet. Click "Add Expense" to start.</td></tr>`;')
    $new_lines.Add('                if (totalRow) totalRow.style.display = "none";')
    $new_lines.Add('                return;')
    $new_lines.Add('            }')
    $new_lines.Add('            renderExpensesRows(expLastData);')
    $new_lines.Add('        })')
    $new_lines.Add('        .catch(() => showAlert("error", "Network error loading expenses."));')
    $new_lines.Add('}')
    
    # Add the rest of the lines
    for ($i = $index+9; $i -lt $lines.Count; $i++) { $new_lines.Add($lines[$i]) }
    
    # Save the file
    [System.IO.File]::WriteAllLines("accounts_dashboard.php", $new_lines, [System.Text.Encoding]::UTF8)
    Write-Host "Successfully corrected loadExpenses function!"
} else {
    Write-Error "Could not find matching lines for loadExpenses closure!"
}
