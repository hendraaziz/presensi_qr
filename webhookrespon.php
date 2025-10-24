<?php
// webhookrespon.php
// Endpoint untuk menerima balikan dari n8n dan menyediakan hasil untuk dibaca front-end
// Mendukung POST (JSON/form) untuk menyimpan balikan dan GET untuk mengambilnya

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
  if (stripos($ctype, 'application/json') !== false) {
    $json = json_decode($raw, true);
    if (is_array($json)) { $data = $json; }
  } else {
    // Fallback untuk x-www-form-urlencoded atau multipart
    $data = $_POST ?: [];
    if (!$data && $raw) {
      // coba parse url-encoded manual
      parse_str($raw, $parsed);
      if (is_array($parsed)) { $data = $parsed; }
    }
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
  $nim = isset($data['nim']) ? trim($data['nim']) : '';
  $sessionId = isset($data['session_id']) ? trim($data['session_id']) : '';
  $fingerprint = isset($data['device_fingerprint']) ? trim($data['device_fingerprint']) : '';
  $message = isset($data['message']) ? trim($data['message']) : '';
  $simulate = isset($data['simulate']) ? (string)$data['simulate'] : '';

  if ($simulate) {
    // Jika simulasi diminta, gunakan pesan bawaan bila kosong
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
    echo json_encode([ 'success' => false, 'message' => 'Parameter message wajib' ]);
    exit;
  }

  $key = buildKey($nim, $sessionId, $fingerprint);
  $path = storePath($storeDir, $key);
  $payload = [
    'success' => true,
    'message' => $message,
    'nim' => $nim,
    'session_id' => $sessionId,
    'device_fingerprint' => $fingerprint,
    'timestamp' => time()
  ];
  file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

// GET: ambil hasil
$nim = isset($_GET['nim']) ? trim($_GET['nim']) : '';
$sessionId = isset($_GET['session_id']) ? trim($_GET['session_id']) : '';
$fingerprint = isset($_GET['device_fingerprint']) ? trim($_GET['device_fingerprint']) : '';
$key = buildKey($nim, $sessionId, $fingerprint);
$path = storePath($storeDir, $key);

if (is_file($path)) {
  $json = file_get_contents($path);
  if ($json) {
    echo $json;
    exit;
  }
}

echo json_encode([ 'success' => false, 'message' => 'Belum ada balikan untuk key ini', 'key' => $key ]);