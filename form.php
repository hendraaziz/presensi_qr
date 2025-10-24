<?php
require_once __DIR__ . '/config.php';

$sessionId = isset($_GET['session_id']) ? trim($_GET['session_id']) : '';
$ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
$userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

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
  <title>Form Presensi</title>
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
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="title">Form Presensi</div>
      <div class="subtitle">Sesi ID: <strong id="sessionIdText"><?php echo htmlspecialchars($sessionId ?: '—'); ?></strong></div>
      <!-- <div class="subtitle">Status Sesi: <span id="sessionStatus">Menunggu validasi oleh n8n</span></div> -->

      <form id="presensiForm">
        <div class="field">
          <label for="nim" class="label">NIM Peserta</label>
          <input type="text" id="nim" name="nim" class="input" placeholder="Masukkan NIM" required />
        </div>

        <div class="field">
          <label class="label">Nama Peserta</label>
          <div id="namaPeserta" class="info">—</div>
        </div>
        <div class="field">
          <label class="label">Perguruan Tinggi</label>
          <div id="ptPeserta" class="info">—</div>
        </div>
        <div class="field">
          <label class="label">Status Peserta</label>
          <div id="statusPeserta" class="info">—</div>
        </div>

        <div class="field">
          <label for="no_wa" class="label">No WhatsApp (format: <?php echo htmlspecialchars($codesLabel); ?>XXXXXXXXX)</label>
          <input type="tel" id="no_wa" name="no_wa" class="input" inputmode="numeric" pattern="^<?php echo $codesPattern; ?>[0-9]{9,}$" minlength="11" placeholder="<?php echo htmlspecialchars($placeholderCode); ?>xxxxxxxxx" required />
          <div class="info">Hanya angka, minimal 11 digit, diawali salah satu dari: <?php echo htmlspecialchars($codesLabel); ?>.</div>
        </div>
        <div class="field">
          <label for="kode_sesi_peserta" class="label">Kode Sesi Peserta</label>
          <input type="text" id="kode_sesi_peserta" name="kode_sesi_peserta" class="input" placeholder="Masukkan kode sesi peserta" required />
        </div>

        <input type="hidden" id="session_id" name="session_id" value="<?php echo htmlspecialchars($sessionId); ?>" />
        <input type="hidden" id="ip_address" name="ip_address" value="<?php echo htmlspecialchars($ipAddress); ?>" />
        <input type="hidden" id="user_agent" name="user_agent" value="<?php echo htmlspecialchars($userAgent); ?>" />
        <input type="hidden" id="device_fingerprint" name="device_fingerprint" value="" />

        <button type="submit" class="btn">Submit Presensi</button>
      </form>

      <div id="feedback" class="info"></div>
    </div>
  </div>

  <script>
    const SUBMIT_URL = 'submit.php';
    const NIM_GSHEET_URL = <?php echo json_encode(NIM_GSHEET_URL); ?>;

    // Load FingerprintJS v3
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
    document.getElementById('nim').addEventListener('blur', async (e) => {
      const nim = e.target.value.trim();
      nimInfo = await lookupNamaByNIM(nim);
      document.getElementById('namaPeserta').textContent = nimInfo?.nama || 'Tidak ditemukan di master NIM';
      document.getElementById('ptPeserta').textContent = nimInfo?.pt || '—';
      const statusText = (nimInfo?.is_aktif || '').toString().toLowerCase();
      document.getElementById('statusPeserta').textContent = statusText ? (['1','true','ya','aktif'].includes(statusText) ? 'Aktif' : 'Tidak Aktif') : '—';
    });

    // Sanitasi input no_wa: hanya angka
    document.getElementById('no_wa').addEventListener('input', (e) => {
      const v = e.target.value.replace(/[^0-9]/g, '');
      e.target.value = v;
    });

    document.getElementById('presensiForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.target;
      // Pastikan fingerprint terisi
      if (!fingerprintValue) {
        fingerprintValue = computeSimpleFingerprint();
        document.getElementById('device_fingerprint').value = fingerprintValue;
      }

      // Pastikan info NIM tersedia
      if (!nimInfo) {
        nimInfo = await lookupNamaByNIM(form.nim.value.trim());
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

      try {
        const res = await fetch(SUBMIT_URL, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams(data)
        });
        const json = await res.json();
        document.getElementById('feedback').textContent = json.success ? 'Sukses: ' + (json.message || '') : 'Gagal: ' + (json.message || 'Unknown error');
      } catch (err) {
        document.getElementById('feedback').textContent = 'Error submit: ' + (err && err.message ? err.message : err);
      }
    });
  </script>
</body>
</html>