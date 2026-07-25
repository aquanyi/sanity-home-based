<?php
/**
 * config.php
 * Centralized dynamic environment configuration for S.H.T.A.
 * Detects whether the system is running locally or in Namecheap hosting.
 */

// ── Timezone: East Africa Time (Nairobi, UTC+3) ──
date_default_timezone_set('Africa/Nairobi');

// ── Namecheap Hosting Database Configuration ──
define('DB_HOST', 'localhost');
define('DB_NAME', 'sanipjgf_sanity_db');
define('DB_USER', 'sanipjgf_sanity_db');
define('DB_PASS', 'Kilingili2017.');
define('DB_CHARSET', 'utf8mb4');

// ── SMTP Configuration ──
define('SMTP_HOST', 'sanityeducation.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'admin@sanityeducation.com');
define('SMTP_PASS', 'RlT_7zFggmK#5P!k');
define('SMTP_FROM_NAME', 'Sanity Education');
define('ADMIN_EMAIL', 'admin@sanityeducation.com');
