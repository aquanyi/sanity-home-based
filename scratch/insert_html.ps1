# Combine HTML sections for: Parent Invoice, Extra Expenses, Expense Reports, Full Financial Report
# 1. Parent Invoice HTML
$pi_html = Get-Content -Path "scratch/step_389_all_chunk_1.txt" -Raw

# 2. Extra Expenses HTML (with Search Bar integrated)
$exp_html = Get-Content -Path "scratch/step_27_all_chunk_1.txt" -Raw
# Replace the header select & button with search bar version from step_81_all_chunk_1.txt
$search_bar = Get-Content -Path "scratch/step_81_all_chunk_1.txt" -Raw
# Target to replace in step_27_all_chunk_1.txt:
$old_header = @"
                <div style="display:flex;gap:8px;align-items:center;">
                    <select id="exp-filter-month" class="form-control" style="min-width:160px;padding:8px 12px;" onchange="loadExpenses()">
                        <option value="">All Months</option>
                    </select>
                    <button class="btn btn-primary" onclick="openExpenseModal()">
                        <i class="fa-solid fa-plus"></i> Add Expense
                    </button>
                </div>
"@
$exp_html = $exp_html.Replace($old_header, $search_bar)

# Remove any leading / trailing main close tags in the chunks if they were included
$pi_html = $pi_html.Trim()
if ($pi_html.StartsWith("</div>")) { $pi_html = $pi_html.Substring(6).Trim() }
if ($pi_html.EndsWith("</div>")) { $pi_html = $pi_html.Substring(0, $pi_html.Length - 6).Trim() } # Wait, let's keep the wrapping divs intact.
# Actually, let's look at the exact file contents:
# step_389_all_chunk_1.txt starts with:
# 1:     </div>
# 2: 
# 3:     <!-- PARENT INVOICE GENERATOR -->
# 4:     <div id="section-parent-invoice" class="section">
# ... and ends with:
# 191:     </div>
# 192: 
# 193:     <!-- EXTRA EXPENSES -->
# So it starts with </div> and ends with </div> (closing the parent-invoice div).
# Let's write a clean version of the combined HTML.

$combined_html = @"

    <!-- PARENT INVOICE GENERATOR -->
$((Get-Content -Path "scratch/step_389_all_chunk_1.txt" -Raw).Substring(6).TrimEnd())

    <!-- EXTRA EXPENSES -->
$((Get-Content -Path "scratch/step_27_all_chunk_1.txt" -Raw).Substring(6).Replace($old_header, $search_bar).TrimEnd())

    <!-- EXPENSE REPORTS -->
$((Get-Content -Path "scratch/step_52_all_chunk_1.txt" -Raw).Substring(6).TrimEnd())

    <!-- FULL SCHOOL FINANCIAL REPORT -->
$((Get-Content -Path "scratch/step_81_all_chunk_2.txt" -Raw).Substring(6).TrimEnd())

"@

# Write combined HTML to scratch file for review
$combined_html | Out-File -FilePath "scratch/combined_sections.html" -Encoding utf8

# Read accounts_dashboard.php
$dash_content = [System.IO.File]::ReadAllText("accounts_dashboard.php", [System.Text.Encoding]::UTF8)

# Find </main>
$main_index = $dash_content.LastIndexOf("</main>")
if ($main_index -lt 0) {
    Write-Error "Could not find </main> tag in accounts_dashboard.php!"
    exit 1
}

# Insert combined HTML before </main>
$dash_content_updated = $dash_content.Insert($main_index, $combined_html + "`r`n")

# Save updated accounts_dashboard.php
[System.IO.File]::WriteAllText("accounts_dashboard.php", $dash_content_updated, [System.Text.Encoding]::UTF8)
Write-Host "Successfully inserted HTML sections into accounts_dashboard.php!"
