<?php
ob_start();
error_reporting(0);
ini_set('display_errors', '0');
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$ct = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
if ($ct && strpos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
} else {
    $data = $_POST;
}
if (!is_array($data)) { $data = []; }

$nim = isset($data['nim']) ? trim($data['nim']) : '';
$sessionId = isset($data['session_id']) ? trim($data['session_id']) : '';
$noWa = isset($data['no_wa']) ? trim($data['no_wa']) : '';
if ($nim === '' || $sessionId === '') {
    http_response_code(400);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Missing required fields: nim, session_id']);
    exit;
}

// Build allowed country codes pattern
$codesStr = defined('COUNTRY_CODES') ? COUNTRY_CODES : (defined('COUNTRY_CODE') ? COUNTRY_CODE : '62');
$codesArr = array_filter(array_map('trim', explode(',', $codesStr)));
if (!$codesArr) { $codesArr = array(defined('COUNTRY_CODE') ? COUNTRY_CODE : '62'); }
$codesPattern = '(' . implode('|', $codesArr) . ')';

if ($noWa !== '' && !preg_match('/^' . $codesPattern . '[0-9]{9,}$/', $noWa)) {
    http_response_code(400);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid no_wa: must start with one of [' . implode(', ', $codesArr) . '] and contain digits only (min 11 chars).']);
    exit;
}

$payload = $data;
$payload['proxy_received_at'] = date('c');
$payload['proxy_ip'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
$payload['proxy_user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
$payload['secret_key'] = defined('SECRET_KEY') ? SECRET_KEY : '';
// Fallback device_fingerprint jika kosong
if (!isset($payload['device_fingerprint']) || trim($payload['device_fingerprint']) === '') {
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $payload['device_fingerprint'] = md5($ua . '|' . $ip . '|' . (defined('SECRET_KEY') ? SECRET_KEY : ''));
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, N8N_WEBHOOK_URL);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
// NOTE: Disable SSL verification to avoid Windows CA issues. Enable once CA is configured.
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$responseBody = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlErr) {
    http_response_code(502);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Proxy request failed: ' . $curlErr]);
    exit;
}

if ($httpCode < 200 || $httpCode >= 300) {
    http_response_code(502);
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Upstream responded with HTTP ' . $httpCode,
        'upstream_body' => $responseBody
    ]);
    exit;
}

ob_clean();
echo json_encode(['success' => true, 'message' => 'Presensi diteruskan ke n8n', 'upstream_body' => $responseBody]);