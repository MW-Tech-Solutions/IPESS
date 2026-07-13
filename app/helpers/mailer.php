<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure JOSTUM_ROOT is defined
if (!defined('JOSTUM_ROOT')) {
    define('JOSTUM_ROOT', dirname(__DIR__, 2));
}

// Include PHPMailer from root PhpMailer/src/
$mailerBase = JOSTUM_ROOT . '/PhpMailer/src/';
$mailerPaths = [
    $mailerBase . 'Exception.php',
    $mailerBase . 'PHPMailer.php',
    $mailerBase . 'SMTP.php',
];

$mailerMissing = false;
foreach ($mailerPaths as $p) {
    if (!file_exists($p)) {
        $mailerMissing = true;
        break;
    }
}

if (!$mailerMissing) {
    require_once $mailerPaths[0];
    require_once $mailerPaths[1];
    require_once $mailerPaths[2];
}

function portal_mail_config(): array
{
    $port = (int)(getenv('SMTP_PORT') ?: 587);
    $enc  = strtolower((string)(getenv('SMTP_ENCRYPTION') ?: 'tls')); // tls|ssl|none

    if (!in_array($enc, ['tls', 'ssl', 'none'], true)) {
        $enc = 'tls';
    }

    return [
        'host'          => (string)(getenv('SMTP_HOST') ?: ''),
        'port'          => $port,
        'user'          => (string)(getenv('SMTP_USER') ?: ''),
        'pass'          => (string)(getenv('SMTP_PASS') ?: ''),
        'encryption'    => $enc,
        'from_email'    => (string)(getenv('SMTP_FROM_EMAIL') ?: (getenv('SMTP_USER') ?: '')),
        'from_name'     => (string)(getenv('SMTP_FROM_NAME') ?: 'Institute of Procurement, Environmental and Social Standard IPESS JOSTUM'),
        'reply_to_email'=> (string)(getenv('SMTP_REPLY_TO_EMAIL') ?: ''),
        'reply_to_name' => (string)(getenv('SMTP_REPLY_TO_NAME') ?: ''),
        'debug'         => (int)(getenv('SMTP_DEBUG') ?: 0), // 0,1,2
        'timeout'       => (int)(getenv('SMTP_TIMEOUT') ?: 30),
    ];
}

function portal_email_template(string $title, string $contentHtml, array $meta = []): string
{
    $preheader  = htmlspecialchars($meta['preheader'] ?? 'Institute of Procurement, Environmental and Social Standard IPESS JOSTUM Notification', ENT_QUOTES, 'UTF-8');
    $footerNote = htmlspecialchars($meta['footer_note'] ?? 'This is an automated message. Please do not reply.', ENT_QUOTES, 'UTF-8');

    $ctaLabel = $meta['cta_label'] ?? '';
    $ctaUrl   = $meta['cta_url'] ?? '';

    $ctaHtml = '';
    if ($ctaLabel !== '' && $ctaUrl !== '') {
        $safeLabel = htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8');
        $safeUrl   = htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8');
        $ctaHtml = <<<HTML
<table role="presentation" cellpadding="0" cellspacing="0" style="margin: 20px auto 0;">
  <tr>
    <td style="background:#6EB533;border-radius:6px;text-align:center;">
      <a href="{$safeUrl}" style="display:inline-block;padding:12px 24px;color:#ffffff;text-decoration:none;font-weight:600;">
        {$safeLabel}
      </a>
    </td>
  </tr>
</table>
HTML;
    }

    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{$safeTitle}</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fb;font-family:'Segoe UI', Tahoma, Arial, sans-serif;color:#222;">
  <span style="display:none;max-height:0;overflow:hidden;opacity:0;">{$preheader}</span>

  <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f5f7fb;padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" cellpadding="0" cellspacing="0" width="640" style="max-width:640px;background:#ffffff;border-radius:12px;box-shadow:0 6px 18px rgba(15,23,42,0.08);overflow:hidden;">
          <tr>
            <td style="background:#782D32;color:#ffffff;padding:22px 28px;font-size:18px;font-weight:600;">
              Institute of Procurement, Environmental and Social Standard IPESS JOSTUM
            </td>
          </tr>
          <tr>
            <td style="padding:28px;">
              <h1 style="margin:0 0 16px;font-size:22px;color:#782D32;">{$safeTitle}</h1>
              <div style="font-size:14.5px;line-height:1.6;color:#333333;">
                {$contentHtml}
              </div>
              {$ctaHtml}
            </td>
          </tr>
          <tr>
            <td style="padding:18px 28px;border-top:1px solid #e6edf3;font-size:12.5px;color:#5f6b7a;">
              {$footerNote}
            </td>
          </tr>
        </table>
        <div style="font-size:11.5px;color:#8a94a6;margin-top:10px;">
          Institute of Procurement, Environmental and Social Standard IPESS JOSTUM
        </div>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

function portal_send_mail(
    string $to,
    string $toName,
    string $subject,
    string $contentHtml,
    string $contentText = '',
    array $meta = []
): array {
    global $mailerMissing;

    if ($mailerMissing) {
        return ['success' => false, 'message' => 'Mailer library missing. Ensure PhpMailer/src files exist.'];
    }

    $config = portal_mail_config();

    if ($config['host'] === '' || $config['user'] === '' || $config['pass'] === '') {
        return ['success' => false, 'message' => 'Mail settings are missing in environment variables (.env).'];
    }

    $mail = new PHPMailer(true);

    try {
        $connectivity = portal_test_smtp_connection();
        if (!$connectivity['success']) {
            return [
                'success' => false,
                'message' => 'Checking network: please check your internet connection and try again.'
            ];
        }

        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->Port = $config['port'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['user'];
        $mail->Password = $config['pass'];

        $mail->SMTPDebug = $config['debug']; // 0,1,2
        $mail->Debugoutput = 'error_log';

        $mail->Timeout = $config['timeout'];
        $mail->CharSet = 'UTF-8';

        if ($config['encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($config['encryption'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = false;
            $mail->SMTPAutoTLS = false;
        }

        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($to, $toName);

        if ($config['reply_to_email'] !== '') {
            $mail->addReplyTo(
                $config['reply_to_email'],
                $config['reply_to_name'] !== '' ? $config['reply_to_name'] : $config['from_name']
            );
        }

        // Embedded images (CID)
        if (!empty($meta['embed_images']) && is_array($meta['embed_images'])) {
            foreach ($meta['embed_images'] as $img) {
                $cid = $img['cid'] ?? '';
                $data = $img['data'] ?? '';
                $name = $img['name'] ?? 'image.png';
                $mime = $img['mime'] ?? 'image/png';
                if ($cid !== '' && $data !== '') {
                    $decodedData = $data;
                    if (is_string($data) && preg_match('/^[a-zA-Z0-9\/+\r\n]*={0,2}$/', trim($data))) {
                        $decoded = base64_decode(trim($data), true);
                        if ($decoded !== false && base64_encode($decoded) === trim($data)) {
                            $decodedData = $decoded;
                        }
                    }
                    $mail->addStringEmbeddedImage($decodedData, $cid, $name, 'base64', $mime);
                }
            }
        }

        // Embedded images from file paths (CID)
        if (!empty($meta['embed_files']) && is_array($meta['embed_files'])) {
            foreach ($meta['embed_files'] as $img) {
                $cid = $img['cid'] ?? '';
                $path = $img['path'] ?? '';
                $name = $img['name'] ?? '';
                $mime = $img['mime'] ?? '';
                if ($cid !== '' && $path !== '' && file_exists($path)) {
                    $mail->addEmbeddedImage($path, $cid, $name ?: basename($path), 'base64', $mime ?: 'image/png');
                }
            }
        }

        // Attachments
        if (!empty($meta['attachments']) && is_array($meta['attachments'])) {
            foreach ($meta['attachments'] as $file) {
                $path = $file['path'] ?? '';
                $name = $file['name'] ?? '';
                if ($path !== '' && file_exists($path)) {
                    $mail->addAttachment($path, $name ?: basename($path));
                }
            }
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = portal_email_template($subject, $contentHtml, $meta);
        $mail->AltBody = $contentText !== '' ? $contentText : trim(strip_tags($contentHtml));

        $mail->send();
        return ['success' => true, 'message' => 'Mail sent.'];

    } catch (Exception $e) {
        $mailError = strtolower(trim((string) $mail->ErrorInfo));
        $exceptionError = strtolower(trim($e->getMessage()));
        $combined = $mailError . ' ' . $exceptionError;

        if (portal_is_network_mail_error($combined)) {
            return [
                'success' => false,
                'message' => 'Checking network: please check your internet connection and try again.'
            ];
        }

        return ['success' => false, 'message' => 'Mail error: ' . ($mail->ErrorInfo ?: $e->getMessage())];
    }
}

function portal_is_network_mail_error(string $message): bool
{
    $needles = [
        'could not connect',
        'connection failed',
        'failed to connect',
        'connection timed out',
        'timed out',
        'network is unreachable',
        'no route to host',
        'temporary failure in name resolution',
        'getaddrinfo',
        'dns',
        'smtp connect() failed'
    ];

    foreach ($needles as $needle) {
        if ($needle !== '' && strpos($message, $needle) !== false) {
            return true;
        }
    }
    return false;
}

function portal_test_smtp_connection(): array
{
    $cfg = portal_mail_config();
    $host = $cfg['host'];
    $port = $cfg['port'];

    if ($host === '' || $port <= 0) {
        return ['success' => false, 'message' => 'SMTP_HOST or SMTP_PORT missing.'];
    }

    $errno = 0;
    $errstr = '';
    $timeout = 10;

    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if (!$fp) {
        return ['success' => false, 'message' => "Cannot connect to $host:$port ($errno) $errstr"];
    }

    fclose($fp);
    return ['success' => true, 'message' => "Connected OK to $host:$port"];
}
