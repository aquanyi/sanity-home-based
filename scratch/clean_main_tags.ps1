# Read accounts_dashboard.php
$content = [System.IO.File]::ReadAllText("accounts_dashboard.php", [System.Text.Encoding]::UTF8)

# 1. Clean transition to Expense Reports
$target1 = @"
        </div>
    </div>

</main>

    <!-- EXPENSE REPORTS -->
div>

    <!-- ===== EXPENSE REPORTS ===== -->
    <div id="section-expreport" class="section">
"@

$replacement1 = @"
        </div>
    </div>

    <!-- ===== EXPENSE REPORTS ===== -->
    <div id="section-expreport" class="section">
"@

$content = $content.Replace($target1, $replacement1)

# 2. Clean transition to Full School Financial Report
$target2 = @"
    </div>

</main>

    <!-- FULL SCHOOL FINANCIAL REPORT -->
div>

    <!-- ===== FULL SCHOOL FINANCIAL REPORT ===== -->
    <div id="section-finreport" class="section">
"@

$replacement2 = @"
    </div>

    <!-- ===== FULL SCHOOL FINANCIAL REPORT ===== -->
    <div id="section-finreport" class="section">
"@

$content = $content.Replace($target2, $replacement2)

# 3. Clean up the duplicate </main> tags at the end of HTML content
$target3 = @"
</main>

</main>
"@

$replacement3 = @"
</main>
"@

$content = $content.Replace($target3, $replacement3)

# Save accounts_dashboard.php
[System.IO.File]::WriteAllText("accounts_dashboard.php", $content, [System.Text.Encoding]::UTF8)
Write-Host "Successfully cleaned up intermediate </main> and stray div tags!"
