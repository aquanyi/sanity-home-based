<?php
/**
 * submit_enrollment.php — Updated for multiple students & new fields
 */
header('Content-Type: application/json');
require_once 'db_connect.php';
require_once 'admission_helper.php';

ensure_schema_updated($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Spam check
    if (!empty($_POST['website_hp'])) {
        echo json_encode(['status' => 'error', 'message' => 'Spam detected.']);
        exit;
    }

    $parent_name        = filter_input(INPUT_POST, 'parent_name', FILTER_SANITIZE_SPECIAL_CHARS);
    $parent_phone       = filter_input(INPUT_POST, 'parent_phone', FILTER_SANITIZE_SPECIAL_CHARS);
    $parent_email       = filter_input(INPUT_POST, 'parent_email', FILTER_VALIDATE_EMAIL);
    $parent_nationality = filter_input(INPUT_POST, 'parent_nationality', FILTER_SANITIZE_SPECIAL_CHARS);
    $learning_needs     = filter_input(INPUT_POST, 'learning_needs', FILTER_SANITIZE_SPECIAL_CHARS);
    $venue_preference   = filter_input(INPUT_POST, 'venue_preference', FILTER_SANITIZE_SPECIAL_CHARS);

    if ($venue_preference === 'home') $venue_preference = 'home_visit';

    $loc_place  = isset($_POST['loc_place'])  ? filter_input(INPUT_POST, 'loc_place', FILTER_SANITIZE_SPECIAL_CHARS) : null;
    $loc_estate = isset($_POST['loc_estate']) ? filter_input(INPUT_POST, 'loc_estate', FILTER_SANITIZE_SPECIAL_CHARS) : null;
    $loc_link   = isset($_POST['loc_link'])   ? filter_input(INPUT_POST, 'loc_link', FILTER_VALIDATE_URL) : null;

    $curriculum_id     = isset($_POST['curriculum_id']) ? $_POST['curriculum_id'] : null;
    $custom_curriculum = isset($_POST['custom_curriculum']) ? trim($_POST['custom_curriculum']) : '';
    $study_type        = isset($_POST['study_type']) ? $_POST['study_type'] : 'tuition';

    if ($study_type !== 'homeschooling') {
        $study_type = 'tuition';
    }

    if ($curriculum_id === 'custom') {
        if (!empty($custom_curriculum)) {
            $stmt_check = $pdo->prepare("SELECT id FROM curriculums WHERE LOWER(name) = LOWER(?)");
            $stmt_check->execute([$custom_curriculum]);
            $existing = $stmt_check->fetch();
            if ($existing) {
                $curriculum_id = $existing['id'];
            } else {
                $stmt_ins = $pdo->prepare("INSERT INTO curriculums (name, is_approved) VALUES (?, 0)");
                $stmt_ins->execute([$custom_curriculum]);
                $curriculum_id = $pdo->lastInsertId();
            }
        } else {
            $curriculum_id = null;
        }
    } else {
        $curriculum_id = filter_var($curriculum_id, FILTER_VALIDATE_INT) ?: null;
    }

    // Process students list
    $students_list = [];
    if (isset($_POST['students']) && is_array($_POST['students'])) {
        foreach ($_POST['students'] as $st) {
            $name = trim($st['name'] ?? '');
            $grade = trim($st['grade'] ?? '');
            $subjects = [];
            if (isset($st['subjects']) && is_array($st['subjects'])) {
                $subjects = array_filter(array_map('trim', $st['subjects']));
            } elseif (!empty($st['subjects'])) {
                $subjects = array_filter(array_map('trim', explode(',', $st['subjects'])));
            }

            if ($name && $grade) {
                $students_list[] = [
                    'name'           => $name,
                    'grade'          => $grade,
                    'nationality'    => trim($st['nationality'] ?? ''),
                    'dob'            => trim($st['dob'] ?? ''),
                    'first_language' => trim($st['first_language'] ?? ''),
                    'subjects'       => array_values($subjects)
                ];
            }
        }
    } else {
        $sName = filter_input(INPUT_POST, 'student_name', FILTER_SANITIZE_SPECIAL_CHARS);
        $sGrade = filter_input(INPUT_POST, 'student_grade', FILTER_SANITIZE_SPECIAL_CHARS);
        $sSubj  = $_POST['student_subjects'] ?? [];
        if (!is_array($sSubj) && !empty($sSubj)) {
            $sSubj = explode(',', $sSubj);
        }
        if ($sName && $sGrade) {
            $students_list[] = [
                'name'           => $sName,
                'grade'          => $sGrade,
                'nationality'    => filter_input(INPUT_POST, 'student_nationality', FILTER_SANITIZE_SPECIAL_CHARS),
                'dob'            => filter_input(INPUT_POST, 'student_dob', FILTER_SANITIZE_SPECIAL_CHARS),
                'first_language' => filter_input(INPUT_POST, 'student_first_language', FILTER_SANITIZE_SPECIAL_CHARS),
                'subjects'       => is_array($sSubj) ? array_values(array_filter(array_map('trim', $sSubj))) : []
            ];
        }
    }

    if (!$parent_name || !$parent_phone || !$parent_email || empty($students_list) || !$learning_needs || !$venue_preference) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields. Parent details and at least one student name & grade are required.']);
        exit;
    }

    $firstStudent = $students_list[0];
    $students_json = json_encode($students_list);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO enrollment_inquiries 
            (parent_name, parent_phone, parent_email, parent_nationality, student_name, student_grade, students_json, venue_preference, loc_place, loc_estate, loc_link, curriculum_id, study_type, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $parent_name,
            $parent_phone,
            $parent_email,
            $parent_nationality,
            $firstStudent['name'],
            $firstStudent['grade'],
            $students_json,
            $venue_preference,
            $loc_place,
            $loc_estate,
            $loc_link,
            $curriculum_id,
            $study_type
        ]);

        require_once 'mail_helper.php';
        
        // Email to User
        $userSubject = "Registration Received - " . MAIL_SCHOOL_NAME;
        $userBody = "
            <h2>Registration Successful</h2>
            <p>Dear {$parent_name},</p>
            <p>We have successfully received your enrollment application for <strong>{$firstStudent['name']}</strong>.</p>
            <p>Our admin team is currently reviewing it. You will receive an email confirmation once your account has been approved and provisioned.</p>
            <p>Thank you for choosing " . MAIL_SCHOOL_NAME . "!</p>
        ";
        sendMail($parent_email, $userSubject, $userBody);

        echo json_encode(['status' => 'success', 'message' => 'Enrollment application successfully received and staged for admin review.']);
    } catch (\PDOException $e) {
        error_log('[SHTA ENROLLMENT ERROR] ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'An error occurred while submitting enrollment. Please try again.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>
