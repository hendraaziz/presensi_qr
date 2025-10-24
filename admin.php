<?php
session_start();
require_once __DIR__ . '/config.php';

$cfgPath = __DIR__ . '/config.php';
$loggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if (isset($_GET['logout'])) {
  $_SESSION['admin_logged_in'] = false;
  session_destroy();
  header('Location: admin.php');
  exit;
}

function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function post($key, $def='') { return isset($_POST[$key]) ? trim($_POST[$key]) : $def; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = post('action');
  if ($action === 'login') {
    $u = post('username');
    $p = post('password');
    if ($u === ADMIN_USERNAME && $p === ADMIN_PASSWORD) {
      $_SESSION['admin_logged_in'] = true;
      header('Location: admin.php');
      exit;
    } else {
      $error = 'Login gagal: kredensial salah';
    }
  } elseif ($action === 'save' && $loggedIn) {
    // Ambil nilai dari form
    $APP_TITLE = post('APP_TITLE', APP_TITLE);
    $ADMIN_USERNAME = post('ADMIN_USERNAME', ADMIN_USERNAME);
    $ADMIN_PASSWORD = post('ADMIN_PASSWORD', ADMIN_PASSWORD);
    $GOOGLE_SHEET_SESI_URL = post('GOOGLE_SHEET_SESI_URL', GOOGLE_SHEET_SESI_URL);
    $NIM_GSHEET_URL = post('NIM_GSHEET_URL', NIM_GSHEET_URL);
    $N8N_WEBHOOK_URL = post('N8N_WEBHOOK_URL', N8N_WEBHOOK_URL);
    $SECRET_KEY = post('SECRET_KEY', SECRET_KEY);
    $COUNTRY_CODE = post('COUNTRY_CODE', COUNTRY_CODE);

    // Bangun konten config.php
    $content = "<?php\n";
    $content .= "// Presensi Wasbang - Konfigurasi\n";
    $content .= "// URL Google Sheets dipublish sebagai CSV export\n";
    $content .= "// Sesi (gid=741782867) dan Master NIM (gid=0)\n\n";
    $content .= "// Judul aplikasi & kredensial admin (login via admin.php)\n";
    $content .= "define('APP_TITLE', " . var_export($APP_TITLE, true) . ");\n";
    $content .= "define('ADMIN_USERNAME', " . var_export($ADMIN_USERNAME, true) . ");\n";
    $content .= "define('ADMIN_PASSWORD', " . var_export($ADMIN_PASSWORD, true) . ");\n\n";
    $content .= "// Google Sheets\n";
    $content .= "define('GOOGLE_SHEET_SESI_URL', " . var_export($GOOGLE_SHEET_SESI_URL, true) . ");\n\n";
    $content .= "define('NIM_GSHEET_URL', " . var_export($NIM_GSHEET_URL, true) . ");\n\n";
    $content .= "// n8n Webhook untuk pencatatan presensi\n";
    $content .= "define('N8N_WEBHOOK_URL', " . var_export($N8N_WEBHOOK_URL, true) . ");\n\n";
    $content .= "// Secret key untuk proses fingerprint (opsional)\n";
    $content .= "define('SECRET_KEY', " . var_export($SECRET_KEY, true) . ");\n\n";
    $content .= "// Zona waktu default\n";
    $content .= "date_default_timezone_set('Asia/Jakarta');\n\n";
    $content .= "// Helper base URL sesuai host saat ini\n";
    $content .= "define('COUNTRY_CODE', " . var_export($COUNTRY_CODE, true) . ");\n\n";
    $content .= "function base_url() {\n";
    $content .= "    \$scheme = (!empty(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';\n";
    $content .= "    \$host = isset(\$_SERVER['HTTP_HOST']) ? \$_SERVER['HTTP_HOST'] : 'localhost';\n";
    $content .= "    return \$scheme . '://' . \$host;\n";
    $content .= "}\n";

    $ok = @file_put_contents($cfgPath, $content, LOCK_EX);
    if ($ok !== false) {
      $saved = true;
      // Reload constants by redirect
      header('Location: admin.php?saved=1');
      exit;
    } else {
      $error = 'Gagal menyimpan config.php. Periksa izin file.';
    }
  }
}

$APP_TITLE = defined('APP_TITLE') ? APP_TITLE : 'Presensi Wasbang';
$cfgMtime = @filemtime($cfgPath);
$cfgMtimeText = $cfgMtime ? date('Y-m-d H:i:s', $cfgMtime) : 'unknown';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo h($APP_TITLE); ?> - Admin</title>
  <style>
    :root { color-scheme: light dark; }
    body { margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; background:#0f172a; color:#e5e7eb; }
    .container { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
    .card { width:min(780px, 92vw); background:#111827; border:1px solid #1f2937; border-radius:16px; padding:24px; box-shadow:0 10px 30px rgba(0,0,0,0.35); }
    .title { font-size:1.5rem; font-weight:700; margin-bottom:8px; }
    .subtitle { color:#9ca3af; margin-bottom:12px; }
    .meta { color:#9ca3af; font-size:0.9rem; margin-bottom:18px; }
    .field { margin:10px 0; }
    .label { display:block; margin-bottom:6px; color:#cbd5e1; }
    .input { width:100%; padding:10px; border-radius:10px; border:1px solid #374151; background:#0b1220; color:#e5e7eb; }
    .btn { padding:12px 16px; border-radius:10px; background:#2563eb; color:#fff; border:none; cursor:pointer; }
    .btn:hover { background:#1d4ed8; }
    .row { display:grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .error { margin-top:16px; padding:12px; background:#7f1d1d; color:#fee2e2; border-radius:10px; }
    .success { margin-top:16px; padding:12px; background:#064e3b; color:#d1fae5; border-radius:10px; }
    .top { display:flex; justify-content:space-between; align-items:center; }
    a.link { color:#93c5fd; text-decoration:none; }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="top">
        <div class="title">Admin Konfigurasi</div>
        <div><a class="link" href="<?php echo h(base_url()); ?>/index.php">Lihat QR</a> | <a class="link" href="<?php echo h(base_url()); ?>/form.php">Form</a> | <?php if ($loggedIn): ?><a class="link" href="admin.php?logout=1">Logout</a><?php endif; ?></div>
      </div>
      <div class="subtitle">Ubah pengaturan aplikasi yang tersimpan di <code>config.php</code>.</div>
      <div class="meta">File aktif: <code><?php echo h($cfgPath); ?></code> • Terakhir diubah: <code><?php echo h($cfgMtimeText); ?></code></div>
      <?php if (isset($error)): ?><div class="error"><?php echo h($error); ?></div><?php endif; ?>
      <?php if (isset($_GET['saved'])): ?><div class="success">Config tersimpan.</div><?php endif; ?>

      <?php if (!$loggedIn): ?>
        <form method="post">
          <input type="hidden" name="action" value="login" />
          <div class="field">
            <label class="label">Username</label>
            <input class="input" type="text" name="username" required />
          </div>
          <div class="field">
            <label class="label">Password</label>
            <input class="input" type="password" name="password" required />
          </div>
          <button class="btn" type="submit">Masuk</button>
        </form>
      <?php else: ?>
        <form method="post">
          <input type="hidden" name="action" value="save" />
          <div class="field">
            <label class="label">Judul Aplikasi (APP_TITLE)</label>
            <input class="input" type="text" name="APP_TITLE" value="<?php echo h(APP_TITLE); ?>" />
          </div>
          <div class="row">
            <div class="field">
              <label class="label">Username Admin (ADMIN_USERNAME)</label>
              <input class="input" type="text" name="ADMIN_USERNAME" value="<?php echo h(ADMIN_USERNAME); ?>" />
            </div>
            <div class="field">
              <label class="label">Password Admin (ADMIN_PASSWORD)</label>
              <input class="input" type="text" name="ADMIN_PASSWORD" value="<?php echo h(ADMIN_PASSWORD); ?>" />
            </div>
          </div>
          <div class="field">
            <label class="label">Google Sheet Sesi (GOOGLE_SHEET_SESI_URL)</label>
            <input class="input" type="text" name="GOOGLE_SHEET_SESI_URL" value="<?php echo h(GOOGLE_SHEET_SESI_URL); ?>" />
          </div>
          <div class="field">
            <label class="label">Google Sheet Master NIM (NIM_GSHEET_URL)</label>
            <input class="input" type="text" name="NIM_GSHEET_URL" value="<?php echo h(NIM_GSHEET_URL); ?>" />
          </div>
          <div class="field">
            <label class="label">n8n Webhook URL (N8N_WEBHOOK_URL)</label>
            <input class="input" type="text" name="N8N_WEBHOOK_URL" value="<?php echo h(N8N_WEBHOOK_URL); ?>" />
          </div>
          <div class="row">
            <div class="field">
              <label class="label">Secret Key (SECRET_KEY)</label>
              <input class="input" type="text" name="SECRET_KEY" value="<?php echo h(SECRET_KEY); ?>" />
            </div>
            <div class="field">
              <label class="label">Kode Negara WA (COUNTRY_CODE)</label>
              <input class="input" type="text" name="COUNTRY_CODE" value="<?php echo h(COUNTRY_CODE); ?>" />
            </div>
          </div>
          <button class="btn" type="submit">Simpan Perubahan</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>