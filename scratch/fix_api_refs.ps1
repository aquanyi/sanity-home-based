$file = 'admin_dashboard.php'
$lines = Get-Content $file

# Map of line numbers (1-indexed) to correct API filename
$fixes = @{
    1521 = 'api_fetch_leads.php'
    1703 = 'api_approve_lead.php'
    1720 = 'api_approve_lead.php'
    1773 = 'api_manage_academic.php'
    1806 = 'api_create_role.php'
    1853 = 'api_schedule_lesson.php'
    2126 = 'api_schedule_lesson.php'
    2140 = 'api_schedule_lesson.php'
    2161 = 'api_schedule_lesson.php'
    2174 = 'api_lesson_attendance.php'
    2219 = 'api_manage_academic.php'
    2314 = 'api_manage_academic.php'
    2331 = 'api_manage_academic.php'
    2476 = 'api_schedule_lesson.php'
    2512 = 'api_manage_reports.php'
    2603 = 'api_manage_reports.php'
    2631 = 'api_manage_reports.php'
    2650 = 'api_manage_reports.php'
    2757 = 'api_resources.php'
    2768 = 'api_resources.php'
    2834 = 'api_resources.php'
    2879 = 'api_resources.php'
    2893 = 'api_resources.php'
    2901 = 'api_resources.php'
    2948 = 'api_resources.php'
    2982 = 'api_resources.php'
    2996 = 'api_resources.php'
    3019 = 'api_profile.php'
    3041 = 'api_profile.php'
    3108 = 'api_create_role.php'
    3123 = 'api_settings.php'
    3248 = 'api_settings.php'
    3264 = 'api_settings.php'
    3583 = 'api_settings.php'
    3616 = 'api_settings.php'
    3632 = 'api_settings.php'
    3666 = 'api_manage_academic.php'
    3744 = 'api_manage_academic.php'
    3793 = 'api_notifications.php'
    3820 = 'api_notifications.php'
    3871 = 'api_notifications.php'
}

for ($i = 0; $i -lt $lines.Count; $i++) {
    $lineNum = $i + 1
    if ($fixes.ContainsKey($lineNum)) {
        $correct = $fixes[$lineNum]
        $lines[$i] = $lines[$i] -replace 'api/api_\.php', "api/$correct"
    }
}

Set-Content $file $lines -Encoding UTF8
Write-Host "Done. Applied fixes to $($fixes.Count) target lines."
