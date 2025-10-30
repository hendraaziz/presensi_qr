<?php
$cfg = __DIR__ . '/config.php';
if (file_exists($cfg)) { require_once $cfg; } else { require_once __DIR__ . '/config.example.php'; }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$base = function_exists('base_url') ? base_url() : '';
$title = defined('APP_TITLE') ? APP_TITLE : 'Presensi Wasbang';
$N8N_URL = 'https://flow.azizhendra.my.id/form/41a0aaed-5d76-4965-9906-7cdae394d0e6';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo h($title . ' - Postest'); ?></title>
  <style>
    :root { color-scheme: light dark; }
    * { box-sizing: border-box; }
    body { margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; background:#0f172a; color:#e5e7eb; }
    .container { min-height: 100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
    .card { width:min(1024px, 94vw); background:#111827; border:1px solid #1f2937; border-radius:16px; padding:24px; box-shadow:0 10px 30px rgba(0,0,0,0.35); }
    .title { font-size:1.5rem; font-weight:700; margin-bottom:8px; }
    .subtitle { color:#9ca3af; margin-bottom:16px; }
    .iframe-wrap { position:relative; width:100%; height:75vh; background:#0b1220; border-radius:12px; overflow:hidden; border:1px solid #1f2937; }
    iframe { width:100%; height:100%; border:0; }
    .footer { margin-top:14px; font-size:.95rem; color:#9ca3af; }
    .btn { display:inline-block; padding:10px 16px; background:#2563eb; color:#fff; border-radius:8px; text-decoration:none; }
    .btn:hover { background:#1d4ed8; }
    .warn { background:#0b1220; border:1px dashed #374151; color:#e5e7eb; padding:12px; border-radius:10px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="title">Postest</div>
      <div class="subtitle">Form Penilaian Evaluasi .</div>
      <div class="iframe-wrap">
        <iframe src="<?php echo h($N8N_URL); ?>" allow="clipboard-write; geolocation; camera; microphone;" referrerpolicy="no-referrer" loading="lazy"></iframe>
      </div>
      <!-- <div class="footer">
        Jika iframe tidak tampil, buka langsung: 
        <a class="btn" href="<?php echo h($N8N_URL); ?>" target="_blank" rel="noopener">Buka Form N8N</a>
        <?php if ($base): ?>
          <span style="margin-left:8px;">atau kembali ke <a class="btn" href="<?php echo h(rtrim($base,'/').'/index.php'); ?>">Beranda</a></span>
        <?php endif; ?>
      </div>
      <div class="warn" style="margin-top:12px;">
        Catatan: Beberapa browser atau kebijakan keamanan situs bisa mencegah halaman di-embed dalam iframe.
      </div> -->
    </div>
  </div>
</body>
</html>