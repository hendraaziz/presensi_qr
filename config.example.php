<?php
// Example configuration for presensi_wasbang
// Copy this file to config.php or set via admin.php

// App title & admin credentials (used by admin.php login)
define('APP_TITLE', 'Presensi Wasbang');
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'changeme');

// QR Code configuration
define('QR_SIZE', 500); // 100–1000
define('QR_MARGIN', 2); // 0–20

// Google Sheet URLs (CSV export)
define('GOOGLE_SHEET_SESI_URL', 'https://docs.google.com/spreadsheets/d/.../export?format=csv&gid=741782867');
define('NIM_GSHEET_URL', 'https://docs.google.com/spreadsheets/d/.../export?format=csv&gid=0');

// n8n Webhook URL
define('N8N_WEBHOOK_URL', 'https://your-n8n-instance.example.com/webhook/presensi-qr');

// Secret key for hashing (fallback fingerprint & QR security)
define('SECRET_KEY', 'change-this-secret-for-production');

// Default country code (used for WhatsApp number validation)
define('COUNTRY_CODE', '62');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Helper: base URL detection (PHP 5.x compatible)
function base_url() {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
  return $scheme . '://' . $host;
}