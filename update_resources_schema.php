<?php
/**
 * update_resources_schema.php
 * Adds indexes and updated_at timestamp to learning_resources table to speed up queries and allow efficient HTTP caching.
 */
// Mock server variables only if running via command line (CLI)
if (php_sapi_name() === 'cli') {
    $_SERVER['SERVER_NAME'] = 'localhost';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}

require_once 'db_connect.php';

try {
    // 1. Add updated_at column to learning_resources
    $checkUpdatedAt = $pdo->query("SHOW COLUMNS FROM learning_resources LIKE 'updated_at'")->fetch();
    if (!$checkUpdatedAt) {
        $pdo->exec("ALTER TABLE learning_resources ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        echo "Added column: updated_at to learning_resources\n";
    } else {
        echo "Column updated_at already exists in learning_resources.\n";
    }

    // 2. Add index on subject column of learning_resources
    try {
        $pdo->exec("CREATE INDEX idx_learning_resources_subject ON learning_resources(subject)");
        echo "Created index idx_learning_resources_subject on learning_resources.\n";
    } catch (\PDOException $e) {
        echo "Index idx_learning_resources_subject already exists or skipped: " . $e->getMessage() . "\n";
    }

    // 3. Add index on updated_at column of learning_resources
    try {
        $pdo->exec("CREATE INDEX idx_learning_resources_updated_at ON learning_resources(updated_at)");
        echo "Created index idx_learning_resources_updated_at on learning_resources.\n";
    } catch (\PDOException $e) {
        echo "Index idx_learning_resources_updated_at already exists or skipped: " . $e->getMessage() . "\n";
    }

    echo "Database resources schema updated successfully!\n";
} catch (\PDOException $e) {
    echo "Schema update failed: " . $e->getMessage() . "\n";
}
?>
