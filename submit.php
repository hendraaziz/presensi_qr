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

// ---- Check active session from Google Sheet ----
function norm_lower($s) { return function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s); }
function http_get($url) {
    if (!$url) return false;
    $ctx = stream_context_create(array('http' => array('method' => 'GET','timeout' => 10,'header' => "User-Agent: PresensiWasbang/1.0\r\n")));
    $content = @file_get_contents($url, false, $ctx);
    if ($content !== false) return $content;
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PresensiWasbang/1.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $body = curl_exec($ch);
        curl_close($ch);
        if ($body !== false) return $body;
    }
    return false;
}
function fetch_csv_assoc($url) {
    $content = http_get($url);
    if ($content === false) return array();
    $lines = preg_split('/\r\n|\r|\n/', trim($content));
    if (!$lines || count($lines) < 2) return array();
    $headers = str_getcsv(array_shift($lines));
    $rows = array();
    foreach ($lines as $line) {
        if ($line === '') continue;
        $cols = str_getcsv($line);
        $row = array();
        foreach ($headers as $i => $h) {
            $row[trim(norm_lower($h))] = isset($cols[$i]) ? $cols[$i] : '';
        }
        $rows[] = $row;
    }
    return $rows;
}
function get_first($arr, $keys, $default='') { if (!is_array($keys)) { $keys = array($keys); } foreach ($keys as $k) { if (isset($arr[$k])) return $arr[$k]; } return $default; }
function parse_dt($val) {
    if (!$val) return null;
    $val = trim($val);
    $formats = array('Y-m-d H:i:s','Y-m-d H:i','d/m/Y H:i','d/m/Y H:i:s','m/d/Y H:i','m/d/Y H:i:s','Y-m-d','d/m/Y','m/d/Y');
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $val, new DateTimeZone('Asia/Jakarta'));
        if ($dt instanceof DateTime) return $dt;
    }
    $ts = strtotime($val);
    if ($ts !== false) return (new DateTime('@' . $ts))->setTimezone(new DateTimeZone('Asia/Jakarta'));
    return null;
}
function is_session_active($url, $sessionId) {
    $rows = fetch_csv_assoc($url);
    if (!$rows) return false;
    $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    foreach ($rows as $row) {
        $id = get_first($row, array('id_sesi','session_id','id','kode_sesi','code'), '');
        if ($id !== $sessionId) continue;
        $start = parse_dt(get_first($row, array('mulai','start','start_time','waktu_mulai','start_at'), null));
        $end = parse_dt(get_first($row, array('selesai','end','end_time','waktu_selesai','end_at'), null));
        if ($start && $end && $now >= $start && $now <= $end) return true;
    }
    return false;
}

if (!is_session_active(GOOGLE_SHEET_SESI_URL, $sessionId)) {
    http_response_code(400);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Sesi tidak aktif atau tidak ditemukan.']);
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