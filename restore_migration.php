<?php
/**
 * restore_migration.php
 * Recovers and correctly maps users from users_old to role-specific tables.
 */
header('Content-Type: text/plain; charset=utf-8');
if (php_sapi_name() === 'cli') {
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}
require_once 'db_connect.php';

echo "=== STARTING DATABASE USER DATA RESTORE ===\n\n";

try {
    // 1. Verify users_old table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users_old'");
    if (!$stmt->fetch()) {
        echo "Error: 'users_old' table does not exist. Cannot restore.\n";
        exit;
    }

    // 2. Define role table mapping
    $roleToTableMap = [
        'admin' => 'admins',
        'timetabler' => 'timetablers',
        'teacher' => 'teachers',
        'parent' => 'parents',
        'student' => 'students',
        'accounts' => 'accounts_officers'
    ];

    // Disable foreign key checks for migration
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Truncate tables first to avoid duplicate errors on re-run
    echo "1. Cleaning up destination tables...\n";
    foreach ($roleToTableMap as $role => $tbl) {
        $pdo->exec("TRUNCATE TABLE `$tbl`");
        echo "   Cleared table: $tbl\n";
    }

    // 3. Fetch all old users
    echo "\n2. Fetching users from users_old...\n";
    $users = $pdo->query("SELECT * FROM users_old")->fetchAll(PDO::FETCH_ASSOC);
    echo "   Found " . count($users) . " records to migrate.\n";

    // 4. Prepare insert statements
    $insStmtMap = [];
    foreach ($roleToTableMap as $role => $tbl) {
        $insStmtMap[$role] = $pdo->prepare("
            INSERT INTO `$tbl` (id, staff_id, name, email, phone, password, must_change_password, security_question, security_answer, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
    }

    // 5. Migrate data
    echo "\n3. Migrating records...\n";
    $counts = [];
    foreach ($users as $u) {
        $role = $u['role'];
        if (isset($insStmtMap[$role])) {
            $insStmtMap[$role]->execute([
                $u['id'],
                $u['staff_id'],
                $u['name'],
                $u['email'],
                $u['phone'],
                $u['password'],
                $u['must_change_password'],
                $u['security_question'],
                $u['security_answer'],
                $u['created_at']
            ]);
            $counts[$role] = ($counts[$role] ?? 0) + 1;
            echo "   ✔ Migrated {$u['name']} ({$u['email']}) -> $role\n";
        } else {
            echo "   ❌ Error: Unknown role '{$role}' for user '{$u['email']}'\n";
        }
    }

    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "\nSummary of migrated accounts:\n";
    foreach ($counts as $r => $cnt) {
        echo "   • Role '$r': $cnt accounts\n";
    }

    echo "\n🎉 RESTORATION COMPLETED SUCCESSFULLY!\n";
} catch (Exception $e) {
    // Attempt to re-enable even if failed
    try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1"); } catch(Exception $ex){}
    echo "\n❌ Restoration Failed: " . $e->getMessage() . "\n";
}
?>
