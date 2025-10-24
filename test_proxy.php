<?php
$payload = [
  'nim' => 'TEST12345',
  'session_id' => 'TEST',
  'ip_address' => '127.0.0.1',
  'user_agent' => 'CLI',
  'device_fingerprint' => 'cli-test',
  'from' => 'proxy-test'
];

$url = 'http://localhost:8000/submit.php';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
$body = http_build_query($payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);

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