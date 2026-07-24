<?php
require_once 'db_connect.php';

try {
    // Check if student_id exists in exam_sessions
    $check = $pdo->query("SHOW COLUMNS FROM exam_sessions LIKE 'student_id'")->fetch();
    
    if (!$check) {
        // Add student_id column
        $pdo->exec("ALTER TABLE exam_sessions ADD COLUMN student_id INT NULL");
        
        // Try adding the foreign key (ignoring errors if student_profiles doesn't exist for some reason)
        try {
            $pdo->exec("ALTER TABLE exam_sessions ADD CONSTRAINT fk_exam_sessions_student FOREIGN KEY (student_id) REFERENCES student_profiles(id) ON DELETE CASCADE");
        } catch (Exception $e) {
            echo "Column added, but foreign key could not be linked (this is fine if student_profiles structure differs): " . $e->getMessage() . "<br>";
        }
        
        echo "<h1>Success!</h1><p>The missing 'student_id' column was added to the 'exam_sessions' table successfully.</p>";
    } else {
        echo "<h1>Already Fixed</h1><p>The 'student_id' column already exists in 'exam_sessions'.</p>";
    }

} catch (\PDOException $e) {
    echo "<h1>Error</h1><p>" . $e->getMessage() . "</p>";
}
?>
