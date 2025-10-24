<?php
// webhookrespon.php
// Endpoint untuk menerima balikan dari n8n dan menyediakan hasil untuk dibaca front-end
// Mendukung POST (JSON/form) untuk menyimpan balikan dan GET untuk mengambilnya

ob_start();
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
$storeDir = __DIR__ . DIRECTORY_SEPARATOR . 'webhook_store';
if (!is_dir($storeDir)) {
  @mkdir($storeDir, 0775, true);
}

function readInputBody() {
  $raw = file_get_contents('php://input');
  $ctype = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
  $data = [];

  // Jika Content-Type JSON, coba decode
  if (stripos($ctype, 'application/json') !== false) {
    $json = json_decode($raw, true);
    if (is_array($json)) { $data = $json; }
  }

  // Jika belum terisi dan body terlihat seperti JSON, coba decode
  if (!$data && $raw) {
    $trim = ltrim($raw);
    if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
      $json = json_decode($raw, true);
      if (is_array($json)) { $data = $json; }
    }
  }

  // Fallback: x-www-form-urlencoded atau multipart
  if (!$data) {
    $data = $_POST ?: [];
    if (!$data && $raw) {
      parse_str($raw, $parsed);
      if (is_array($parsed)) { $data = $parsed; }
    }
  }

  // Jika data adalah array list (JSON array), ambil elemen pertama
  if ($data && isset($data[0]) && is_array($data[0])) {
    $data = $data[0];
  }

  return $data;
}

function buildKey($nim, $sessionId, $fingerprint) {
  $parts = [];
  if ($sessionId) $parts[] = $sessionId;
  if ($nim) $parts[] = $nim;
  if ($fingerprint) $parts[] = $fingerprint;
  if (!$parts) $parts[] = 'unknown';
  return implode('|', $parts);
}

function storePath($dir, $key) {
  $safeKey = preg_replace('/[^a-zA-Z0-9_\-\|]/', '_', $key);
  return $dir . DIRECTORY_SEPARATOR . $safeKey . '.json';
}

if (strtoupper($method) === 'POST') {
  $data = readInputBody();

  // Normalisasi field dari variasi payload
  $nimRaw = isset($data['nim']) ? $data['nim'] : '';
  $sessionIdRaw = isset($data['session_id']) ? $data['session_id'] : '';
  $fingerprintRaw = isset($data['device_fingerprint']) ? $data['device_fingerprint'] : '';
  $nim = trim((string)$nimRaw);
  $sessionId = trim((string)$sessionIdRaw);
  $fingerprint = trim((string)$fingerprintRaw);
  // Dukungan key eksplisit dari upstream
  $keyParam = isset($data['key']) ? trim((string)$data['key']) : '';

  // Dukungan field 'status' (opsional) dan 'message' (wajib)
  $message = '';
  if (isset($data['message'])) {
    $message = trim((string)$data['message']);
  } elseif (isset($data['msg'])) {
    $message = trim((string)$data['msg']);
  }

  $simulate = isset($data['simulate']) ? (string)$data['simulate'] : '';
  if ($simulate) {
    if ($message === '') {
      $msgs = [
        'data presensi berhasil disimpan',
        'anda telah presensi di sesi ini',
        'device sudah digunakan untuk presensi'
      ];
      $message = $msgs[array_rand($msgs)];
    }
  }

  if ($message === '') {
    http_response_code(400);
    ob_clean();
    echo json_encode([ 'success' => false, 'message' => 'Parameter message wajib' ]);
    exit;
  }

  $key = $keyParam !== '' ? $keyParam : buildKey($nim, $sessionId, $fingerprint);
  $path = storePath($storeDir, $key);
  $payload = [
    'success' => true,
    'message' => $message,
    'nim' => $nim,
    'session_id' => $sessionId,
    'device_fingerprint' => $fingerprint,
    'key' => $key,
    'timestamp' => time()
  ];
  file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

  ob_clean();
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

// GET: ambil hasil
$nim = isset($_GET['nim']) ? trim($_GET['nim']) : '';
$sessionId = isset($_GET['session_id']) ? trim($_GET['session_id']) : '';
$fingerprint = isset($_GET['device_fingerprint']) ? trim($_GET['device_fingerprint']) : '';
$keyParam = isset($_GET['key']) ? trim($_GET['key']) : '';
$key = $keyParam !== '' ? $keyParam : buildKey($nim, $sessionId, $fingerprint);
$path = storePath($storeDir, $key);

if (is_file($path)) {
  $json = file_get_contents($path);
  if ($json) {
    ob_clean();
    echo $json;
    exit;
  }
}

ob_clean();
echo json_encode([ 'success' => false, 'message' => 'Belum ada balikan untuk key ini', 'key' => $key ]);