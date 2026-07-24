import re
import os

def fix_file_endpoints(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # We will find all fetch calls to api/api_.php and parse them
    # For GET: check the query parameter in the fetch URL (e.g. fetch('api/api_.php?action=...'))
    # For POST: we look backwards from the match to find the most recent fd.append('action', '...')

    lines = content.split('\n')
    new_lines = list(lines)

    for idx, line in enumerate(lines):
        if 'api/api_.php' in line:
            # Determine correct api name
            api_name = None
            
            # Case A: GET query parameters in url
            if 'action=' in line or 'student_id=' in line or 'teacher_id=' in line:
                if 'action=get_term_dates' in line or 'action=get_grading_scales' in line or 'action=get_terms_by_year' in line:
                    api_name = 'api_settings.php'
                elif 'action=get_notifications' in line:
                    api_name = 'api_notifications.php'
                elif 'action=get_profile' in line:
                    api_name = 'api_profile.php'
                elif 'action=get_subjects' in line or 'action=all' in line:
                    api_name = 'api_resources.php'
                elif 'action=student_exam_report' in line:
                    api_name = 'api_manage_reports.php'
                elif 'action=session_students' in line or 'action=teacher_exams' in line or 'action=exam_results' in line:
                    api_name = 'api_manage_academic.php'
                elif 'action=fetch_teacher_lessons' in line:
                    api_name = 'api_lesson_attendance.php'
                elif 'student_id=' in line or 'teacher_id=' in line:
                    # e.g. api_schedule_lesson.php
                    api_name = 'api_schedule_lesson.php'

            # Case B: POST requests where body is fd (FormData)
            # Search backwards for fd.append('action', '...') or similar
            if not api_name:
                action_value = None
                for back_idx in range(idx - 1, max(-1, idx - 40), -1):
                    back_line = lines[back_idx]
                    match = re.search(r"\.append\(\s*['\"]action['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)", back_line)
                    if match:
                        action_value = match.group(1)
                        break

                if action_value:
                    if action_value in ['save_term_dates', 'delete_term', 'save_grading_scale', 'delete_grading_scale']:
                        api_name = 'api_settings.php'
                    elif action_value in ['send_notification']:
                        api_name = 'api_notifications.php'
                    elif action_value in ['update_profile', 'change_password']:
                        api_name = 'api_profile.php'
                    elif action_value in ['upload_resource', 'edit_resource', 'delete_resource', 'add_subject', 'edit_subject', 'delete_subject']:
                        api_name = 'api_resources.php'
                    elif action_value in ['submit_report', 'release_report', 'add_override', 'send_nudge']:
                        api_name = 'api_manage_reports.php'
                    elif action_value in ['create_exam', 'schedule_exam', 'add_assignment', 'delete_assignment', 'submit_grades', 'edit_exam_session', 'delete_exam_session']:
                        api_name = 'api_manage_academic.php'
                    elif action_value in ['generate_otp', 'verify_otp', 'log_check_out']:
                        api_name = 'api_lesson_attendance.php'
                    elif action_value in ['schedule', 'edit_slot', 'delete_slot']:
                        api_name = 'api_schedule_lesson.php'
                    elif action_value in ['approve']:
                        api_name = 'api_approve_lead.php'
                    elif action_value in ['reject']:
                        api_name = 'api_fetch_leads.php'
                    elif action_value in ['add_staff', 'create_role']:
                        api_name = 'api_create_role.php'

            # Case C: Simple parameterless fetch without query params
            if not api_name:
                # Search backwards for functions/contexts to guess the endpoint
                # Let's inspect the surrounding code
                context_chunk = "\n".join(lines[max(0, idx - 15):idx + 1])
                if 'loadAcademicSection' in context_chunk or 'allSystemReports' in context_chunk or 'moderation' in context_chunk:
                    api_name = 'api_manage_reports.php'
                elif 'loadExamsSection' in context_chunk or 'smart invigilation' in context_chunk:
                    api_name = 'api_manage_academic.php'
                elif 'loadAttendanceSection' in context_chunk:
                    api_name = 'api_lesson_attendance.php'
                elif 'loadLeads' in context_chunk or 'leads' in context_chunk:
                    api_name = 'api_fetch_leads.php'

            if api_name:
                new_line = line.replace('api/api_.php', f'api/{api_name}')
                new_lines[idx] = new_line
                print(f"Fixed line {idx+1} in {os.path.basename(filepath)}: replaced with api/{api_name}")
            else:
                print(f"Warning: Could not resolve endpoint for line {idx+1} in {os.path.basename(filepath)}: {line}")

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write("\n".join(new_lines))

# Run on the two broken views
fix_file_endpoints('admin_dashboard.php')
fix_file_endpoints('teacher_portal.php')
