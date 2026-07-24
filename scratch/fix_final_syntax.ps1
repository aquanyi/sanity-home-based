# Read accounts_dashboard.php
$content = [System.IO.File]::ReadAllText("accounts_dashboard.php", [System.Text.Encoding]::UTF8)

# Replace the online_zoom label syntax error
$content = $content.Replace("online_meet: '🎥 Online (Google Meet)',`r`nonline_zoom: '📹'📹 Online (Zoom)'", "online_meet: '💻 Online (Google Meet)',`r`nonline_zoom: '📹 Online (Zoom)'")
$content = $content.Replace("online_meet: '🎥 Online (Google Meet)',`nonline_zoom: '📹'📹 Online (Zoom)'", "online_meet: '💻 Online (Google Meet)',`nonline_zoom: '📹 Online (Zoom)'")
$content = $content.Replace("online_zoom: '📹'📹 Online (Zoom)'", "online_zoom: '📹 Online (Zoom)'")

# Replace the petty_cash icon trailing question mark
$content = $content.Replace("petty_cash:      { label:'Petty Cash',      icon:'☕?',  color:'#8B5CF6' }", "petty_cash:      { label:'Petty Cash',      icon:'☕',  color:'#8B5CF6' }")
$content = $content.Replace("petty_cash:      { label:'Petty Cash',      icon:'☕?', color:'#8B5CF6'", "petty_cash:      { label:'Petty Cash',      icon:'☕', color:'#8B5CF6'")

# Save accounts_dashboard.php
[System.IO.File]::WriteAllText("accounts_dashboard.php", $content, [System.Text.Encoding]::UTF8)
Write-Host "Successfully corrected final syntax and typos!"
