<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --------------------------
// 1) Load .env (simple parser)
// --------------------------
$envCandidates = [
    dirname(__DIR__) . '/.env',
    dirname(__DIR__, 2) . '/.env',
];

foreach ($envCandidates as $envFile) {
    if (!file_exists($envFile)) {
        continue;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);

        // skip empty/comment
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        // allow "export KEY=VALUE"
        if (str_starts_with($line, 'export ')) {
            $line = trim(substr($line, 7));
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        // strip quotes
        $value = trim($value, "\"'");

        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
    break;
}

// --------------------------
// 2) Include PHPMailer (no composer)
// --------------------------
$mailerBase = dirname(__DIR__) . '/PhpMailer/src/';
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

// --------------------------
// 3) Read config
// --------------------------
function portal_mail_config(): array
{
    $port = (int)(getenv('SMTP_PORT') ?: 587);
    $enc  = strtolower((string)(getenv('SMTP_ENCRYPTION') ?: 'tls')); // tls|ssl|none

    // normalize encryption values
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
        'from_name'     => (string)(getenv('SMTP_FROM_NAME') ?: 'JOSTUM PG School'),
        'reply_to_email'=> (string)(getenv('SMTP_REPLY_TO_EMAIL') ?: ''),
        'reply_to_name' => (string)(getenv('SMTP_REPLY_TO_NAME') ?: ''),
        'debug'         => (int)(getenv('SMTP_DEBUG') ?: 0), // 0,1,2
        'timeout'       => (int)(getenv('SMTP_TIMEOUT') ?: 30),
    ];
}

// --------------------------
// 4) Email template (HTML)
// --------------------------
function portal_email_template(string $title, string $contentHtml, array $meta = []): string
{
    $preheader  = htmlspecialchars($meta['preheader'] ?? 'JOSTUM PG School Notification', ENT_QUOTES, 'UTF-8');
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
    <td style="background:#0b6a3a;border-radius:6px;text-align:center;">
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
            <td style="background:#0b6a3a;color:#ffffff;padding:22px 28px;font-size:18px;font-weight:600;">
              JOSTUM PG School
            </td>
          </tr>
          <tr>
            <td style="padding:28px;">
              <h1 style="margin:0 0 16px;font-size:22px;color:#0b2b1a;">{$safeTitle}</h1>
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
          JOSTUM - Joseph Sarwuan Tarka University, Makurdi
        </div>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

// --------------------------
// 5) Send email (main function)
// --------------------------
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

        // Debug (write to PHP error log if enabled)
        $mail->SMTPDebug = $config['debug']; // 0,1,2
        $mail->Debugoutput = 'error_log';

        $mail->Timeout = $config['timeout'];
        $mail->CharSet = 'UTF-8';

        // IMPORTANT: Encryption (Gmail: port 587 => STARTTLS)
        if ($config['encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // port 465 typically
        } elseif ($config['encryption'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // port 587
        } else {
            $mail->SMTPSecure = false; // no encryption
            $mail->SMTPAutoTLS = false;
        }

        // From / To
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($to, $toName);

        if ($config['reply_to_email'] !== '') {
            $mail->addReplyTo(
                $config['reply_to_email'],
                $config['reply_to_name'] !== '' ? $config['reply_to_name'] : $config['from_name']
            );
        }        // Embedded images (CID)
        if (!empty($meta['embed_images']) && is_array($meta['embed_images'])) {
            foreach ($meta['embed_images'] as $img) {
                $cid = $img['cid'] ?? '';
                $data = $img['data'] ?? '';
                $name = $img['name'] ?? 'image.png';
                $mime = $img['mime'] ?? 'image/png';
                if ($cid !== '' && $data !== '') {
                    $mail->addStringEmbeddedImage($data, $cid, $name, 'base64', $mime);
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

        // Content
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

// --------------------------
// 6) Optional: Quick connectivity test helper
// --------------------------
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
