<?php
require_once __DIR__ . '/config.php';

function norm_lower($s) { return function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s); }

function getv($arr, $key, $default='') { return isset($arr[$key]) ? $arr[$key] : $default; }
function get_first($arr, $keys, $default='') { if (!is_array($keys)) { $keys = array($keys); } foreach ($keys as $k) { if (isset($arr[$k])) return $arr[$k]; } return $default; }

// Robust HTTP fetch: try file_get_contents, fallback to cURL
function http_get($url) {
    if (!$url) return false;
    // Attempt file_get_contents with context
    $ctx = stream_context_create(array(
        'http' => array(
            'method' => 'GET',
            'timeout' => 10,
            'header' => "User-Agent: PresensiWasbang/1.0\r\n"
        )
    ));
    $content = @file_get_contents($url, false, $ctx);
    if ($content !== false) return $content;
    // Fallback to cURL
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PresensiWasbang/1.0');
        // Avoid SSL CA issues on some shared host
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $body = curl_exec($ch);
        curl_close($ch);
        if ($body !== false) return $body;
    }
    return false;
}

function fetch_csv_assoc($url) {
    if (!$url) return array();
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

function find_header($headers, $candidates) {
    foreach ($candidates as $c) {
        foreach ($headers as $h) {
            if (norm_lower($h) === norm_lower($c)) return $h;
        }
    }
    return null;
}

function parse_dt($val) {
    if (!$val) return null;
    $val = trim($val);
    // Try multiple formats typical from Google Sheets
    $formats = array(
        'Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i', 'd/m/Y H:i:s', 'm/d/Y H:i', 'm/d/Y H:i:s',
        'Y-m-d', 'd/m/Y', 'm/d/Y'
    );
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $val, new DateTimeZone('Asia/Jakarta'));
        if ($dt instanceof DateTime) return $dt;
    }
    $ts = strtotime($val);
    if ($ts !== false) return (new DateTime('@' . $ts))->setTimezone(new DateTimeZone('Asia/Jakarta'));
    return null;
}

function get_active_session($url) {
    $rows = fetch_csv_assoc($url);
    if (!$rows) return null;
    $headers = array_keys($rows[0]);

    // Guess header keys
    $idKey = find_header($headers, array('id_sesi','session_id','id','kode_sesi','code'));
    $nameKey = find_header($headers, array('nama_sesi','nama','session_name','name','title'));
    $startKey = find_header($headers, array('mulai','start','start_time','waktu_mulai','start_at'));
    $endKey = find_header($headers, array('selesai','end','end_time','waktu_selesai','end_at'));

    $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    $active = array();
    $upcoming = array();

    foreach ($rows as $row) {
        $id = $idKey ? getv($row, $idKey, '') : get_first($row, array('id_sesi','session_id','id','kode_sesi','code'), '');
        $name = $nameKey ? getv($row, $nameKey, '') : get_first($row, array('nama_sesi','nama','session_name','name','title'), 'Sesi');
        $start = parse_dt($startKey ? getv($row, $startKey, null) : get_first($row, array('mulai','start','start_time','waktu_mulai','start_at'), null));
        $end = parse_dt($endKey ? getv($row, $endKey, null) : get_first($row, array('selesai','end','end_time','waktu_selesai','end_at'), null));
        if (!$start && !$end) continue;
        if ($start && $end && $now >= $start && $now <= $end) {
            $active[] = array('id' => $id, 'name' => $name, 'start' => $start, 'end' => $end, 'raw' => $row);
        } elseif ($start && $start > $now) {
            $upcoming[] = array('id' => $id, 'name' => $name, 'start' => $start, 'end' => $end, 'raw' => $row);
        }
    }

    if ($active) {
        // If multiple, pick the one ending soonest
        usort($active, function($a,$b){
            $at = ($a['end'] instanceof DateTime) ? $a['end']->getTimestamp() : 0;
            $bt = ($b['end'] instanceof DateTime) ? $b['end']->getTimestamp() : 0;
            if ($at === $bt) return 0;
            return ($at < $bt) ? -1 : 1;
        });
        return $active[0];
    }
    if ($upcoming) {
        // Pick the nearest upcoming by start time
        usort($upcoming, function($a,$b){
            $at = ($a['start'] instanceof DateTime) ? $a['start']->getTimestamp() : 0;
            $bt = ($b['start'] instanceof DateTime) ? $b['start']->getTimestamp() : 0;
            if ($at === $bt) return 0;
            return ($at < $bt) ? -1 : 1;
        });
        return $upcoming[0];
    }
    return null;
}

$session = get_active_session(GOOGLE_SHEET_SESI_URL);
$formUrl = null;
if ($session) {
    $formUrl = base_url() . '/form.php?session_id=' . urlencode($session['id']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo htmlspecialchars(defined('APP_TITLE') ? APP_TITLE : 'Presensi Wasbang'); ?></title>
  <style>
    :root { color-scheme: light dark; }
    body { margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; background: #0f172a; color:#e5e7eb; }
    .container { min-height: 100vh; display:flex; align-items:center; justify-content:center; padding: 24px; }
    .card { width: min(720px, 92vw); background: #111827; border: 1px solid #1f2937; border-radius: 16px; padding: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.35); }
    .title { font-size: 1.5rem; font-weight: 700; margin-bottom: 8px; }
    .subtitle { color: #9ca3af; margin-bottom: 20px; }
    .qr-wrap { display:flex; align-items:center; justify-content:center; padding: 24px; background:#0b1220; border-radius: 12px; }
    .footer { margin-top: 18px; font-size:.9rem; color:#9ca3af; }
    .btn { display:inline-block; padding:10px 16px; background:#2563eb; color:#fff; border-radius:8px; text-decoration:none; }
    .btn:hover { background:#1d4ed8; }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="title">Sistem <?php echo htmlspecialchars(defined('APP_TITLE') ? APP_TITLE : 'Presensi Wasbang'); ?></div>
      <?php if ($session): ?>
        <div class="subtitle">Sesi: <strong><?php echo htmlspecialchars($session['name']); ?></strong></div>
        <div class="subtitle">Waktu: <?php echo $session['start'] ? $session['start']->format('d/m/Y H:i') : '—'; ?> s/d <?php echo $session['end'] ? $session['end']->format('d/m/Y H:i') : '—'; ?></div>
        <div class="qr-wrap">
          <?php if ($formUrl): ?>
            <img alt="QR Presensi" src="https://quickchart.io/qr?text=<?php echo urlencode($formUrl); ?>&size=<?php echo defined('QR_SIZE') ? (int)QR_SIZE : 500; ?>&margin=<?php echo defined('QR_MARGIN') ? (int)QR_MARGIN : 2; ?>&format=png" />
          <?php endif; ?>
        </div>
        <!-- <div class="footer">
          Jika QR tidak terbaca, buka link manual: <a class="btn" href="<?php echo htmlspecialchars($formUrl); ?>">Buka Form Presensi</a>
        </div> -->
      <?php else: ?>
        <div class="subtitle">Tidak ada sesi aktif yang ditemukan saat ini. Silakan cek kembali jadwal.</div>
        <div class="footer">Jika ini terjadi di hosting: pastikan server mengizinkan outbound HTTPS (allow_url_fopen atau cURL dengan SSL), karena data sesi diambil dari Google Sheets pada sisi server.</div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>