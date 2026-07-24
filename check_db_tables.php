<?php
header('Content-Type: text/plain; charset=utf-8');
require_once 'db_connect.php';

echo "=== CURRENT DATABASE TABLES ===\n\n";

try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($tables)) {
        echo "No tables found in the database. Is it empty?\n";
    } else {
        foreach ($tables as $table) {
            echo "• $table\n";
        }
    }
} catch (Exception $e) {
    echo "Error querying database: " . $e->getMessage() . "\n";
}
?>
