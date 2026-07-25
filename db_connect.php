<?php
// MySQL database connection configuration for S.H.T.A
require_once __DIR__ . '/config.php';

$host = DB_HOST;
$db   = DB_NAME;
$user = DB_USER;
$pass = DB_PASS;
$charset = DB_CHARSET;

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     // Force MySQL session to use East Africa Time (UTC+3)
     $pdo->exec("SET time_zone = '+03:00'");
} catch (\PDOException $e) {
     // Log the real error privately — never expose to the client
     error_log('[SHTA DB ERROR] ' . $e->getMessage());
     // Return a safe, generic error response
     header('Content-Type: application/json');
     http_response_code(503);
     echo json_encode([
         'status'  => 'error',
         'message' => 'Service temporarily unavailable. Please try again later.'
     ]);
     exit;
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contract_teachers (
            id             INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id     INT NOT NULL,
            basic_salary   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            contract_start DATE NOT NULL,
            contract_end   DATE NOT NULL,
            created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_teacher (teacher_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contract_teacher_disbursements (
            id                 INT AUTO_INCREMENT PRIMARY KEY,
            contract_teacher_id INT NOT NULL,
            amount             DECIMAL(10,2) NOT NULL,
            payment_date       DATE NOT NULL,
            reference          VARCHAR(150) NULL,
            created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (\PDOException $e) {
    // Log or ignore if already configured
}
?>
