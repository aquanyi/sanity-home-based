<?php
require_once 'db_connect.php';
date_default_timezone_set('Africa/Nairobi');

echo "PHP timezone: " . date_default_timezone_get() . "\n";
echo "PHP local time: " . date('Y-m-d H:i:s') . "\n";

try {
    $stmt = $pdo->query("SELECT NOW() as mysql_now, @@global.time_zone, @@session.time_zone");
    $row = $stmt->fetch();
    echo "MySQL NOW(): " . $row['mysql_now'] . "\n";
    echo "MySQL Global Timezone: " . $row['global.time_zone'] . "\n";
    echo "MySQL Session Timezone: " . $row['session.time_zone'] . "\n";
    
    $stmt = $pdo->query("SELECT id, title, created_at FROM system_notifications ORDER BY id DESC LIMIT 5");
    echo "\nRecent Notifications:\n";
    while ($n = $stmt->fetch()) {
        echo "ID: {$n['id']} | Title: {$n['title']} | Created At: {$n['created_at']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
