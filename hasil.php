<?php
// Catatan: Pastikan file config.php tidak menghasilkan output HTML atau teks apa pun
// sebelum baris header di bawah dieksekusi, atau respons JSON akan gagal.
require_once __DIR__ . '/config.php';

// --- BAGIAN LOGIKA PENERIMA POST JSON DARI N8N ---

// Ambil body POST mentah (JSON)
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

$rows = array();
$errorMsg = '';
$nim_received = '';

// Cek apakah data yang masuk berasal dari n8n (dengan format array of objects dengan kunci 'json')
if (json_last_error() === JSON_ERROR_NONE && 
    is_array($data) && 
    isset($data[0]) && 
    is_array($data[0])) {
    
    // Asumsi: Data dari n8n adalah array of objects dengan kunci 'json'
    foreach ($data as $item) {
        if (isset($item['json']) && is_array($item['json'])) {
            $rows[] = $item['json'];
        } else {
            // Fallback jika n8n tidak menggunakan kunci 'json'
            $rows[] = $item;
        }
    }

    if (!empty($rows)) {
        // Ambil NIM dari data pertama sebagai acuan
        $nim_received = isset($rows[0]['nim']) ? $rows[0]['nim'] : 'N/A';
    }

    // === DI SINI TEMPAT ANDA MELAKUKAN PROSES BACKEND (INSERT KE DB LOKAL, LOGGING, DLL.) ===

    // === RESPON JSON KE N8N (WAJIB UNTUK MENGHENTIKAN LOOP HTML) ===
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => 'Data peserta NIM ' . $nim_received . ' berhasil diterima dan diproses oleh hasil.php.',
        'count' => count($rows)
    ]);
    exit; // Menghentikan eksekusi skrip agar tidak menampilkan HTML
    
} else {
    // Jika tidak ada data POST JSON (kemungkinan diakses langsung via browser GET)
    // Atau data POST JSON gagal diurai.
    $errorMsg = 'Gagal menerima atau mengurai data POST JSON.';
    
    // Jika diakses langsung via GET, ambil NIM untuk fallback tampilan HTML
    $nim = isset($_GET['nim']) ? trim($_GET['nim']) : '';
}

// --- BAGIAN FALLBACK HTML (HANYA DITAMPILKAN JIKA SCRIPT TIDAK DIHENTIKAN OLEH exit;) ---

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$base = function_exists('base_url') ? base_url() : '';
$title = defined('APP_TITLE') ? APP_TITLE : 'Presensi Wasbang';
// Gunakan $nim dari GET (jika ada) untuk mengisi form fallback
$nim_display = isset($nim) ? $nim : '';
$url = ''; // Hapus variabel url karena tidak ada panggilan webhook

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
    /* ... Semua CSS Anda tetap di sini ... */
    .container { min-height: 100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
    .card { width:min(720px, 92vw); background:#111827; border:1px solid #1f2937; border-radius:16px; padding:24px; box-shadow:0 10px 30px rgba(0,0,0,0.35); }
    /* ... CSS lainnya ... */
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
          <input class="input" type="text" id="nim" name="nim" value="<?php echo h($nim_display); ?>" placeholder="Masukkan NIM" required />
        </div>
        <button class="btn" type="submit">Cari</button>
      </form>

      <?php if ($errorMsg): // Error hanya ditampilkan jika gagal POST atau diakses langsung tanpa NIM GET ?>
        <div class="error"><?php echo h($errorMsg); ?></div>
      <?php elseif (!empty($rows) && isset($nim_received)): // Logika ini tidak akan dieksekusi jika POST berhasil karena ada exit; ?>
        <div class="success">Ditemukan <?php echo h(count($rows)); ?> data untuk NIM <?php echo h($nim_received); ?>.</div>
        <?php else: ?>
        <?php endif; ?>

      </div>
  </div>
</body>
</html>