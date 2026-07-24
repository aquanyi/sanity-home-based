<?php
/**
 * mail_helper.php
 * Central email dispatch helper for Sanity Homebased Tuition Academy.
 * All system emails are sent through this file.
 *
 * SENDER EMAILS (match your cPanel accounts exactly):
 *   accounts@sanityeducation.com  — invoices & fee notifications
 *   admin@sanityeducation.com     — admin alerts & system notices
 *   info@sanityeducation.com      — general / OTP emails to parents
 *
 * HOW IT WORKS:
 *   cPanel servers route mail() via the server's local sendmail.
 *   Using a "From" that matches a real cPanel account ensures delivery.
 *
 * ADMIN COPY LIST — all system emails are BCC'd to these addresses:
 */

define('MAIL_INVOICES_FROM',   'admin@sanityeducation.com');
define('MAIL_ADMIN_FROM',      'admin@sanityeducation.com');
define('MAIL_INFO_FROM',       'admin@sanityeducation.com');
define('MAIL_SCHOOL_NAME',     'Sanity Homebased Tuition Academy');
define('MAIL_CONTACT_EMAIL',   'admin@sanityeducation.com');
define('MAIL_CONTACT_PHONE',   '+254 716 942 939 / +254 731 091 000');

// Admin copy list — empty to prevent bulk/BCC sending
define('MAIL_ADMIN_COPIES', []);

require_once __DIR__ . '/send_mail.php';

/**
 * sendMail()
 * @param string $to          Recipient email
 * @param string $subject     Email subject
 * @param string $htmlBody    HTML body content
 * @param string $fromEmail   Sender email (use MAIL_* constants above)
 * @param string $fromName    Sender display name
 * @param bool   $bccAdmins  Whether to BCC admin addresses (default false)
 * @return bool
 */
function sendMail(
    string $to,
    string $subject,
    string $htmlBody,
    string $fromEmail = MAIL_INFO_FROM,
    string $fromName  = MAIL_SCHOOL_NAME,
    bool   $bccAdmins = false,
    array  $attachments = []
): bool {

    $bccList = [];
    if ($bccAdmins && defined('MAIL_ADMIN_COPIES') && is_array(MAIL_ADMIN_COPIES)) {
        $bccList = array_values(array_filter(MAIL_ADMIN_COPIES, fn($a) => strtolower($a) !== strtolower($to)));
    }

    return sendSystemEmail($to, $to, $subject, $htmlBody, $fromEmail, $fromName, $attachments, $bccList);
}

/**
 * buildEmailTemplate()
 * Wraps any email body content in the school's branded HTML template.
 */
function buildEmailTemplate(string $title, string $content): string {
    $schoolName = MAIL_SCHOOL_NAME;
    $contact    = MAIL_CONTACT_EMAIL;
    $phone      = MAIL_CONTACT_PHONE;
    $year       = date('Y');

    return "<!DOCTYPE html>
<html lang='en'>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1.0'>
  <title>{$title}</title>
</head>
<body style='margin:0;padding:0;background:#F5F5F5;font-family:Arial,Helvetica,sans-serif;'>
  <table width='100%' cellpadding='0' cellspacing='0' style='background:#F5F5F5;padding:30px 0;'>
    <tr><td align='center'>
      <table width='600' cellpadding='0' cellspacing='0' style='background:#FFFFFF;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);'>

        <!-- Header -->
        <tr>
          <td style='background:#4A0E17;padding:28px 40px;text-align:center;'>
            <h1 style='color:#E5A93B;margin:0;font-size:22px;letter-spacing:1px;'>{$schoolName}</h1>
            <p style='color:rgba(255,255,255,0.75);margin:6px 0 0;font-size:13px;'>Professional Home-Based Tuition Services</p>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style='padding:36px 40px;color:#1e293b;font-size:15px;line-height:1.7;'>
            {$content}
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style='background:#FAF7F2;padding:20px 40px;text-align:center;border-top:1px solid #E9ECEF;'>
            <p style='margin:0;font-size:12px;color:#6C757D;'>
              Questions? Contact us at <a href='mailto:{$contact}' style='color:#4A0E17;'>{$contact}</a> | {$phone}
            </p>
            <p style='margin:6px 0 0;font-size:11px;color:#ADB5BD;'>
              &copy; {$year} {$schoolName}. All rights reserved.
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>";
}
?>
