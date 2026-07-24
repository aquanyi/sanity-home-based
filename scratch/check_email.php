<?php
require 'db_connect.php';
$email = 'elsie.maina10@gmail.com';
$tables = ['admins', 'teachers', 'parents', 'students', 'timetablers', 'accounts_officers'];
foreach($tables as $tbl) {
    $stmt = $pdo->prepare("SELECT id FROM $tbl WHERE email = ?");
    $stmt->execute([$email]);
    while($row = $stmt->fetch()) {
        echo "$tbl: id {$row['id']}\n";
    }
}
