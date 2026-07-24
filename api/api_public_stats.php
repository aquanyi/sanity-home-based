<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../db_connect.php';

try {
    // Scheduled lessons 
    $scheduled_lessons = $pdo->query("SELECT COUNT(*) FROM timetable_slots")->fetchColumn();
    
    // Active users 
    $active_users = 
        $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn() +
        $pdo->query("SELECT COUNT(*) FROM timetablers")->fetchColumn() +
        $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn() +
        $pdo->query("SELECT COUNT(*) FROM parents")->fetchColumn() +
        $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn() +
        $pdo->query("SELECT COUNT(*) FROM accounts_officers")->fetchColumn();

    // New applications (pending leads)
    $new_applications = $pdo->query("SELECT COUNT(*) FROM enrollment_inquiries WHERE status = 'pending'")->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'scheduled_lessons' => $scheduled_lessons,
        'active_users' => $active_users,
        'new_applications' => $new_applications
    ]);
} catch (\PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
}
