# Read accounts_dashboard.php
$content = [System.IO.File]::ReadAllText("accounts_dashboard.php", [System.Text.Encoding]::UTF8)

# Remove duplicate </main>
$content = $content.Replace("</main>`r`n`r`n</main>", "</main>")
$content = $content.Replace("</main>`n`n</main>", "</main>")
$content = $content.Replace("</main>`r`n</main>", "</main>")

# Read expenseModal HTML
$modal_html = Get-Content -Path "scratch/step_27_all_chunk_2.txt" -Raw

# Insert before pricingModal
$target = '<!-- MODAL: Edit Pricing -->'
if ($content.Contains($target)) {
    $content = $content.Replace($target, $modal_html + "`r`n`r`n" + $target)
} else {
    $target_alt = '<div class="modal-bg" id="pricingModal">'
    $content = $content.Replace($target_alt, $modal_html + "`r`n`r`n" + $target_alt)
}

# Save accounts_dashboard.php
[System.IO.File]::WriteAllText("accounts_dashboard.php", $content, [System.Text.Encoding]::UTF8)
Write-Host "Successfully cleaned up duplicate </main> and inserted expenseModal!"
