# Presensi (PHP + n8n)

Aplikasi presensi sederhana berbasis PHP murni yang terintegrasi dengan n8n Webhook dan Google Sheets. Aplikasi ini menampilkan QR code ke halaman form presensi, melakukan lookup Nama/PT peserta dari master NIM (Google Sheets), mengumpulkan fingerprint perangkat, dan mengirim data ke n8n melalui proxy server-side agar bebas masalah CORS/HTTPS.

## Fitur
- Mendapatkan sesi aktif dari Google Sheets (kolom kandidat: `code/kode_sesi/id_sesi/session_id`).
- Menampilkan QR ke `form.php?session_id=[ID]`.
- Form presensi:
  - Input NIM + lookup Nama dan Perguruan Tinggi dari Google Sheets master.
  - Device fingerprint (FingerprintJS + fallback hash ringan jika FingerprintJS gagal).
  - Tambahan input: `no_wa` (hanya angka, minimal 11 digit, harus diawali kode negara dari config) dan `kode_sesi_peserta` (varchar bebas untuk validasi di n8n).
- Pengiriman ke n8n melalui `submit.php` (proxy server-side) untuk menghindari CORS/HTTPS issue.
- Kompatibel dengan PHP 5.6 (tanpa operator `??`).

## Struktur Berkas
- `index.php` — Menentukan sesi aktif dan menampilkan QR ke form.
- `form.php` — Form presensi peserta + lookup Nama/PT + fingerprint.
- `submit.php` — Proxy server-side yang meneruskan payload ke n8n Webhook sebagai JSON.
- `config.php` — Pengaturan lingkungan (DI-ABA IKAN dari repo; gunakan `config.example.php`).
- `config.example.php` — Contoh konfigurasi yang perlu disalin ke `config.php`.

## Persiapan
1. Salin dan sesuaikan `config.example.php` menjadi `config.php`.
2. Isi nilai berikut sesuai lingkungan Anda:
   - `GOOGLE_SHEET_SESI_URL` — URL CSV export Google Sheet untuk sesi aktif.
   - `NIM_GSHEET_URL` — URL CSV export Google Sheet master NIM.
   - `N8N_WEBHOOK_URL` — URL Webhook n8n untuk menerima data presensi.
   - `SECRET_KEY` — string rahasia (dipakai untuk fingerprint fallback di server).
   - `COUNTRY_CODE` — Kode negara nomor WA (contoh: `62`).

Contoh cepat (lihat `config.example.php`):
```php
define('GOOGLE_SHEET_SESI_URL', 'https://docs.google.com/...&gid=741782867');
define('NIM_GSHEET_URL', 'https://docs.google.com/...&gid=0');
define('N8N_WEBHOOK_URL', 'https://flow.example.com/webhook/presensi-qr');
define('SECRET_KEY', 'eventrahasia2025');
define('COUNTRY_CODE', '62');
```

## Menjalankan Lokal
- Jalankan built-in server PHP:

```sh
php -S localhost:8000
```

- Buka `http://localhost:8000/index.php` untuk melihat QR.
- Atau langsung `http://localhost:8000/form.php?session_id=TEST` untuk mencoba form.

## Alur Pengiriman
- Browser mengirim ke `submit.php` sebagai `x-www-form-urlencoded`.
- `submit.php` memvalidasi input (khususnya `no_wa`) dan menambahkan metadata proxy: `proxy_received_at`, `proxy_ip`, `proxy_user_agent`, serta `secret_key`.
- `submit.php` meneruskan payload ke `N8N_WEBHOOK_URL` sebagai JSON; jika Fingerprint kosong, dibuat fallback.
- Respon JSON dari proxy dipakai UI untuk menampilkan status.

## Catatan Produksi
- Jika ingin mengirim JSON dari browser, pastikan `php.ini` menyetel `always_populate_raw_post_data = -1` dan nonaktifkan tampilan error `E_DEPRECATED` di produksi.
- Di cPanel/hosting, tempatkan berkas di public_html (atau sesuai folder publik) dan pastikan izin file benar.

## Lisensi
Proyek ini ditujukan sebagai contoh implementasi presensi sederhana; gunakan sesuai kebutuhan Anda.