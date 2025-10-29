<?php
require_once __DIR__ . '/config.php';

// Helper: simple GET with fallback to cURL
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

$nim = isset($_GET['nim']) ? trim($_GET['nim']) : '';
$baseWebhook = defined('N8N_LOOKUP_WEBHOOK_URL') ? constant('N8N_LOOKUP_WEBHOOK_URL') : 'https://flow.azizhendra.my.id/webhook/kehadiran-wasbang';
$url = $nim !== '' ? ($baseWebhook . '?nim=' . urlencode($nim)) : null;

$data = null; $rows = array(); $errorMsg = '';
if ($nim === '') {
  $errorMsg = 'Parameter NIM belum diberikan. Silakan akses dari Form Presensi atau masukkan NIM di atas.';
} else {
  $response = http_get($url);
  if ($response === false) {
    $errorMsg = 'Gagal terhubung ke n8n Webhook.';
  } else {
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      $errorMsg = 'Respons webhook bukan JSON valid.';
    } else {
      if (is_array($data)) {
        if (isset($data[0]) && is_array($data[0])) {
          if (isset($data[0]['json']) && is_array($data[0]['json'])) {
            foreach ($data as $item) {
              if (isset($item['json']) && is_array($item['json'])) {
                $rows[] = $item['json'];
              }
            }
          } else {
            // Asumsikan array of objects dengan field langsung (session_code, nama, nim, dll)
            $rows = $data;
          }
        } elseif (isset($data['json']) && is_array($data['json'])) {
          $rows[] = $data['json'];
        } elseif (!empty($data)) {
          // Single object fallback
          $rows[] = $data;
        }
      }
      if (!$rows) {
        $errorMsg = 'Data peserta tidak ditemukan.';
      }
    }
  }
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$base = function_exists('base_url') ? base_url() : '';
$title = defined('APP_TITLE') ? APP_TITLE : 'Presensi Wasbang';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo h($title . ' - Hasil Presensi'); ?></title>
  <style>
    :root { color-scheme: light dark; }
    body { margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; background:#0f172a; color:#e5e7eb; }
    .container { min-height: 100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
    .card { width:min(720px, 92vw); background:#111827; border:1px solid #1f2937; border-radius:16px; padding:24px; box-shadow:0 10px 30px rgba(0,0,0,0.35); }
    .title { font-size:1.5rem; font-weight:700; margin-bottom:8px; }
    .subtitle { color:#9ca3af; margin-bottom:12px; }
    .field { margin:14px 0; }
    .label { display:block; margin-bottom:6px; color:#cbd5e1; }
    .input { width:100%; padding:12px; border-radius:10px; border:1px solid #374151; background:#0b1220; color:#e5e7eb; }
    .btn { padding:12px 16px; border-radius:10px; background:#2563eb; color:#fff; border:none; cursor:pointer; }
    .btn:hover { background:#1d4ed8; }
    .info { margin-top:8px; font-size:.95rem; color:#9ca3af; }
    .success { margin-top:16px; padding:12px; background:#064e3b; color:#d1fae5; border-radius:10px; }
    .error { margin-top:16px; padding:12px; background:#7f1d1d; color:#fee2e2; border-radius:10px; }
    .warn { margin-top:16px; padding:12px; background:#78350f; color:#fde68a; border-radius:10px; }
    .footer { margin-top:18px; font-size:.9rem; color:#9ca3af; }
    .table { width:100%; border-collapse: collapse; margin-top:12px; }
    .table th, .table td { padding:10px; border:1px solid #374151; text-align:left; }
    .table thead { background:#0b1220; }
    .table tbody tr:nth-child(even) { background:#0f172a; }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="title">Hasil Presensi</div>
      <div class="subtitle">Masukkan NIM peserta untuk melihat data presensi.</div>

      <form method="get" action="<?php echo h(($base ? $base.'/' : '').'hasil.php'); ?>">
        <div class="field">
          <label class="label" for="nim">NIM Peserta</label>
          <input class="input" type="text" id="nim" name="nim" value="<?php echo h($nim); ?>" placeholder="Masukkan NIM" required />
        </div>
        <button class="btn" type="submit">Cari</button>
      </form>

      <?php if ($errorMsg): ?>
        <div class="error"><?php echo h($errorMsg); ?></div>
        <?php if ($url): ?><div class="info">Endpoint: <code><?php echo h($url); ?></code></div><?php endif; ?>
      <?php elseif (!empty($rows)): ?>
        <div class="success">Ditemukan <?php echo h(count($rows)); ?> data untuk NIM <?php echo h($nim); ?>.</div>
        <table class="table">
          <thead>
            <tr>
              <th>#</th>
              <th>Session Code</th>
              <th>Nama</th>
              <th>NIM</th>
              <th>Perguruan Tinggi</th>
              <th>No WA</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $i => $r): ?>
              <tr>
                <td><?php echo h($i+1); ?></td>
                <td><?php echo h(isset($r['session_code']) ? $r['session_code'] : (isset($r['kode_sesi']) ? $r['kode_sesi'] : '—')); ?></td>
                <td><?php echo h(isset($r['nama']) ? $r['nama'] : '—'); ?></td>
                <td><?php echo h(isset($r['nim']) ? $r['nim'] : $nim); ?></td>
                <td><?php echo h(isset($r['perguruan_tinggi']) ? $r['perguruan_tinggi'] : '—'); ?></td>
                <td><?php echo h(isset($r['no_wa']) ? $r['no_wa'] : '—'); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div class="info">Sumber: Server Presensi</div>
      <?php else: ?>
        <div class="warn">Tidak ada data untuk NIM tersebut.</div>
      <?php endif; ?>

      <div class="footer">
        <a class="btn" href="<?php echo h(($base ? $base.'/' : '').'index.php'); ?>">Lihat QR</a>
        <a class="btn" style="margin-left:8px" href="<?php echo h(($base ? $base.'/' : '').'form.php'); ?>">Form Presensi</a>
      </div>
    </div>
  </div>
</body>
</html>