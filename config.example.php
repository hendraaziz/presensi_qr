<?php
// Presensi Wasbang - Contoh Konfigurasi
// Salin file ini menjadi config.php dan sesuaikan nilainya

// URL Google Sheets dipublish sebagai CSV export
// Sesi (gid=741782867) dan Master NIM (gid=0)
define('GOOGLE_SHEET_SESI_URL', 'https://docs.google.com/spreadsheets/d/XXXX/export?format=csv&gid=741782867');
define('NIM_GSHEET_URL', 'https://docs.google.com/spreadsheets/d/XXXX/export?format=csv&gid=0');

// n8n Webhook untuk pencatatan presensi
define('N8N_WEBHOOK_URL', 'https://flow.example.com/webhook/presensi-qr');

// Secret key untuk proses fingerprint (opsional)
define('SECRET_KEY', 'eventrahasia2025');

// Kode negara untuk validasi nomor WhatsApp
define('COUNTRY_CODE', '62');

// Zona waktu default
date_default_timezone_set('Asia/Jakarta');

// Helper base URL sesuai host saat ini
function base_url() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    return $scheme . '://' . $host;
}