<?php
/**
 * test_mail.php
 * SMTP Diagnostic Tool — run this on the live server to pinpoint email failures.
 * DELETE THIS FILE after testing!
 */

// Basic auth — change this password before uploading
$testPassword = 'shta_test_2026';
if (!isset($_GET['key']) || $_GET['key'] !== $testPassword) {
    die('<h2>Access denied. Append ?key=shta_test_2026 to the URL.</h2>');
}

require_once __DIR__ . '/vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/src/SMTP.php';
require_once __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$testTo      = $_GET['to']   ?? '';
$testHost    = $_GET['host'] ?? SMTP_HOST;
$testPort    = (int)($_GET['port'] ?? SMTP_PORT);
$testSecure  = $_GET['secure'] ?? 'smtps'; // smtps or tls

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>SMTP Test — SHTA</title>
<style>
  body { font-family: monospace; background: #1a1a2e; color: #eee; padding: 30px; }
  h1 { color: #E5A93B; }
  .box { background: #16213e; border-radius: 8px; padding: 20px; margin: 20px 0; }
  .ok  { color: #4ade80; }
  .err { color: #f87171; }
  .info { color: #93c5fd; }
  input, select { background:#0f3460; color:#eee; border:1px solid #444; padding:8px 12px; border-radius:4px; width:300px; }
  button { background:#4A0E17; color:#E5A93B; border:none; padding:10px 24px; border-radius:4px; cursor:pointer; font-size:15px; margin-top:10px; }
  label { display:block; margin-top:12px; color:#93c5fd; font-size:13px; }
  table { width:100%; border-collapse:collapse; }
  td { padding:8px 12px; border-bottom:1px solid #333; }
  td:first-child { color:#93c5fd; width:200px; }
</style>
</head>
<body>
<h1>🔧 SMTP Diagnostic — Sanity Homebased Tuition Academy</h1>

<div class="box">
  <h3 style="color:#E5A93B;margin-top:0;">Current SMTP Configuration (from config.php)</h3>
  <table>
    <tr><td>SMTP_HOST</td><td><?= htmlspecialchars(SMTP_HOST) ?></td></tr>
    <tr><td>SMTP_PORT</td><td><?= htmlspecialchars(SMTP_PORT) ?></td></tr>
    <tr><td>SMTP_USER</td><td><?= htmlspecialchars(SMTP_USER) ?></td></tr>
    <tr><td>SMTP_PASS</td><td><?= str_repeat('*', strlen(SMTP_PASS)) ?> (<?= strlen(SMTP_PASS) ?> chars)</td></tr>
    <tr><td>Encryption</td><td>SMTPS (Implicit SSL)</td></tr>
    <tr><td>PHP Version</td><td><?= phpversion() ?></td></tr>
    <tr><td>Server</td><td><?= htmlspecialchars($_SERVER['SERVER_NAME'] ?? 'unknown') ?></td></tr>
  </table>
</div>

<div class="box">
  <h3 style="color:#E5A93B;margin-top:0;">Send a Test Email</h3>
  <form method="GET">
    <input type="hidden" name="key" value="<?= htmlspecialchars($testPassword) ?>">
    <label>Send test email TO:</label>
    <input type="email" name="to" value="<?= htmlspecialchars($testTo) ?>" placeholder="your@email.com" required>

    <label>SMTP Host (try alternatives if current fails):</label>
    <select name="host">
      <option value="<?= SMTP_HOST ?>" <?= $testHost === SMTP_HOST ? 'selected':'' ?>><?= SMTP_HOST ?> (current)</option>
      <option value="mail.sanityeducation.com" <?= $testHost === 'mail.sanityeducation.com' ? 'selected':'' ?>>mail.sanityeducation.com</option>
      <option value="localhost" <?= $testHost === 'localhost' ? 'selected':'' ?>>localhost (cPanel internal)</option>
    </select>

    <label>Port:</label>
    <select name="port">
      <option value="465" <?= $testPort === 465 ? 'selected':'' ?>>465 (SMTPS — Implicit SSL)</option>
      <option value="587" <?= $testPort === 587 ? 'selected':'' ?>>587 (STARTTLS)</option>
      <option value="25"  <?= $testPort === 25  ? 'selected':'' ?>>25  (Plain — last resort)</option>
    </select>

    <label>Encryption:</label>
    <select name="secure">
      <option value="smtps" <?= $testSecure === 'smtps' ? 'selected':'' ?>>SMTPS (Implicit SSL — port 465)</option>
      <option value="tls"   <?= $testSecure === 'tls'   ? 'selected':'' ?>>STARTTLS (port 587)</option>
    </select>

    <br>
    <button type="submit" name="send" value="1">▶ Send Test Email</button>
  </form>
</div>

<?php if (isset($_GET['send']) && $testTo): ?>
<div class="box">
  <h3 style="color:#E5A93B;margin-top:0;">📨 Test Result</h3>
  <?php
  $mail = new PHPMailer(true);
  $mail->SMTPDebug  = 3;  // Verbose output
  $mail->Debugoutput = function($str, $level) {
      $color = str_contains($str, 'ERROR') || str_contains($str, 'FAIL') ? 'err' : 'info';
      echo "<div class='{$color}'>".htmlspecialchars($str)."</div>";
  };

  try {
      $mail->isSMTP();
      $mail->Host       = $testHost;
      $mail->SMTPAuth   = true;
      $mail->Username   = SMTP_USER;
      $mail->Password   = SMTP_PASS;
      $mail->Port       = $testPort;

      if ($testSecure === 'tls') {
          $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
      } else {
          $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
      }

      $mail->SMTPOptions = array(
          'ssl' => array(
              'verify_peer'       => false,
              'verify_peer_name'  => false,
              'allow_self_signed' => true
          )
      );

      $mail->setFrom(SMTP_USER, 'Sanity Education Test');
      $mail->addAddress($testTo);
      $mail->isHTML(true);
      $mail->Subject = '✅ SMTP Test — Sanity Homebased Tuition Academy';
      $mail->Body    = '<h2>SMTP Test Successful!</h2><p>If you received this email, your SMTP configuration is working correctly on the live server.</p><p><strong>Host:</strong> '.$testHost.'<br><strong>Port:</strong> '.$testPort.'<br><strong>Encryption:</strong> '.$testSecure.'</p>';

      $mail->send();
      echo "<p class='ok' style='font-size:18px;'>✅ SUCCESS! Email sent to <strong>" . htmlspecialchars($testTo) . "</strong>. Check your inbox (and spam folder).</p>";
      echo "<p class='ok'>Working config → Host: <strong>$testHost</strong> | Port: <strong>$testPort</strong> | Encryption: <strong>$testSecure</strong></p>";

  } catch (Exception $e) {
      echo "<p class='err' style='font-size:16px;'>❌ FAILED: " . htmlspecialchars($mail->ErrorInfo) . "</p>";
  }
  ?>
</div>
<?php endif; ?>

<div class="box" style="border:1px solid #f87171;">
  <p class="err">⚠️ <strong>SECURITY REMINDER:</strong> Delete <code>test_mail.php</code> from your server immediately after testing!</p>
</div>

</body>
</html>
