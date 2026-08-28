<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/app/bootstrap.php';

echo "<h2>SMTP Configuration Testing</h2>";

$config = portal_mail_config();
echo "<pre>SMTP Configuration:\n";
print_r([
    'host' => $config['host'],
    'port' => $config['port'],
    'user' => $config['user'],
    'encryption' => $config['encryption'],
    'from_email' => $config['from_email'],
    'from_name' => $config['from_name']
]);
echo "</pre>";

echo "<h3>1. Testing TCP Connection (fsockopen)...</h3>";
$conn = portal_test_smtp_connection();
echo "<pre>";
print_r($conn);
echo "</pre>";

if (!$conn['success']) {
    echo "<p style='color:red;'><b>TCP Connection failed.</b> Your web server is likely blocking outbound traffic on port {$config['port']} to {$config['host']}. Contact your hosting provider to open this port.</p>";
} else {
    echo "<p style='color:green;'><b>TCP Connection successful!</b></p>";
}

echo "<h3>2. Testing SMTP Mail Delivery...</h3>";
// Send to the SMTP user itself as a test loopback, or fallback
$test_email = $config['user'] ?: 'test@example.com';
echo "<p>Sending test email to: <b>{$test_email}</b></p>";

// Enable verbose debug output to stdout by overriding PHPMailer SMTP debug
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!defined('JOSTUM_ROOT')) {
    define('JOSTUM_ROOT', __DIR__);
}

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = $config['host'];
    $mail->Port = $config['port'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['user'];
    $mail->Password = $config['pass'];

    // Enable echo debugging to browser
    $mail->SMTPDebug = 3; 
    $mail->Debugoutput = 'html';

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
    $mail->addAddress($test_email, "SMTP Test User");

    $mail->isHTML(true);
    $mail->Subject = "IPESS SMTP Diagnostic Test";
    $mail->Body    = "<p>This is a diagnostic SMTP test email from IPESS Postgraduate Portal.</p>";
    $mail->AltBody = "This is a diagnostic SMTP test email from IPESS Postgraduate Portal.";

    echo "<div style='background:#f8f9fa; border:1px solid #ccc; padding:15px; max-height:400px; overflow:auto;'>";
    echo "<h4>PHPMailer Verbose SMTP Log:</h4>";
    $sent = $mail->send();
    echo "</div>";

    if ($sent) {
        echo "<p style='color:green;'><b>Email sent successfully!</b></p>";
    }
} catch (Exception $e) {
    echo "</div>";
    echo "<p style='color:red;'><b>PHPMailer Exception:</b> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p style='color:red;'><b>ErrorInfo:</b> " . htmlspecialchars($mail->ErrorInfo) . "</p>";
}
