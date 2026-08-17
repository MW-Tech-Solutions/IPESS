<?php
require_once __DIR__ . '/mailer.php';

$result = portal_test_smtp_connection();
var_dump($result);

$send = portal_send_mail(
  'someone@example.com',
  'Someone',
  'Test Mail',
  '<p>Hello, this is a test.</p>'
);
var_dump($send);
