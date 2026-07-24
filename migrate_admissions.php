<?php
/**
 * migrate_admissions.php
 * Run this script ONCE in your browser to update all existing students
 * to the new S000A admission number format.
 */

require_once 'db_connect.php'; // Make sure this points to your database connection file

try {
    // 1. Find all students that have an admission number
    $stmt = $pdo->query("SELECT id, admission_no FROM students WHERE admission_no IS NOT NULL AND admission_no != ''");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updatedCount = 0;
    
    $updateStmt = $pdo->prepare("UPDATE students SET admission_no = ? WHERE id = ?");

    foreach ($students as $student) {
        $oldAdm = $student['admission_no'];
        $newAdm = $oldAdm;

        // Check if it matches the old A000S format
        if (preg_match('/^A([0-9]+)S$/', $oldAdm, $matches)) {
            // Convert A000S to S000A while keeping the numbers intact
            $numbers = $matches[1];
            $newAdm = 'S' . $numbers . 'A';
        } 
        // Or if it just doesn't start with S or end with A but has numbers we can try to fix it
        else if (!preg_match('/^S[0-9]+A$/', $oldAdm)) {
            // Extract numbers if possible
            preg_match('/([0-9]+)/', $oldAdm, $numMatches);
            if (!empty($numMatches[1])) {
                $newAdm = 'S' . sprintf('%03d', $numMatches[1]) . 'A';
            }
        }

        // Only update if it actually changed
        if ($newAdm !== $oldAdm) {
            $updateStmt->execute([$newAdm, $student['id']]);
            $updatedCount++;
        }
    }

    echo "<h3>Success!</h3>";
    echo "<p>Updated {$updatedCount} existing student admission numbers to the new S000A format.</p>";
    echo "<p>You can now delete this file from your server.</p>";

} catch (Exception $e) {
    echo "<h3>Error</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
