<?php
/**
 * setup_admin.php — ONE-TIME SETUP
 * Run once via browser, then delete for security.
 * URL: http://localhost/sanity%20home%20based/setup_admin.php
 */
require_once 'db_connect.php';

// Fixed credentials
$name     = 'Admin Principal';
$email    = 'admin';
$phone    = '+254700000000';
$password = '12345';
$role     = 'admin';

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

try {
    // Remove any previous admin with this email
    $pdo->prepare("DELETE FROM users WHERE email = ?")->execute([$email]);

    $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $email, $phone, $hashedPassword, $role]);
    $id = $pdo->lastInsertId();

    // Verify the password works right now
    $check = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $check->execute([$id]);
    $saved = $check->fetchColumn();
    $verified = password_verify($password, $saved) ? '✅ Password verify: PASS' : '❌ Password verify: FAIL';

    echo "<!DOCTYPE html><html><head>
    <title>Admin Setup – S.H.T.A</title>
    <link href='https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap' rel='stylesheet'>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:Outfit,sans-serif;}
        body{background:#FAF7F2;display:flex;align-items:center;justify-content:center;min-height:100vh;}
        .card{background:#fff;border-radius:18px;padding:44px;max-width:500px;width:92%;box-shadow:0 10px 40px rgba(74,14,23,0.1);text-align:center;}
        h2{color:#4A0E17;font-size:1.7rem;margin:16px 0 6px;}
        p{color:#6C757D;line-height:1.6;margin-bottom:18px;}
        .creds{background:#FAF7F2;border-radius:10px;padding:22px;margin:18px 0;text-align:left;border:1px solid rgba(74,14,23,0.1);}
        .row{display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid #E9ECEF;font-size:0.95rem;}
        .row:last-child{border-bottom:none;}
        .lbl{color:#6C757D;font-weight:600;}
        .val{color:#4A0E17;font-weight:700;font-family:monospace;font-size:1rem;}
        .verify{font-size:0.85rem;padding:10px;background:#D1FAE5;border-radius:8px;color:#065F46;margin-bottom:16px;font-weight:600;}
        .btn{display:inline-block;background:#4A0E17;color:#fff;text-decoration:none;padding:14px 32px;border-radius:10px;font-weight:700;margin:6px 4px;}
        .btn-gold{background:#E5A93B;}
        .warn{background:#FEF3C7;border:1px solid #F59E0B;border-radius:8px;padding:12px 16px;color:#92400E;font-size:0.83rem;margin-top:18px;text-align:left;}
    </style></head><body>
    <div class='card'>
        <div style='font-size:3.5rem;'>✅</div>
        <h2>Admin Account Ready!</h2>
        <p>Your administrator account has been created with a secure bcrypt-hashed password. You may now log in.</p>
        <div class='verify'>{$verified}</div>
        <div class='creds'>
            <div class='row'><span class='lbl'>Login Email</span><span class='val'>{$email}</span></div>
            <div class='row'><span class='lbl'>Username shortcut</span><span class='val'>admin</span></div>
            <div class='row'><span class='lbl'>Password</span><span class='val'>{$password}</span></div>
            <div class='row'><span class='lbl'>Role</span><span class='val'>ADMIN</span></div>
            <div class='row'><span class='lbl'>User ID</span><span class='val'>#{$id}</span></div>
        </div>
        <a href='login.html#admin' class='btn'>Go to Admin Login →</a>
        <div class='warn'>⚠️ <strong>Delete this file after setup.</strong> Leaving <code>setup_admin.php</code> accessible allows anyone to reset the admin password.</div>
    </div></body></html>";

} catch (\PDOException $e) {
    echo "<div style='padding:40px;font-family:monospace;'>
        <h2 style='color:red;'>Database Error</h2>
        <p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        <p><strong>Check:</strong> Is XAMPP running? Is the database imported from init.sql?</p>
        <p><a href='init.sql'>Download init.sql</a> and import it via phpMyAdmin first.</p>
    </div>";
}
?>
