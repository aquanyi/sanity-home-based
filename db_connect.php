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
?>
