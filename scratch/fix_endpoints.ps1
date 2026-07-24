function Fix-FileEndpoints ($filepath) {
    $content = Get-Content $filepath -Raw
    $lines = $content -split "`r?`n"
    $newLines = @()
    
    for ($i = 0; $i -lt $lines.Length; $i++) {
        $line = $lines[$i]
        if ($line -like "*api/api_.php*") {
            $apiName = $null
            
            # Case A: GET query parameters in URL
            if ($line -like "*action=*" -or $line -like "*student_id=*" -or $line -like "*teacher_id=*") {
                if ($line -like "*action=get_term_dates*" -or $line -like "*action=get_grading_scales*" -or $line -like "*action=get_terms_by_year*") {
                    $apiName = "api_settings.php"
                } elseif ($line -like "*action=get_notifications*") {
                    $apiName = "api_notifications.php"
                } elseif ($line -like "*action=get_profile*") {
                    $apiName = "api_profile.php"
                } elseif ($line -like "*action=get_subjects*" -or $line -like "*action=all*") {
                    $apiName = "api_resources.php"
                } elseif ($line -like "*action=student_exam_report*") {
                    $apiName = "api_manage_reports.php"
                } elseif ($line -like "*action=session_students*" -or $line -like "*action=teacher_exams*" -or $line -like "*action=exam_results*") {
                    $apiName = "api_manage_academic.php"
                } elseif ($line -like "*action=fetch_teacher_lessons*") {
                    $apiName = "api_lesson_attendance.php"
                } elseif ($line -like "*student_id=*" -or $line -like "*teacher_id=*") {
                    $apiName = "api_schedule_lesson.php"
                }
            }
            
            # Case B: POST requests where body is fd (FormData)
            if ($null -eq $apiName) {
                $actionValue = $null
                # Search backwards up to 40 lines
                $startIdx = $i - 1
                $endIdx = [Math]::Max(0, $i - 40)
                for ($j = $startIdx; $j -ge $endIdx; $j--) {
                    $backLine = $lines[$j]
                    if ($backLine -match "\.append\(\s*['\"`]action['\"`]\s*,\s*['\"`]([^'\"`]+)['\"`]\s*\)") {
                        $actionValue = $Matches[1]
                        break
                    }
                }
                
                if ($null -ne $actionValue) {
                    if ($actionValue -in 'save_term_dates', 'delete_term', 'save_grading_scale', 'delete_grading_scale') {
                        $apiName = "api_settings.php"
                    } elseif ($actionValue -eq 'send_notification') {
                        $apiName = "api_notifications.php"
                    } elseif ($actionValue -in 'update_profile', 'change_password') {
                        $apiName = "api_profile.php"
                    } elseif ($actionValue -in 'upload_resource', 'edit_resource', 'delete_resource', 'add_subject', 'edit_subject', 'delete_subject') {
                        $apiName = "api_resources.php"
                    } elseif ($actionValue -in 'submit_report', 'release_report', 'add_override', 'send_nudge') {
                        $apiName = "api_manage_reports.php"
                    } elseif ($actionValue -in 'create_exam', 'schedule_exam', 'add_assignment', 'delete_assignment', 'submit_grades', 'edit_exam_session', 'delete_exam_session') {
                        $apiName = "api_manage_academic.php"
                    } elseif ($actionValue -in 'generate_otp', 'verify_otp', 'log_check_out') {
                        $apiName = "api_lesson_attendance.php"
                    } elseif ($actionValue -in 'schedule', 'edit_slot', 'delete_slot') {
                        $apiName = "api_schedule_lesson.php"
                    } elseif ($actionValue -eq 'approve') {
                        $apiName = "api_approve_lead.php"
                    } elseif ($actionValue -eq 'reject') {
                        $apiName = "api_fetch_leads.php"
                    } elseif ($actionValue -in 'add_staff', 'create_role') {
                        $apiName = "api_create_role.php"
                    }
                }
            }
            
            # Case C: Context based fallback
            if ($null -eq $apiName) {
                # Look backwards for context
                $startIdx = [Math]::Max(0, $i - 15)
                $context = ""
                for ($j = $startIdx; $j -le $i; $j++) {
                    $context += $lines[$j] + " "
                }
                if ($context -like "*loadAcademicSection*" -or $context -like "*allSystemReports*" -or $context -like "*moderation*") {
                    $apiName = "api_manage_reports.php"
                } elseif ($context -like "*loadExamsSection*" -or $context -like "*smart invigilation*") {
                    $apiName = "api_manage_academic.php"
                } elseif ($context -like "*loadAttendanceSection*") {
                    $apiName = "api_lesson_attendance.php"
                } elseif ($context -like "*loadLeads*" -or $context -like "*leads*") {
                    $apiName = "api_fetch_leads.php"
                }
            }
            
            if ($null -ne $apiName) {
                $line = $line.Replace("api/api_.php", "api/$apiName")
                Write-Host "Fixed line $($i+1) in $(Split-Path $filepath -Leaf): replaced with api/$apiName"
            } else {
                Write-Warning "Could not resolve endpoint for line $($i+1) in $(Split-Path $filepath -Leaf): $line"
            }
        }
        $newLines += $line
    }
    
    $newLines -join "`r`n" | Set-Content $filepath -Force
}

Fix-FileEndpoints "admin_dashboard.php"
Fix-FileEndpoints "teacher_portal.php"
