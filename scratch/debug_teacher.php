<?php
require_once __DIR__ . '/../db_connect.php';

header('Content-Type: text/plain');

echo "=== TEACHERS ===\n";
$teachers = $pdo->query("SELECT * FROM teachers")->fetchAll(PDO::FETCH_ASSOC);
print_r($teachers);

echo "\n=== PENDING TEACHERS ===\n";
$pending = $pdo->query("SELECT * FROM pending_teachers")->fetchAll(PDO::FETCH_ASSOC);
print_r($pending);

echo "\n=== TEACHER SUBJECTS ===\n";
$ts = $pdo->query("SELECT * FROM teacher_subjects")->fetchAll(PDO::FETCH_ASSOC);
print_r($ts);

echo "\n=== SYSTEM NOTIFICATIONS ===\n";
$notifs = $pdo->query("SELECT id, title, message FROM system_notifications WHERE message LIKE '%teacher%' OR title LIKE '%teacher%'")->fetchAll(PDO::FETCH_ASSOC);
print_r($notifs);

echo "\n=== SUBJECT AREAS ===\n";
$subs = $pdo->query("SELECT * FROM subject_areas")->fetchAll(PDO::FETCH_ASSOC);
print_r($subs);
?>
