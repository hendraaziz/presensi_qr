<?php
$cfg = __DIR__ . '/config.php';
if (file_exists($cfg)) { require_once $cfg; } else { require_once __DIR__ . '/config.example.php'; }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function norm_lower($s){ return function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s); }
function getv($arr, $key, $default=''){ return isset($arr[$key]) ? $arr[$key] : $default; }
function find_header($headers, $candidates) { foreach ($candidates as $c) { foreach ($headers as $h) { if (norm_lower($h) === norm_lower($c)) return $h; } } return null; }
function is_postest_title($title) {
  $t = norm_lower(trim($title));
  $tNoSep = str_replace(array(' ', '-', '_'), '', $t);
  $keywords = array('sesiposttest','sesi posttest','sesi post-test','posttest','post-test','post test','postest','evaluasi','evaluation','eval','penilaian','assessment');
  foreach ($keywords as $kw) { if (strpos($t, $kw) !== false || strpos($tNoSep, str_replace(array(' ', '-', '_'),'',$kw)) !== false) return true; }
  if (strpos($t, 'post') !== false && strpos($t, 'test') !== false) return true;
  return false;
}
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
  $content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // strip BOM if present
  $lines = preg_split('/\r\n|\r|\n/', trim($content));
  if (!$lines || count($lines) < 2) return array();
  $headerline = array_shift($lines);
  $headers = str_getcsv($headerline, ',', '"', '\\');
  $headers = array_map(function($h){ return trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)); }, $headers);
  $rows = array();
  foreach ($lines as $line) {
    if ($line === '') continue;
    $cols = str_getcsv($line, ',', '"', '\\');
    $row = array();
    foreach ($headers as $i => $h) { $key = trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)); $row[$key] = isset($cols[$i]) ? $cols[$i] : ''; }
    $rows[] = $row;
  }
  return $rows;
}
function parse_dt($val) {
  if (!$val) return null;
  $val = trim($val);
  $tz = new DateTimeZone('Asia/Jakarta');
  $formats = array(
    'Y-m-d H:i:s','Y-m-d H:i','Y/m/d H:i:s','Y/m/d H:i',
    'd/m/Y H:i:s','d/m/Y H:i','d-m-Y H:i:s','d-m-Y H:i',
    'd.m.Y H:i:s','d.m.Y H:i','Y-m-d\TH:i:s','Y-m-d\TH:i',
    'Y-m-d','d/m/Y','d-m-Y','Y/m/d','d.m.Y',
    'Y-m-d H.i','d/m/Y H.i','d-m-Y H.i','Y/m/d H.i','d.m.Y H.i'
  );
  foreach ($formats as $fmt) {
    $dt = DateTime::createFromFormat($fmt, $val, $tz);
    if ($dt instanceof DateTime) return $dt;
  }
  $ts = strtotime($val);
  if ($ts !== false) return (new DateTime('@' . $ts))->setTimezone($tz);
  return null;
}
function get_active_postest($csvUrl) {
  $rows = fetch_csv_assoc($csvUrl);
  if (!$rows) return null;
  $headers = array_keys($rows[0]);
  $titleKey = find_header($headers, array('title','nama_sesi','name','session_name','judul','kegiatan','session','title_sesi'));
  $startKey = find_header($headers, array('start_at','mulai','start','start time','waktu_mulai','start_time','start date','start_datetime','tanggal_mulai','jam_mulai','mulai_pukul'));
  $endKey   = find_header($headers, array('end_at','selesai','end','end time','waktu_selesai','end_time','end date','end_datetime','tanggal_selesai','jam_selesai','selesai_pukul'));
  $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
  $candidates = array();
  foreach ($rows as $row) {
    $title = $titleKey ? getv($row, $titleKey, '') : '';
    $start = parse_dt($startKey ? getv($row, $startKey, null) : null);
    $end   = parse_dt($endKey   ? getv($row, $endKey, null)   : null);
    if (!$start || !$end) continue;
    if ($now >= $start && $now <= $end) {
      if (is_postest_title($title)) {
        $candidates[] = array('title' => $title, 'start' => $start, 'end' => $end, 'raw' => $row);
      }
    }
  }
  if (!$candidates) return null;
  usort($candidates, function($a,$b){
    $at = ($a['end'] instanceof DateTime) ? $a['end']->getTimestamp() : 0;
    $bt = ($b['end'] instanceof DateTime) ? $b['end']->getTimestamp() : 0;
    if ($at === $bt) return 0; return ($at < $bt) ? -1 : 1;
  });
  return $candidates[0];
}

$base = function_exists('base_url') ? base_url() : '';
$title = defined('APP_TITLE') ? APP_TITLE : 'Presensi Wasbang';
$target = rtrim($base, '/') . '/postest.php';
$qrSize = defined('QR_SIZE') ? (int)QR_SIZE : 500;
$qrMargin = defined('QR_MARGIN') ? (int)QR_MARGIN : 2;
$POSTEST_SHEET_CSV_URL = defined('POSTEST_SHEET_CSV_URL') ? constant('POSTEST_SHEET_CSV_URL') : 'https://docs.google.com/spreadsheets/d/1_8GBoeFlWwC7Z_-DZmmVws-hztOb7IwbR8y5Akre_1Q/export?format=csv&gid=1498685016';
$active = get_active_postest($POSTEST_SHEET_CSV_URL);
$isActive = !!$active;
$sheetTitle = $active && isset($active['title']) ? $active['title'] : '';
$startTxt = ($active && $active['start'] instanceof DateTime) ? $active['start']->format('d/m/Y H:i') : '';
$endTxt   = ($active && $active['end']   instanceof DateTime) ? $active['end']->format('d/m/Y H:i') : '';
$qrImg = 'https://quickchart.io/qr?text=' . urlencode($target) . '&size=' . $qrSize . '&margin=' . $qrMargin . '&format=png';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo h($title . ' - QR Postest'); ?></title>
  <style>
    :root { color-scheme: light dark; }
    * { box-sizing: border-box; }
    body { margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; background:#0f172a; color:#e5e7eb; }
    .container { min-height: 100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
    .card { width:min(720px, 92vw); background:#111827; border:1px solid #1f2937; border-radius:16px; padding:24px; box-shadow:0 10px 30px rgba(0,0,0,0.35); text-align:center; }
    .title { font-size:1.6rem; font-weight:700; margin-bottom:8px; }
    .subtitle { color:#9ca3af; margin-bottom:16px; }
    .qr-wrap { display:flex; align-items:center; justify-content:center; padding:24px; background:#0b1220; border-radius:12px; border:1px solid #1f2937; }
    .qr-wrap img { image-rendering: crisp-edges; }
    .footer { margin-top:14px; font-size:.95rem; color:#9ca3af; }
    .btn { display:inline-block; padding:10px 16px; background:#2563eb; color:#fff; border-radius:8px; text-decoration:none; }
    .btn:hover { background:#1d4ed8; }
    .warn { margin-top:16px; padding:12px; background:#78350f; color:#fde68a; border-radius:10px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="title"><?php echo h($sheetTitle ?: 'QR Postest'); ?></div>
      <?php if ($isActive): ?>
        <?php if ($startTxt || $endTxt): ?>
          <div class="subtitle">Waktu: <?php echo h($startTxt ?: '—'); ?> s/d <?php echo h($endTxt ?: '—'); ?></div>
        <?php endif; ?>
        <div class="qr-wrap">
          <img src="<?php echo h($qrImg); ?>" alt="QR ke <?php echo h($target); ?>" style="width:<?php echo (int)$qrSize; ?>px;height:<?php echo (int)$qrSize; ?>px;" />
        </div>
        <!-- <div class="footer">
          Atau buka langsung: <a class="btn" href="<?php echo h($target); ?>">Buka Halaman Postest</a>
        </div> -->
        <div style="margin-top:10px;">URL tujuan: <span class="code"><?php echo h($target); ?></span></div>
      <?php else: ?>
        <div class="warn">Tidak ada SesiPosttest yang aktif saat ini. Silakan cek jadwal pada spreadsheet.</div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>