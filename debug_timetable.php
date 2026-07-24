<?php
/**
 * debug_timetable.php - Run this to diagnose why timetabler shows empty
 * REMOVE THIS FILE from server after debugging is done!
 */
require_once 'db_connect.php';

echo "<pre>";
echo "=== DATABASE TABLE CHECK ===\n\n";

// Check which tables exist
$tables_to_check = ['users', 'students', 'teachers', 'student_profiles', 'teacher_subjects', 'student_subjects', 'timetable_slots', 'subject_areas'];

foreach ($tables_to_check as $tbl) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM `$tbl`")->fetchColumn();
        echo "✅ TABLE '$tbl' EXISTS - $count rows\n";
    } catch (Exception $e) {
        echo "❌ TABLE '$tbl' MISSING or ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\n\n=== STUDENTS CHECK ===\n\n";

// Try students table
try {
    $rows = $pdo->query("SELECT id, name, email, admission_no FROM students LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    echo "Students table (" . count($rows) . " rows):\n";
    foreach ($rows as $r) {
        echo "  - ID:{$r['id']} | {$r['name']} | {$r['email']} | Adm:{$r['admission_no']}\n";
    }
} catch (Exception $e) {
    echo "ERROR reading students table: " . $e->getMessage() . "\n";
}

echo "\n";

// Try users table if it exists
try {
    $rows = $pdo->query("SELECT id, name, email, role FROM users WHERE role='student' LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    echo "Users table (role=student): " . count($rows) . " rows\n";
    foreach ($rows as $r) {
        echo "  - ID:{$r['id']} | {$r['name']} | {$r['email']}\n";
    }
} catch (Exception $e) {
    echo "users table: " . $e->getMessage() . "\n";
}

echo "\n=== STUDENT_PROFILES CHECK ===\n\n";
try {
    $rows = $pdo->query("SELECT sp.id, sp.user_id, sp.grade_level, u.name FROM student_profiles sp LEFT JOIN students u ON sp.user_id = u.id LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    echo "student_profiles (" . count($rows) . " rows):\n";
    foreach ($rows as $r) {
        echo "  - Profile ID:{$r['id']} | user_id:{$r['user_id']} | grade:{$r['grade_level']} | name:{$r['name']}\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    // Try joining with users table
    try {
        $rows = $pdo->query("SELECT sp.id, sp.user_id, sp.grade_level, u.name FROM student_profiles sp LEFT JOIN users u ON sp.user_id = u.id LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
        echo "student_profiles via users table (" . count($rows) . " rows):\n";
        foreach ($rows as $r) {
            echo "  - Profile ID:{$r['id']} | user_id:{$r['user_id']} | grade:{$r['grade_level']} | name:{$r['name']}\n";
        }
    } catch (Exception $e2) {
        echo "ERROR with users join too: " . $e2->getMessage() . "\n";
    }
}

echo "\n=== TEACHERS CHECK ===\n\n";
try {
    $rows = $pdo->query("SELECT id, name, email FROM teachers LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    echo "Teachers table (" . count($rows) . " rows):\n";
    foreach ($rows as $r) {
        echo "  - ID:{$r['id']} | {$r['name']} | {$r['email']}\n";
    }
} catch (Exception $e) {
    echo "ERROR reading teachers: " . $e->getMessage() . "\n";
}

try {
    $rows = $pdo->query("SELECT id, name, email FROM users WHERE role='teacher' LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    echo "Users table (role=teacher): " . count($rows) . " rows\n";
    foreach ($rows as $r) {
        echo "  - ID:{$r['id']} | {$r['name']} | {$r['email']}\n";
    }
} catch (Exception $e) {
    echo "users (teacher): " . $e->getMessage() . "\n";
}

echo "\n=== FINAL: What timetable API query would return ===\n\n";
try {
    $result = $pdo->query("
        SELECT 
            COALESCE(sp.id, u.id) AS id,
            u.id AS user_id,
            u.name AS student_name,
            COALESCE(sp.grade_level, 'Student') AS grade_level,
            u.admission_no,
            u.staff_id
        FROM students u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        ORDER BY u.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    echo "Query returned " . count($result) . " students:\n";
    foreach ($result as $r) {
        echo "  - {$r['student_name']} (Profile ID:{$r['id']}, User ID:{$r['user_id']}, Grade:{$r['grade_level']})\n";
    }
} catch (Exception $e) {
    echo "QUERY FAILED: " . $e->getMessage() . "\n";
}

echo "\n=== STUDENT PROFILES FOREIGN KEY CHECK ===\n";
try {
    // Show orphan students (students without profiles)
    $orphans = $pdo->query("
        SELECT u.id, u.name FROM students u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE sp.id IS NULL
    ")->fetchAll(PDO::FETCH_ASSOC);
    echo "Students WITHOUT profiles (orphans): " . count($orphans) . "\n";
    foreach ($orphans as $o) {
        echo "  - ID:{$o['id']} | {$o['name']}\n";
    }
} catch (Exception $e) {
    echo "orphan check error: " . $e->getMessage() . "\n";
}

echo "\n</pre>";
?>
