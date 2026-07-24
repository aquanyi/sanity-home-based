<?php
/**
 * split_users_migration.php
 * Splits the unified `users` table into: `admins`, `teachers`, `parents`, `students`, `timetablers`, `accounts_officers`
 */
header('Content-Type: text/plain; charset=utf-8');
if (php_sapi_name() === 'cli') {
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}
require_once 'db_connect.php';

echo "=== STARTING DATABASE USER TABLE SPLIT MIGRATION ===\n\n";

try {
    // 1. Verify users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if (!$stmt->fetch()) {
        echo "Error: 'users' table does not exist. Already migrated?\n";
        exit;
    }

    // 2. Discover foreign keys referencing `users` table
    echo "1. Discovering foreign key constraints referencing 'users'...\n";
    $fkQuery = "
        SELECT TABLE_NAME, CONSTRAINT_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE REFERENCED_TABLE_NAME = 'users' 
          AND TABLE_SCHEMA = DATABASE()
    ";
    $fkeys = $pdo->query($fkQuery)->fetchAll(PDO::FETCH_ASSOC);

    // 3. Drop discovered foreign keys
    echo "2. Dropping foreign key constraints...\n";
    foreach ($fkeys as $fk) {
        $table = $fk['TABLE_NAME'];
        $constraint = $fk['CONSTRAINT_NAME'];
        try {
            $pdo->exec("ALTER TABLE `$table` DROP FOREIGN KEY `$constraint`");
            echo "   Dropped FK '$constraint' from table '$table'\n";
        } catch (\PDOException $ex) {
            echo "   Warning: Failed to drop FK '$constraint' from '$table': " . $ex->getMessage() . "\n";
        }
    }

    // 4. Create new role-specific tables
    $tablesToCreate = [
        'admins' => 'admins',
        'teachers' => 'teachers',
        'parents' => 'parents',
        'students' => 'students',
        'timetablers' => 'timetablers',
        'accounts_officers' => 'accounts_officers'
    ];

    echo "\n3. Creating role-specific tables...\n";
    foreach ($tablesToCreate as $tbl) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `$tbl` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                staff_id VARCHAR(50) UNIQUE NULL,
                name VARCHAR(150) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                phone VARCHAR(20) NOT NULL,
                password VARCHAR(255) NOT NULL,
                must_change_password TINYINT(1) DEFAULT 1,
                security_question VARCHAR(255) NULL,
                security_answer VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        echo "   Created table: $tbl\n";
    }

    // 5. Migrate data preserving IDs
    echo "\n4. Migrating user data...\n";
    $users = $pdo->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC);
    
    $insStmtMap = [];
    foreach ($tablesToCreate as $role => $tbl) {
        $insStmtMap[$role] = $pdo->prepare("
            INSERT INTO `$tbl` (id, staff_id, name, email, phone, password, must_change_password, security_question, security_answer, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
    }
    // Handle accounts officer mapping
    $insStmtMap['accounts'] = $insStmtMap['accounts_officers'];

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
        } else {
            echo "   Warning: Unknown role '{$role}' for user '{$u['email']}'\n";
        }
    }
    
    foreach ($counts as $r => $cnt) {
        echo "   Migrated $cnt users of role '$r'\n";
    }

    // 6. Establish new foreign key constraints referencing the new tables
    echo "\n5. Creating new foreign key constraints...\n";
    
    $newConstraints = [
        // Table => Alter statement
        'student_profiles' => [
            "ALTER TABLE student_profiles ADD CONSTRAINT fk_student_profiles_user FOREIGN KEY (user_id) REFERENCES students(id) ON DELETE CASCADE",
            "ALTER TABLE student_profiles ADD CONSTRAINT fk_student_profiles_parent FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE"
        ],
        'timetable_slots' => [
            "ALTER TABLE timetable_slots ADD CONSTRAINT fk_timetable_slots_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE"
        ],
        'exam_sessions' => [
            "ALTER TABLE exam_sessions ADD CONSTRAINT fk_exam_sessions_invigilator FOREIGN KEY (invigilator_teacher_id) REFERENCES teachers(id)"
        ],
        'student_assignments' => [
            "ALTER TABLE student_assignments ADD CONSTRAINT fk_student_assignments_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE"
        ],
        'academic_reports' => [
            "ALTER TABLE academic_reports ADD CONSTRAINT fk_academic_reports_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE",
            "ALTER TABLE academic_reports ADD CONSTRAINT fk_academic_reports_approver FOREIGN KEY (approved_by) REFERENCES admins(id)"
        ],
        'teacher_subjects' => [
            "ALTER TABLE teacher_subjects ADD CONSTRAINT fk_teacher_subjects_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE"
        ],
        'teacher_pricing' => [
            "ALTER TABLE teacher_pricing ADD CONSTRAINT fk_teacher_pricing_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE"
        ],
        'teacher_payments' => [
            "ALTER TABLE teacher_payments ADD CONSTRAINT fk_teacher_payments_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE"
        ]
    ];

    foreach ($newConstraints as $tbl => $sqls) {
        foreach ($sqls as $sql) {
            try {
                $pdo->exec($sql);
                echo "   Added constraint to $tbl\n";
            } catch (\PDOException $ex) {
                echo "   Warning: Failed to add constraint to $tbl: " . $ex->getMessage() . "\n";
            }
        }
    }

    // Rename users to users_old for backup
    $pdo->exec("RENAME TABLE users TO users_old");
    echo "\n6. Renamed 'users' table to 'users_old' as backup.\n";

    echo "\n🎉 MIGRATION COMPLETED SUCCESSFULLY!\n";
} catch (Exception $e) {
    echo "\n❌ Migration Failed: " . $e->getMessage() . "\n";
}
?>
