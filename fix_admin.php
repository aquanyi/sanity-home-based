<!DOCTYPE html>
<?php
/**
 * fix_admin.php
 * Diagnostic + Auto-Fix tool — runs directly in browser.
 * Fixes the admin account with a correct bcrypt hash for password "12345"
 * URL: http://localhost/sanity%20home%20based/fix_admin.php
 */

$steps   = [];
$success = false;

// Step 1: DB connection
try {
    require_once 'db_connect.php';
    $steps[] = ['ok', 'Database connection', 'Connected to <strong>sanity_db</strong> on localhost'];
} catch (\Throwable $e) {
    $steps[] = ['fail', 'Database connection', $e->getMessage()];
    goto render;
}

// Step 2: Check if admins table exists
try {
    $pdo->query("SELECT 1 FROM admins LIMIT 1");
    $steps[] = ['ok', 'Admins table', 'Table exists and is accessible'];
} catch (\PDOException $e) {
    $steps[] = ['fail', 'Admins table', 'Table missing — please verify database schema. Error: ' . $e->getMessage()];
    goto render;
}

// Step 3: Check existing admin rows
$existing = $pdo->query("SELECT id, name, email, password FROM admins")->fetchAll();
if ($existing) {
    $steps[] = ['info', 'Existing admin rows', count($existing) . ' found: ' . implode(', ', array_column($existing, 'email'))];
} else {
    $steps[] = ['info', 'Existing admin rows', 'None found — will create fresh'];
}

// Step 4: Delete old bad admin row and insert a clean one
$plainPassword  = '12345';
$correctHash    = password_hash($plainPassword, PASSWORD_DEFAULT);
$name           = 'Admin Principal';
$email          = 'admin';
$phone          = '+254700000000';

try {
    // Wipe any existing admin with this email
    $pdo->prepare("DELETE FROM admins WHERE email = ?")->execute([$email]);
    // Insert fresh with correct hash
    $pdo->prepare("INSERT INTO admins (name, email, phone, password, must_change_password) VALUES (?, ?, ?, ?, 0)")
        ->execute([$name, $email, $phone, $correctHash]);
    $newId = $pdo->lastInsertId();
    $steps[] = ['ok', 'Admin account created', "ID #{$newId} | Email: {$email} | Password hash generated fresh"];
} catch (\PDOException $e) {
    $steps[] = ['fail', 'Insert admin', $e->getMessage()];
    goto render;
}

// Step 5: Verify password immediately
$row = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
$row->execute([$newId]);
$savedHash = $row->fetchColumn();
$verified  = password_verify($plainPassword, $savedHash);

if ($verified) {
    $steps[] = ['ok', 'Password verify test', 'password_verify("12345", hash) = <strong>TRUE ✅</strong>'];
    $success = true;
} else {
    $steps[] = ['fail', 'Password verify test', 'password_verify returned FALSE — server PHP version issue'];
}

render:
?>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Fix – S.H.T.A</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:Outfit,sans-serif;}
    body{background:#FAF7F2;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:30px;}
    .card{background:#fff;border-radius:18px;padding:44px;max-width:580px;width:100%;box-shadow:0 10px 40px rgba(74,14,23,0.1);}
    h2{color:#4A0E17;font-size:1.7rem;margin-bottom:6px;font-weight:800;}
    .subtitle{color:#6C757D;font-size:0.95rem;margin-bottom:28px;}
    .step{display:flex;align-items:flex-start;gap:14px;padding:13px 16px;border-radius:10px;margin-bottom:10px;font-size:0.9rem;}
    .step.ok   {background:#D1FAE5;color:#065F46;border:1px solid #A7F3D0;}
    .step.fail {background:#FEE2E2;color:#991B1B;border:1px solid #FCA5A5;}
    .step.info {background:#DBEAFE;color:#1E40AF;border:1px solid #BFDBFE;}
    .icon{font-size:1.2rem;flex-shrink:0;margin-top:1px;}
    .label{font-weight:700;display:block;margin-bottom:3px;}
    .creds{background:#FAF7F2;border-radius:12px;padding:22px;margin:22px 0;border:2px dashed #E5A93B;}
    .creds h3{color:#4A0E17;font-size:1rem;margin-bottom:14px;font-weight:700;}
    .row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #E9ECEF;font-size:0.95rem;}
    .row:last-child{border-bottom:none;}
    .lbl{color:#6C757D;font-weight:600;}
    .val{color:#4A0E17;font-weight:700;font-family:monospace;font-size:1rem;background:#fff;padding:2px 8px;border-radius:4px;}
    .btns{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px;}
    .btn{flex:1;text-align:center;text-decoration:none;padding:14px 24px;border-radius:10px;font-weight:700;font-size:1rem;}
    .btn-primary{background:#4A0E17;color:#fff;}
    .btn-gold{background:#E5A93B;color:#fff;}
    .warn{background:#FEF3C7;border:1px solid #F59E0B;border-radius:8px;padding:12px 16px;color:#92400E;font-size:0.82rem;margin-top:18px;}
    code{background:#F3F4F6;padding:2px 6px;border-radius:4px;font-size:0.85rem;}
</style>
</head>
<body>
<div class="card">
    <h2>🔧 Admin Account Fix Tool</h2>
    <p class="subtitle">Diagnostic and auto-repair for S.H.T.A login system</p>

    <?php foreach ($steps as [$type, $label, $detail]): ?>
    <div class="step <?= $type ?>">
        <span class="icon"><?= $type === 'ok' ? '✅' : ($type === 'fail' ? '❌' : 'ℹ️') ?></span>
        <div><span class="label"><?= $label ?></span><?= $detail ?></div>
    </div>
    <?php endforeach; ?>

    <?php if ($success): ?>
    <div class="creds">
        <h3>🔐 Your Admin Login Credentials</h3>
        <div class="row"><span class="lbl">Login Username</span><span class="val">admin</span></div>
        <div class="row"><span class="lbl">Username (also works)</span><span class="val">Admin Principal</span></div>
        <div class="row"><span class="lbl">Password</span><span class="val">12345</span></div>
        <div class="row"><span class="lbl">Role</span><span class="val">ADMIN</span></div>
    </div>
    <div class="btns">
        <a href="login.html#admin" class="btn btn-primary">Go to Admin Login →</a>
        <a href="admin_dashboard.php" class="btn btn-gold">Go Directly to Dashboard</a>
    </div>
    <div class="warn">⚠️ <strong>Delete <code>fix_admin.php</code> after you log in successfully.</strong></div>
    <?php else: ?>
    <div class="warn" style="margin-top:20px;">
        ❌ Fix failed. Check the errors above.<br><br>
        <strong>Most common cause:</strong> The <code>init.sql</code> file was not imported into phpMyAdmin yet.<br>
        Open phpMyAdmin → Select <code>sanity_db</code> (or create it) → Import → choose <code>init.sql</code>.<br>
        Then reload this page.
    </div>
    <?php endif; ?>
</div>
</body>
</html>
