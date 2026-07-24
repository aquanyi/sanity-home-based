$file = 'teacher_portal.php'
$lines = Get-Content $file

$fixes = @{
    356 = 'api_manage_academic.php'
    402 = 'api_manage_academic.php'
    455 = 'api_manage_academic.php'
    478 = 'api_manage_academic.php'
    689 = 'api_lesson_attendance.php'
    717 = 'api_lesson_attendance.php'
    728 = 'api_lesson_attendance.php'
    748 = 'api_lesson_attendance.php'
    754 = 'api_manage_reports.php'
    769 = 'api_manage_reports.php'
    775 = 'api_profile.php'
    792 = 'api_profile.php'
    808 = 'api_notifications.php'
    828 = 'api_notifications.php'
}

for ($i = 0; $i -lt $lines.Count; $i++) {
    $lineNum = $i + 1
    if ($fixes.ContainsKey($lineNum)) {
        $correct = $fixes[$lineNum]
        $lines[$i] = $lines[$i] -replace 'api/api_\.php', "api/$correct"
    }
}

Set-Content $file $lines -Encoding UTF8
Write-Host "Done. Applied fixes to $($fixes.Count) target lines in teacher_portal.php."
