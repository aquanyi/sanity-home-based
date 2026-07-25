<?php
/**
 * security.php — Shared Security Helper
 * S.H.T.A School Management System
 *
 * Provides:
 *   - start_secure_session()   : Hardened session with idle timeout & cookie protection
 *   - generate_csrf_token()    : Returns a per-session CSRF token (creates one if absent)
 *   - validate_csrf_token($t)  : Validates a submitted token; exits with error on failure
 *   - check_login_attempts()   : Blocks IP after too many failures (rate limiter)
 *   - record_login_failure()   : Increments failure count for an IP
 *   - clear_login_attempts()   : Clears failure count after successful login
 *
 * Include ONCE at the top of every PHP file that handles user input.
 */

define('SESSION_IDLE_TIMEOUT', 1800);   // 30 minutes
define('LOGIN_MAX_ATTEMPTS',   5);      // Max failures before lockout
define('LOGIN_LOCKOUT_WINDOW', 900);    // Lockout window: 15 minutes (seconds)

// ─────────────────────────────────────────────────────────────────────────────
// 1. Secure Session Initialisation
// ─────────────────────────────────────────────────────────────────────────────

function start_secure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // Harden session cookie parameters before starting
        session_set_cookie_params([
            'lifetime' => 0,                // Browser-session cookie (expires when tab closes)
            'path'     => '/',
            'domain'   => '',               // Current domain only
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,             // Inaccessible to JavaScript
            'samesite' => 'Strict',         // CSRF mitigation
        ]);
        session_start();
    }

    // Enforce idle timeout
    if (isset($_SESSION['_last_activity'])) {
        if ((time() - $_SESSION['_last_activity']) > SESSION_IDLE_TIMEOUT) {
            // Session has expired — destroy and redirect
            session_unset();
            session_destroy();
            // Restart a clean session to set the flash message
            session_start();
            $_SESSION['_last_activity'] = time();
            
            // Check if it's an API request
            if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['status' => 'error', 'message' => 'session_expired']);
                exit;
            }
            
            $__script = basename($_SERVER['SCRIPT_NAME'] ?? '');
            $__hash   = '#parent';
            if (strpos($__script, 'accounts') !== false)      { $__hash = '#accounts'; }
            elseif (strpos($__script, 'admin') !== false)     { $__hash = '#admin'; }
            elseif (strpos($__script, 'teacher') !== false)   { $__hash = '#teachers'; }
            elseif (strpos($__script, 'timetabler') !== false){ $__hash = '#timetable'; }
            elseif (strpos($__script, 'student') !== false)   { $__hash = '#student'; }
            header('Location: login.html?error=' . urlencode('Your session expired. Please log in again.') . $__hash);
            exit;
        }
    }
    $_SESSION['_last_activity'] = time();

    // Bind session to user-agent fingerprint to detect session hijacking
    $ua_hash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    if (isset($_SESSION['_ua_hash'])) {
        if ($_SESSION['_ua_hash'] !== $ua_hash) {
            // Possible session hijack — invalidate
            session_unset();
            session_destroy();
            session_start();
            $__script = basename($_SERVER['SCRIPT_NAME'] ?? '');
            $__hash   = '#parent';
            if (strpos($__script, 'accounts') !== false)      { $__hash = '#accounts'; }
            elseif (strpos($__script, 'admin') !== false)     { $__hash = '#admin'; }
            elseif (strpos($__script, 'teacher') !== false)   { $__hash = '#teachers'; }
            elseif (strpos($__script, 'timetabler') !== false){ $__hash = '#timetable'; }
            elseif (strpos($__script, 'student') !== false)   { $__hash = '#student'; }
            header('Location: login.html?error=' . urlencode('Security alert: session invalidated. Please log in again.') . $__hash);
            exit;
        }
    } else {
        $_SESSION['_ua_hash'] = $ua_hash;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. CSRF Protection
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Returns the current session's CSRF token, generating one if it doesn't exist.
 * Call this inside every HTML form that submits state-changing data.
 *
 * Usage in a form:
 *   <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates a submitted CSRF token.
 * Call this at the top of every POST handler.
 * On failure, halts execution and returns a 403 JSON or redirect depending on context.
 *
 * @param string $submitted_token   The value from $_POST['csrf_token']
 * @param bool   $is_api            If true, returns JSON 403. If false, redirects to login.
 */
function validate_csrf_token(string $submitted_token, bool $is_api = false): void {
    $stored = $_SESSION['csrf_token'] ?? '';
    if (empty($stored) || !hash_equals($stored, $submitted_token)) {
        if ($is_api) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Invalid or expired security token. Please refresh and try again.']);
        } else {
            http_response_code(403);
            $__script = basename($_SERVER['SCRIPT_NAME'] ?? '');
            $__hash   = '#parent';
            if (strpos($__script, 'accounts') !== false)      { $__hash = '#accounts'; }
            elseif (strpos($__script, 'admin') !== false)     { $__hash = '#admin'; }
            elseif (strpos($__script, 'teacher') !== false)   { $__hash = '#teachers'; }
            elseif (strpos($__script, 'timetabler') !== false){ $__hash = '#timetable'; }
            elseif (strpos($__script, 'student') !== false)   { $__hash = '#student'; }
            header('Location: login.html?error=' . urlencode('Security validation failed. Please try again.') . $__hash);
        }
        exit;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. Login Rate Limiter (IP-based, backed by DB)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Ensures the login_attempts table exists (runs once, idempotent).
 * Called automatically by the rate-limit functions.
 */
function _ensure_rate_limit_table(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            ip_address  VARCHAR(45) NOT NULL,
            attempts    SMALLINT    NOT NULL DEFAULT 1,
            last_attempt TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_ip (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $checked = true;
}

/**
 * Returns the visitor's real IP, accounting for common proxies.
 */
function get_visitor_ip(): string {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            // X-Forwarded-For can be a comma-separated list; take the first
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

/**
 * Check if this IP is currently rate-limited.
 * Call BEFORE processing login credentials.
 * Returns the number of seconds remaining in the lockout (0 = not locked).
 *
 * @param PDO $pdo
 * @return int  Seconds remaining in lockout. 0 means not locked out.
 */
function check_login_attempts(PDO $pdo): int {
    _ensure_rate_limit_table($pdo);
    $ip  = get_visitor_ip();
    $stmt = $pdo->prepare("
        SELECT attempts, TIMESTAMPDIFF(SECOND, last_attempt, NOW()) AS age_sec
        FROM login_attempts
        WHERE ip_address = ?
    ");
    $stmt->execute([$ip]);
    $row = $stmt->fetch();

    if (!$row) return 0;

    // If the window has expired, clean up stale record
    if ($row['age_sec'] >= LOGIN_LOCKOUT_WINDOW) {
        $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$ip]);
        return 0;
    }

    if ($row['attempts'] >= LOGIN_MAX_ATTEMPTS) {
        return LOGIN_LOCKOUT_WINDOW - $row['age_sec']; // seconds remaining
    }

    return 0;
}

/**
 * Record a failed login attempt for this IP.
 * Call AFTER a failed authentication.
 *
 * @param PDO $pdo
 */
function record_login_failure(PDO $pdo): void {
    _ensure_rate_limit_table($pdo);
    $ip = get_visitor_ip();
    $pdo->prepare("
        INSERT INTO login_attempts (ip_address, attempts, last_attempt)
        VALUES (?, 1, NOW())
        ON DUPLICATE KEY UPDATE
            attempts     = IF(TIMESTAMPDIFF(SECOND, last_attempt, NOW()) >= " . LOGIN_LOCKOUT_WINDOW . ", 1, attempts + 1),
            last_attempt = NOW()
    ")->execute([$ip]);
}

/**
 * Clear all failed attempts for this IP after a successful login.
 *
 * @param PDO $pdo
 */
function clear_login_attempts(PDO $pdo): void {
    _ensure_rate_limit_table($pdo);
    $ip = get_visitor_ip();
    $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$ip]);
}
