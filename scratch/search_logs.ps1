$path = 'C:\Users\HP EliteBook 840 G8\.gemini\antigravity\brain\8a97a84d-26f2-4b27-bfb9-9ed8d9bbd362\.system_generated\logs\transcript_full.jsonl'
$lines = Get-Content -Path $path
# Find the last step that modified accounts_dashboard.php
for ($i = $lines.Count - 1; $i -ge 0; $i--) {
    try {
        $json = ConvertFrom-Json $lines[$i]
        if ($json.tool_calls) {
            foreach ($tc in $json.tool_calls) {
                if ($tc.args.TargetFile -like '*accounts_dashboard.php*' -or $tc.args.TargetFile -like '*accounts_dashboard.php*') {
                    Write-Host "Found write in step $($json.step_index) of type $($tc.name)"
                    if ($tc.name -eq 'write_to_file') {
                        $tc.args.CodeContent | Out-File -FilePath "scratch/accounts_dashboard_pristine.php" -Encoding utf8
                        Write-Host "Restored to scratch/accounts_dashboard_pristine.php!"
                        exit 0
                    }
                }
            }
        }
    } catch {}
}
Write-Host "Done scanning, looking for alternative ways."
