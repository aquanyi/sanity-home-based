<?php
header('Content-Type: text/html; charset=utf-8');
require_once 'security.php';
start_secure_session();

$role = $_SESSION['user_role'] ?? 'parent';
$hash_map = [
    'admin'      => 'admin',
    'teacher'    => 'teachers',
    'parent'     => 'parent',
    'timetabler' => 'timetable',
    'accounts'   => 'accounts',
];
$hash = $hash_map[$role] ?? 'parent';

// Regenerate the session ID before destroying to invalidate the old one
session_regenerate_id(true);

// Clear all session data
$_SESSION = [];

// Explicitly expire the session cookie in the browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();
header("Location: login.html#$hash");
exit;
?>
