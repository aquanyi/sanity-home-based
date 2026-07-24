<?php
require_once 'db_connect.php';
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_pricing (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL UNIQUE,
            price_online DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            price_offline DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES student_profiles(id) ON DELETE CASCADE
        )
    ");
    echo "student_pricing table created/verified successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
