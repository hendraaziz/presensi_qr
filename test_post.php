<?php
require_once __DIR__ . '/config.php';

$payload = [
  'nim' => 'TEST12345',
  'session_id' => 'TEST',
  'ip_address' => '127.0.0.1',
  'user_agent' => 'CLI',
  'device_fingerprint' => 'cli-test',
  'from' => 'manual-test'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, N8N_WEBHOOK_URL);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$body = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP code: $code\n";
if ($err) {
  echo "cURL error: $err\n";
}
if ($body !== false) {
  echo "Response body:\n$body\n";
} else {
  echo "No response body.\n";
}