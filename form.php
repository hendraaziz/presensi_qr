<?php
require_once __DIR__ . '/config.php';

// Validate time-based secret for QR security
function validate_time_secret($session_id, $provided_secret, $time_window = 300, $tolerance = 1) {
    $current_time = time();
    $current_slot = floor($current_time / $time_window);
    $secret_key = defined('SECRET_KEY') ? SECRET_KEY : 'default_secret_key_change_this';
    
    // Check current time slot and previous slots within tolerance
    for ($i = 0; $i <= $tolerance; $i++) {
        $check_slot = $current_slot - $i;
        $expected_secret = hash('sha256', $session_id . '|' . $check_slot . '|' . $secret_key);
        if (hash_equals($expected_secret, $provided_secret)) {
            return true;
        }
    }
    return false;
}

$sessionId = isset($_GET['session_id']) ? trim($_GET['session_id']) : '';
$providedSecret = isset($_GET['secret']) ? trim($_GET['secret']) : '';
$ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
$userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

// Security check: validate secret parameter
if (empty($sessionId) || empty($providedSecret) || !validate_time_secret($sessionId, $providedSecret)) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Akses Ditolak - <?php echo htmlspecialchars(defined('APP_TITLE') ? APP_TITLE : 'Presensi Wasbang'); ?></title>
        <style>
            body { margin:0; font-family: system-ui, -apple-system, sans-serif; background: #0f172a; color:#e5e7eb; }
            .container { min-height: 100vh; display:flex; align-items:center; justify-content:center; padding: 24px; }
            .card { width: min(500px, 92vw); background: #111827; border: 1px solid #dc2626; border-radius: 16px; padding: 24px; text-align: center; }
            .title { font-size: 1.5rem; font-weight: 700; color: #dc2626; margin-bottom: 16px; }
            .message { color: #9ca3af; margin-bottom: 20px; line-height: 1.5; }
            .btn { display:inline-block; padding:10px 16px; background:#2563eb; color:#fff; border-radius:8px; text-decoration:none; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <div class="title">🚫 Akses Ditolak</div>
                <div class="message">
                    Anda tidak memiliki akses ke halaman ini.<br>
                    Silakan scan QR code yang valid untuk melakukan presensi.
                </div>
                <!-- <a href="<?php echo base_url(); ?>" class="btn">Kembali ke Halaman Utama</a> -->
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Helper functions to detect active session (server-side)
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
function get_active_sessions($url) {
  $rows = fetch_csv_assoc($url);
  if (!$rows) return array();
  $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
  $active = array();
  foreach ($rows as $row) {
    $id = get_first($row, array('id_sesi','session_id','id','kode_sesi','code'), '');
    $start = parse_dt(get_first($row, array('mulai','start','start_time','waktu_mulai','start_at'), null));
    $end = parse_dt(get_first($row, array('selesai','end','end_time','waktu_selesai','end_at'), null));
    if ($start && $end && $now >= $start && $now <= $end) {
      $active[] = $id;
    }
  }
  return $active;
}
function is_session_active($url, $sid) {
  if (!$sid) return false;
  $actives = get_active_sessions($url);
  return in_array($sid, $actives, true);
}

// Determine session ID if missing
$sessionActive = false;
if ($sessionId === '') {
  $activeIds = get_active_sessions(GOOGLE_SHEET_SESI_URL);
  if ($activeIds) {
    $sessionId = $activeIds[0];
    $sessionActive = true;
  } else {
    $sessionActive = false;
  }
} else {
  $sessionActive = is_session_active(GOOGLE_SHEET_SESI_URL, $sessionId);
}

// Derive allowed country codes and patterns
$codesStr = defined('COUNTRY_CODES') ? COUNTRY_CODES : (defined('COUNTRY_CODE') ? COUNTRY_CODE : '62');
$codesArr = array_filter(array_map('trim', explode(',', $codesStr)));
if (!$codesArr) { $codesArr = array(defined('COUNTRY_CODE') ? COUNTRY_CODE : '62'); }
$codesPattern = '(' . implode('|', $codesArr) . ')';
$codesLabel = implode('/', $codesArr);
$placeholderCode = isset($codesArr[0]) ? $codesArr[0] : (defined('COUNTRY_CODE') ? COUNTRY_CODE : '62');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo htmlspecialchars((defined('APP_TITLE') ? APP_TITLE : 'Presensi Wasbang') . ' - Form Presensi'); ?></title>
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
    .info { margin-top:16px; font-size:.95rem; color:#9ca3af; }
    .success { margin-top:16px; padding:12px; background:#064e3b; color:#d1fae5; border-radius:10px; }
    .error { margin-top:16px; padding:12px; background:#7f1d1d; color:#fee2e2; border-radius:10px; }
    .warn { margin-top:16px; padding:12px; background:#78350f; color:#fde68a; border-radius:10px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="title">Form <?php echo htmlspecialchars(defined('APP_TITLE') ? APP_TITLE : 'Presensi Wasbang'); ?></div>
      <?php if ($sessionActive): ?>
        <div class="subtitle">Sesi ID: <strong id="sessionIdText"><?php echo htmlspecialchars($sessionId); ?></strong></div>
      <?php else: ?>
        <div class="subtitle">Tidak ada sesi aktif.</div>
        <div class="subtitle">Sesi ID: <strong id="sessionIdText">—</strong></div>
      <?php endif; ?>

      <form id="presensiForm">
        <div class="field">
          <label for="nim" class="label">NIM Peserta</label>
          <input type="text" id="nim" name="nim" class="input" placeholder="Masukkan NIM" required <?php echo $sessionActive ? '' : 'disabled'; ?> />
        </div>

        <div class="field">
          <label class="label">Nama Peserta</label>
          <div id="namaPeserta" class="info">—</div>
        </div>
        <div class="field">
          <label class="label">Perguruan Tinggi</label>
          <div id="ptPeserta" class="info">—</div>
        </div>
        <!-- <div class="field">
          <label class="label">Status Peserta</label>
          <div id="statusPeserta" class="info">—</div>
        </div> -->

        <div class="field">
          <label for="no_wa" class="label">No WhatsApp (format: <?php echo htmlspecialchars($codesLabel); ?>XXXXXXXXX)</label>
          <input type="tel" id="no_wa" name="no_wa" class="input" inputmode="numeric" pattern="^<?php echo $codesPattern; ?>[0-9]{9,}$" minlength="11" placeholder="<?php echo htmlspecialchars($placeholderCode); ?>xxxxxxxxx" required <?php echo $sessionActive ? '' : 'disabled'; ?> />
          <div class="info">Hanya angka, minimal 11 digit, diawali Kode Negara Misalnya: <?php echo htmlspecialchars($codesLabel); ?>.</div>
        </div>
        <div class="field">
          <label for="kode_sesi_peserta" class="label">Kode Sesi Peserta</label>
          <input type="text" id="kode_sesi_peserta" name="kode_sesi_peserta" class="input" placeholder="Masukkan kode sesi peserta" required <?php echo $sessionActive ? '' : 'disabled'; ?> />
        </div>

        <input type="hidden" id="session_id" name="session_id" value="<?php echo htmlspecialchars($sessionId); ?>" />
        <input type="hidden" id="ip_address" name="ip_address" value="<?php echo htmlspecialchars($ipAddress); ?>" />
        <input type="hidden" id="user_agent" name="user_agent" value="<?php echo htmlspecialchars($userAgent); ?>" />
        <input type="hidden" id="device_fingerprint" name="device_fingerprint" value="" />

        <button type="submit" class="btn" id="submitBtn" <?php echo $sessionActive ? '' : 'disabled'; ?>>Submit Presensi</button>
        <a class="btn" id="cekPresensiBtn" style="margin-left:8px" href="#">Cek data presensi Anda</a>
      </form>

      <div id="feedback" class="info"></div>
    </div>
  </div>

  <script>
    const SUBMIT_URL = 'submit.php';
    const WEBHOOK_URL = 'webhookrespon.php';
    const NIM_GSHEET_URL = <?php echo json_encode(NIM_GSHEET_URL); ?>;
    const SESSION_ACTIVE = <?php echo $sessionActive ? 'true' : 'false'; ?>;
    const baseUrl = <?php echo json_encode(base_url()); ?>;

    (function loadFP() {
      const s = document.createElement('script');
      s.async = true;
      s.src = 'https://openfpcdn.io/fingerprintjs/v3';
      s.onload = initFP;
      document.head.appendChild(s);
    })();

    function simpleHash(str) {
      let h = 0; for (let i = 0; i < str.length; i++) { h = ((h<<5)-h) + str.charCodeAt(i); h |= 0; }
      return Math.abs(h).toString(36);
    }
    function computeSimpleFingerprint() {
      try {
        const ua = navigator.userAgent || '';
        const lang = navigator.language || '';
        const tz = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
        const scr = (screen.width||'') + 'x' + (screen.height||'');
        return simpleHash([ua, lang, tz, scr].join('|'));
      } catch (e) { return simpleHash((navigator.userAgent||'') + '|' + Date.now()); }
    }

    let fingerprintValue = '';
    async function initFP() {
      try {
        const fp = await FingerprintJS.load();
        const result = await fp.get();
        fingerprintValue = result.visitorId;
        document.getElementById('device_fingerprint').value = fingerprintValue;
      } catch (e) {
        console.warn('Fingerprint init failed:', e);
        fingerprintValue = computeSimpleFingerprint();
        document.getElementById('device_fingerprint').value = fingerprintValue;
      }
    }

    function splitCSVLine(line) {
      const result = [];
      let current = '';
      let inQuotes = false;
      for (let i = 0; i < line.length; i++) {
        const char = line[i];
        if (char === '"') {
          if (inQuotes && line[i+1] === '"') { current += '"'; i++; }
          else { inQuotes = !inQuotes; }
        } else if (char === ',' && !inQuotes) {
          result.push(current);
          current = '';
        } else {
          current += char;
        }
      }
      result.push(current);
      return result.map(s => s.trim());
    }

    async function lookupNamaByNIM(nim) {
      if (!nim || !NIM_GSHEET_URL) return null;
      try {
        const res = await fetch(NIM_GSHEET_URL, { cache: 'no-store' });
        if (!res.ok) return null;
        const text = await res.text();
        const lines = text.split(/\r\n|\r|\n/).filter(Boolean);
        if (lines.length < 2) return null;
        const headers = splitCSVLine(lines[0]).map(h => h.toLowerCase());
        const nimIdx = headers.findIndex(h => ['nim','participant_id','id_peserta'].includes(h));
        const namaIdx = headers.findIndex(h => ['nama','nama_peserta','name'].includes(h));
        const ptIdx = headers.findIndex(h => ['perguruan tinggi','perguruan_tinggi','pt','institusi','universitas'].includes(h));
        const aktifIdx = headers.findIndex(h => ['is_aktif','aktif','status','is active'].includes(h));
        if (nimIdx < 0) return null;
        for (let i = 1; i < lines.length; i++) {
          const cols = splitCSVLine(lines[i]);
          if ((cols[nimIdx] || '').toLowerCase() === nim.toLowerCase()) {
            return {
              nama: namaIdx >= 0 ? (cols[namaIdx] || '') : '',
              pt: ptIdx >= 0 ? (cols[ptIdx] || '') : '',
              is_aktif: aktifIdx >= 0 ? (cols[aktifIdx] || '') : ''
            };
          }
        }
        return null;
      } catch (e) {
        console.warn('Lookup NIM error:', e);
        return null;
      }
    }

    let nimInfo = null;
    const namaEl = document.getElementById('namaPeserta');
    const ptEl = document.getElementById('ptPeserta');
    const statusEl = document.getElementById('statusPeserta');

    document.getElementById('nim').addEventListener('blur', async (e) => {
      const nim = e.target.value.trim();
      nimInfo = await lookupNamaByNIM(nim);
      namaEl.textContent = nimInfo?.nama || 'Nama Tidak Ditemukan, Cek kembali NIM anda';
      ptEl.textContent = nimInfo?.pt || '—';
      const statusText = (nimInfo?.is_aktif || '').toString().toLowerCase();
      statusEl.textContent = statusText ? (['1','true','ya','aktif'].includes(statusText) ? 'Aktif' : 'Tidak Aktif') : '—';
    });

    // Sanitasi input no_wa: hanya angka
    document.getElementById('no_wa').addEventListener('input', (e) => {
      const v = e.target.value.replace(/[^0-9]/g, '');
      e.target.value = v;
    });

    // Tombol cek hasil presensi menuju hasil.php?nim=...
    const cekBtn = document.getElementById('cekPresensiBtn');
    function updateCekLink() {
      const nimVal = (document.getElementById('nim')?.value || '').trim();
      if (!cekBtn) return;
      cekBtn.href = baseUrl + '/hasil.php' + (nimVal ? ('?nim=' + encodeURIComponent(nimVal)) : '');
      const active = SESSION_ACTIVE && !!nimVal;
      cekBtn.style.opacity = active ? '1' : '0.6';
      cekBtn.style.pointerEvents = active ? 'auto' : 'none';
    }
    document.getElementById('nim').addEventListener('input', updateCekLink);
    updateCekLink();
    // Fungsi simulateWebhook dihapus - gunakan balikan asli dari n8n

    async function fetchWebhookResult(nim, sessionId, fingerprint, key) {
      const paramsObj = { nim, session_id: sessionId, device_fingerprint: fingerprint };
      if (key) paramsObj.key = key;
      const params = new URLSearchParams(paramsObj);
      try {
        const res = await fetch(WEBHOOK_URL + '?' + params.toString(), { cache: 'no-store' });
        if (!res.ok) return null;
        const json = await res.json();
        return json;
      } catch (e) { return null; }
    }

    document.getElementById('presensiForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.target;
      const submitBtn = document.getElementById('submitBtn');
      const feedback = document.getElementById('feedback');

      if (!SESSION_ACTIVE) {
        feedback.className = 'error';
        feedback.textContent = 'Sesi tidak aktif. Gagal menyimpan.';
        return;
      }

      // Pastikan fingerprint terisi
      if (!fingerprintValue) {
        fingerprintValue = computeSimpleFingerprint();
        document.getElementById('device_fingerprint').value = fingerprintValue;
      }

      // Pastikan info NIM tersedia
      if (!nimInfo) {
        nimInfo = await lookupNamaByNIM(form.nim.value.trim());
      }

      // Validasi: nama dan perguruan tinggi harus ada
      if (!nimInfo || !(nimInfo.nama && nimInfo.pt)) {
        feedback.className = 'error';
        feedback.textContent = 'Data peserta tidak lengkap (nama/perguruan tinggi). Silakan cek NIM dan coba lagi.';
        return;
      }

      const data = {
        nim: form.nim.value.trim(),
        session_id: document.getElementById('session_id').value,
        ip_address: document.getElementById('ip_address').value,
        user_agent: document.getElementById('user_agent').value,
        device_fingerprint: document.getElementById('device_fingerprint').value,
        no_wa: form.no_wa.value.trim(),
        kode_sesi_peserta: form.kode_sesi_peserta.value.trim(),
        nama_peserta: nimInfo?.nama || '',
        perguruan_tinggi: nimInfo?.pt || ''
      };

      feedback.className = 'warn';
      feedback.textContent = 'Proses Penyimpanan Data ...';
      submitBtn.disabled = true;

      try {
        const res = await fetch(SUBMIT_URL, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams(data)
        });
        const json = await res.json();
        if (!json.success) {
          feedback.className = 'error';
          feedback.textContent = 'Gagal mengirim ke proxy: ' + (json.message || 'Unknown error');
          submitBtn.disabled = false;
          return;
        }
        // Berhenti simulasi; lakukan polling balikan n8n yang asli
        feedback.className = 'warn';
        feedback.textContent = 'Data terkirim ke server. Menunggu respon...';

        let finalResult = null;
        const key = [data.session_id, data.nim, data.device_fingerprint].filter(Boolean).join('|') || 'unknown';
        const maxAttempts = 12; // ~18 detik dengan interval 1.5s
        for (let attempt = 1; attempt <= maxAttempts; attempt++) {
          const r = await fetchWebhookResult(data.nim, data.session_id, data.device_fingerprint, key);
          if (r && r.success) { finalResult = r; break; }
          await new Promise(resolve => setTimeout(resolve, 1500));
        }

        submitBtn.disabled = false;
        if (finalResult) {
          feedback.className = 'success';
          feedback.textContent = 'Hasil: ' + (finalResult.message || 'Berhasil');
        } else {
          feedback.className = 'error';
          feedback.textContent = 'Belum ada respon dari server untuk saat ini. Coba beberapa detik lagi.';
        }
      } catch (err) {
        feedback.className = 'error';
        feedback.textContent = 'Error submit: ' + (err && err.message ? err.message : err);
        submitBtn.disabled = false;
      }
    });
  </script>
</body>
</html>