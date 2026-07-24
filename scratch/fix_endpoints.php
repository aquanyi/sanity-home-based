<?php
function fix_file_endpoints($filepath) {
    $content = file_get_contents($filepath);
    $lines = explode("\n", $content);
    $new_lines = $lines;

    foreach ($lines as $idx => $line) {
        if (strpos($line, 'api/api_.php') !== false) {
            $api_name = null;

            // Case A: GET query parameters in url
            if (strpos($line, 'action=') !== false || strpos($line, 'student_id=') !== false || strpos($line, 'teacher_id=') !== false) {
                if (strpos($line, 'action=get_term_dates') !== false || strpos($line, 'action=get_grading_scales') !== false || strpos($line, 'action=get_terms_by_year') !== false) {
                    $api_name = 'api_settings.php';
                } elseif (strpos($line, 'action=get_notifications') !== false) {
                    $api_name = 'api_notifications.php';
                } elseif (strpos($line, 'action=get_profile') !== false) {
                    $api_name = 'api_profile.php';
                } elseif (strpos($line, 'action=get_subjects') !== false || strpos($line, 'action=all') !== false) {
                    $api_name = 'api_resources.php';
                } elseif (strpos($line, 'action=student_exam_report') !== false) {
                    $api_name = 'api_manage_reports.php';
                } elseif (strpos($line, 'action=session_students') !== false || strpos($line, 'action=teacher_exams') !== false || strpos($line, 'action=exam_results') !== false) {
                    $api_name = 'api_manage_academic.php';
                } elseif (strpos($line, 'action=fetch_teacher_lessons') !== false) {
                    $api_name = 'api_lesson_attendance.php';
                } elseif (strpos($line, 'student_id=') !== false || strpos($line, 'teacher_id=') !== false) {
                    $api_name = 'api_schedule_lesson.php';
                }
            }

            // Case B: POST requests where body is fd (FormData)
            if (!$api_name) {
                $action_value = null;
                for ($back_idx = $idx - 1; $back_idx >= max(0, $idx - 40); $back_idx--) {
                    $back_line = $lines[$back_idx];
                    if (preg_match("/\.append\(\s*['\"]action['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $back_line, $match)) {
                        $action_value = $match[1];
                        break;
                    }
                }

                if ($action_value) {
                    if (in_array($action_value, ['save_term_dates', 'delete_term', 'save_grading_scale', 'delete_grading_scale'])) {
                        $api_name = 'api_settings.php';
                    } elseif (in_array($action_value, ['send_notification'])) {
                        $api_name = 'api_notifications.php';
                    } elseif (in_array($action_value, ['update_profile', 'change_password'])) {
                        $api_name = 'api_profile.php';
                    } elseif (in_array($action_value, ['upload_resource', 'edit_resource', 'delete_resource', 'add_subject', 'edit_subject', 'delete_subject'])) {
                        $api_name = 'api_resources.php';
                    } elseif (in_array($action_value, ['submit_report', 'release_report', 'add_override', 'send_nudge'])) {
                        $api_name = 'api_manage_reports.php';
                    } elseif (in_array($action_value, ['create_exam', 'schedule_exam', 'add_assignment', 'delete_assignment', 'submit_grades', 'edit_exam_session', 'delete_exam_session'])) {
                        $api_name = 'api_manage_academic.php';
                    } elseif (in_array($action_value, ['generate_otp', 'verify_otp', 'log_check_out'])) {
                        $api_name = 'api_lesson_attendance.php';
                    } elseif (in_array($action_value, ['schedule', 'edit_slot', 'delete_slot'])) {
                        $api_name = 'api_schedule_lesson.php';
                    } elseif (in_array($action_value, ['approve'])) {
                        $api_name = 'api_approve_lead.php';
                    } elseif (in_array($action_value, ['reject'])) {
                        $api_name = 'api_fetch_leads.php';
                    } elseif (in_array($action_value, ['add_staff', 'create_role'])) {
                        $api_name = 'api_create_role.php';
                    }
                }
            }

            // Case C: Simple parameterless fetch without query params
            if (!$api_name) {
                // Slice lines
                $context_lines = array_slice($lines, max(0, $idx - 15), 16);
                $context_chunk = implode("\n", $context_lines);
                if (strpos($context_chunk, 'loadAcademicSection') !== false || strpos($context_chunk, 'allSystemReports') !== false || strpos($context_chunk, 'moderation') !== false) {
                    $api_name = 'api_manage_reports.php';
                } elseif (strpos($context_chunk, 'loadExamsSection') !== false || strpos($context_chunk, 'smart invigilation') !== false) {
                    $api_name = 'api_manage_academic.php';
                } elseif (strpos($context_chunk, 'loadAttendanceSection') !== false) {
                    $api_name = 'api_lesson_attendance.php';
                } elseif (strpos($context_chunk, 'loadLeads') !== false || strpos($context_chunk, 'leads') !== false) {
                    $api_name = 'api_fetch_leads.php';
                }
            }

            if ($api_name) {
                $new_line = str_replace('api/api_.php', "api/{$api_name}", $line);
                $new_lines[$idx] = $new_line;
                echo "Fixed line " . ($idx + 1) . " in " . basename($filepath) . ": replaced with api/{$api_name}\n";
            } else {
                echo "Warning: Could not resolve endpoint for line " . ($idx + 1) . " in " . basename($filepath) . ": {$line}\n";
            }
        }
    }

    file_put_contents($filepath, implode("\n", $new_lines));
}

fix_file_endpoints('admin_dashboard.php');
fix_file_endpoints('teacher_portal.php');
?>
