<?php
/**
 * config.php
 * Centralized dynamic environment configuration for S.H.T.A.
 * Detects whether the system is running locally or in Namecheap hosting.
 */

// ── Timezone: East Africa Time (Nairobi, UTC+3) ──
date_default_timezone_set('Africa/Nairobi');

// Determine if we are running in local environment (XAMPP / localhost)
$hostHeader = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal = in_array($remoteAddr, ['127.0.0.1', '::1']) 
           || (strpos($hostHeader, 'localhost') !== false) 
           || (strpos($hostHeader, '127.0.0.1') !== false)
           || (strpos($remoteAddr, '192.168.') === 0)
           || (strpos($remoteAddr, '10.') === 0)
           || php_sapi_name() === 'cli';

if ($isLocal) {
    // ── Local Database Configuration ──
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'sanity_db');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8mb4');
} else {
    // ── Namecheap Hosting Database Configuration ──
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'sanipjgf_sanity_db');
    define('DB_USER', 'sanipjgf_sanity_db');
    define('DB_PASS', 'Kilingili2017.');
    define('DB_CHARSET', 'utf8mb4');
}

// ── SMTP Configuration ──
define('SMTP_HOST', 'sanityeducation.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'admin@sanityeducation.com');
define('SMTP_PASS', 'RlT_7zFggmK#5P!k');
define('SMTP_FROM_NAME', 'Sanity Education');
define('ADMIN_EMAIL', 'admin@sanityeducation.com');
