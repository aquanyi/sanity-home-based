<?php
/**
 * send_mail.php
 * Core Email Engine using PHPMailer for S.H.T.A.
 */

// Load Composer's autoloader
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Require config if SMTP_HOST is not defined
if (!defined('SMTP_HOST')) {
    require_once __DIR__ . '/config.php';
}

/**
 * sendSystemEmail()
 * Robust helper function using PHPMailer
 */
function sendSystemEmail($toEmail, $toName, $subject, $bodyHtml, $fromEmail = null, $fromName = null, $attachments = [], $bccArray = []) {
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $senderEmail = $fromEmail ? $fromEmail : (defined('SMTP_USER') ? SMTP_USER : 'admin@sanityeducation.com');
    $senderName  = $fromName ? $fromName : (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Sanity Education');

    // Ensure email body is wrapped in branded HTML template
    if (strpos($bodyHtml, '<html') === false) {
        $bodyHtml = buildEmailTemplatePHPMailer($subject, $bodyHtml);
    }

    try {
        // Server settings
        $mail->isSMTP();
        $smtpHost = defined('SMTP_HOST') ? SMTP_HOST : 'localhost';
        $mail->Host       = $smtpHost . ';localhost;mail.sanityeducation.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = defined('SMTP_USER') ? SMTP_USER : 'admin@sanityeducation.com';
        $mail->Password   = defined('SMTP_PASS') ? SMTP_PASS : '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Implicit TLS
        $mail->Port       = defined('SMTP_PORT') ? SMTP_PORT : 465;
        $mail->Timeout    = 10; // 10 seconds connection timeout

        // Relax SSL verification to prevent cPanel *.web-hosting.com certificate mismatch errors
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            )
        );

        // Sender & Return-Path for SPF/DKIM compliance
        $mail->setFrom($senderEmail, $senderName);
        if (defined('SMTP_USER') && !empty(SMTP_USER)) {
            $mail->Sender = SMTP_USER;
        }
        
        $mail->addAddress($toEmail, $toName);

        // Add BCC recipients directly in single transaction
        if (!empty($bccArray) && is_array($bccArray)) {
            foreach ($bccArray as $bccEmail) {
                if (!empty($bccEmail) && strtolower($bccEmail) !== strtolower($toEmail)) {
                    $mail->addBCC($bccEmail);
                }
            }
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;

        // Add attachments if any
        if (!empty($attachments) && is_array($attachments)) {
            foreach ($attachments as $att) {
                if (isset($att['path'])) {
                    $mail->addAttachment($att['path'], $att['name'] ?? '');
                } elseif (isset($att['string']) && isset($att['name'])) {
                    $mail->addStringAttachment($att['string'], $att['name'], 'base64', $att['type'] ?? 'application/pdf');
                }
            }
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        $smtpError = $mail->ErrorInfo;

        // ── Fallback 1: Try Port 587 STARTTLS ──
        try {
            $mail587 = new PHPMailer(true);
            $mail587->CharSet = 'UTF-8';
            $mail587->isSMTP();
            $mail587->Host       = (defined('SMTP_HOST') ? SMTP_HOST : 'localhost') . ';localhost';
            $mail587->SMTPAuth   = true;
            $mail587->Username   = defined('SMTP_USER') ? SMTP_USER : 'admin@sanityeducation.com';
            $mail587->Password   = defined('SMTP_PASS') ? SMTP_PASS : '';
            $mail587->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail587->Port       = 587;
            $mail587->Timeout    = 8;
            $mail587->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true
                )
            );
            $mail587->setFrom($senderEmail, $senderName);
            if (defined('SMTP_USER') && !empty(SMTP_USER)) {
                $mail587->Sender = SMTP_USER;
            }
            $mail587->addAddress($toEmail, $toName);
            if (!empty($bccArray) && is_array($bccArray)) {
                foreach ($bccArray as $bccEmail) {
                    if (!empty($bccEmail) && strtolower($bccEmail) !== strtolower($toEmail)) {
                        $mail587->addBCC($bccEmail);
                    }
                }
            }
            $mail587->isHTML(true);
            $mail587->Subject = $subject;
            $mail587->Body    = $bodyHtml;

            if (!empty($attachments) && is_array($attachments)) {
                foreach ($attachments as $att) {
                    if (isset($att['path'])) {
                        $mail587->addAttachment($att['path'], $att['name'] ?? '');
                    } elseif (isset($att['string']) && isset($att['name'])) {
                        $mail587->addStringAttachment($att['string'], $att['name'], 'base64', $att['type'] ?? 'application/pdf');
                    }
                }
            }

            $mail587->send();
            return true;
        } catch (Exception $e587) {
            // ── Fallback 2: Try cPanel Local sendmail / mail() ──
            try {
                $mailFallback = new PHPMailer(true);
                $mailFallback->CharSet = 'UTF-8';
                $mailFallback->isMail();
                $mailFallback->setFrom($senderEmail, $senderName);
                $mailFallback->addAddress($toEmail, $toName);
                if (!empty($bccArray) && is_array($bccArray)) {
                    foreach ($bccArray as $bccEmail) {
                        if (!empty($bccEmail) && strtolower($bccEmail) !== strtolower($toEmail)) {
                            $mailFallback->addBCC($bccEmail);
                        }
                    }
                }
                $mailFallback->isHTML(true);
                $mailFallback->Subject = $subject;
                $mailFallback->Body    = $bodyHtml;

                if (!empty($attachments) && is_array($attachments)) {
                    foreach ($attachments as $att) {
                        if (isset($att['path'])) {
                            $mailFallback->addAttachment($att['path'], $att['name'] ?? '');
                        } elseif (isset($att['string']) && isset($att['name'])) {
                            $mailFallback->addStringAttachment($att['string'], $att['name'], 'base64', $att['type'] ?? 'application/pdf');
                        }
                    }
                }

                $mailFallback->send();
                return true;
            } catch (Exception $eFallback) {
                // Log all failure details
                $logFile = __DIR__ . '/logs/email_errors.log';
                $logDir = dirname($logFile);
                if (!is_dir($logDir)) {
                    @mkdir($logDir, 0777, true);
                }
                $errorMessage = "[" . date('Y-m-d H:i:s') . "] Email delivery failed to $toEmail.\n" .
                                "  - Port 465 SMTP Error: {$smtpError}\n" .
                                "  - Port 587 SMTP Error: {$mail587->ErrorInfo}\n" .
                                "  - Local Mail Fallback Error: {$mailFallback->ErrorInfo}\n";
                @file_put_contents($logFile, $errorMessage, FILE_APPEND);
                return false;
            }
        }
    }
}

/**
 * Template Helper Function for consistent styling
 */
function buildEmailTemplatePHPMailer(string $title, string $content): string {
    $schoolName = defined('MAIL_SCHOOL_NAME') ? MAIL_SCHOOL_NAME : 'Sanity Homebased Tuition Academy';
    $contact    = defined('MAIL_CONTACT_EMAIL') ? MAIL_CONTACT_EMAIL : 'accounts@sanityeducation.com';
    $phone      = defined('MAIL_CONTACT_PHONE') ? MAIL_CONTACT_PHONE : '+254 716 942 939 / +254 731 091 000';
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
