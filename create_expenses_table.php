<?php
require 'db_connect.php';
$sql = "CREATE TABLE IF NOT EXISTS extra_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category ENUM('inventory', 'utility', 'general_repairs', 'petty_cash') NOT NULL,
    item_name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    expense_date DATE NOT NULL,
    recorded_by INT NOT NULL,
    reference VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;";

try {
    $pdo->exec($sql);
    echo '<div style="font-family:sans-serif;padding:30px;background:#D1FAE5;border-radius:10px;color:#065F46;">';
    echo '<h2>✅ extra_expenses table created successfully!</h2>';
    echo '<p>You can now use the Extra Expenses tab in the Accounts Dashboard.</p>';
    echo '<p><a href="accounts_dashboard.php" style="color:#065F46;font-weight:700;">→ Go to Accounts Dashboard</a></p>';
    echo '</div>';
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "already exists") !== false) {
        echo '<div style="font-family:sans-serif;padding:30px;background:#DBEAFE;border-radius:10px;color:#1E40AF;">';
        echo '<h2>ℹ️ Table already exists — no changes needed.</h2>';
        echo '<p><a href="accounts_dashboard.php" style="color:#1E40AF;font-weight:700;">→ Go to Accounts Dashboard</a></p>';
        echo '</div>';
    } else {
        echo '<div style="font-family:sans-serif;padding:30px;background:#FEE2E2;border-radius:10px;color:#991B1B;">';
        echo '<h2>❌ Error: ' . htmlspecialchars($e->getMessage()) . '</h2>';
        echo '</div>';
    }
}
?>
