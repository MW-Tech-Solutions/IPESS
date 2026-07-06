<?php

require_once __DIR__ . '/../../../../app/bootstrap.php';

function send_portal_mail(string $to, string $to_name, string $subject, string $html_body, string $text_body = ''): array
{
    $res = portal_send_mail($to, $to_name, $subject, $html_body, $text_body);
    if ($res['success']) {
        return ['ok' => true, 'message' => 'Mail sent.'];
    }
    return ['ok' => false, 'message' => $res['message']];
}
