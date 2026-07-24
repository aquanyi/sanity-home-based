const fs = require('fs');

// ─── 1. Patch api_teacher_register.php ───
let reg = fs.readFileSync('api/api_teacher_register.php', 'utf8');

const regInsertSuccess = `            echo json_encode([
                'status' => 'success',
                'message' => 'Registration request submitted successfully! Your account is pending admin approval.'
            ]);`;

const regNotifCode = `            // Dispatch Admin System Notification
            try {
                // Fetch subject names for notification
                $subjNames = [];
                if (!empty($subjectIds)) {
                    $stmtSub = $pdo->query("SELECT id, name FROM subject_areas");
                    $subjects = $stmtSub->fetchAll(PDO::FETCH_KEY_PAIR);
                    foreach (explode(',', $subjectIds) as $sId) {
                        if (isset($subjects[$sId])) {
                            $subjNames[] = $subjects[$sId];
                        }
                    }
                }
                $subjList = implode(', ', $subjNames);
                
                $notifMsg = "Teacher " . $name . " has requested registration.\\n";
                $notifMsg .= "Email: " . $email . " | Phone: " . $phone . "\\n";
                if (!empty($subjList)) {
                    $notifMsg .= "Teaching Subjects: " . $subjList . "\\n";
                }
                if (!empty($customSubjects)) {
                    $notifMsg .= "Suggested New Subjects: " . $customSubjects . "\\n";
                }
                $notifMsg .= "Please open the 'Role Delegation' panel to review and approve.";

                $notifStmt = $pdo->prepare("INSERT INTO system_notifications (sender_name, recipient_role, title, message) VALUES ('System', 'admin', ?, ?)");
                $notifStmt->execute([
                    "New Teacher Registration: " . $name,
                    $notifMsg
                ]);
            } catch (\\Exception $notifEx) {
                // Ignore notifications failure so registration still succeeds
            }
`;

if (reg.includes(regInsertSuccess)) {
    reg = reg.replace(regInsertSuccess, regNotifCode + '\n' + regInsertSuccess);
    fs.writeFileSync('api/api_teacher_register.php', reg, 'utf8');
    console.log('✅ Injected notifications into api_teacher_register.php');
}


// ─── 2. Patch api_lesson_attendance.php ───
let att = fs.readFileSync('api/api_lesson_attendance.php', 'utf8');

// For start_lesson OTP (verify_otp)
const checkInSuccess = `        echo json_encode([
            'status'        => 'success',
            'message'       => "✅ OTP Verified. Lesson is now IN PROGRESS. Check-in locked at {$checkInTime}.",
            'check_in_time' => $checkInTime
        ]);`;

const checkInNotifCode = `        // Dispatch Check-In Admin System Notification
        try {
            $notifStmt = $pdo->prepare("INSERT INTO system_notifications (sender_name, recipient_role, title, message) VALUES (?, 'admin', ?, ?)");
            $notifStmt->execute([
                $lesson['teacher_name'],
                "Lesson Started: " . $lesson['student_name'],
                "Teacher " . $lesson['teacher_name'] . " has verified the OTP and commenced lesson with student " . $lesson['student_name'] . " at " . $checkInTime . ". Format: " . $venueLabel . "."
            ]);
        } catch (\\Exception $notifEx) {
            // Ignore
        }
`;

if (att.includes(checkInSuccess)) {
    att = att.replace(checkInSuccess, checkInNotifCode + '\n' + checkInSuccess);
} else {
    // Try with alternative carriage returns
    const checkInSuccessCRLF = checkInSuccess.replace(/\n/g, '\r\n');
    att = att.replace(checkInSuccessCRLF, checkInNotifCode.replace(/\n/g, '\r\n') + '\r\n' + checkInSuccessCRLF);
}

// For end_lesson checkout
const checkOutSuccess = `        echo json_encode([
            'status'         => 'success',
            'message'        => "✅ Session closed. Check-out locked at {$checkOutTime}. Full session report emailed to parent and all admin addresses.",
            'check_out_time' => $checkOutTime
        ]);`;

const checkOutNotifCode = `        // Dispatch Check-Out Admin System Notification
        try {
            $notifMsg = "Teacher " . $lesson['teacher_name'] . " completed the session with student " . $lesson['student_name'] . " at " . $checkOutTime . ".\\n";
            $notifMsg .= "Checked in: " . $checkInTime . " | Checked out: " . $checkOutTime . "\\n\\n";
            $notifMsg .= "Topics Covered: " . $topics_covered . "\\n";
            $notifMsg .= "Progress Notes: " . $progress_notes . "\\n";
            $notifMsg .= "Homework: " . $homework_assigned;

            $notifStmt = $pdo->prepare("INSERT INTO system_notifications (sender_name, recipient_role, title, message) VALUES (?, 'admin', ?, ?)");
            $notifStmt->execute([
                $lesson['teacher_name'],
                "Lesson Completed: " . $lesson['student_name'],
                $notifMsg
            ]);
        } catch (\\Exception $notifEx) {
            // Ignore
        }
`;

if (att.includes(checkOutSuccess)) {
    att = att.replace(checkOutSuccess, checkOutNotifCode + '\n' + checkOutSuccess);
} else {
    const checkOutSuccessCRLF = checkOutSuccess.replace(/\n/g, '\r\n');
    att = att.replace(checkOutSuccessCRLF, checkOutNotifCode.replace(/\n/g, '\r\n') + '\r\n' + checkOutSuccessCRLF);
}

fs.writeFileSync('api/api_lesson_attendance.php', att, 'utf8');
console.log('✅ Injected notifications into api_lesson_attendance.php');
